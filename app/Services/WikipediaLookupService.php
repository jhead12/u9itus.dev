<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Resolves a politician's Wikipedia article URL by name.
 *
 * Free, no API key required. Same exact-title-then-search-and-score approach
 * as BackfillPoliticianPhotos (which resolves a headshot via the same API),
 * scoring candidate pages for biography-likelihood so institutional pages
 * (e.g. "Governor of California") aren't mistaken for the person's own page.
 */
class WikipediaLookupService
{
    private const USER_AGENT = 'U9itus-civic-enrichment/1.0 (+https://u9itus.dev/about)';

    public function resolveUrl(string $name, string $state = '', string $office = ''): ?string
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        try {
            $title = $this->resolveTitle($name, $state, $office);

            return $title !== null
                ? 'https://en.wikipedia.org/wiki/'.str_replace(' ', '_', $title)
                : null;
        } catch (\Throwable $e) {
            Log::debug('WikipediaLookupService: lookup failed', [
                'name' => $name,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function resolveTitle(string $name, string $state, string $office): ?string
    {
        // 1) Exact title lookup first (fast path)
        $response = Http::timeout(10)
            ->withHeaders(['User-Agent' => self::USER_AGENT])
            ->get('https://en.wikipedia.org/w/api.php', [
                'action' => 'query',
                'titles' => $name,
                'prop' => 'description',
                'format' => 'json',
                'formatversion' => '2',
            ]);

        if (! $response->ok()) {
            return null;
        }

        foreach ($response->json('query.pages') ?? [] as $page) {
            if (isset($page['missing']) || ($page['pageid'] ?? -1) < 0) {
                continue;
            }

            $title = (string) ($page['title'] ?? '');
            $description = (string) ($page['description'] ?? '');

            if ($this->isLikelyPersonPage($title, $description, $name, $office)) {
                return $title;
            }
        }

        // 2) Search and score likely biography pages when exact title misses
        $searchQuery = trim($name.' '.$state.' '.$office.' politician');
        $searchResp = Http::timeout(10)
            ->withHeaders(['User-Agent' => self::USER_AGENT])
            ->get('https://en.wikipedia.org/w/api.php', [
                'action' => 'query',
                'list' => 'search',
                'srsearch' => $searchQuery,
                'srlimit' => 5,
                'srwhat' => 'title',
                'format' => 'json',
                'formatversion' => '2',
            ]);

        if (! $searchResp->ok()) {
            return null;
        }

        $titles = collect($searchResp->json('query.search') ?? [])
            ->pluck('title')
            ->filter(fn ($t) => is_string($t) && trim($t) !== '')
            ->values()
            ->all();

        if (empty($titles)) {
            return null;
        }

        $candidatesResp = Http::timeout(10)
            ->withHeaders(['User-Agent' => self::USER_AGENT])
            ->get('https://en.wikipedia.org/w/api.php', [
                'action' => 'query',
                'titles' => implode('|', $titles),
                'prop' => 'description',
                'format' => 'json',
                'formatversion' => '2',
            ]);

        if (! $candidatesResp->ok()) {
            return null;
        }

        $bestScore = -1;
        $bestTitle = null;
        $targetName = strtolower(trim($name));

        foreach ($candidatesResp->json('query.pages') ?? [] as $page) {
            if (isset($page['missing']) || ($page['pageid'] ?? -1) < 0) {
                continue;
            }

            $title = (string) ($page['title'] ?? '');
            $description = (string) ($page['description'] ?? '');

            if (! $this->isLikelyPersonPage($title, $description, $name, $office)) {
                continue;
            }

            $score = 0;
            $lowerTitle = strtolower($title);
            $lowerDesc = strtolower($description);

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
                $bestTitle = $title;
            }
        }

        return $bestTitle;
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

        // Reject institutional pages (these describe an office, not the person).
        foreach (['governor of ', 'secretary of state of ', 'attorney general of ', 'state treasurer of ', 'state controller of ', 'office of ', 'mayor of ', 'list of '] as $bad) {
            if (str_starts_with($t, $bad) || str_contains($t, $bad)) {
                return false;
            }
        }

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
}
