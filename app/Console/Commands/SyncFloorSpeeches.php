<?php

namespace App\Console\Commands;

use App\Services\CongressionalRecordImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Imports members' floor speeches and statements from the daily Congressional Record
 * (GovInfo; needs CONGRESS_API_KEY, which is an api.data.gov key). One MODS call per day,
 * plus one text fetch per substantive article with a profiled speaker.
 *
 * Usage:
 *   php artisan congress:sync-floor-speeches                                # the last 7 days
 *   php artisan congress:sync-floor-speeches --from=2025-01-03 --to=2025-12-31
 *   php artisan congress:sync-floor-speeches --days=3 --refresh
 */
class SyncFloorSpeeches extends Command
{
    protected $signature = 'congress:sync-floor-speeches
        {--from=    : First day (Y-m-d)}
        {--to=      : Last day (Y-m-d, default today)}
        {--days=7   : Days back from --to when --from is not given}
        {--refresh  : Re-fetch articles already imported}';

    protected $description = 'Import floor speeches and statements from the Congressional Record (GovInfo).';

    public function handle(CongressionalRecordImporter $importer): int
    {
        if (! $importer->isConfigured()) {
            $this->warn('CONGRESS_API_KEY is not set; skipping.');

            return self::SUCCESS;
        }

        $to = Carbon::parse($this->option('to') ?: 'today')->startOfDay();
        $from = $this->option('from')
            ? Carbon::parse($this->option('from'))->startOfDay()
            : $to->copy()->subDays(max(1, (int) $this->option('days')) - 1);

        $totals = ['days' => 0, 'articles' => 0, 'speeches' => 0];
        $failed = 0;
        for ($day = $from->copy(); $day->lte($to); $day->addDay()) {
            try {
                $stats = $importer->importDay($day, (bool) $this->option('refresh'));
            } catch (\Throwable $e) {
                $failed++;
                $this->warn("  ✗ {$day->toDateString()}: {$e->getMessage()}");

                continue;
            }
            if ($stats === null) {
                continue; // No Record issue: not a session day.
            }

            $totals['days']++;
            $totals['articles'] += $stats['articles'];
            $totals['speeches'] += $stats['speeches'];
            $this->line("  ✓ {$day->toDateString()}: {$stats['speeches']} speeches from {$stats['articles']} articles ({$stats['skipped']} already imported)");
        }

        $this->info("Done. Session days: {$totals['days']} | Articles: {$totals['articles']} | Speeches: {$totals['speeches']} | Failed days: {$failed}");

        return $failed > 0 && $totals['days'] === 0 ? self::FAILURE : self::SUCCESS;
    }
}
