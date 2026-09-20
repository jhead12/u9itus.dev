<?php

namespace App\Console\Commands;

use App\Models\ElectionDataSource;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

/**
 * Per-state coverage of the election_data_sources registry: how many jurisdictions have a
 * ballot-measures or sample-ballot link, and how many of those links checked out.
 *
 * --export-gaps writes the rows that still have no link as a CSV in the layout
 * civic:import-source-urls reads, so the file can be filled in and loaded back.
 *
 * Usage:
 *   php artisan civic:coverage
 *   php artisan civic:coverage --level=county --gaps
 *   php artisan civic:coverage --state=CA --level=municipal --export-gaps=storage/app/ca-gaps.csv
 */
class CivicSourceCoverage extends Command
{
    protected $signature = 'civic:coverage
        {--state= : Limit to one state (two-letter USPS code)}
        {--level= : Limit to one level: state, county, municipal, township or special}
        {--gaps : Only show states that still have rows with no link}
        {--export-gaps= : Write the rows with no link to this CSV, in civic:import-source-urls layout}';

    protected $description = 'Report, per state, how many registry jurisdictions have a ballot-measures / sample-ballot link (and how many are verified).';

    public function handle(): int
    {
        $level = strtolower(trim((string) $this->option('level')));
        if ($level !== '' && ! in_array($level, ElectionDataSource::LEVELS, true)) {
            $this->error('--level must be one of: '.implode(', ', ElectionDataSource::LEVELS));

            return self::FAILURE;
        }

        $hasLink = "((ballot_measures_url IS NOT NULL AND ballot_measures_url <> '') OR (sample_ballot_url IS NOT NULL AND sample_ballot_url <> ''))";

        $stats = $this->scope($level)
            ->selectRaw("state, COUNT(*) AS total, SUM(CASE WHEN {$hasLink} THEN 1 ELSE 0 END) AS linked, "
                ."SUM(CASE WHEN {$hasLink} AND scrape_status = 'ok' THEN 1 ELSE 0 END) AS verified, "
                ."SUM(CASE WHEN source_of_record = 'manual' THEN 1 ELSE 0 END) AS curated")
            ->groupBy('state')
            ->orderBy('state')
            ->get();

        if ($stats->isEmpty()) {
            $this->warn('The registry has no rows for that filter. Run civic:seed-jurisdictions first.');

            return self::SUCCESS;
        }

        $rows = $stats
            ->filter(fn ($s) => ! $this->option('gaps') || (int) $s->linked < (int) $s->total)
            ->map(fn ($s) => [
                $s->state,
                (int) $s->total,
                (int) $s->linked,
                (int) $s->verified,
                (int) $s->total - (int) $s->linked,
                (int) $s->curated,
                $s->total > 0 ? round(100 * $s->linked / $s->total).'%' : '—',
            ])
            ->values()
            ->all();

        $this->table(['State', 'Rows', 'With link', 'Verified ok', 'No link', 'Curated', 'Coverage'], $rows);

        $total = (int) $stats->sum('total');
        $linked = (int) $stats->sum('linked');
        $this->info(sprintf('Total: %d row(s), %d with a link (%d%%), %d verified ok, %d with no link.',
            $total, $linked, $total > 0 ? round(100 * $linked / $total) : 0, (int) $stats->sum('verified'), $total - $linked));

        $export = trim((string) $this->option('export-gaps'));
        if ($export !== '') {
            return $this->exportGaps($export, $level);
        }

        return self::SUCCESS;
    }

    /** @return Builder<ElectionDataSource> */
    private function scope(string $level): Builder
    {
        return ElectionDataSource::query()
            ->when($this->option('state'), fn ($q, $s) => $q->where('state', strtoupper(trim((string) $s))))
            ->when($level !== '', fn ($q) => $q->where('level', $level));
    }

    private function exportGaps(string $path, string $level): int
    {
        $handle = @fopen($path, 'w');
        if (! $handle) {
            $this->error("Cannot write to {$path}");

            return self::FAILURE;
        }

        fputcsv($handle, ['ocd_id', 'state', 'level', 'jurisdiction_name', 'ballot_measures_url', 'sample_ballot_url', 'elections_home_url', 'results_url', 'notes'], ',', '"', '');

        $count = 0;
        $this->scope($level)
            ->where(fn ($q) => $q->where(fn ($w) => $w->whereNull('ballot_measures_url')->orWhere('ballot_measures_url', ''))
                ->where(fn ($w) => $w->whereNull('sample_ballot_url')->orWhere('sample_ballot_url', '')))
            ->orderBy('state')->orderBy('level')->orderBy('jurisdiction_name')
            ->chunk(1000, function ($chunk) use ($handle, &$count) {
                foreach ($chunk as $row) {
                    fputcsv($handle, [$row->ocd_id, $row->state, $row->level, $row->jurisdiction_name, '', '', $row->elections_home_url, '', ''], ',', '"', '');
                    $count++;
                }
            });
        fclose($handle);

        $this->info("Wrote {$count} row(s) with no link to {$path}. Fill in the URL columns, then: php artisan civic:import-source-urls {$path}");

        return self::SUCCESS;
    }
}
