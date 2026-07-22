<?php

namespace App\Services;

use App\Models\CandidateNewsArticle;
use App\Models\Politician;
use App\Models\PoliticianEndorsement;
use App\Models\PoliticianTopic;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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

    /** Max rows requested from paid APIs before local filtering. */
    protected int $maxArticles = 12;

    /** Balanced verification threshold for name+context relevance. */
    protected float $verificationThreshold = 0.65;

    public function __construct(
        protected ?string $newsApiKey = null,
        protected ?string $gNewsApiKey = null,
        protected ?EndorsementClassifier $endorsementClassifier = null,
    ) {
        $this->newsApiKey  = config('services.newsapi.api_key');
        $this->gNewsApiKey = config('services.gnews.api_key');
        $this->endorsementClassifier ??= new EndorsementClassifier();
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
            websiteUrl: $politician->website_url,
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
    public function getForCandidateName(string $candidateName, int $limit = 5, ?string $state = null): Collection
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

        $this->fetchAndPersist(null, $candidateName, $state);

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
     * @param string|null $websiteUrl Candidate's official website — when present, also pulls
     *                                content from that domain and classifies it as press
     *                                releases / events rather than third-party news.
     */
    public function fetchAndPersist(?int $politicianId, string $candidateName, ?string $state = null, ?string $websiteUrl = null): void
    {
        $requests = $this->buildRequestPlan($candidateName, $state, $websiteUrl);

        if ($requests === []) {
            return;
        }

        // Fire every source (national/state RSS + optional paid APIs) concurrently
        // instead of one blocking HTTP call after another — this endpoint is called
        // synchronously from the map overview request path on a cache miss, so a
        // sequential fan-out directly added to that request's latency.
        $responses = Http::pool(fn (Pool $pool) => collect($requests)
            ->map(function (array $req, int $index) use ($pool) {
                $client = $pool->as((string) $index)->timeout(10);

                return isset($req['query']) ? $client->get($req['url'], $req['query']) : $client->get($req['url']);
            })
            ->all());

        $seen = [];
        foreach ($requests as $index => $req) {
            $articles = $this->parsePooledResponse($req, $responses[(string) $index] ?? null, $candidateName);
            $this->persistArticles($articles, $politicianId, $candidateName, $seen);
        }
    }

    /**
     * Build the list of pending HTTP requests (national/state RSS + optional
     * paid APIs + the candidate's own official site) for a candidate, without
     * issuing any of them yet.
     *
     * @return array<int, array{type:string, provider_id:string, url:string, query?:array<string,mixed>}>
     */
    protected function buildRequestPlan(string $candidateName, ?string $state, ?string $websiteUrl = null): array
    {
        $requests = [];

        foreach (config('news_sources.national', []) as $source) {
            $requests[] = [
                'type' => 'rss',
                'provider_id' => $source['id'],
                'url' => $this->buildRssUrl($candidateName, $source['id'], $source['rss_url']),
            ];
        }

        if ($state) {
            $stateCode = strtoupper(trim($state));
            foreach (config("news_sources.state.{$stateCode}", []) as $source) {
                $requests[] = [
                    'type' => 'rss',
                    'provider_id' => $source['id'],
                    'url' => $this->buildRssUrl($candidateName, $source['id'], $source['rss_url']),
                ];
            }
        }

        // Candidate's own official site — same site: scoping technique already used
        // for the 'ap'/'politico' national sources, just pointed at their own domain
        // instead of a third-party outlet. Classified as press_release/event below
        // rather than news, since it's the candidate's own communication.
        $officialHost = $this->extractHost($websiteUrl);
        if ($officialHost !== null) {
            $requests[] = [
                'type' => 'rss',
                'provider_id' => 'official_site',
                'url' => $this->buildRssUrl(
                    $candidateName,
                    'official_site',
                    "https://news.google.com/rss/search?q={QUERY}+site:{$officialHost}&hl=en-US&gl=US&ceid=US:en",
                ),
            ];
        }

        if ($this->newsApiKey) {
            $requests[] = [
                'type' => 'newsapi',
                'provider_id' => 'newsapi',
                'url' => 'https://newsapi.org/v2/everything',
                'query' => [
                    'q' => '"' . $candidateName . '"',
                    'language' => 'en',
                    'sortBy' => 'publishedAt',
                    'pageSize' => $this->maxArticles,
                    'apiKey' => $this->newsApiKey,
                ],
            ];
        }

        if ($this->gNewsApiKey) {
            $requests[] = [
                'type' => 'gnews',
                'provider_id' => 'gnews',
                'url' => 'https://gnews.io/api/v4/search',
                'query' => [
                    'q' => '"' . $candidateName . '"',
                    'lang' => 'en',
                    'country' => 'us',
                    'max' => $this->maxArticles,
                    'apikey' => $this->gNewsApiKey,
                ],
            ];
        }

        return $requests;
    }

    /**
     * Parse a single pooled response, tolerating per-source failures the same
     * way the old sequential fetchers did (log + skip, never throw).
     *
     * @param array{type:string, provider_id:string, url:string, query?:array<string,mixed>} $req
     * @return array<int, array<string, mixed>>
     */
    protected function parsePooledResponse(array $req, mixed $response, string $candidateName): array
    {
        try {
            if ($response instanceof \Throwable) {
                throw $response;
            }

            if (! $response instanceof Response || ! $response->successful()) {
                return [];
            }

            return match ($req['type']) {
                'rss' => $this->parseRssResponse($response, $req['provider_id']),
                'newsapi' => $this->parseNewsApiResponse($response),
                'gnews' => $this->parseGNewsResponse($response),
                default => [],
            };
        } catch (\Throwable $e) {
            Log::warning('CandidateNewsService: pooled request failed', [
                'provider' => $req['provider_id'],
                'candidate' => $candidateName,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Re-run endorsement detection over already-stored verified articles.
     * Needed for the initial backfill, and whenever config/endorsements.php
     * patterns change — live ingestion (see persistArticles) only classifies
     * an article once, at fetch time.
     */
    public function detectEndorsementsForStoredArticles(int $limit = 500, ?int $politicianId = null): array
    {
        $query = CandidateNewsArticle::query()
            ->where('verification_status', 'verified')
            ->whereNotNull('politician_id')
            ->when($politicianId, fn ($q, $id) => $q->where('politician_id', $id))
            ->orderByDesc('published_at')
            ->limit($limit);

        $articles = $query->get();

        foreach ($articles as $article) {
            $this->detectEndorsements(
                politicianId: $article->politician_id,
                headline: (string) $article->headline,
                snippet: (string) ($article->snippet ?? ''),
                articleId: $article->id,
                sourceUrl: (string) ($article->source_url ?? ''),
            );
        }

        return [
            'processed' => $articles->count(),
        ];
    }

    /**
     * Re-run verification/topic extraction on existing stored articles.
     * Useful for backfills and quality-cleaning workflows.
     */
    public function reverifyStoredArticles(int $limit = 500, ?int $politicianId = null, ?string $state = null): array
    {
        $query = CandidateNewsArticle::query()
            ->when($politicianId, fn ($q, $id) => $q->where('politician_id', $id))
            ->when($state, fn ($q, $s) => $q->whereHas(
                'politician',
                fn ($pq) => $pq->whereRaw('UPPER(COALESCE(state, \'\')) = ?', [$s])
            ))
            ->orderByDesc('published_at')
            ->limit($limit);

        $articles = $query->get();
        $verified = 0;
        $rejected = 0;

        foreach ($articles as $article) {
            $politician = $article->politician_id
                ? Politician::query()->find($article->politician_id)
                : null;

            $verification = $this->verifyCandidateRelevance(
                candidateName: (string) $article->candidate_name,
                headline: (string) $article->headline,
                snippet: (string) ($article->snippet ?? ''),
                sourceName: (string) ($article->source_name ?? ''),
                state: (string) ($politician?->state ?? ''),
                office: (string) ($politician?->political_office ?? ''),
            );

            $topic = $verification['status'] === 'verified'
                ? $this->extractTopicKey((string) $article->headline, (string) ($article->snippet ?? ''))
                : ['topic_key' => null, 'topic_confidence' => null];

            $article->update([
                'verification_status' => $verification['status'],
                'verification_reason' => $verification['reason'],
                'verification_confidence' => $verification['confidence'],
                'name_match_score' => $verification['name_match_score'],
                'context_match_score' => $verification['context_match_score'],
                'verified_at' => $verification['status'] === 'verified' ? now() : null,
                'verification_meta' => [
                    'full_name_match' => $verification['full_name_match'],
                    'surname_match' => $verification['surname_match'],
                    'context_hits' => $verification['context_hits'],
                    'reverified' => true,
                ],
                'topic_key' => $topic['topic_key'],
                'topic_confidence' => $topic['topic_confidence'],
            ]);

            if ($verification['status'] === 'verified') {
                $verified++;
            } else {
                $rejected++;
            }
        }

        return [
            'processed' => $articles->count(),
            'verified' => $verified,
            'rejected' => $rejected,
        ];
    }

    /**
     * Upsert a batch of articles, skipping already-seen URL hashes.
     *
     * @param array<int, array<string, mixed>> $articles
     * @param array<string, bool>              $seen     Pass by reference — shared across provider calls
     */
    protected function persistArticles(array $articles, ?int $politicianId, string $candidateName, array &$seen): void
    {
        $politician = $politicianId ? Politician::query()->find($politicianId) : null;

        // Verify the politician actually exists before using it as a FK value.
        // If it has been deleted or was never imported, save articles as unlinked
        // (politician_id = null) rather than throwing a constraint violation.
        if ($politicianId !== null && ! Politician::where('id', $politicianId)->exists()) {
            Log::warning('CandidateNewsService: politician_id not found in politicians table; saving articles as unlinked', [
                'politician_id'  => $politicianId,
                'candidate_name' => $candidateName,
            ]);
            $politicianId = null;
        }

        foreach (array_slice($articles, 0, $this->maxPerProvider) as $article) {
            $hash = $article['source_hash'] ?? hash('sha256', $article['source_url'] ?? '');

            if (isset($seen[$hash])) {
                continue;
            }
            $seen[$hash] = true;

            $providerId = (string) ($article['provider'] ?? '');

            // The name/surname relevance gate exists to filter broad third-party
            // search results down to ones that actually mention the candidate. It
            // doesn't apply to the candidate's own official site — the site: scope
            // already guarantees relevance, and official press releases routinely
            // don't repeat the candidate's full name (e.g. "Contact Form", "Summer
            // Dance with constituents"). Gating those the same way as third-party
            // news would silently drop legitimate press releases/events.
            $verification = $providerId === 'official_site'
                ? [
                    'status' => 'verified',
                    'reason' => 'official site (site-scoped, name match not required)',
                    'confidence' => 1.0,
                    'name_match_score' => 1.0,
                    'context_match_score' => 1.0,
                    'full_name_match' => false,
                    'surname_match' => false,
                    'context_hits' => [],
                ]
                : $this->verifyCandidateRelevance(
                    candidateName: $candidateName,
                    headline: (string) ($article['headline'] ?? ''),
                    snippet: (string) ($article['snippet'] ?? ''),
                    sourceName: (string) ($article['source_name'] ?? ''),
                    state: (string) ($politician?->state ?? ''),
                    office: (string) ($politician?->political_office ?? ''),
                );

            $topic = $verification['status'] === 'verified'
                ? $this->extractTopicKey(
                    headline: (string) ($article['headline'] ?? ''),
                    snippet: (string) ($article['snippet'] ?? '')
                )
                : ['topic_key' => null, 'topic_confidence' => null];

            $contentType = $this->classifyContentType(
                providerId: $providerId,
                headline: (string) ($article['headline'] ?? ''),
                snippet: (string) ($article['snippet'] ?? ''),
            );

            $saved = CandidateNewsArticle::updateOrCreate(
                ['source_hash' => $hash],
                array_merge($article, [
                    'politician_id'  => $politicianId,
                    'candidate_name' => $candidateName,
                    'content_type'   => $contentType,
                    'scraped_at'     => now(),
                    'verification_status' => $verification['status'],
                    'verification_reason' => $verification['reason'],
                    'verification_confidence' => $verification['confidence'],
                    'name_match_score' => $verification['name_match_score'],
                    'context_match_score' => $verification['context_match_score'],
                    'verified_at' => $verification['status'] === 'verified' ? now() : null,
                    'verification_meta' => [
                        'full_name_match' => $verification['full_name_match'],
                        'surname_match' => $verification['surname_match'],
                        'context_hits' => $verification['context_hits'],
                    ],
                    'topic_key' => $topic['topic_key'],
                    'topic_confidence' => $topic['topic_confidence'],
                ]),
            );

            // Endorsements need a real politician row to attach to — unlike news
            // articles, which tolerate politician_id = null for unregistered candidates.
            if ($verification['status'] === 'verified' && $politicianId !== null) {
                $this->detectEndorsements(
                    politicianId: $politicianId,
                    headline: (string) ($article['headline'] ?? ''),
                    snippet: (string) ($article['snippet'] ?? ''),
                    articleId: $saved->id,
                    sourceUrl: (string) ($article['source_url'] ?? ''),
                );
            }
        }
    }

    /**
     * Detect endorsement claims in one article's text and upsert them into
     * politician_endorsements, deduped per (politician, group). Repeat
     * coverage of the same endorsement strengthens match_count/confidence
     * and grows the detected_article_ids evidence trail instead of creating
     * duplicate rows.
     */
    protected function detectEndorsements(int $politicianId, string $headline, string $snippet, int $articleId, string $sourceUrl): void
    {
        $matches = $this->endorsementClassifier->classify($headline, $snippet);
        if (empty($matches)) {
            return;
        }

        foreach ($matches as $match) {
            $existing = PoliticianEndorsement::query()
                ->where('politician_id', $politicianId)
                ->where('group_key', $match['group'])
                ->first();

            $articleIds = array_unique(array_merge($existing->detected_article_ids ?? [], [$articleId]));

            PoliticianEndorsement::updateOrCreate(
                ['politician_id' => $politicianId, 'group_key' => $match['group']],
                [
                    'label' => $match['label'],
                    'endorser_name' => $match['endorser_name'] ?? $existing?->endorser_name,
                    'matched_phrase' => $existing && $existing->confidence >= $match['confidence']
                        ? $existing->matched_phrase
                        : $match['matched_phrase'],
                    'confidence' => max($match['confidence'], (float) ($existing->confidence ?? 0)),
                    'source_article_id' => $articleId,
                    'source_url' => $sourceUrl !== '' ? $sourceUrl : $existing?->source_url,
                    'detected_article_ids' => array_values($articleIds),
                    'match_count' => count($articleIds),
                ],
            );
        }
    }

    /**
     * First-pass relevance gate (balanced policy):
     * - Accept exact full-name matches.
     * - Else accept surname match with office/state/news context terms.
     * - Else reject (kept in DB as rejected for audit).
     *
     * @return array{status:string,reason:string,confidence:float,name_match_score:float,context_match_score:float,full_name_match:bool,surname_match:bool,context_hits:array<int,string>}
     */
    protected function verifyCandidateRelevance(
        string $candidateName,
        string $headline,
        string $snippet,
        string $sourceName,
        string $state,
        string $office,
    ): array {
        $haystack = Str::lower(trim($headline . ' ' . $snippet));
        $fullName = Str::lower(trim(preg_replace('/\s+/', ' ', $candidateName)));

        $parts = preg_split('/\s+/', trim($candidateName)) ?: [];
        $surname = Str::lower((string) end($parts));
        if (in_array($surname, ['jr', 'sr', 'ii', 'iii', 'iv'], true) && count($parts) > 1) {
            $surname = Str::lower((string) $parts[count($parts) - 2]);
        }

        $fullNameMatch = $fullName !== '' && str_contains($haystack, $fullName);
        $surnameMatch = $surname !== '' && strlen($surname) >= 3 && preg_match('/\b' . preg_quote($surname, '/') . '\b/u', $haystack) === 1;

        $contextTerms = array_filter(array_unique(array_map('strtolower', [
            trim($state),
            trim($office),
            'election', 'campaign', 'candidate', 'primary', 'general election',
            'governor', 'senator', 'representative', 'congress', 'mayor', 'attorney general',
            strtolower(trim($sourceName)),
        ])));

        $contextHits = [];
        foreach ($contextTerms as $term) {
            if ($term !== '' && strlen($term) >= 3 && str_contains($haystack, $term)) {
                $contextHits[] = $term;
            }
        }

        $nameScore = $fullNameMatch ? 1.0 : ($surnameMatch ? 0.65 : 0.0);
        $contextScore = min(1.0, count($contextHits) / 3.0);
        $confidence = max($nameScore, ($surnameMatch ? 0.55 : 0.0) + (0.35 * $contextScore));

        $isVerified = $fullNameMatch || ($surnameMatch && $contextScore >= 0.35 && $confidence >= $this->verificationThreshold);

        return [
            'status' => $isVerified ? 'verified' : 'rejected',
            'reason' => $isVerified
                ? ($fullNameMatch ? 'full-name match' : 'surname + context match')
                : 'candidate name/context mismatch',
            'confidence' => round($confidence, 3),
            'name_match_score' => round($nameScore, 3),
            'context_match_score' => round($contextScore, 3),
            'full_name_match' => $fullNameMatch,
            'surname_match' => (bool) $surnameMatch,
            'context_hits' => array_values($contextHits),
        ];
    }

    /**
     * Lightweight issue/topic extraction for verified articles.
     * Uses active PoliticianTopic slugs and names as keyword anchors.
     *
     * @return array{topic_key:?string,topic_confidence:?float}
     */
    protected function extractTopicKey(string $headline, string $snippet): array
    {
        $text = Str::lower(trim($headline . ' ' . $snippet));
        if ($text === '') {
            return ['topic_key' => null, 'topic_confidence' => null];
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

        $best = null;
        $bestScore = 0;

        foreach ($topics as $topic) {
            $slug = (string) ($topic['slug'] ?? '');
            $name = (string) ($topic['name'] ?? '');
            if ($slug === '' && $name === '') {
                continue;
            }

            $score = 0;
            if ($slug !== '' && str_contains($text, str_replace('-', ' ', $slug))) {
                $score += 0.65;
            }
            if ($name !== '' && str_contains($text, $name)) {
                $score += 0.55;
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $slug ?: null;
            }
        }

        if ($best === null || $bestScore < 0.55) {
            return ['topic_key' => null, 'topic_confidence' => null];
        }

        return [
            'topic_key' => $best,
            'topic_confidence' => round(min(1.0, $bestScore), 3),
        ];
    }

    // -------------------------------------------------------------------------
    // Generic RSS fetcher (Google News, C-SPAN, local outlets, etc.)
    // -------------------------------------------------------------------------

    /**
     * Build the request URL for any RSS feed that accepts a {QUERY} placeholder.
     *
     * Works for Google News RSS, C-SPAN RSS, and any site: scoped Google News
     * feed defined in config/news_sources.php.
     */
    protected function buildRssUrl(string $candidateName, string $providerId, string $rssUrl): string
    {
        $query = '"' . $candidateName . '"';
        // C-SPAN and some feeds work better without the word "politician" appended
        if ($providerId === 'google_rss') {
            $query .= ' politician';
        }

        return str_replace('{QUERY}', rawurlencode($query), $rssUrl);
    }

    /**
     * Extract a bare host (no scheme, no "www.") from a candidate's website URL,
     * suitable for a Google News `site:` search. Returns null when there's no
     * usable domain to scope to.
     */
    protected function extractHost(?string $websiteUrl): ?string
    {
        if ($websiteUrl === null || trim($websiteUrl) === '') {
            return null;
        }

        $host = parse_url(trim($websiteUrl), PHP_URL_HOST)
            ?? parse_url('https://' . trim($websiteUrl), PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return null;
        }

        return preg_replace('/^www\./i', '', strtolower($host));
    }

    /**
     * Classify a persisted item's content type. Only the candidate's own official
     * site is ever press_release/event — third-party coverage always stays 'news',
     * so the distinction reflects who is speaking, not what the headline says.
     */
    protected function classifyContentType(string $providerId, string $headline, string $snippet): string
    {
        if ($providerId !== 'official_site') {
            return 'news';
        }

        $haystack = Str::lower($headline . ' ' . $snippet);

        $eventKeywords = [
            'town hall', 'townhall', 'office hours', 'community meeting',
            'listening session', 'meet and greet', 'constituent coffee',
            'public forum', 'town-hall',
        ];

        foreach ($eventKeywords as $keyword) {
            if (str_contains($haystack, $keyword)) {
                return 'event';
            }
        }

        return 'press_release';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function parseRssResponse(Response $response, string $providerId): array
    {
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
    }

    // -------------------------------------------------------------------------
    // Source: NewsAPI.org
    // -------------------------------------------------------------------------

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function parseNewsApiResponse(Response $response): array
    {
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
    }

    // -------------------------------------------------------------------------
    // Source: GNews API
    // -------------------------------------------------------------------------

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function parseGNewsResponse(Response $response): array
    {
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
    }
}
