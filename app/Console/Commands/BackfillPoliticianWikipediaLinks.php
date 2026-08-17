<?php

namespace App\Console\Commands;

use App\Models\Politician;
use App\Services\WikipediaLookupService;
use Illuminate\Console\Command;

/**
 * Backfill wikipedia_url for politicians who are missing one.
 *
 * Uses the same Wikipedia search-and-score approach as
 * politicians:backfill-photos, just resolving an article URL instead of a
 * thumbnail. Politicians with no Wikipedia presence are skipped and their
 * wikipedia_url stays empty until a future run or manual entry fills it in.
 *
 * Only unclaimed (user_id IS NULL) politicians are touched by default; pass
 * --include-claimed to also update profiles owned by a registered user.
 *
 * Usage:
 *   php artisan politicians:backfill-wikipedia
 *   php artisan politicians:backfill-wikipedia --state=CA
 *   php artisan politicians:backfill-wikipedia --limit=200 --dry-run
 */
class BackfillPoliticianWikipediaLinks extends Command
{
    protected $signature = 'politicians:backfill-wikipedia
        {--state=             : Two-letter state code — limit to one state}
        {--limit=1000         : Maximum number of politicians to process per run}
        {--overwrite          : Re-fetch even if wikipedia_url is already set}
        {--include-claimed    : Also update politicians who have claimed their profile (user_id IS NOT NULL)}
        {--dry-run            : Report found URLs only — no DB writes}';

    protected $description = 'Backfill wikipedia_url from the Wikipedia search API for politicians missing one.';

    private const DELAY_MS = 350;

    public function handle(WikipediaLookupService $wikipedia): int
    {
        $stateFilter = $this->option('state') ? strtoupper(trim((string) $this->option('state'))) : null;
        $limit = max(1, (int) ($this->option('limit') ?? 1000));
        $overwrite = (bool) $this->option('overwrite');
        $includeClaimed = (bool) $this->option('include-claimed');
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->line('<fg=yellow>[dry-run] No database writes will occur.</>');
        }

        $politicians = Politician::query()
            ->whereNotNull('full_name')
            ->where('full_name', '!=', '')
            ->when(! $includeClaimed, fn ($q) => $q->whereNull('user_id'))
            ->when(! $overwrite, fn ($q) => $q->where(function ($q) {
                $q->whereNull('wikipedia_url')
                    ->orWhere('wikipedia_url', '');
            }))
            ->when($stateFilter, fn ($q) => $q->whereRaw("UPPER(COALESCE(state, '')) = ?", [$stateFilter]))
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $total = $politicians->count();
        $found = 0;
        $missing = 0;

        $this->line("Scanning {$total} politician(s) for missing Wikipedia links...\n");

        foreach ($politicians as $pol) {
            $url = $wikipedia->resolveUrl(
                (string) $pol->full_name,
                (string) ($pol->state ?? ''),
                (string) ($pol->political_office ?? '')
            );

            if ($url) {
                $this->line("  <fg=cyan>✓</> {$pol->full_name}");
                $this->line("      → {$url}");

                if (! $dryRun) {
                    $pol->update(['wikipedia_url' => $url]);
                }

                $found++;
            } else {
                $this->line("  <fg=gray>–</> {$pol->full_name}: no Wikipedia match");
                $missing++;
            }

            usleep(self::DELAY_MS * 1000);
        }

        $this->newLine();
        $this->info("Done: {$found} link(s) backfilled, {$missing} politician(s) with no match.");

        return self::SUCCESS;
    }
}
