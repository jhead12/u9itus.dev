<?php

namespace App\Console\Commands;

use App\Models\BallotMeasureCommittee;
use App\Services\CampaignFinance\CalAccessExport;
use App\Services\CampaignFinance\CommitteeFinanceWriter;
use App\Support\MeasureCommitteeRules;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Imports campaign finance data from the CAL-ACCESS daily export for every California
 * committee linked to a ballot measure (any link that isn't rejected). Runs nightly in
 * .github/workflows/import-cal-access.yml, followed by the link audit.
 *
 * Four passes over the export, each keeping only the latest amendment of a filing:
 *  1. CVR_CAMPAIGN_DISCLOSURE_CD (cover pages): whether each linked filer ID filed at all,
 *     the name it files under, its latest filing date, each Form 460's period and declared
 *     ballot measure/side, and its late contribution reports (Form 497).
 *  2. SMRY_CD (summary pages): Form 460 lines 4 (non-cash), 5 (total contributions),
 *     11 (total expenditures) and 16 (ending cash).
 *  3. RCPT_CD (itemized receipts): Schedule A (cash) and C (non-cash) contributions on the
 *     committee's Form 460s for the calendar year of its latest statement. Schedule I
 *     ("miscellaneous increases", e.g. investment income) is not a donor and is skipped.
 *  4. S497_CD (late contribution reports): contributions received after the latest
 *     statement's period (Form 497 part 1), which the next statement will itemize; and
 *     contributions the committee made to other committees (part 2), with the measure the
 *     filing names — the money path between committees.
 *
 * Self-reports to politician_cleanup_run_metrics as 'cal-access-finance'. Its findings are
 * data anomalies: a committee's calendar-year total going down between statements, or its
 * itemized donors adding up to more than it reported raising. Either usually means an
 * amendment was mis-read or the export changed format.
 */
class ImportCalAccessCommitteeFinance extends Command
{
    protected $signature = 'ballot-measures:import-cal-access
        {--file= : Path to a downloaded dbwebexport.zip (downloads it when omitted)}
        {--dry-run : Report only, no DB writes}';

    protected $description = 'Import Form 460 totals, donors, late contributions and transfers from the CAL-ACCESS export for California committees linked to ballot measures.';

    /** Form 460 summary-page lines we keep. */
    private const LINES = ['4', '5', '11', '16'];

    /** Contributions a committee made are kept from this many years back, for link suggestions. */
    private const TRANSFER_YEARS = 2;

    public function handle(): int
    {
        $startedAt = now();
        $dryRun = (bool) $this->option('dry-run');
        $writer = new CommitteeFinanceWriter('CA', 'cal-access', 'cal-access-finance');

        $committeeIds = BallotMeasureCommittee::query()
            ->where('state', 'CA')
            ->where('status', '!=', BallotMeasureCommittee::STATUS_REJECTED)
            ->distinct()->pluck('committee_id')
            ->mapWithKeys(fn ($id) => [(string) $id => true])->all();

        if ($committeeIds === []) {
            $this->info('No California committees are linked to ballot measures; nothing to import.');
            if (! $dryRun) {
                $writer->recordMetrics($startedAt, 0, 0, []);
            }

            return self::SUCCESS;
        }

        $path = $this->option('file') ?: $this->download();
        $downloaded = ! $this->option('file');

        try {
            $export = new CalAccessExport($path);
            [$filers, $filings, $lateFilings] = $this->readCoverPages($export, $committeeIds);
            $amounts = $this->readSummaries($export, $filings);
            $latestPeriod = $this->latestPeriods($filings);
            $donors = $this->readReceipts($export, $filings, $latestPeriod);
            [$late, $transfers] = $this->readLateReports($export, $lateFilings, $latestPeriod, $donors);
        } finally {
            if ($downloaded) {
                @unlink($path);
            }
        }

        $anomalies = [];
        $written = 0;

        foreach (array_keys($committeeIds) as $committeeId) {
            $committeeId = (string) $committeeId;
            $filer = $filers[$committeeId] ?? null;
            $previous = $writer->latest($committeeId);

            $committeeFilings = array_filter($filings, fn ($cover) => $cover['committee_id'] === $committeeId);
            $written += count($committeeFilings);

            if ($dryRun) {
                continue;
            }

            $writer->transaction(function () use ($writer, $committeeId, $filer, $committeeFilings, $amounts, $latestPeriod, $late, $donors, $transfers) {
                $writer->saveFiler($committeeId, [
                    'found' => $filer !== null,
                    'filer_name' => $filer['name'] ?? null,
                    'latest_filing_on' => $filer['latest'] ?? null,
                    'late_contributions' => isset($latestPeriod[$committeeId]) ? ($late[$committeeId] ?? 0.0) : null,
                    'late_since' => $latestPeriod[$committeeId] ?? null,
                ]);

                foreach ($committeeFilings as $filingId => $cover) {
                    $writer->saveSnapshot($committeeId, (string) $filingId, $this->snapshotAttributes($cover, $amounts[$filingId] ?? []));
                }

                if (isset($latestPeriod[$committeeId])) {
                    $writer->saveDonors($committeeId, (int) substr($latestPeriod[$committeeId], 0, 4), $donors[$committeeId] ?? []);
                }
                $writer->saveTransfers($committeeId, $transfers[$committeeId] ?? []);
            });

            $itemized = array_sum(array_map(fn ($d) => $d['amount'] - $d['late'], $donors[$committeeId] ?? []));
            foreach ($writer->anomalies($previous, $writer->latest($committeeId), $itemized) as $anomaly) {
                $anomalies[$anomaly] = ($anomalies[$anomaly] ?? 0) + 1;
                $this->warn("CA {$committeeId}: {$anomaly}");
            }
        }

        if (! $dryRun) {
            $writer->recordMetrics($startedAt, array_sum($anomalies), $written, $anomalies);
        }

        $found = count($filers);
        $missing = count($committeeIds) - $found;
        $this->info(($dryRun ? '[dry run] ' : '').'Checked '.count($committeeIds)." CA committee(s): {$found} found, {$missing} not found; {$written} Form 460 filing(s), "
            .array_sum(array_map('count', $donors)).' donor(s), '.array_sum(array_map('count', $transfers)).' transfer(s); '.array_sum($anomalies).' anomaly(ies).');

        return self::SUCCESS;
    }

    /**
     * @param  array<string, true>  $committeeIds
     * @return array{0: array<string, array{name: string, latest: ?string}>, 1: array<string, array<string, mixed>>, 2: array<string, array{committee_id: string, amend_id: int}>}
     */
    private function readCoverPages(CalAccessExport $export, array $committeeIds): array
    {
        $filers = [];
        $filings = [];
        $lateFilings = [];

        $rows = $export->rows('CVR_CAMPAIGN_DISCLOSURE_CD', fn (array $f, array $c) => isset($committeeIds[$f[$c['FILER_ID']] ?? '']));

        foreach ($rows as $row) {
            $committeeId = $row['FILER_ID'];
            $filed = $this->date($row['RPT_DATE']);
            $amendId = (int) $row['AMEND_ID'];

            // The name on the most recent filing of any form, e.g. a late contribution report.
            $known = $filers[$committeeId] ?? null;
            if ($known === null || ($filed !== null && ($known['latest'] === null || $filed >= $known['latest']))) {
                $filers[$committeeId] = ['name' => trim($row['FILER_NAML']), 'latest' => $filed ?? $known['latest'] ?? null];
            }

            if ($row['FORM_TYPE'] === 'F497') {
                if (($lateFilings[$row['FILING_ID']]['amend_id'] ?? -1) < $amendId) {
                    $lateFilings[$row['FILING_ID']] = ['committee_id' => $committeeId, 'amend_id' => $amendId];
                }

                continue;
            }

            if ($row['FORM_TYPE'] !== 'F460' || ($filings[$row['FILING_ID']]['amend_id'] ?? -1) > $amendId) {
                continue;
            }

            $balName = trim($row['BAL_NAME']) ?: null;
            $filings[$row['FILING_ID']] = [
                'committee_id' => $committeeId,
                'amend_id' => $amendId,
                'from' => $this->date($row['FROM_DATE']),
                'thru' => $this->date($row['THRU_DATE']),
                'filed' => $filed,
                // Committees often leave the number blank and write it in the name ("PROPOSITION 40: …").
                'bal_num' => (trim($row['BAL_NUM']) ?: MeasureCommitteeRules::measureNumberFrom($balName)) ?: null,
                'bal_name' => $balName,
                'position' => $this->position($row['SUP_OPP_CD']),
            ];
        }

        return [$filers, $filings, $lateFilings];
    }

    /**
     * @param  array<string, array<string, mixed>>  $filings
     * @return array<string, array<string, array{a: ?float, b: ?float}>>
     */
    private function readSummaries(CalAccessExport $export, array $filings): array
    {
        $amounts = [];
        if ($filings === []) {
            return $amounts;
        }

        $rows = $export->rows(
            'SMRY_CD',
            fn (array $f, array $c) => ($f[$c['FORM_TYPE']] ?? '') === 'F460' && in_array($f[$c['LINE_ITEM']] ?? '', self::LINES, true),
            $filings,
        );

        foreach ($rows as $row) {
            // Only the amendment we kept from the cover pages.
            if ((int) $row['AMEND_ID'] !== $filings[$row['FILING_ID']]['amend_id']) {
                continue;
            }

            $amounts[$row['FILING_ID']][$row['LINE_ITEM']] = [
                'a' => $row['AMOUNT_A'] === '' ? null : (float) $row['AMOUNT_A'],
                'b' => $row['AMOUNT_B'] === '' ? null : (float) $row['AMOUNT_B'],
            ];
        }

        return $amounts;
    }

    /**
     * Each committee's latest Form 460 period end (Y-m-d): its donor year, and the date after
     * which late contribution reports add to its totals.
     *
     * @param  array<string, array<string, mixed>>  $filings
     * @return array<string, string>
     */
    private function latestPeriods(array $filings): array
    {
        $latest = [];
        foreach ($filings as $cover) {
            if ($cover['thru'] !== null && $cover['thru'] > ($latest[$cover['committee_id']] ?? '')) {
                $latest[$cover['committee_id']] = $cover['thru'];
            }
        }

        return $latest;
    }

    /**
     * Schedule A and C contributions on each committee's Form 460s in the calendar year of
     * its latest statement, aggregated by donor.
     *
     * @param  array<string, array<string, mixed>>  $filings
     * @param  array<string, string>  $latestPeriod
     * @return array<string, array<string, array<string, mixed>>> committee ID => donor key => donor
     */
    private function readReceipts(CalAccessExport $export, array $filings, array $latestPeriod): array
    {
        $yearFilings = array_filter($filings, fn ($cover) => $cover['thru'] !== null
            && isset($latestPeriod[$cover['committee_id']])
            && substr($cover['thru'], 0, 4) === substr($latestPeriod[$cover['committee_id']], 0, 4));

        $donors = [];
        if ($yearFilings === []) {
            return $donors;
        }

        $rows = $export->rows('RCPT_CD', fn (array $f, array $c) => in_array($f[$c['FORM_TYPE']] ?? '', ['A', 'C'], true), $yearFilings);

        foreach ($rows as $row) {
            $cover = $yearFilings[$row['FILING_ID']];
            if ((int) $row['AMEND_ID'] !== $cover['amend_id'] || $row['MEMO_CODE'] === 'X') {
                continue;
            }

            $this->addDonor($donors, $cover['committee_id'], $row, 'CTRIB', (float) $row['AMOUNT'], nonmonetary: $row['FORM_TYPE'] === 'C');
        }

        return $donors;
    }

    /**
     * Form 497 reports: part 1 contributions received after the latest statement (added to
     * $donors and totalled per committee), and part 2 contributions made to committees.
     *
     * @param  array<string, array{committee_id: string, amend_id: int}>  $lateFilings
     * @param  array<string, string>  $latestPeriod
     * @param  array<string, array<string, array<string, mixed>>>  $donors
     * @return array{0: array<string, float>, 1: array<string, array<string, array<string, mixed>>>}
     */
    private function readLateReports(CalAccessExport $export, array $lateFilings, array $latestPeriod, array &$donors): array
    {
        $late = [];
        $transfers = [];
        if ($lateFilings === []) {
            return [$late, $transfers];
        }

        $transferCutoff = now()->subYears(self::TRANSFER_YEARS)->startOfYear()->toDateString();
        $rows = $export->rows('S497_CD', fn (array $f, array $c) => in_array($f[$c['FORM_TYPE']] ?? '', ['F497P1', 'F497P2'], true), $lateFilings);

        foreach ($rows as $row) {
            $filing = $lateFilings[$row['FILING_ID']];
            if ((int) $row['AMEND_ID'] !== $filing['amend_id'] || $row['MEMO_CODE'] === 'X') {
                continue;
            }

            $committeeId = $filing['committee_id'];
            $date = $this->date($row['CTRIB_DATE']);
            $amount = (float) $row['AMOUNT'];

            if ($row['FORM_TYPE'] === 'F497P1') {
                $since = $latestPeriod[$committeeId] ?? null;
                if ($since !== null && $date !== null && $date > $since && substr($date, 0, 4) === substr($since, 0, 4)) {
                    $late[$committeeId] = ($late[$committeeId] ?? 0.0) + $amount;
                    $this->addDonor($donors, $committeeId, $row, 'ENTY', $amount, late: true);
                }

                continue;
            }

            $toCommittee = trim($row['CMTE_ID']);
            if ($toCommittee === '' || $date === null || $date < $transferCutoff) {
                continue;
            }

            $number = MeasureCommitteeRules::measureNumberFrom(trim($row['BAL_NUM']) ? 'Measure '.trim($row['BAL_NUM']) : $row['BAL_NAME']);
            $key = $toCommittee.'|'.($number ?? '');
            $existing = $transfers[$committeeId][$key] ?? null;
            $transfers[$committeeId][$key] = [
                'to_committee_id' => $toCommittee,
                'to_committee_name' => trim($row['ENTY_NAML']),
                'measure_reference' => trim($row['BAL_NAME']) ?: ($existing['measure_reference'] ?? null),
                'measure_number' => $number,
                'measure_jurisdiction' => trim($row['BAL_JURIS']) ?: ($existing['measure_jurisdiction'] ?? null),
                'position' => $this->position($row['SUP_OPP_CD']) ?? $existing['position'] ?? null,
                'amount' => ($existing['amount'] ?? 0.0) + $amount,
                'latest_on' => max($date, $existing['latest_on'] ?? ''),
            ];
        }

        return [$late, $transfers];
    }

    /**
     * @param  array<string, array<string, array<string, mixed>>>  $donors
     * @param  array<string, string>  $row
     */
    private function addDonor(array &$donors, string $committeeId, array $row, string $prefix, float $amount, bool $nonmonetary = false, bool $late = false): void
    {
        $last = trim($row["{$prefix}_NAML"]);
        $first = trim($row["{$prefix}_NAMF"]);
        $name = $first !== '' ? "{$first} {$last}" : $last;
        if ($name === '') {
            return;
        }

        $donorCommittee = trim($row['CMTE_ID'] ?? '') ?: null;
        $key = $donorCommittee !== null ? "cmte:{$donorCommittee}" : mb_strtoupper(trim($row['ENTITY_CD']).'|'.$name);

        $donor = $donors[$committeeId][$key] ?? [
            'donor_name' => $name,
            'entity_type' => trim($row['ENTITY_CD']) ?: null,
            'donor_committee_id' => $donorCommittee,
            'employer' => null,
            'amount' => 0.0,
            'nonmonetary' => 0.0,
            'late' => 0.0,
        ];

        $donor['amount'] += $amount;
        $donor['nonmonetary'] += $nonmonetary ? $amount : 0.0;
        $donor['late'] += $late ? $amount : 0.0;
        $employer = trim($row['CTRIB_EMP'] ?? '');
        if ($donor['entity_type'] === 'IND' && $donor['employer'] === null && ! in_array(strtoupper($employer), ['', 'NONE', 'N/A', 'NA'], true)) {
            $donor['employer'] = $employer;
        }

        $donors[$committeeId][$key] = $donor;
    }

    /**
     * @param  array<string, mixed>  $cover
     * @param  array<string, array{a: ?float, b: ?float}>  $lines
     * @return array<string, mixed>
     */
    private function snapshotAttributes(array $cover, array $lines): array
    {
        return [
            'amend_id' => $cover['amend_id'],
            'form_type' => 'F460',
            'period_start' => $cover['from'],
            'period_end' => $cover['thru'],
            'filed_on' => $cover['filed'],
            'contributions_period' => $lines['5']['a'] ?? null,
            'contributions_ytd' => $lines['5']['b'] ?? null,
            'nonmonetary_ytd' => $lines['4']['b'] ?? null,
            'expenditures_ytd' => $lines['11']['b'] ?? null,
            'cash_on_hand' => $lines['16']['a'] ?? null,
            'declared_measure_number' => $cover['bal_num'],
            'declared_measure_name' => $cover['bal_name'],
            'declared_position' => $cover['position'],
        ];
    }

    private function position(string $code): ?string
    {
        return match (strtoupper(trim($code))) {
            'S' => 'support',
            'O' => 'oppose',
            default => null,
        };
    }

    private function download(): string
    {
        $path = storage_path('app/imports/dbwebexport-'.now()->format('YmdHis').'.zip');
        @mkdir(dirname($path), 0775, true);

        $this->info('Downloading '.CalAccessExport::DOWNLOAD_URL.' …');
        Http::timeout(1800)->sink($path)->get(CalAccessExport::DOWNLOAD_URL)->throw();

        return $path;
    }

    /** CAL-ACCESS dates look like "9/24/2026 12:00:00 AM". */
    private function date(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat('n/j/Y g:i:s A', $value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
