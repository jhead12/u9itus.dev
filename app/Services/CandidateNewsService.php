<?php

namespace App\Services;

use App\Models\CandidateNewsArticle;
use App\Models\Politician;
use App\Models\PoliticianEndorsement;
use App\Services\Concerns\HasRssParsing;
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
    use HasRssParsing;

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
     *
     * Detected rows are derived data, so each politician touched is rebuilt from
     * all of their verified articles: a row an older detector produced (say a
     * "Governor" chip for "Trump endorses Hilton for governor") disappears instead
     * of lingering next to the correct one. `limit` counts the articles used to
     * pick which politicians to rebuild.
     */
    public function detectEndorsementsForStoredArticles(int $limit = 500, ?int $politicianId = null): array
    {
        $verified = fn () => CandidateNewsArticle::query()
            ->where('verification_status', 'verified')
            ->whereNotNull('politician_id')
            ->when($politicianId, fn ($q, $id) => $q->where('politician_id', $id));

        $politicianIds = $verified()
            ->orderByDesc('published_at')
            ->limit($limit)
            ->pluck('politician_id');

        // Politicians who already have detected rows are always rebuilt too, so a stale
        // row whose article was since rejected or removed cannot outlive its evidence.
        $politicianIds = $politicianIds
            ->merge(PoliticianEndorsement::query()->active()
                ->when($politicianId, fn ($q, $id) => $q->where('politician_id', $id))
                ->pluck('politician_id'))
            ->unique()
            ->values();

        if ($politicianIds->isEmpty()) {
            return ['processed' => 0];
        }

        PoliticianEndorsement::query()
            ->whereIn('politician_id', $politicianIds)
            ->active()
            ->delete();

        $processed = 0;

        $verified()
            ->whereIn('politician_id', $politicianIds)
            ->with('politician:id,full_name')
            ->orderBy('published_at')
            ->orderBy('id')
            ->chunk(200, function ($articles) use (&$processed) {
                foreach ($articles as $article) {
                    $this->detectEndorsements(
                        politicianId: $article->politician_id,
                        headline: (string) $article->headline,
                        snippet: (string) ($article->snippet ?? ''),
                        articleId: $article->id,
                        sourceUrl: (string) ($article->source_url ?? ''),
                        politicianFullName: $article->politician?->full_name,
                    );
                    $processed++;
                }
            });

        // Guests are served a cached copy of the profile page (see PublicProfileController).
        foreach ($politicianIds as $id) {
            Cache::forget("profile.page.seo-v2.{$id}");
        }

        return [
            'processed' => $processed,
        ];
    }

    /**
     * Re-run verification/topic extraction on existing stored articles.
     * Useful for backfills and quality-cleaning workflows.
     *
     * $onlyUnchecked limits the pass to rows that never went through the gate
     * (verification_reason is null — they got the column's 'verified' default).
     * $dryRun computes verdicts without writing and returns sample status flips.
     *
     * @return array{processed:int,verified:int,rejected:int,newly_rejected:int,newly_verified:int,samples:array<int,array<string,mixed>>}
     */
    public function reverifyStoredArticles(int $limit = 500, ?int $politicianId = null, ?string $state = null, bool $onlyUnchecked = false, bool $dryRun = false): array
    {
        $query = CandidateNewsArticle::query()
            ->when($politicianId, fn ($q, $id) => $q->where('politician_id', $id))
            ->when($state, fn ($q, $s) => $q->whereHas(
                'politician',
                fn ($pq) => $pq->whereRaw('UPPER(COALESCE(state, \'\')) = ?', [$s])
            ))
            ->when($onlyUnchecked, fn ($q) => $q->whereNull('verification_reason'))
            ->orderByDesc('published_at')
            ->limit($limit);

        $politicians = [];
        $counts = ['processed' => 0, 'verified' => 0, 'rejected' => 0, 'newly_rejected' => 0, 'newly_verified' => 0];
        $samples = [];

        foreach ($query->cursor() as $article) {
            $politician = null;
            if ($article->politician_id) {
                $politician = $politicians[$article->politician_id] ??= Politician::query()->find($article->politician_id);
            }

            $verification = $article->provider === 'official_site'
                ? null
                : $this->verifyCandidateRelevance(
                    candidateName: (string) $article->candidate_name,
                    headline: (string) $article->headline,
                    snippet: (string) ($article->snippet ?? ''),
                    sourceName: (string) ($article->source_name ?? ''),
                    state: (string) ($politician?->state ?? ''),
                    office: (string) ($politician?->political_office ?? ''),
                    district: (string) ($politician?->district ?? ''),
                );

            $counts['processed']++;

            // Official-site rows are site-scoped and verified by construction
            // (see persistArticles); re-running the name gate would drop them.
            if ($verification === null) {
                $counts['verified']++;
                continue;
            }

            $counts[$verification['status']]++;
            if ($article->verification_status !== $verification['status']) {
                $counts[$verification['status'] === 'rejected' ? 'newly_rejected' : 'newly_verified']++;
                if (count(array_filter($samples, fn ($row) => $row['to'] === $verification['status'])) < 25) {
                    $samples[] = [
                        'id' => $article->id,
                        'candidate' => $article->candidate_name,
                        'to' => $verification['status'],
                        'reason' => $verification['reason'],
                        'headline' => Str::limit((string) $article->headline, 110),
                    ];
                }
            }

            if ($dryRun) {
                continue;
            }

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
        }

        usort($samples, fn ($a, $b) => strcmp($b['to'], $a['to']));

        return $counts + ['samples' => $samples];
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
                    district: (string) ($politician?->district ?? ''),
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
                    politicianFullName: $politician?->full_name,
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
    protected function detectEndorsements(int $politicianId, string $headline, string $snippet, int $articleId, string $sourceUrl, ?string $politicianFullName = null): void
    {
        $matches = $this->endorsementClassifier->classify($headline, $snippet, $politicianFullName);
        if (empty($matches)) {
            return;
        }

        foreach ($matches as $match) {
            // The classifier only checks proximity of an office title to an
            // endorsement verb, not who's the subject vs. the object — a
            // sitting official's own coverage routinely reads "U.S.
            // Representative Jane Doe ... endorses/backs ...", which the
            // classifier captures as Jane Doe being the endorser via
            // captureEndorserName(). Since that's her own name, this isn't a
            // real endorsement of her — skip it rather than attaching a
            // self-referential badge to her own profile.
            if ($this->isSelfReference($match['endorser_name'] ?? null, $politicianFullName)) {
                continue;
            }

            $name = $this->resolveEndorserName($politicianId, $match['group'], $match['endorser_name'] ?? null);
            $existing = $this->matchingEndorsement($politicianId, $match['group'], $name);
            // An editor already confirmed or dismissed this endorser: their decision stands.
            // Rebuilds replace only rows awaiting review (see detectEndorsementsForStoredArticles).
            if ($existing && $existing->status !== PoliticianEndorsement::STATUS_DETECTED) {
                continue;
            }

            $articleIds = array_unique(array_merge($existing?->detected_article_ids ?? [], [$articleId]));

            // Prefer the fuller name ("Gavin Newsom" over an earlier "Newsom"); never blank a known one.
            $endorserName = $name;
            if ($existing?->endorser_name && mb_strlen($existing->endorser_name) >= mb_strlen((string) $name)) {
                $endorserName = $existing->endorser_name;
            }

            $fields = [
                'label' => $match['label'],
                'endorser_key' => $this->endorserKey($endorserName),
                'endorser_name' => $endorserName,
                'matched_phrase' => $existing && $existing->confidence >= $match['confidence']
                    ? $existing->matched_phrase
                    : $match['matched_phrase'],
                'confidence' => max($match['confidence'], (float) ($existing->confidence ?? 0)),
                'source_article_id' => $articleId,
                'source_url' => $sourceUrl !== '' ? $sourceUrl : $existing?->source_url,
                'detected_article_ids' => array_values($articleIds),
                'match_count' => count($articleIds),
            ];

            $existing
                ? $existing->update($fields)
                : PoliticianEndorsement::create($fields + ['politician_id' => $politicianId, 'group_key' => $match['group']]);
        }
    }

    /**
     * The row this detection belongs to: the same endorser (compared by name words, so
     * "Newsom" and "Gavin Newsom" are one person), else — when only the office was named —
     * the office's existing row, so "the governor" never adds a second, nameless chip
     * beside a named governor.
     */
    protected function matchingEndorsement(int $politicianId, string $group, ?string $name): ?PoliticianEndorsement
    {
        $rows = PoliticianEndorsement::query()
            ->where('politician_id', $politicianId)
            ->where('group_key', $group)
            ->orderBy('id')
            ->get();

        if ($name === null) {
            return $rows->first(fn (PoliticianEndorsement $row) => $row->endorser_name === null) ?? $rows->first();
        }

        $words = fn (?string $n) => array_values(array_filter(explode(' ', strtolower((string) preg_replace('/[^a-z\s]/i', '', (string) $n)))));
        $mine = $words($name);

        return $rows->first(function (PoliticianEndorsement $row) use ($words, $mine) {
            $theirs = $words($row->endorser_name);
            if ($theirs === []) {
                return false;
            }

            return empty(array_diff($mine, $theirs)) || empty(array_diff($theirs, $mine));
        }) ?? $rows->first(fn (PoliticianEndorsement $row) => $row->endorser_name === null);
    }

    protected function endorserKey(?string $name): string
    {
        return $name === null ? '' : Str::limit(Str::slug($name), 120, '');
    }

    /**
     * A lone surname ("Gov. Newsom") becomes the sitting official's full name when exactly
     * one seated officeholder of that office in the candidate's state has it. Anything
     * ambiguous is left as the article wrote it.
     */
    protected function resolveEndorserName(int $politicianId, string $group, ?string $name): ?string
    {
        if ($name === null || str_contains($name, ' ')) {
            return $name;
        }

        $office = match ($group) {
            'governor' => 'governor',
            'attorney_general' => 'attorney general',
            'us_senator' => 'senator',
            'us_representative' => 'representative',
            'mayor' => 'mayor',
            default => null,
        };
        $state = $office === null ? null : Politician::query()->whereKey($politicianId)->value('state');
        if ($state === null) {
            return $name;
        }

        $matches = Politician::query()
            ->whereRaw('UPPER(COALESCE(state, \'\')) = ?', [strtoupper((string) $state)])
            ->where('is_active', true)
            ->where('term_status', 'seated')
            ->whereRaw('LOWER(political_office) LIKE ?', ["%{$office}%"])
            ->when($group === 'governor', fn ($q) => $q->whereRaw('LOWER(political_office) NOT LIKE ?', ['%lieutenant%']))
            ->whereRaw('LOWER(full_name) LIKE ?', ['% '.strtolower($name)])
            ->pluck('full_name');

        return $matches->count() === 1 ? $matches->first() : $name;
    }

    /**
     * True when the captured "endorser" name is actually just the politician's
     * own name (every word in it appears in their full name), i.e. the
     * classifier matched the politician's own title+name rather than a real
     * third-party endorser.
     */
    protected function isSelfReference(?string $endorserName, ?string $politicianFullName): bool
    {
        if (!$endorserName || !$politicianFullName) {
            return false;
        }

        $tokenize = fn (string $s) => array_values(array_filter(explode(' ', preg_replace('/[^a-z\s]/', '', strtolower($s)))));

        $endorserTokens = $tokenize($endorserName);
        $politicianTokens = $tokenize($politicianFullName);

        if (empty($endorserTokens) || empty($politicianTokens)) {
            return false;
        }

        return empty(array_diff($endorserTokens, $politicianTokens));
    }

    /**
     * Words that tie a bare surname mention to politics or public office.
     * Matched as whole words, with an optional plural "s".
     */
    protected const POLITICAL_TERMS = [
        'election', 'campaign', 'candidate', 'candidacy', 'primary', 'ballot', 'reelection', 're-election',
        'governor', 'gov.', 'senator', 'sen.', 'senate', 'representative', 'rep.', 'congress', 'congressman',
        'congresswoman', 'congressional', 'u.s. house', 'state house', 'lawmaker', 'legislator', 'legislature', 'legislative',
        'mayor', 'attorney general', 'lieutenant governor', 'council', 'commissioner', 'speaker', 'caucus', 'committee',
        'district', 'bill', 'vote', 'voted', 'voter', 'voting', 'poll', 'republican', 'democrat', 'democratic', 'gop', 'town hall', 'white house', 'capitol', 'administration', 'incumbent',
        'endorse', 'endorsed', 'endorsement', 'constituent', 'impeach', 'veto', 'political',
        'politician', 'politics', 'debate', 'fundrais', 'nominee', 'gubernatorial', 'statehouse',
    ];

    /** Reference works that describe historical namesakes, not current news. */
    protected const REFERENCE_SOURCES = ['britannica', 'wikipedia', 'biography.com', 'history.com', 'encyclopedia', 'findagrave', 'ushistory'];

    /** Outlet tag/index pages that aggregators return as if they were articles. */
    protected const INDEX_PAGE_PATTERNS = ['breaking news, photos and videos', 'latest news, top stories', 'news, photos and videos'];

    /**
     * First-pass relevance gate:
     * - Reject reference/encyclopedia entries and "this day in history" pieces
     *   dated before 1900 — they describe namesakes (Daniel Webster the 1830s
     *   senator), not the official. Reject outlet tag/index pages too.
     * - Accept exact full-name matches.
     * - Else accept a surname match with a political/office/state term.
     * - Else reject (kept in DB as rejected for audit).
     *
     * The outlet's own name never counts as context: aggregator headlines end in
     * " - Source Name", so counting it verified nearly everything.
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
        string $district = '',
    ): array {
        $rawHaystack = Str::lower(trim($headline . ' ' . $snippet));
        $haystack = Str::lower(trim($this->withoutSourceSuffix($headline, $sourceName) . ' ' . $snippet));
        $fullName = Str::lower(trim(preg_replace('/\s+/', ' ', $candidateName)));

        $parts = preg_split('/\s+/', trim($candidateName)) ?: [];
        $surname = Str::lower((string) end($parts));
        if (in_array($surname, ['jr', 'sr', 'ii', 'iii', 'iv'], true) && count($parts) > 1) {
            $surname = Str::lower((string) $parts[count($parts) - 2]);
        }

        $fullNameMatch = $fullName !== '' && str_contains($haystack, $fullName);
        $surnameMatch = $surname !== '' && strlen($surname) >= 3 && preg_match('/\b' . preg_quote($surname, '/') . '\b/u', $haystack) === 1;

        $stateCode = strtoupper(trim($state));
        $districtNumber = preg_match('/(\d+)\s*$/', trim($district), $m) ? ltrim($m[1], '0') : '';

        $stateName = Str::lower((string) config("u9itus.us_states.{$stateCode}", ''));
        $contextTerms = array_unique(array_map('strtolower', array_filter([
            ...self::POLITICAL_TERMS,
            $stateName,
            trim($office),
            $districtNumber !== '' ? "district {$districtNumber}" : '',
        ])));

        $contextHits = [];
        foreach ($contextTerms as $term) {
            if (strlen($term) >= 3 && preg_match('/(?<![a-z])' . preg_quote($term, '/') . 's?(?![a-z])/u', $haystack) === 1) {
                $contextHits[] = $term;
            }
        }

        $nameScore = $fullNameMatch ? 1.0 : ($surnameMatch ? 0.65 : 0.0);
        $contextScore = min(1.0, count($contextHits) / 3.0);
        $confidence = max($nameScore, ($surnameMatch ? 0.55 : 0.0) + (0.35 * $contextScore));

        $nonCoverage = $this->nonCoverageReason($rawHaystack, Str::lower($sourceName));

        $reason = match (true) {
            $nonCoverage !== null => $nonCoverage,
            $fullNameMatch => 'full-name match',
            // A state name alone doesn't tie a common surname to the official
            // ("Brown" + "Washington"); it needs a political/office term.
            $surnameMatch && array_diff($contextHits, [$stateName]) !== [] && $confidence >= $this->verificationThreshold => 'surname + context match',
            default => 'candidate name/context mismatch',
        };
        $isVerified = in_array($reason, ['full-name match', 'surname + context match'], true);

        return [
            'status' => $isVerified ? 'verified' : 'rejected',
            'reason' => $reason,
            'confidence' => round($isVerified ? $confidence : min($confidence, 0.5), 3),
            'name_match_score' => round($nameScore, 3),
            'context_match_score' => round($contextScore, 3),
            'full_name_match' => $fullNameMatch,
            'surname_match' => (bool) $surnameMatch,
            'context_hits' => array_values($contextHits),
        ];
    }

    /**
     * Drop the " - Outlet" suffix aggregators append to headlines when it names
     * the article's source ("... - Florida Politics", "... - politico.com"), so
     * words in the outlet's name aren't mistaken for context.
     */
    protected function withoutSourceSuffix(string $headline, string $sourceName): string
    {
        $pos = strrpos($headline, ' - ');
        if ($pos === false) {
            return $headline;
        }

        $normalize = fn (string $v) => preg_replace('/[^a-z0-9]/', '', preg_replace('/^the\s+|\.(com|org|net)$/', '', Str::lower(trim($v))));
        $suffix = $normalize(substr($headline, $pos + 3));
        $source = $normalize($sourceName);

        return $suffix !== '' && $source !== '' && (str_contains($suffix, $source) || str_contains($source, $suffix))
            ? substr($headline, 0, $pos)
            : $headline;
    }

    /**
     * Returns a rejection reason when the article isn't current coverage of the
     * official: an outlet tag/index page, a reference-work source, or a pre-1900 dated
     * anniversary headline ("resigns from the Senate, July 22, 1850"). A bare
     * old year isn't enough — current news cites old laws ("the 1864 ban").
     */
    protected function nonCoverageReason(string $haystack, string $sourceName): ?string
    {
        foreach (self::INDEX_PAGE_PATTERNS as $pattern) {
            if (str_contains($haystack, $pattern)) {
                return 'outlet index page, not an article';
            }
        }

        foreach (self::REFERENCE_SOURCES as $ref) {
            if (str_contains($sourceName, $ref) || str_contains($haystack, "- {$ref}")) {
                return 'reference/encyclopedia source';
            }
        }

        $month = '(jan|feb|mar|apr|may|jun|jul|aug|sep|sept|oct|nov|dec)[a-z]*\.?';
        if (preg_match('/\b' . $month . '\s+\d{1,2},?\s+1[0-8]\d\d\b/u', $haystack) === 1
            || preg_match('/\(\s*1[0-8]\d\d\s*[-\x{2013}]\s*1[0-9]\d\d\s*\)/u', $haystack) === 1) {
            return 'historical namesake (pre-1900 date)';
        }

        return null;
    }

    /**
     * Issue/topic tagging for verified articles, via the shared keyword matcher
     * (topic slugs, names and synonym keywords).
     *
     * @return array{topic_key:?string,topic_confidence:?float}
     */
    protected function extractTopicKey(string $headline, string $snippet): array
    {
        $match = app(IssueClassifierService::class)->confidentKeywordMatch(trim($headline.' '.$snippet));

        return [
            'topic_key' => $match['topic_slug'] ?? null,
            'topic_confidence' => $match['confidence'] ?? null,
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
