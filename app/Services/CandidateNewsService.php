<?php

namespace App\Services;

use App\Models\CandidateNewsArticle;
use App\Models\Politician;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Candidate News Service
 *
 * Fetches recent news headlines for a candidate from configurable sources
 * defined in config/news_sources.php:
 *   - National sources (Google News RSS, C-SPAN, AP, Politico, The Hill)
 *   - State-specific local outlets keyed by two-letter state abbreviation
 *   - Optional paid APIs: NewsAPI.org and GNews (set keys in .env)
 *
 * Results are persisted to `candidate_news_articles` keyed by provider so
 * the front-end can filter by source.
 */
class CandidateNewsService
{
    /** How long (seconds) before a provider's articles are considered stale (6 h). */
    protected int $cacheTtl = 21_600;

    /** Maximum articles stored per provider per candidate. */
    protected int $maxPerProvider = 6;

    public function __construct(
        protected ?string $newsApiKey = null,
        protected ?string $gNewsApiKey = null,
    ) {
        $this->newsApiKey  = config('services.newsapi.api_key');
        $this->gNewsApiKey = config('services.gnews.api_key');
    }

    /**
     * Return cached news articles for a politician grouped by provider.
     * Triggers a refresh if no articles exist yet or all are stale.
     *
     * @return Collection<int, CandidateNewsArticle>
     */
    public function getForPolitician(Politician $politician, int $limit = 60): Collection
    {
        $fresh = CandidateNewsArticle::query()
            ->where('politician_id', $politician->id)
            ->where('scraped_at', '>=', now()->subSeconds($this->cacheTtl))
            ->orderByDesc('published_at')
            ->limit($limit)
            ->get();

        if ($fresh->isNotEmpty()) {
            return $fresh;
        }

        $this->fetchAndPersist(
            politicianId: $politician->id,
            candidateName: (string) $politician->full_name,
            state: $politician->state,
        );

        return CandidateNewsArticle::query()
            ->where('politician_id', $politician->id)
            ->orderByDesc('published_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Return news articles for an unregistered candidate by name.
     *
     * @return Collection<int, CandidateNewsArticle>
     */
    public function getForCandidateName(string $candidateName, int $limit = 5): Collection
    {
        $fresh = CandidateNewsArticle::query()
            ->where('candidate_name', $candidateName)
            ->whereNull('politician_id')
            ->where('scraped_at', '>=', now()->subSeconds($this->cacheTtl))
            ->orderByDesc('published_at')
            ->limit($limit)
            ->get();

        if ($fresh->isNotEmpty()) {
            return $fresh;
        }

        $this->fetchAndPersist(null, $candidateName);

        return CandidateNewsArticle::query()
            ->where('candidate_name', $candidateName)
            ->whereNull('politician_id')
            ->orderByDesc('published_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Fetch articles from all configured sources and upsert into DB.
     *
     * @param string|null $state Two-letter state abbreviation to include local sources
     */
    public function fetchAndPersist(?int $politicianId, string $candidateName, ?string $state = null): void
    {
        $seen = [];

        // ── National RSS sources from config ─────────────────────────────────
        $nationalSources = config('news_sources.national', []);
        foreach ($nationalSources as $source) {
            $articles = $this->fetchFromRss(
                candidateName: $candidateName,
                providerId: $source['id'],
                rssUrl: $source['rss_url'],
            );
            $this->persistArticles($articles, $politicianId, $candidateName, $seen);
        }

        // ── State-specific local sources ──────────────────────────────────────
        if ($state) {
            $stateCode = strtoupper(trim($state));
            $localSources = config("news_sources.state.{$stateCode}", []);
            foreach ($localSources as $source) {
                $articles = $this->fetchFromRss(
                    candidateName: $candidateName,
                    providerId: $source['id'],
                    rssUrl: $source['rss_url'],
                );
                $this->persistArticles($articles, $politicianId, $candidateName, $seen);
            }
        }

        // ── Optional paid APIs ────────────────────────────────────────────────
        if ($this->newsApiKey) {
            $articles = $this->fetchFromNewsApi($candidateName);
            $this->persistArticles($articles, $politicianId, $candidateName, $seen);
        }

        if ($this->gNewsApiKey) {
            $articles = $this->fetchFromGNews($candidateName);
            $this->persistArticles($articles, $politicianId, $candidateName, $seen);
        }
    }

    /**
     * Upsert a batch of articles, skipping already-seen URL hashes.
     *
     * @param array<int, array<string, mixed>> $articles
     * @param array<string, bool>              $seen     Pass by reference — shared across provider calls
     */
    protected function persistArticles(array $articles, ?int $politicianId, string $candidateName, array &$seen): void
    {
        foreach (array_slice($articles, 0, $this->maxPerProvider) as $article) {
            $hash = $article['source_hash'] ?? hash('sha256', $article['source_url'] ?? '');

            if (isset($seen[$hash])) {
                continue;
            }
            $seen[$hash] = true;

            CandidateNewsArticle::updateOrCreate(
                ['source_hash' => $hash],
                array_merge($article, [
                    'politician_id'  => $politicianId,
                    'candidate_name' => $candidateName,
                    'scraped_at'     => now(),
                ]),
            );
        }
    }

    // -------------------------------------------------------------------------
    // Generic RSS fetcher (Google News, C-SPAN, local outlets, etc.)
    // -------------------------------------------------------------------------

    /**
     * Fetch and parse any RSS feed that accepts a {QUERY} placeholder in its URL.
     *
     * Works for Google News RSS, C-SPAN RSS, and any site: scoped Google News
     * feed defined in config/news_sources.php.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function fetchFromRss(string $candidateName, string $providerId, string $rssUrl): array
    {
        $query = '"' . $candidateName . '"';
        // C-SPAN and some feeds work better without the word "politician" appended
        if ($providerId === 'google_rss') {
            $query .= ' politician';
        }

        $url = str_replace('{QUERY}', rawurlencode($query), $rssUrl);

        try {
            $response = Http::timeout(10)->get($url);

            if (! $response->successful()) {
                return [];
            }

            $xml = @simplexml_load_string($response->body());

            if ($xml === false || ! isset($xml->channel->item)) {
                return [];
            }

            $articles = [];

            foreach ($xml->channel->item as $item) {
                $sourceUrl = (string) ($item->link ?? '');
                if ($sourceUrl === '') {
                    continue;
                }

                $pubDate = null;
                try {
                    $pubDate = \Carbon\Carbon::parse((string) ($item->pubDate ?? ''));
                } catch (\Throwable) {
                    // leave null
                }

                // Google News RSS wraps publisher name in <source>; C-SPAN uses <author>
                $sourceName = (string) ($item->source
                    ?? $item->author
                    ?? $item->children('dc', true)->creator
                    ?? '');
                if ($sourceName === '') {
                    // Derive a display name from the config label if we can
                    $allSources = array_merge(
                        config('news_sources.national', []),
                        ...array_values(config('news_sources.state', [])),
                    );
                    foreach ($allSources as $src) {
                        if (($src['id'] ?? '') === $providerId) {
                            $sourceName = $src['label'];
                            break;
                        }
                    }
                }

                $articles[] = [
                    'headline'     => strip_tags((string) ($item->title ?? '')),
                    'source_name'  => $sourceName ?: null,
                    'source_url'   => $sourceUrl,
                    'snippet'      => strip_tags((string) ($item->description ?? '')),
                    'image_url'    => null,
                    'published_at' => $pubDate,
                    'provider'     => $providerId,
                    'source_hash'  => hash('sha256', $sourceUrl),
                ];
            }

            return array_slice($articles, 0, $this->maxPerProvider);
        } catch (\Throwable $e) {
            Log::warning('CandidateNewsService: RSS fetch failed', [
                'provider'  => $providerId,
                'candidate' => $candidateName,
                'error'     => $e->getMessage(),
            ]);

            return [];
        }
    }

    // -------------------------------------------------------------------------
    // Source: NewsAPI.org
    // -------------------------------------------------------------------------

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function fetchFromNewsApi(string $candidateName): array
    {
        try {
            $response = Http::timeout(10)
                ->get('https://newsapi.org/v2/everything', [
                    'q'        => '"' . $candidateName . '"',
                    'language' => 'en',
                    'sortBy'   => 'publishedAt',
                    'pageSize' => $this->maxArticles,
                    'apiKey'   => $this->newsApiKey,
                ]);

            if (! $response->successful()) {
                return [];
            }

            $data = $response->json('articles', []);

            return array_map(function (array $a): array {
                $url = $a['url'] ?? '';

                return [
                    'headline'    => $a['title'] ?? '',
                    'source_name' => $a['source']['name'] ?? null,
                    'source_url'  => $url,
                    'snippet'     => $a['description'] ?? null,
                    'image_url'   => $a['urlToImage'] ?? null,
                    'published_at' => isset($a['publishedAt']) ? \Carbon\Carbon::parse($a['publishedAt']) : null,
                    'provider'    => 'newsapi',
                    'source_hash' => hash('sha256', $url),
                ];
            }, $data);
        } catch (\Throwable $e) {
            Log::warning('CandidateNewsService: NewsAPI fetch failed', [
                'candidate' => $candidateName,
                'error'     => $e->getMessage(),
            ]);

            return [];
        }
    }

    // -------------------------------------------------------------------------
    // Source: GNews API
    // -------------------------------------------------------------------------

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function fetchFromGNews(string $candidateName): array
    {
        try {
            $response = Http::timeout(10)
                ->get('https://gnews.io/api/v4/search', [
                    'q'        => '"' . $candidateName . '"',
                    'lang'     => 'en',
                    'country'  => 'us',
                    'max'      => $this->maxArticles,
                    'apikey'   => $this->gNewsApiKey,
                ]);

            if (! $response->successful()) {
                return [];
            }

            $articles = $response->json('articles', []);

            return array_map(function (array $a): array {
                $url = $a['url'] ?? '';

                return [
                    'headline'    => $a['title'] ?? '',
                    'source_name' => $a['source']['name'] ?? null,
                    'source_url'  => $url,
                    'snippet'     => $a['description'] ?? null,
                    'image_url'   => $a['image'] ?? null,
                    'published_at' => isset($a['publishedAt']) ? \Carbon\Carbon::parse($a['publishedAt']) : null,
                    'provider'    => 'gnews',
                    'source_hash' => hash('sha256', $url),
                ];
            }, $articles);
        } catch (\Throwable $e) {
            Log::warning('CandidateNewsService: GNews fetch failed', [
                'candidate' => $candidateName,
                'error'     => $e->getMessage(),
            ]);

            return [];
        }
    }
}
