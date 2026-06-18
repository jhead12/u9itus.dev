<?php

namespace App\Console\Commands;

use App\Models\Politician;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Backfill profile_photo_url for politicians who are missing a photo.
 *
 * Uses the Wikipedia pageimages API — free, no key required — to fetch a
 * thumbnail for each politician by name.  The Wikipedia page must exist and
 * have an associated image; politicians with no Wikipedia presence are skipped
 * and their photo slot remains empty until a future enrichment run or manual
 * upload fills it in.
 *
 * Only unclaimed (user_id IS NULL) politicians are touched by default; pass
 * --include-claimed to also update profiles owned by a registered user.
 *
 * Usage:
 *   php artisan politicians:backfill-photos
 *   php artisan politicians:backfill-photos --state=CA
 *   php artisan politicians:backfill-photos --limit=200 --dry-run
 *   php artisan politicians:backfill-photos --overwrite   # re-fetch even if photo exists
 */
class BackfillPoliticianPhotos extends Command
{
    protected $signature = 'politicians:backfill-photos
        {--state=             : Two-letter state code — limit to one state}
        {--limit=1000         : Maximum number of politicians to process per run}
        {--overwrite          : Re-fetch even if profile_photo_url is already set}
        {--include-claimed    : Also update politicians who have claimed their profile (user_id IS NOT NULL)}
        {--dry-run            : Report found URLs only — no DB writes}';

    protected $description = 'Backfill profile_photo_url from the Wikipedia pageimages API for politicians missing a photo.';

    private const DELAY_MS  = 350;
    private const USER_AGENT = 'U9itus-civic-enrichment/1.0 (+https://u9itus.dev/about)';

    public function handle(): int
    {
        $stateFilter     = $this->option('state') ? strtoupper(trim((string) $this->option('state'))) : null;
        $limit           = max(1, (int) ($this->option('limit') ?? 1000));
        $overwrite       = (bool) $this->option('overwrite');
        $includeClaimed  = (bool) $this->option('include-claimed');
        $dryRun          = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->line('<fg=yellow>[dry-run] No database writes will occur.</>');
        }

        $politicians = Politician::query()
            ->whereNotNull('full_name')
            ->where('full_name', '!=', '')
            // By default skip claimed profiles so we never overwrite a politician's own photo
            ->when(! $includeClaimed, fn ($q) => $q->whereNull('user_id'))
            // Skip politicians who already have a photo unless --overwrite
            ->when(! $overwrite, fn ($q) => $q->where(function ($q) {
                $q->whereNull('profile_photo_url')
                  ->orWhere('profile_photo_url', '');
            }))
            ->when($stateFilter, fn ($q) => $q->whereRaw("UPPER(COALESCE(state, '')) = ?", [$stateFilter]))
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $total   = $politicians->count();
        $found   = 0;
        $missing = 0;

        $this->line("Scanning {$total} politician(s) for missing photos...\n");

        foreach ($politicians as $pol) {
            $photoUrl = $this->fetchWikipediaPhoto($pol->full_name);

            if ($photoUrl) {
                $this->line("  <fg=cyan>✓</> {$pol->full_name}");
                $this->line("      → {$photoUrl}");

                if (! $dryRun) {
                    $pol->update(['profile_photo_url' => $photoUrl]);
                }

                $found++;
            } else {
                $this->line("  <fg=gray>–</> {$pol->full_name}: no Wikipedia photo");
                $missing++;
            }

            usleep(self::DELAY_MS * 1000);
        }

        $this->newLine();
        $this->info("Done: {$found} photo(s) backfilled, {$missing} politician(s) with no match.");

        return self::SUCCESS;
    }

    /**
     * Look up the politician on Wikipedia and return the thumbnail URL if one exists.
     *
     * Uses the MediaWiki action API with prop=pageimages, which returns the
     * "lead" image from the article — always the politician's headshot for
     * biography pages.
     */
    private function fetchWikipediaPhoto(string $name): ?string
    {
        try {
            $response = Http::timeout(10)
                ->withHeaders(['User-Agent' => self::USER_AGENT])
                ->get('https://en.wikipedia.org/w/api.php', [
                    'action'        => 'query',
                    'titles'        => $name,
                    'prop'          => 'pageimages',
                    'pithumbsize'   => 400,
                    'pilicense'     => 'any',
                    'format'        => 'json',
                    'formatversion' => '2',
                ]);

            if (! $response->ok()) {
                return null;
            }

            $pages = $response->json('query.pages') ?? [];

            foreach ($pages as $page) {
                // Skip missing / disambiguation pages
                if (isset($page['missing']) || ($page['pageid'] ?? -1) < 0) {
                    continue;
                }

                $thumb = $page['thumbnail']['source'] ?? null;

                if ($thumb && str_starts_with($thumb, 'https://')) {
                    return $thumb;
                }
            }
        } catch (\Throwable $e) {
            Log::debug('politicians:backfill-photos wikipedia fetch failed', [
                'name'  => $name,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }
}
