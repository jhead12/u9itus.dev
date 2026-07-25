<?php

namespace App\Services;

use App\Contracts\MomentFetcher;
use App\Models\Politician;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * YouTube Data API v3 fetcher for the viral-moment feature.
 *
 * Searches YouTube for clips about a politician, then pulls engagement stats
 * (view/like/comment counts) for each result. Returns normalized clip
 * candidates — scoring is done by MomentScoreService in the enricher, not here,
 * so this service stays a thin fetcher (mirrors FECService's shape: Http facade,
 * 24h cache, isConfigured gate, telemetry logging).
 *
 * Quarterly cost note: search costs 100 quota units and videos.list costs 1
 * unit per call; the default quota is 10,000 units/day. The enricher gates on
 * candidate_news_articles freshness so only politicians with recent news are
 * queried, keeping a national roster within budget.
 *
 * Setup: add YOUTUBE_API_KEY to .env / GitHub Actions secrets / Railway env.
 */
class YouTubeMomentService implements MomentFetcher
{
    protected ?string $apiKey;
    protected string $baseUrl;
    protected int $cacheDuration = 86400; // 24 hours

    /** Max clips to return per politician — bounded so we stay within quota. */
    protected int $maxClips = 10;

    public function __construct()
    {
        $this->apiKey = config('services.google.youtube_api_key');
        $this->baseUrl = (string) config('services.google.youtube_api_base_url', 'https://www.googleapis.com/youtube/v3');
    }

    public function source(): string
    {
        return 'youtube';
    }

    public function isConfigured(): bool
    {
        return ! empty($this->apiKey);
    }

    /**
     * Search YouTube for clips about the politician and return normalized
     * candidates + a fetch status the enricher records on the run row.
     *
     * @return array{
     *     status: string,
     *     http_status: int|null,
     *     query: string|null,
     *     clips: array<int, array<string, mixed>>
     * }
     */
    public function fetchMoments(Politician $politician): array
    {
        $empty = ['status' => 'empty', 'http_status' => null, 'query' => null, 'clips' => []];

        if (! $this->isConfigured()) {
            Log::warning('YouTubeMomentService: API key not configured');
            return ['status' => 'failed', 'http_status' => null, 'query' => null, 'clips' => []];
        }

        $cacheKey = "youtube.moments.{$politician->id}";

        return Cache::remember($cacheKey, $this->cacheDuration, function () use ($politician) {
            $query = $this->buildQuery($politician);
            if ($query === '') {
                return ['status' => 'empty', 'http_status' => null, 'query' => null, 'clips' => []];
            }

            try {
                $videoIds = $this->searchVideos($politician, $query);
                if (empty($videoIds)) {
                    return ['status' => 'empty', 'http_status' => null, 'query' => $query, 'clips' => []];
                }

                $clips = $this->loadStatistics($videoIds, $politician);

                return [
                    'status' => 'ok',
                    'http_status' => 200,
                    'query' => $query,
                    'clips' => $clips,
                ];
            } catch (HttpFailureException $e) {
                return [
                    'status' => $e->status,
                    'http_status' => $e->httpStatus,
                    'query' => $query,
                    'clips' => [],
                ];
            } catch (\Throwable $e) {
                $this->logProviderException('fetch_moments', $e, ['politician_id' => $politician->id]);

                return ['status' => 'failed', 'http_status' => null, 'query' => $query, 'clips' => []];
            }
        });
    }

    public function clearCache(Politician $politician): void
    {
        Cache::forget("youtube.moments.{$politician->id}");
    }

    // ── Internals ─────────────────────────────────────────────────────────

    /**
     * Build a YouTube search query: politician name + office + state context.
     * Keeps the query tight enough to surface clips *about* the politician
     * rather than clips *by* them (their own campaign channel dominates a bare
     * name search otherwise).
     */
    protected function buildQuery(Politician $politician): string
    {
        $name = trim((string) $politician->full_name);
        if ($name === '') {
            return '';
        }

        $parts = [$name];
        $office = trim((string) ($politician->political_office ?? ''));
        if ($office !== '') {
            $parts[] = $office;
        }
        $state = trim((string) ($politician->state ?? ''));
        if ($state !== '') {
            $parts[] = $state;
        }

        return implode(' ', $parts);
    }

    /**
     * Run a search.list call and return up to $maxClips video IDs, ordered by
     * publish date (most recent first). Scoring (view velocity/decay/authority)
     * still runs downstream in MomentScoreService, but discovery now surfaces
     * the newest clips rather than the highest-view ones.
     *
     * @return list<string>
     */
    protected function searchVideos(Politician $politician, string $query): array
    {
        $response = Http::timeout(10)->get("{$this->baseUrl}/search", [
            'key' => $this->apiKey,
            'part' => 'id',
            'type' => 'video',
            'q' => $query,
            'maxResults' => $this->maxClips,
            'order' => 'date',
            'publishedAfter' => now()->subDays((int) config('u9itus.moments.eligibility_window_days', 30))->toIso8601String(),
        ]);

        if (! $response->successful()) {
            $this->handleHttpFailure('search_videos', $response->status(), $response->body());
        }

        $items = $response->json('items', []);
        $ids = [];
        foreach ($items as $item) {
            $id = $item['id']['videoId'] ?? null;
            if (is_string($id) && $id !== '') {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /**
     * Batch-fetch snippet + statistics for the search result IDs and normalize
     * into clip candidates. match_confidence is a rough name-token overlap so a
     * homonym clip can't outrank a real one once scored.
     *
     * @param  list<string>  $videoIds
     * @return list<array<string, mixed>>
     */
    protected function loadStatistics(array $videoIds, Politician $politician): array
    {
        if (empty($videoIds)) {
            return [];
        }

        $response = Http::timeout(10)->get("{$this->baseUrl}/videos", [
            'key' => $this->apiKey,
            'part' => 'snippet,statistics,contentDetails',
            'id' => implode(',', $videoIds),
        ]);

        if (! $response->successful()) {
            $this->handleHttpFailure('load_statistics', $response->status(), $response->body());
        }

        $nameTokens = $this->nameTokens($politician);
        $clips = [];

        foreach ($response->json('items', []) as $item) {
            $id = $item['id'] ?? null;
            if (! is_string($id) || $id === '') {
                continue;
            }

            $snippet = $item['snippet'] ?? [];
            $stats = $item['statistics'] ?? [];
            $content = $item['contentDetails'] ?? [];

            $publishedAt = isset($snippet['publishedAt'])
                ? Carbon::parse($snippet['publishedAt'])
                : null;

            $clips[] = [
                'source' => 'youtube',
                'source_id' => $id,
                'title' => trim((string) ($snippet['title'] ?? '')),
                'url' => "https://www.youtube.com/watch?v={$id}",
                'thumbnail_url' => $snippet['thumbnails']['high']['url']
                    ?? $snippet['thumbnails']['medium']['url']
                    ?? $snippet['thumbnails']['default']['url']
                    ?? null,
                'published_at' => $publishedAt,
                'duration_seconds' => $this->parseIsoDuration((string) ($content['duration'] ?? '')),
                'view_count' => isset($stats['viewCount']) ? (int) $stats['viewCount'] : null,
                'like_count' => isset($stats['likeCount']) ? (int) $stats['likeCount'] : null,
                'comment_count' => isset($stats['commentCount']) ? (int) $stats['commentCount'] : null,
                'match_confidence' => $this->matchConfidence((string) ($snippet['title'] ?? ''), $nameTokens),
            ];
        }

        return $clips;
    }

    /**
     * Tokenize the politician's name into lowercase search tokens (drops tiny
     * tokens like initials/short names shorter than 3 chars to avoid matching
     * common words).
     *
     * @return list<string>
     */
    protected function nameTokens(Politician $politician): array
    {
        $name = strtolower(trim((string) $politician->full_name));
        if ($name === '') {
            return [];
        }

        $tokens = array_values(array_filter(preg_split('/[^a-z0-9]+/', $name) ?: [], fn ($t) => strlen($t) >= 3));

        return $tokens;
    }

    /**
     * Fraction of the politician's name tokens present in the clip title,
     * floored at 0.1 so a weak match still has a (small) score rather than 0.
     */
    protected function matchConfidence(string $title, array $nameTokens): float
    {
        if (empty($nameTokens)) {
            return 0.1;
        }

        $title = ' ' . strtolower($title) . ' ';
        $hits = 0;
        foreach ($nameTokens as $token) {
            if (str_contains($title, " {$token}")) {
                $hits++;
            }
        }

        $ratio = $hits / count($nameTokens);

        return max($ratio, 0.1);
    }

    /**
     * Parse an ISO-8601 duration (PT1M30S, PT45S, PT1H2M3S) into seconds.
     */
    protected function parseIsoDuration(string $duration): ?int
    {
        if ($duration === '' || ! preg_match('/^PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?$/', $duration, $m)) {
            return null;
        }

        $hours = (int) ($m[1] ?? 0);
        $minutes = (int) ($m[2] ?? 0);
        $seconds = (int) ($m[3] ?? 0);

        return $hours * 3600 + $minutes * 60 + $seconds;
    }

    /**
     * Map an HTTP failure to a fetch_status + throw so the caller records it on
     * the run row. 403 with quotaExceeded → quota_exceeded; 429 → rate_limited.
     */
    protected function handleHttpFailure(string $operation, int $status, string $body): void
    {
        $this->logHttpFailure($operation, $status, ['body' => substr($body, 0, 500)]);

        $statusKey = $status === 429
            ? 'rate_limited'
            : (str_contains($body, 'quotaExceeded') ? 'quota_exceeded' : 'http_error');

        throw new HttpFailureException($statusKey, $status);
    }

    protected function logHttpFailure(string $operation, int $status, array $context = []): void
    {
        Log::warning('YouTubeMomentService telemetry: HTTP request failed', array_merge($context, [
            'operation' => $operation,
            'status' => $status,
            'is_rate_limited' => $status === 429,
        ]));
    }

    protected function logProviderException(string $operation, \Throwable $exception, array $context = []): void
    {
        Log::warning('YouTubeMomentService telemetry: provider exception', array_merge($context, [
            'operation' => $operation,
            'error' => $exception->getMessage(),
        ]));
    }
}