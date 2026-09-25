<?php

namespace App\Console\Commands;

use App\Models\BallotMeasureCommittee;
use App\Services\CampaignFinance\CommitteeFinanceWriter;
use App\Services\CampaignFinance\FloridaElectionsClient;
use App\Support\MeasureCommitteeRules;
use Illuminate\Console\Command;

/**
 * Imports this calendar year's campaign finance activity from the Florida Division of
 * Elections for every Florida committee linked to a ballot measure (any link that isn't
 * rejected). Runs nightly in .github/workflows/import-florida.yml, followed by the link
 * audit.
 *
 * Florida committees don't file summary pages the way California's do, but they itemize
 * every contribution and expenditure, so totals are summed from the itemized rows:
 *  - raised: all contributions except loans (type LOA); non-cash is type INK.
 *  - spent: all expenditures.
 *  - cash on hand isn't published in a form this can read, so it's left empty.
 * The year's totals are stored as one snapshot per committee ("FL-2026").
 *
 * Donors and payees that are themselves Florida committees are matched by name against
 * the Division's list of active committees, so money passed between linked committees is
 * netted (MeasureFunding) and a committee's gifts to other committees become link
 * suggestions and side-conflict checks.
 *
 * Self-reports to politician_cleanup_run_metrics as 'fl-finance'. Findings are anomalies:
 * a year-to-date total lower than the last run's (usually a name-search mismatch), a query
 * that hit the row limit (results cut off), or a query the state's server failed — in which
 * case that committee's last good data is kept.
 */
class ImportFloridaCommitteeFinance extends Command
{
    protected $signature = 'ballot-measures:import-florida {--dry-run : Report only, no DB writes}';

    protected $description = 'Import this year\'s contributions, expenditures, donors and transfers from the Florida Division of Elections for Florida committees linked to ballot measures.';

    public function handle(FloridaElectionsClient $client): int
    {
        $startedAt = now();
        $dryRun = (bool) $this->option('dry-run');
        $writer = new CommitteeFinanceWriter('FL', 'fl-doe', 'fl-finance');
        $year = (int) now()->year;
        $since = "{$year}-01-01";

        $committeeIds = BallotMeasureCommittee::query()
            ->where('state', 'FL')
            ->where('status', '!=', BallotMeasureCommittee::STATUS_REJECTED)
            ->distinct()->pluck('committee_id')->map(fn ($id) => (string) $id)->all();

        $anomalies = [];
        $written = 0;
        $committeesByName = $committeeIds === [] ? [] : $this->committeesByName($client->activeCommittees());

        foreach ($committeeIds as $committeeId) {
            $detail = $client->committee($committeeId);
            if ($detail === null) {
                $this->warn("FL {$committeeId}: no committee with this account number.");
                if (! $dryRun) {
                    $writer->saveFiler($committeeId, ['found' => false, 'filer_name' => null, 'latest_filing_on' => null]);
                }

                continue;
            }

            try {
                $contributions = $client->contributions($detail['name'], $since);
                $expenditures = $client->expenditures($detail['name'], $since);
            } catch (\Throwable $e) {
                // Keep the last good data rather than saving an empty year.
                $anomalies['fetch_failed'] = ($anomalies['fetch_failed'] ?? 0) + 1;
                $this->error("FL {$committeeId}: {$e->getMessage()}");

                continue;
            }

            foreach ([$contributions, $expenditures] as $rows) {
                if (count($rows) >= FloridaElectionsClient::ROW_LIMIT) {
                    $anomalies['row_limit_reached'] = ($anomalies['row_limit_reached'] ?? 0) + 1;
                }
            }

            [$donors, $raised, $nonmonetary] = $this->donors($contributions, $committeesByName);
            $spent = array_sum(array_map(fn ($row) => $this->amount($row['Amount'] ?? ''), $expenditures));
            $transfers = $this->transfers($expenditures, $committeesByName, $committeeId);
            $dates = array_filter(array_map(fn ($row) => $this->date($row['Date'] ?? ''), [...$contributions, ...$expenditures]));
            $latestOn = $dates === [] ? null : max($dates);
            $written++;

            if ($dryRun) {
                $this->line("FL {$committeeId} {$detail['name']}: raised {$raised}, spent {$spent}, ".count($donors).' donor(s), '.count($transfers).' transfer(s).');

                continue;
            }

            $previous = $writer->latest($committeeId);

            $writer->transaction(function () use ($writer, $committeeId, $detail, $latestOn, $year, $raised, $nonmonetary, $spent, $donors, $transfers) {
                $writer->saveFiler($committeeId, [
                    'found' => true,
                    'filer_name' => $detail['name'],
                    'latest_filing_on' => $latestOn,
                    'late_contributions' => null,
                    'late_since' => null,
                ]);
                $writer->saveSnapshot($committeeId, "FL-{$year}", [
                    'form_type' => 'ITEMIZED',
                    'period_start' => "{$year}-01-01",
                    'period_end' => $latestOn ?? now()->toDateString(),
                    'filed_on' => now()->toDateString(),
                    'contributions_ytd' => round($raised, 2),
                    'nonmonetary_ytd' => round($nonmonetary, 2),
                    'expenditures_ytd' => round($spent, 2),
                    'cash_on_hand' => null,
                ]);
                $writer->saveDonors($committeeId, $year, $donors);
                $writer->saveTransfers($committeeId, $transfers);
            });

            // Same-year snapshot is re-summed each run, so compare with the last run directly.
            if ($previous !== null && $previous->period_start?->year === $year && $previous->contributions_ytd !== null
                && $raised < $previous->contributions_ytd * 0.99) {
                $anomalies['total_decreased'] = ($anomalies['total_decreased'] ?? 0) + 1;
                $this->warn("FL {$committeeId}: year-to-date contributions fell from {$previous->contributions_ytd} to {$raised}.");
            }
        }

        if (! $dryRun) {
            $writer->recordMetrics($startedAt, array_sum($anomalies), $written, $anomalies);
        }

        $this->info(($dryRun ? '[dry run] ' : '').'Checked '.count($committeeIds)." FL committee(s); {$written} imported, ".array_sum($anomalies).' anomaly(ies).');

        return self::SUCCESS;
    }

    /**
     * Aggregates contributions by donor. Loans aren't contributions; in-kind (INK) is
     * non-cash.
     *
     * @param  list<array<string, string>>  $rows
     * @param  array<string, string>  $committeesByName
     * @return array{0: array<string, array<string, mixed>>, 1: float, 2: float}
     */
    private function donors(array $rows, array $committeesByName): array
    {
        $donors = [];
        $raised = 0.0;
        $nonmonetary = 0.0;

        foreach ($rows as $row) {
            $type = strtoupper($row['Typ'] ?? '');
            if ($type === 'LOA') {
                continue;
            }

            $amount = $this->amount($row['Amount'] ?? '');
            $isInKind = $type === 'INK';
            $raised += $amount;
            $nonmonetary += $isInKind ? $amount : 0.0;

            $name = trim($row['Contributor Name'] ?? '');
            if ($name === '') {
                continue;
            }

            $committee = $committeesByName[$this->committeeKey($name)] ?? null;
            $key = $committee !== null ? "cmte:{$committee}" : FloridaElectionsClient::normalizeName($name);
            $donor = $donors[$key] ?? [
                'donor_name' => $name,
                'entity_type' => $committee !== null ? 'COM' : null,
                'donor_committee_id' => $committee,
                'employer' => null,
                'amount' => 0.0,
                'nonmonetary' => 0.0,
                'late' => 0.0,
            ];
            $donor['amount'] += $amount;
            $donor['nonmonetary'] += $isInKind ? $amount : 0.0;
            $donors[$key] = $donor;
        }

        return [$donors, $raised, $nonmonetary];
    }

    /**
     * Expenditures paid to another Florida committee, with the measure and side the
     * recipient's name announces ("Vote Yes on 2 PC").
     *
     * @param  list<array<string, string>>  $rows
     * @param  array<string, string>  $committeesByName
     * @return array<string, array<string, mixed>>
     */
    private function transfers(array $rows, array $committeesByName, string $fromCommitteeId): array
    {
        $transfers = [];
        foreach ($rows as $row) {
            $payee = trim($row['Payee Name'] ?? '');
            $toCommittee = $committeesByName[$this->committeeKey($payee)] ?? null;
            if ($toCommittee === null || $toCommittee === $fromCommitteeId) {
                continue;
            }

            $number = MeasureCommitteeRules::measureNumberFrom($payee);
            $key = $toCommittee.'|'.($number ?? '');
            $date = $this->date($row['Date'] ?? '');
            $existing = $transfers[$key] ?? null;

            $transfers[$key] = [
                'to_committee_id' => $toCommittee,
                'to_committee_name' => $payee,
                'measure_reference' => null,
                'measure_number' => $number,
                'measure_jurisdiction' => null,
                'position' => MeasureCommitteeRules::positionFromName($payee),
                'amount' => ($existing['amount'] ?? 0.0) + $this->amount($row['Amount'] ?? ''),
                'latest_on' => max((string) $date, (string) ($existing['latest_on'] ?? '')) ?: null,
            ];
        }

        return $transfers;
    }

    /**
     * Committee names keyed for matching against contributor and payee names, which
     * often add or drop "Inc." or the "PC"/"PAC" suffix.
     *
     * @param  array<string, array{name: string, type: string}>  $committees
     * @return array<string, string> key => account number
     */
    private function committeesByName(array $committees): array
    {
        $byName = [];
        foreach ($committees as $account => $committee) {
            $byName[$this->committeeKey($committee['name'])] = (string) $account;
        }

        return $byName;
    }

    private function committeeKey(string $name): string
    {
        return (string) preg_replace('/\s+(inc|pc|pac|llc|corp)$/', '', FloridaElectionsClient::normalizeName($name));
    }

    private function amount(string $value): float
    {
        return (float) str_replace([',', '$'], '', $value);
    }

    /** Florida dates look like "07/17/2026". */
    private function date(string $value): ?string
    {
        return preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', trim($value), $m) ? "{$m[3]}-{$m[1]}-{$m[2]}" : null;
    }
}
