<?php

namespace App\Console\Commands;

use App\Models\BallotMeasureCommittee;
use App\Models\CommitteeFiler;
use App\Models\CommitteeFinanceSnapshot;
use App\Services\CampaignFinance\CalAccessExport;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Imports Form 460 summary totals from the CAL-ACCESS daily export for every California
 * committee linked to a ballot measure (any link that isn't rejected). Runs nightly in
 * .github/workflows/import-cal-access.yml, before politicians-cleanup.yml audits the links.
 *
 * Two passes over the export:
 *  1. CVR_CAMPAIGN_DISCLOSURE_CD (cover pages): for each linked filer ID, whether it filed at
 *     all, the name it files under, its latest filing date, and each Form 460's period and
 *     declared ballot measure/side — keeping only the latest amendment of each filing.
 *  2. SMRY_CD (summary pages): lines 4 (non-cash), 5 (total contributions), 11 (total
 *     expenditures) and 16 (ending cash) of those Form 460s.
 *
 * Self-reports to politician_cleanup_run_metrics as 'cal-access-finance'. Its findings are
 * data anomalies — a committee's calendar-year total going down between filings — which
 * usually means an amendment was mis-read or the export changed format.
 */
class ImportCalAccessCommitteeFinance extends Command
{
    protected $signature = 'ballot-measures:import-cal-access
        {--file= : Path to a downloaded dbwebexport.zip (downloads it when omitted)}
        {--dry-run : Report only, no DB writes}';

    protected $description = 'Import Form 460 totals from the CAL-ACCESS export for California committees linked to ballot measures.';

    /** Form 460 summary-page lines we keep. */
    private const LINES = ['4', '5', '11', '16'];

    public function handle(): int
    {
        $startedAt = now();
        $dryRun = (bool) $this->option('dry-run');

        $committeeIds = BallotMeasureCommittee::query()
            ->where('state', 'CA')
            ->where('status', '!=', BallotMeasureCommittee::STATUS_REJECTED)
            ->distinct()->pluck('committee_id')
            ->flip()->all();

        if ($committeeIds === []) {
            $this->info('No California committees are linked to ballot measures; nothing to import.');
            $this->recordMetrics($startedAt, 0, 0, [], $dryRun);

            return self::SUCCESS;
        }

        $path = $this->option('file') ?: $this->download();
        $downloaded = ! $this->option('file');

        try {
            $export = new CalAccessExport($path);
            [$filers, $filings] = $this->readCoverPages($export, $committeeIds);
            $amounts = $this->readSummaries($export, $filings);
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
            $previous = CommitteeFinanceSnapshot::latestFor('CA', $committeeId);

            if (! $dryRun) {
                CommitteeFiler::updateOrCreate(
                    ['state' => 'CA', 'committee_id' => $committeeId],
                    [
                        'source' => 'cal-access',
                        'found' => $filer !== null,
                        'filer_name' => $filer['name'] ?? null,
                        'latest_filing_on' => $filer['latest'] ?? null,
                        'checked_at' => now(),
                    ],
                );
            }

            foreach ($filings as $filingId => $cover) {
                if ($cover['committee_id'] !== $committeeId) {
                    continue;
                }

                $lines = $amounts[$filingId] ?? [];
                $attrs = [
                    'source' => 'cal-access',
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

                if (! $dryRun) {
                    CommitteeFinanceSnapshot::updateOrCreate(
                        ['state' => 'CA', 'committee_id' => $committeeId, 'filing_id' => (string) $filingId],
                        $attrs,
                    );
                }
                $written++;
            }

            $latest = $dryRun ? null : CommitteeFinanceSnapshot::latestFor('CA', $committeeId);
            if ($this->totalDecreased($previous, $latest)) {
                $anomalies['total_decreased'] = ($anomalies['total_decreased'] ?? 0) + 1;
                $this->warn("CA {$committeeId}: calendar-year contributions fell from {$previous->contributions_ytd} to {$latest->contributions_ytd} (filing {$latest->filing_id}).");
            }
        }

        $found = count($filers);
        $missing = count($committeeIds) - $found;
        $this->recordMetrics($startedAt, array_sum($anomalies), $written, $anomalies, $dryRun);
        $this->info(($dryRun ? '[dry run] ' : '').'Checked '.count($committeeIds)." CA committee(s): {$found} found, {$missing} not found; {$written} Form 460 filing(s) imported, ".array_sum($anomalies).' anomaly(ies).');

        return self::SUCCESS;
    }

    /**
     * @param  array<string, int>  $committeeIds  filer ID => anything
     * @return array{0: array<string, array{name: string, latest: ?string}>, 1: array<string, array<string, mixed>>}
     */
    private function readCoverPages(CalAccessExport $export, array $committeeIds): array
    {
        $filers = [];
        $filings = [];

        $rows = $export->rows('CVR_CAMPAIGN_DISCLOSURE_CD', fn (array $f, array $c) => isset($committeeIds[$f[$c['FILER_ID']] ?? '']));

        foreach ($rows as $row) {
            $committeeId = $row['FILER_ID'];
            $filed = $this->date($row['RPT_DATE']);

            // The name on the most recent filing of any form, e.g. a late contribution report.
            $known = $filers[$committeeId] ?? null;
            if ($known === null || ($filed !== null && ($known['latest'] === null || $filed >= $known['latest']))) {
                $filers[$committeeId] = ['name' => trim($row['FILER_NAML']), 'latest' => $filed ?? $known['latest'] ?? null];
            }

            if ($row['FORM_TYPE'] !== 'F460') {
                continue;
            }

            $amendId = (int) $row['AMEND_ID'];
            if (isset($filings[$row['FILING_ID']]) && $filings[$row['FILING_ID']]['amend_id'] > $amendId) {
                continue;
            }

            $filings[$row['FILING_ID']] = [
                'committee_id' => $committeeId,
                'amend_id' => $amendId,
                'from' => $this->date($row['FROM_DATE']),
                'thru' => $this->date($row['THRU_DATE']),
                'filed' => $filed,
                'bal_num' => trim($row['BAL_NUM']) ?: null,
                'bal_name' => trim($row['BAL_NAME']) ?: null,
                'position' => match (strtoupper(trim($row['SUP_OPP_CD']))) {
                    'S' => 'support',
                    'O' => 'oppose',
                    default => null,
                },
            ];
        }

        return [$filers, $filings];
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

        $rows = $export->rows('SMRY_CD', fn (array $f, array $c) => isset($filings[$f[$c['FILING_ID']] ?? ''])
            && ($f[$c['FORM_TYPE']] ?? '') === 'F460'
            && in_array($f[$c['LINE_ITEM']] ?? '', self::LINES, true));

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

    /** A later filing in the same calendar year reporting less raised year to date. */
    private function totalDecreased(?CommitteeFinanceSnapshot $previous, ?CommitteeFinanceSnapshot $latest): bool
    {
        return $previous !== null && $latest !== null
            && $previous->filing_id !== $latest->filing_id
            && $previous->period_end !== null && $latest->period_end !== null
            && $previous->period_end->year === $latest->period_end->year
            && $latest->period_end->gte($previous->period_end)
            && $previous->contributions_ytd !== null && $latest->contributions_ytd !== null
            && $latest->contributions_ytd < $previous->contributions_ytd;
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

    /** @param  array<string, int>  $breakdown */
    private function recordMetrics(Carbon $startedAt, int $findings, int $written, array $breakdown, bool $dryRun): void
    {
        if ($dryRun) {
            return;
        }

        DB::table('politician_cleanup_run_metrics')->insert([
            'step' => 'cal-access-finance',
            'scope' => 'CA',
            'exit_code' => self::SUCCESS,
            'findings_count' => $findings,
            'auto_applied_count' => $written,
            'queued_count' => 0,
            'breakdown' => json_encode($breakdown),
            'started_at' => $startedAt,
            'finished_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
