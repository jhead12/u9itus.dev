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
            $photoUrl = $this->fetchWikipediaPhoto(
                (string) $pol->full_name,
                (string) ($pol->state ?? ''),
                (string) ($pol->political_office ?? '')
            );

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
     * Look up the politician on Wikipedia and return a likely headshot URL.
     *
     * We first try the exact-title page, then search candidate pages and score
     * them for biography-likelihood. We reject obvious non-person images
     * (flags, seals, coats of arms, logos).
     */
    private function fetchWikipediaPhoto(string $name, string $state = '', string $office = ''): ?string
    {
        try {
            // 1) Exact title lookup first (fast path)
            $response = Http::timeout(10)
                ->withHeaders(['User-Agent' => self::USER_AGENT])
                ->get('https://en.wikipedia.org/w/api.php', [
                    'action'        => 'query',
                    'titles'        => $name,
                    'prop'          => 'pageimages|description',
                    'piprop'        => 'thumbnail|name',
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
                $imageName = (string) ($page['pageimage'] ?? '');
                $title = (string) ($page['title'] ?? '');
                $description = (string) ($page['description'] ?? '');

                if (
                    $thumb
                    && str_starts_with($thumb, 'https://')
                    && $this->isLikelyPersonPage($title, $description, $name, $office)
                    && ! $this->isLikelyNonPersonImage($thumb, $imageName)
                ) {
                    return $thumb;
                }
            }

            // 2) Search and score likely biography pages when exact title misses
            $searchQuery = trim($name . ' ' . $state . ' ' . $office . ' politician');
            $searchResp = Http::timeout(10)
                ->withHeaders(['User-Agent' => self::USER_AGENT])
                ->get('https://en.wikipedia.org/w/api.php', [
                    'action'        => 'query',
                    'list'          => 'search',
                    'srsearch'      => $searchQuery,
                    'srlimit'       => 5,
                    'srwhat'        => 'title',
                    'format'        => 'json',
                    'formatversion' => '2',
                ]);

            if (! $searchResp->ok()) {
                return null;
            }

            $titles = collect($searchResp->json('query.search') ?? [])
                ->pluck('title')
                ->filter(fn($t) => is_string($t) && trim($t) !== '')
                ->values()
                ->all();

            if (empty($titles)) {
                return null;
            }

            $candidatesResp = Http::timeout(10)
                ->withHeaders(['User-Agent' => self::USER_AGENT])
                ->get('https://en.wikipedia.org/w/api.php', [
                    'action'        => 'query',
                    'titles'        => implode('|', $titles),
                    'prop'          => 'pageimages|description',
                    'piprop'        => 'thumbnail|name',
                    'pithumbsize'   => 400,
                    'pilicense'     => 'any',
                    'format'        => 'json',
                    'formatversion' => '2',
                ]);

            if (! $candidatesResp->ok()) {
                return null;
            }

            $bestScore = -1;
            $bestThumb = null;
            $targetName = strtolower(trim($name));

            foreach (($candidatesResp->json('query.pages') ?? []) as $page) {
                if (isset($page['missing']) || ($page['pageid'] ?? -1) < 0) {
                    continue;
                }

                $title       = (string) ($page['title'] ?? '');
                $description = (string) ($page['description'] ?? '');
                $thumb       = (string) ($page['thumbnail']['source'] ?? '');
                $imageName   = (string) ($page['pageimage'] ?? '');

                if ($thumb === '' || ! str_starts_with($thumb, 'https://')) {
                    continue;
                }
                if (! $this->isLikelyPersonPage($title, $description, $name, $office)) {
                    continue;
                }
                if ($this->isLikelyNonPersonImage($thumb, $imageName)) {
                    continue;
                }

                $score = 0;
                $lowerTitle = strtolower($title);
                $lowerDesc  = strtolower($description);

                if ($lowerTitle === $targetName) {
                    $score += 60;
                }
                if (str_contains($lowerTitle, $targetName)) {
                    $score += 25;
                }
                if (str_contains($lowerDesc, 'politician') || str_contains($lowerDesc, 'mayor') || str_contains($lowerDesc, 'governor') || str_contains($lowerDesc, 'attorney general') || str_contains($lowerDesc, 'senator') || str_contains($lowerDesc, 'representative')) {
                    $score += 25;
                }

                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestThumb = $thumb;
                }
            }

            if (is_string($bestThumb) && $bestThumb !== '') {
                return $bestThumb;
            }
        } catch (\Throwable $e) {
            Log::debug('politicians:backfill-photos wikipedia fetch failed', [
                'name'  => $name,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    private function isLikelyPersonPage(string $title, string $description, string $targetName, string $office): bool
    {
        $t = strtolower(trim($title));
        $d = strtolower(trim($description));
        $n = strtolower(trim($targetName));
        $o = strtolower(trim($office));

        if ($t === '' || str_contains($t, 'disambiguation')) {
            return false;
        }

        // Reject institutional pages that often produce flags/seals.
        foreach (['governor of ', 'secretary of state of ', 'attorney general of ', 'state treasurer of ', 'state controller of ', 'office of ', 'flag of ', 'seal of ', 'coat of arms'] as $bad) {
            if (str_starts_with($t, $bad) || str_contains($t, $bad)) {
                return false;
            }
        }

        // Prefer pages that look like a personal biography.
        if ($n !== '' && str_contains($t, $n)) {
            return true;
        }

        if ($d !== '') {
            foreach (['politician', 'mayor', 'governor', 'attorney general', 'senator', 'representative', 'american politician'] as $signal) {
                if (str_contains($d, $signal)) {
                    return true;
                }
            }
            if ($o !== '' && str_contains($d, $o)) {
                return true;
            }
        }

        return false;
    }

    private function isLikelyNonPersonImage(string $thumbUrl, string $imageName = ''): bool
    {
        $v = strtolower($thumbUrl . ' ' . $imageName);

        foreach (['flag_of_', 'state_flag', 'seal_of_', 'state_seal', 'coat_of_arms', 'logo', 'wordmark'] as $bad) {
            if (str_contains($v, $bad)) {
                return true;
            }
        }

        return false;
    }
}
