<?php

namespace App\Services;

use App\Models\DistrictNewsArticle;
use App\Models\PoliticianTopic;
use App\Services\Concerns\HasRssParsing;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * District News Service
 *
 * Sibling to CandidateNewsService: fetches recent news about the mechanics
 * of voting (polling places, ballot measures, redistricting, election
 * administration) scoped to a congressional district's locality rather than
 * a candidate's name. Sources come from the same config/news_sources.php
 * national/state lists; see its 'civic_keywords' entry for the relevance
 * gate used here.
 *
 * Results are persisted to `district_news_articles`, keyed by district_code.
 */
class DistrictNewsService
{
    use HasRssParsing;

    /** How long (seconds) before a district's articles are considered stale (6 h). */
    protected int $cacheTtl = 21_600;

    /** Maximum articles stored per provider per locality query. */
    protected int $maxPerProvider = 6;

    /**
     * Compact keyword OR-group used to build the RSS search query itself
     * (kept short for URL-length reasons). The full
     * config('news_sources.civic_keywords') list is used afterward, against
     * already-fetched text, to verify relevance — same "cast a wide net via
     * RSS, narrow via a local gate" split CandidateNewsService already uses.
     */
    protected array $searchKeywords = [
        'polling place', 'ballot measure', 'redistricting', 'voter registration',
        'election security', 'voting machine', 'poll worker', 'early voting',
    ];

    /**
     * Return cached news articles for a district. Triggers a refresh if no
     * articles exist yet or all are stale.
     *
     * @param array<int, string> $localities
     * @return Collection<int, DistrictNewsArticle>
     */
    public function getForDistrict(string $districtCode, string $state, array $localities, int $limit = 10): Collection
    {
        $fresh = DistrictNewsArticle::query()
            ->where('district_code', $districtCode)
            ->where('scraped_at', '>=', now()->subSeconds($this->cacheTtl))
            ->orderByDesc('published_at')
            ->limit($limit)
            ->get();

        if ($fresh->isNotEmpty()) {
            return $fresh;
        }

        $this->fetchAndPersist($districtCode, $state, $localities);

        return DistrictNewsArticle::query()
            ->where('district_code', $districtCode)
            ->orderByDesc('published_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Fetch articles from all configured sources for each locality and
     * upsert into district_news_articles.
     *
     * @param array<int, string> $localities
     */
    public function fetchAndPersist(string $districtCode, string $state, array $localities): void
    {
        $localities = array_values(array_unique(array_filter(
            array_map(fn ($l) => trim((string) $l), $localities),
            fn (string $l) => $l !== '',
        )));

        if ($localities === []) {
            return;
        }

        $requests = $this->buildRequestPlan($state, $localities);
        if ($requests === []) {
            return;
        }

        // Same pooled fan-out as CandidateNewsService::fetchAndPersist — this
        // is called synchronously from the map's district-panel request path
        // on a cache miss, so a sequential fan-out would directly add to
        // that request's latency.
        $responses = Http::pool(fn (Pool $pool) => collect($requests)
            ->map(fn (array $req, int $index) => $pool->as((string) $index)->timeout(10)->get($req['url']))
            ->all());

        $seen = [];
        foreach ($requests as $index => $req) {
            $articles = $this->parsePooledResponse($req, $responses[(string) $index] ?? null, $districtCode);
            $this->persistArticles($articles, $districtCode, $state, $localities, $seen);
        }
    }

    /**
     * @param array<int, string> $localities
     * @return array<int, array{provider_id:string, url:string, locality:string}>
     */
    protected function buildRequestPlan(string $state, array $localities): array
    {
        $stateCode = strtoupper(trim($state));

        $sources = array_merge(
            config('news_sources.national', []),
            config("news_sources.state.{$stateCode}", []),
        );

        $requests = [];
        foreach ($localities as $locality) {
            foreach ($sources as $source) {
                $requests[] = [
                    'provider_id' => $source['id'],
                    'url' => $this->buildRssUrl($locality, $source['rss_url']),
                    'locality' => $locality,
                ];
            }
        }

        return $requests;
    }

    /**
     * Parse a single pooled response, tolerating per-source failures the same
     * way CandidateNewsService does (log + skip, never throw).
     *
     * @param array{provider_id:string, url:string, locality:string} $req
     * @return array<int, array<string, mixed>>
     */
    protected function parsePooledResponse(array $req, mixed $response, string $districtCode): array
    {
        try {
            if ($response instanceof \Throwable) {
                throw $response;
            }

            if (! $response instanceof Response || ! $response->successful()) {
                return [];
            }

            return $this->parseRssResponse($response, $req['provider_id']);
        } catch (\Throwable $e) {
            Log::warning('DistrictNewsService: pooled request failed', [
                'provider' => $req['provider_id'],
                'district_code' => $districtCode,
                'locality' => $req['locality'],
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Upsert a batch of articles, skipping already-seen URL hashes.
     *
     * @param array<int, array<string, mixed>> $articles
     * @param array<int, string> $localities
     * @param array<string, bool> $seen Pass by reference — shared across provider calls
     */
    protected function persistArticles(array $articles, string $districtCode, string $state, array $localities, array &$seen): void
    {
        foreach (array_slice($articles, 0, $this->maxPerProvider) as $article) {
            $hash = $article['source_hash'] ?? hash('sha256', $article['source_url'] ?? '');

            if (isset($seen[$hash])) {
                continue;
            }
            $seen[$hash] = true;

            $verification = $this->verifyDistrictRelevance(
                headline: (string) ($article['headline'] ?? ''),
                snippet: (string) ($article['snippet'] ?? ''),
                localities: $localities,
                state: $state,
            );

            $topicKey = $verification['status'] === 'verified'
                ? $this->extractTopicKey((string) ($article['headline'] ?? ''), (string) ($article['snippet'] ?? ''))
                : null;

            DistrictNewsArticle::updateOrCreate(
                ['source_hash' => $hash],
                [
                    'district_code' => $districtCode,
                    'state' => strtoupper(trim($state)),
                    'matched_locality' => $verification['matched_locality'],
                    'headline' => $article['headline'] ?? '',
                    'source_name' => $article['source_name'] ?? null,
                    'source_url' => $article['source_url'] ?? '',
                    'snippet' => $article['snippet'] ?? null,
                    'published_at' => $article['published_at'] ?? null,
                    'provider' => $article['provider'] ?? 'google_rss',
                    'verification_status' => $verification['status'],
                    'verification_reason' => $verification['reason'],
                    'topic_key' => $topicKey,
                    'scraped_at' => now(),
                ],
            );
        }
    }

    /**
     * Verified iff the text mentions one of this district's localities (or
     * the state itself) AND at least one civic-election keyword from the
     * full config('news_sources.civic_keywords') list — broader than the
     * compact group used to build the search query, since this runs after
     * the fact against already-fetched text.
     *
     * @param array<int, string> $localities
     * @return array{status:string, reason:string, matched_locality:?string}
     */
    protected function verifyDistrictRelevance(string $headline, string $snippet, array $localities, string $state): array
    {
        $haystack = Str::lower(trim($headline . ' ' . $snippet));

        $stateName = (string) (config('u9itus.us_states.' . strtoupper(trim($state))) ?? '');
        $placeCandidates = array_merge($localities, array_filter([$stateName]));

        $matchedLocality = null;
        foreach ($placeCandidates as $place) {
            $needle = Str::lower(trim((string) $place));
            if ($needle !== '' && str_contains($haystack, $needle)) {
                $matchedLocality = $place;
                break;
            }
        }

        if ($matchedLocality === null) {
            return ['status' => 'rejected', 'reason' => 'no locality/state match', 'matched_locality' => null];
        }

        $keywordHit = null;
        foreach (config('news_sources.civic_keywords', []) as $keyword) {
            if (str_contains($haystack, Str::lower($keyword))) {
                $keywordHit = $keyword;
                break;
            }
        }

        if ($keywordHit === null) {
            return ['status' => 'rejected', 'reason' => 'no civic-keyword match', 'matched_locality' => $matchedLocality];
        }

        return [
            'status' => 'verified',
            'reason' => "matched \"{$matchedLocality}\" + \"{$keywordHit}\"",
            'matched_locality' => $matchedLocality,
        ];
    }

    /**
     * Lightweight topic tagging for verified articles, reusing the same
     * active PoliticianTopic slugs CandidateNewsService anchors to
     * (voting-rights, democracy, etc.) — no district-specific topics needed.
     */
    protected function extractTopicKey(string $headline, string $snippet): ?string
    {
        $text = Str::lower(trim($headline . ' ' . $snippet));
        if ($text === '') {
            return null;
        }

        $topics = Cache::remember('news:topic-slug-map', 300, function () {
            return PoliticianTopic::query()
                ->where('is_active', true)
                ->get(['slug', 'name'])
                ->map(fn (PoliticianTopic $t) => [
                    'slug' => strtolower((string) $t->slug),
                    'name' => strtolower((string) $t->name),
                ])
                ->all();
        });

        foreach ($topics as $topic) {
            $slug = (string) ($topic['slug'] ?? '');
            $name = (string) ($topic['name'] ?? '');

            if ($slug !== '' && str_contains($text, str_replace('-', ' ', $slug))) {
                return $slug;
            }
            if ($name !== '' && str_contains($text, $name)) {
                return $slug !== '' ? $slug : null;
            }
        }

        return null;
    }

    /**
     * Build the RSS search URL for one locality: "{locality}" scoped to a
     * compact OR-group of civic-election-administration keywords.
     */
    protected function buildRssUrl(string $locality, string $rssUrl): string
    {
        $terms = array_map(
            fn (string $term) => str_contains($term, ' ') ? '"' . $term . '"' : $term,
            $this->searchKeywords,
        );

        $query = '"' . $locality . '" (' . implode(' OR ', $terms) . ')';

        return str_replace('{QUERY}', rawurlencode($query), $rssUrl);
    }
}
