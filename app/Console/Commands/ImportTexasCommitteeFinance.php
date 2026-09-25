<?php

namespace App\Console\Commands;

use App\Models\BallotMeasureCommittee;
use App\Services\CampaignFinance\CommitteeFinanceWriter;
use App\Services\CampaignFinance\TexasEthicsExport;
use App\Support\MeasureCommitteeRules;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Imports campaign finance data from the Texas Ethics Commission's nightly export for every
 * Texas committee linked to a ballot measure (any link that isn't rejected). Runs nightly in
 * .github/workflows/import-texas.yml, followed by the link audit.
 *
 * The Commission covers state-level filers, including general-purpose PACs that report on
 * city, county and school district measures; committees that file only with a local clerk
 * aren't in it. Superseded reports (infoOnlyFlag = Y) are skipped throughout.
 *
 *  - filers.csv: whether each filer ID exists and its name; also every committee's name,
 *    to recognize donors and payees that are committees.
 *  - cover.csv (regular reports): each report's period totals and cash on hand. Texas
 *    reports per period, so year-to-date totals are summed over the calendar year's reports.
 *  - cover_t.csv (daily pre-election reports): only their filing dates. The Commission keeps
 *    daily reports in separate _t files because the same money is re-reported on the next
 *    regular report.
 *  - purpose.csv: the measures each committee says it supports or opposes (number, side,
 *    election date, description), for the filing checks and for link suggestions.
 *  - contribs_*.csv (Schedules A1/AJ1/C1/C3 cash, A2/C2 in-kind): donors for the latest
 *    report's year; cont_t.csv adds contributions received after the latest report.
 *  - expend_*.csv and expn_t.csv: contributions made (category DONATIONS) to other
 *    committees, as transfers.
 *
 * Self-reports to politician_cleanup_run_metrics as 'tx-finance' (see CommitteeFinanceWriter).
 */
class ImportTexasCommitteeFinance extends Command
{
    protected $signature = 'ballot-measures:import-texas
        {--file= : Path to a downloaded TEC_CF_CSV.zip (downloads it when omitted)}
        {--dry-run : Report only, no DB writes}';

    protected $description = 'Import report totals, donors, late contributions, declared measures and transfers from the Texas Ethics Commission export for Texas committees linked to ballot measures.';

    private const CASH_SCHEDULES = ['A1', 'AJ1', 'C1', 'C3'];

    private const IN_KIND_SCHEDULES = ['A2', 'C2'];

    /** Filer types that are committees (not candidates or officeholders). */
    private const COMMITTEE_TYPES = ['GPAC', 'SPAC', 'MPAC', 'JSPAC', 'SCSPAC', 'CEC', 'MCEC', 'PACSS', 'SPACSS', 'DCE', 'SCC'];

    /** Reports, donors and transfers are kept from this many years back. */
    private const YEARS = 2;

    public function handle(): int
    {
        $startedAt = now();
        $dryRun = (bool) $this->option('dry-run');
        $writer = new CommitteeFinanceWriter('TX', 'tec', 'tx-finance');
        $cutoff = now()->subYears(self::YEARS)->startOfYear()->format('Ymd');

        $committeeIds = BallotMeasureCommittee::query()
            ->where('state', 'TX')
            ->where('status', '!=', BallotMeasureCommittee::STATUS_REJECTED)
            ->distinct()->pluck('committee_id')
            ->mapWithKeys(fn ($id) => [(string) $id => true])->all();

        if ($committeeIds === []) {
            $this->info('No Texas committees are linked to ballot measures; nothing to import.');
            if (! $dryRun) {
                $writer->recordMetrics($startedAt, 0, 0, []);
            }

            return self::SUCCESS;
        }

        $path = $this->option('file') ?: $this->download();
        $downloaded = ! $this->option('file');
        $needles = array_map(fn ($id) => ",{$id},", array_keys($committeeIds));

        try {
            $export = new TexasEthicsExport($path);
            [$filerNames, $committeesByName] = $this->readFilers($export, $committeeIds);
            [$reports, $latestFiled] = $this->readCovers($export, $committeeIds, $needles, $cutoff);
            [$declarationsByReport, $latestDeclarationsByFiler] = $this->readPurposes($export, $cutoff);
            $latestPeriod = $this->latestPeriods($reports);
            [$donors, $nonmonetaryByReport, $late] = $this->readContributions($export, $committeeIds, $needles, $reports, $latestPeriod, $committeesByName);
            $transfers = $this->readTransfers($export, $committeeIds, $needles, $cutoff, $committeesByName, $latestDeclarationsByFiler);
        } finally {
            if ($downloaded) {
                @unlink($path);
            }
        }

        $anomalies = [];
        $written = 0;

        foreach (array_keys($committeeIds) as $committeeId) {
            $committeeId = (string) $committeeId;
            $committeeReports = $this->withYearToDate($reports[$committeeId] ?? [], $nonmonetaryByReport);
            $written += count($committeeReports);

            if ($dryRun) {
                continue;
            }

            $previous = $writer->latest($committeeId);

            $writer->transaction(function () use ($writer, $committeeId, $filerNames, $latestFiled, $latestPeriod, $late, $committeeReports, $declarationsByReport, $donors, $transfers) {
                $writer->saveFiler($committeeId, [
                    'found' => isset($filerNames[$committeeId]),
                    'filer_name' => $filerNames[$committeeId] ?? null,
                    'latest_filing_on' => $latestFiled[$committeeId] ?? null,
                    'late_contributions' => isset($latestPeriod[$committeeId]) ? round($late[$committeeId] ?? 0.0, 2) : null,
                    'late_since' => $latestPeriod[$committeeId] ?? null,
                ]);

                foreach ($committeeReports as $reportId => $report) {
                    $writer->saveSnapshot($committeeId, (string) $reportId, $report['snapshot'] + [
                        'declared_measures' => $declarationsByReport[$reportId] ?? null,
                    ]);
                }

                // Every year in the window: Texas decides statewide measures in odd years, so a
                // decided measure needs its election year's donors, not just the latest year's.
                foreach ($donors[$committeeId] ?? [] as $year => $yearDonors) {
                    $writer->saveDonors($committeeId, (int) $year, $yearDonors);
                }
                $writer->saveTransfers($committeeId, $transfers[$committeeId] ?? []);
            });

            $latestYear = isset($latestPeriod[$committeeId]) ? substr($latestPeriod[$committeeId], 0, 4) : null;
            $itemized = array_sum(array_map(fn ($d) => $d['amount'] - $d['late'], $donors[$committeeId][$latestYear] ?? []));
            foreach ($writer->anomalies($previous, $writer->latest($committeeId), $itemized) as $anomaly) {
                $anomalies[$anomaly] = ($anomalies[$anomaly] ?? 0) + 1;
                $this->warn("TX {$committeeId}: {$anomaly}");
            }
        }

        if (! $dryRun) {
            $writer->recordMetrics($startedAt, array_sum($anomalies), $written, $anomalies);
        }

        $found = count(array_intersect_key($filerNames, $committeeIds));
        $this->info(($dryRun ? '[dry run] ' : '').'Checked '.count($committeeIds)." TX committee(s): {$found} found; {$written} report(s), "
            .array_sum(array_map(fn ($years) => array_sum(array_map('count', $years)), $donors)).' donor(s), '.array_sum(array_map('count', $transfers)).' transfer(s); '.array_sum($anomalies).' anomaly(ies).');

        return self::SUCCESS;
    }

    /**
     * @param  array<string, true>  $committeeIds
     * @return array{0: array<string, string>, 1: array<string, string>} linked filer ID => name; committee name key => filer ID
     */
    private function readFilers(TexasEthicsExport $export, array $committeeIds): array
    {
        $names = [];
        $committeesByName = [];

        foreach ($export->rows('filers.csv') as $row) {
            $id = $row['filerIdent'];
            if (isset($committeeIds[$id])) {
                $names[$id] = trim($row['filerName']);
            }
            if (in_array($row['filerTypeCd'], self::COMMITTEE_TYPES, true)) {
                $committeesByName[$this->nameKey($row['filerName'])] = $id;
            }
        }

        return [$names, $committeesByName];
    }

    /**
     * Regular reports (cover.csv) in the window, and each filer's latest filing date across
     * regular and daily reports.
     *
     * @param  array<string, true>  $committeeIds
     * @param  list<string>  $needles
     * @return array{0: array<string, array<string, array<string, mixed>>>, 1: array<string, string>}
     */
    private function readCovers(TexasEthicsExport $export, array $committeeIds, array $needles, string $cutoff): array
    {
        $reports = [];
        $latestFiled = [];

        foreach (['cover.csv' => true, 'cover_t.csv' => false] as $file => $regular) {
            foreach ($export->rows($file, $needles) as $row) {
                $id = $row['filerIdent'];
                if (! isset($committeeIds[$id]) || $row['infoOnlyFlag'] === 'Y') {
                    continue;
                }

                $filed = $this->date($row['filedDt'] ?: $row['receivedDt']);
                if ($filed !== null && $filed > ($latestFiled[$id] ?? '')) {
                    $latestFiled[$id] = $filed;
                }

                if (! $regular || $row['periodEndDt'] < $cutoff) {
                    continue;
                }

                $reports[$id][$row['reportInfoIdent']] = [
                    'start' => $this->date($row['periodStartDt']),
                    'end' => $this->date($row['periodEndDt']),
                    'filed' => $filed,
                    'contributions' => $this->amount($row['totalContribAmount']),
                    'expenditures' => $this->amount($row['totalExpendAmount']),
                    'cash' => $row['contribsMaintainedAmount'] === '' ? null : $this->amount($row['contribsMaintainedAmount']),
                    'type' => $row['reportTypeCd1'],
                ];
            }
        }

        return [$reports, $latestFiled];
    }

    /**
     * Measures declared on committee-purpose sheets: per report (for linked filers'
     * snapshots) and each filer's latest declaring report (to tie transfers to a measure).
     *
     * @return array{0: array<string, list<array<string, ?string>>>, 1: array<string, list<array<string, ?string>>>}
     */
    private function readPurposes(TexasEthicsExport $export, string $cutoff): array
    {
        $byReport = [];
        $latestReportOf = [];

        foreach ($export->rows('purpose.csv') as $row) {
            if ($row['subjectCategoryCd'] !== 'MEASURE' || $row['infoOnlyFlag'] === 'Y' || $row['receivedDt'] < $cutoff) {
                continue;
            }

            foreach ($this->declarations($row) as $declaration) {
                $byReport[$row['reportInfoIdent']][] = $declaration;
            }

            $current = $latestReportOf[$row['filerIdent']] ?? null;
            if ($current === null || $row['receivedDt'] >= $current['received']) {
                $latestReportOf[$row['filerIdent']] = ['report' => $row['reportInfoIdent'], 'received' => $row['receivedDt']];
            }
        }

        $byFiler = [];
        foreach ($latestReportOf as $filerId => $latest) {
            $byFiler[$filerId] = $byReport[$latest['report']] ?? [];
        }

        return [$byReport, $byFiler];
    }

    /**
     * One declaration per measure number. A row can name one measure ("Prop A", "Hood Cty
     * B") or a list ("State Prop" with "2, 3, 5-10" in the description), which is expanded.
     *
     * @param  array<string, string>  $row
     * @return list<array<string, ?string>>
     */
    private function declarations(array $row): array
    {
        $ballot = trim($row['subjectBallotNumber']);
        $description = trim((string) preg_replace('/\s+/', ' ', $row['subjectDescr']));
        $position = match ($row['subjectPositionCd']) {
            'SUPPORT' => 'support',
            'OPPOSE' => 'oppose',
            default => null,
        };

        $number = MeasureCommitteeRules::measureNumberFrom($ballot)
            ?? (preg_match('/^(?:.*\s)?([A-Z]{1,2}|\d{1,3}[A-Z]?)$/', $ballot, $m) ? $m[1] : null);
        $numbers = $number !== null ? [$number] : $this->numberList($ballot.' '.$description);
        if ($numbers === [] && $number === null) {
            $numbers = [MeasureCommitteeRules::measureNumberFrom($description)];
        }

        $electionDate = $this->date($row['subjectElectionDt']);
        $statewide = (bool) preg_match('/constitution|amendment|\b[SH]JR\b|\bstate\b/i', $ballot.' '.$description);

        return array_map(fn ($n) => [
            'number' => $n,
            'position' => $position,
            'election_date' => $electionDate,
            'description' => $description !== '' ? $description : ($statewide ? 'State' : null),
        ], $numbers);
    }

    /**
     * Numbers listed in text, with ranges expanded: "Amendments 2, 5-10,12" → 2, 5 … 10, 12.
     * Only used for lists; a single bare number could be anything (a year, a district).
     *
     * @return list<string>
     */
    private function numberList(string $text): array
    {
        if (! preg_match_all('/\b(\d{1,2})(?:\s*-\s*(\d{1,2}))?\b/', $text, $matches, PREG_SET_ORDER) || count($matches) < 2) {
            return [];
        }

        $numbers = [];
        foreach ($matches as $match) {
            $from = (int) $match[1];
            $to = isset($match[2]) && $match[2] !== '' ? (int) $match[2] : $from;
            for ($n = $from; $n <= min($to, $from + 30); $n++) {
                $numbers[] = (string) $n;
            }
        }

        return array_values(array_unique($numbers));
    }

    /**
     * @param  array<string, array<string, array<string, mixed>>>  $reports
     * @return array<string, string> filer ID => latest regular report period end (Y-m-d)
     */
    private function latestPeriods(array $reports): array
    {
        $latest = [];
        foreach ($reports as $id => $filerReports) {
            foreach ($filerReports as $report) {
                if ($report['end'] !== null && $report['end'] > ($latest[$id] ?? '')) {
                    $latest[$id] = $report['end'];
                }
            }
        }

        return $latest;
    }

    /**
     * Donors per committee and calendar year (regular reports in the window), plus
     * contributions from daily reports dated after its latest regular report; and each
     * report's in-kind total.
     *
     * @param  array<string, true>  $committeeIds
     * @param  list<string>  $needles
     * @param  array<string, array<string, array<string, mixed>>>  $reports
     * @param  array<string, string>  $latestPeriod
     * @param  array<string, string>  $committeesByName
     * @return array{0: array<string, array<string, array<string, array<string, mixed>>>>, 1: array<string, float>, 2: array<string, float>}
     */
    private function readContributions(TexasEthicsExport $export, array $committeeIds, array $needles, array $reports, array $latestPeriod, array $committeesByName): array
    {
        $donors = [];
        $nonmonetaryByReport = [];
        $late = [];
        $schedules = [...self::CASH_SCHEDULES, ...self::IN_KIND_SCHEDULES];

        $files = [...$export->files('/^contribs_\d+\.csv$/'), 'cont_t.csv'];
        foreach ($files as $file) {
            $daily = $file === 'cont_t.csv';

            foreach ($export->rows($file, $needles) as $row) {
                $id = $row['filerIdent'];
                if (! isset($committeeIds[$id]) || $row['infoOnlyFlag'] === 'Y' || ! in_array($row['schedFormTypeCd'], $schedules, true)) {
                    continue;
                }

                $amount = $this->amount($row['contributionAmount']);
                $inKind = in_array($row['schedFormTypeCd'], self::IN_KIND_SCHEDULES, true);
                $since = $latestPeriod[$id] ?? null;

                if ($daily) {
                    $date = $this->date($row['contributionDt']);
                    if ($since === null || $date === null || $date <= $since || substr($date, 0, 4) !== substr($since, 0, 4)) {
                        continue;
                    }
                    $late[$id] = ($late[$id] ?? 0.0) + $amount;
                    $this->addDonor($donors, $id, substr($since, 0, 4), $row, $amount, $inKind, true, $committeesByName);

                    continue;
                }

                $report = $reports[$id][$row['reportInfoIdent']] ?? null;
                if ($report === null) {
                    continue;
                }
                if ($inKind) {
                    $nonmonetaryByReport[$row['reportInfoIdent']] = ($nonmonetaryByReport[$row['reportInfoIdent']] ?? 0.0) + $amount;
                }
                $this->addDonor($donors, $id, substr((string) $report['end'], 0, 4), $row, $amount, $inKind, false, $committeesByName);
            }
        }

        return [$donors, $nonmonetaryByReport, $late];
    }

    /**
     * @param  array<string, array<string, array<string, array<string, mixed>>>>  $donors  committee => year => donor key => donor
     * @param  array<string, string>  $row
     * @param  array<string, string>  $committeesByName
     */
    private function addDonor(array &$donors, string $committeeId, string $year, array $row, float $amount, bool $inKind, bool $late, array $committeesByName): void
    {
        $individual = $row['contributorPersentTypeCd'] === 'INDIVIDUAL';
        $name = $individual
            ? trim(trim($row['contributorNameFirst']).' '.trim($row['contributorNameLast']))
            : trim($row['contributorNameOrganization']);
        if ($name === '') {
            return;
        }

        $donorCommittee = $individual ? null : ($committeesByName[$this->nameKey($name)] ?? null);
        $key = $donorCommittee !== null ? "cmte:{$donorCommittee}" : ($individual ? 'IND|' : 'ORG|').$this->nameKey($name);

        $donor = $donors[$committeeId][$year][$key] ?? [
            'donor_name' => $name,
            'entity_type' => $individual ? 'IND' : ($donorCommittee !== null ? 'COM' : 'OTH'),
            'donor_committee_id' => $donorCommittee,
            'employer' => $individual ? (trim($row['contributorEmployer']) ?: null) : null,
            'amount' => 0.0,
            'nonmonetary' => 0.0,
            'late' => 0.0,
        ];
        $donor['amount'] += $amount;
        $donor['nonmonetary'] += $inKind ? $amount : 0.0;
        $donor['late'] += $late ? $amount : 0.0;
        $donors[$committeeId][$year][$key] = $donor;
    }

    /**
     * Payments to other Texas committees. The measure comes from the recipient's name
     * ("Yes on Prop A") or, failing that, from the recipient's own purpose sheet when it
     * declares exactly one measure.
     *
     * @param  array<string, true>  $committeeIds
     * @param  list<string>  $needles
     * @param  array<string, string>  $committeesByName
     * @param  array<string, list<array<string, ?string>>>  $declarationsByFiler
     * @return array<string, array<string, array<string, mixed>>>
     */
    private function readTransfers(TexasEthicsExport $export, array $committeeIds, array $needles, string $cutoff, array $committeesByName, array $declarationsByFiler): array
    {
        $transfers = [];
        $seen = [];

        foreach ([...$export->files('/^expend_\d+\.csv$/'), 'expn_t.csv'] as $file) {
            foreach ($export->rows($file, $needles) as $row) {
                $id = $row['filerIdent'];
                // Only contributions made (DONATIONS): a payment to "UPS" or "Lyft" isn't a
                // transfer just because a PAC shares the vendor's name.
                if (! isset($committeeIds[$id]) || $row['infoOnlyFlag'] === 'Y' || $row['expendDt'] < $cutoff || $row['expendCatCd'] !== 'DONATIONS') {
                    continue;
                }

                $payee = trim($row['payeeNameOrganization']);
                $toCommittee = $payee === '' ? null : ($committeesByName[$this->nameKey($payee)] ?? null);
                if ($toCommittee === null || $toCommittee === $id) {
                    continue;
                }

                // A daily report's payment is re-reported on the next regular report.
                $amount = $this->amount($row['expendAmount']);
                $dedupe = "{$id}|{$toCommittee}|{$row['expendDt']}|{$amount}";
                if (isset($seen[$dedupe])) {
                    continue;
                }
                $seen[$dedupe] = true;

                $declared = $declarationsByFiler[$toCommittee] ?? [];
                $single = count($declared) === 1 ? $declared[0] : null;
                $number = MeasureCommitteeRules::measureNumberFrom($payee) ?? ($single['number'] ?? null);
                $reference = $single['description'] ?? null;
                $key = $toCommittee.'|'.($number ?? '');
                $existing = $transfers[$id][$key] ?? null;

                $transfers[$id][$key] = [
                    'to_committee_id' => $toCommittee,
                    'to_committee_name' => $payee,
                    'measure_reference' => $reference,
                    'measure_number' => $number,
                    'measure_jurisdiction' => $reference === null ? null
                        : (preg_match('/constitution|amendment|\b[SH]JR\b|^state\b/i', $reference) ? 'STATEWIDE' : 'LOCAL'),
                    'position' => MeasureCommitteeRules::positionFromName($payee) ?? ($single['position'] ?? null),
                    'amount' => ($existing['amount'] ?? 0.0) + $amount,
                    'latest_on' => max((string) $this->date($row['expendDt']), (string) ($existing['latest_on'] ?? '')) ?: null,
                ];
            }
        }

        return $transfers;
    }

    /**
     * Snapshot attributes for each regular report, with year-to-date totals summed over the
     * calendar year's reports up to it (Texas reports cover only their own period).
     *
     * @param  array<string, array<string, mixed>>  $reports
     * @param  array<string, float>  $nonmonetaryByReport
     * @return array<string, array{snapshot: array<string, mixed>}>
     */
    private function withYearToDate(array $reports, array $nonmonetaryByReport): array
    {
        uasort($reports, fn ($a, $b) => [$a['end'], $a['start']] <=> [$b['end'], $b['start']]);

        $result = [];
        $year = null;
        $raised = $spent = $inKind = 0.0;

        foreach ($reports as $reportId => $report) {
            $reportYear = substr((string) $report['end'], 0, 4);
            if ($reportYear !== $year) {
                $year = $reportYear;
                $raised = $spent = $inKind = 0.0;
            }
            $raised += $report['contributions'];
            $spent += $report['expenditures'];
            $inKind += $nonmonetaryByReport[$reportId] ?? 0.0;

            $result[$reportId] = ['snapshot' => [
                'form_type' => (string) $report['type'],
                'period_start' => $report['start'],
                'period_end' => $report['end'],
                'filed_on' => $report['filed'],
                'contributions_period' => round($report['contributions'], 2),
                'contributions_ytd' => round($raised, 2),
                'nonmonetary_ytd' => round($inKind, 2),
                'expenditures_ytd' => round($spent, 2),
                'cash_on_hand' => $report['cash'],
            ]];
        }

        return $result;
    }

    private function nameKey(string $name): string
    {
        $key = trim((string) preg_replace('/[^a-z0-9]+/', ' ', mb_strtolower($name)));

        return (string) preg_replace('/\s+(inc|pac|llc|corp)$/', '', $key);
    }

    private function amount(string $value): float
    {
        return (float) str_replace([',', '$'], '', $value);
    }

    /** TEC dates look like "20251104". */
    private function date(string $value): ?string
    {
        return preg_match('/^(\d{4})(\d{2})(\d{2})$/', trim($value), $m) ? "{$m[1]}-{$m[2]}-{$m[3]}" : null;
    }

    private function download(): string
    {
        $path = storage_path('app/imports/tec-cf-'.now()->format('YmdHis').'.zip');
        @mkdir(dirname($path), 0775, true);

        $this->info('Downloading '.TexasEthicsExport::DOWNLOAD_URL.' …');
        Http::timeout(1800)->sink($path)->get(TexasEthicsExport::DOWNLOAD_URL)->throw();

        return $path;
    }
}
