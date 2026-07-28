<?php

namespace App\Services;

use App\Contracts\MomentFetcher;
use App\Models\Politician;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Podcast/radio-interview fetcher for the viral-moment feature.
 *
 * Fans out to whichever provider(s) are configured, same pattern as
 * CandidateNewsService's NewsAPI/GNews fan-out — both are queried (not
 * either/or) when both keys are present, and results are merged:
 *   - Podcast Index (podcastindex.org) — free, default. /search/byperson
 *     returns episodes where the person appears in the episode's people tags,
 *     which is a good fit for "interview with {candidate}" discovery.
 *   - ListenNotes — optional paid provider with stronger relevance ranking
 *     and a documented embeddable player, used when LISTENNOTES_API_KEY is set.
 *
 * Neither provider exposes view/like/comment counts, so those stay null —
 * same consequence as CspanMomentService: clips score 0 and surface by
 * recency rather than featuring (config/u9itus.php moments.podcast).
 */
class PodcastMomentFetcher implements MomentFetcher
{
    protected int $cacheMinutes;

    /** Max clips to request per provider per politician. */
    protected int $maxClips;

    protected ?string $podcastIndexKey;

    protected ?string $podcastIndexSecret;

    protected ?string $listenNotesKey;

    public function __construct()
    {
        $this->cacheMinutes = (int) config('u9itus.moments.podcast.cache_minutes', 1440);
        $this->maxClips = (int) config('u9itus.moments.podcast.max_clips', 10);
        $this->podcastIndexKey = config('services.podcastindex.api_key');
        $this->podcastIndexSecret = config('services.podcastindex.api_secret');
        $this->listenNotesKey = config('services.listennotes.api_key');
    }

    public function source(): string
    {
        return 'podcast';
    }

    /**
     * Configured when the source is enabled and at least one provider has
     * credentials set — mirrors CandidateNewsService's "query whichever
     * providers are configured" gating.
     */
    public function isConfigured(): bool
    {
        if (! (bool) config('u9itus.moments.podcast.enabled', true)) {
            return false;
        }

        return $this->podcastIndexConfigured() || $this->listenNotesConfigured();
    }

    protected function podcastIndexConfigured(): bool
    {
        return filled($this->podcastIndexKey) && filled($this->podcastIndexSecret);
    }

    protected function listenNotesConfigured(): bool
    {
        return filled($this->listenNotesKey);
    }

    /**
     * Search configured podcast providers for episodes about the politician
     * and return normalized candidates + a fetch status the enricher records.
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
        if (! $this->isConfigured()) {
            Log::warning('PodcastMomentFetcher: source disabled or no provider configured');

            return ['status' => 'failed', 'http_status' => null, 'query' => null, 'clips' => []];
        }

        $cacheKey = "podcast.moments.{$politician->id}";

        return Cache::remember($cacheKey, $this->cacheMinutes * 60, function () use ($politician) {
            $query = $this->buildQuery($politician);
            if ($query === '') {
                return ['status' => 'empty', 'http_status' => null, 'query' => null, 'clips' => []];
            }

            [$clips, $anySucceeded] = $this->fetchFromAllProviders($politician, $query);
            $clips = $this->dedupe($clips);

            return [
                'status' => $clips === [] ? 'empty' : 'ok',
                'http_status' => $anySucceeded ? 200 : null,
                'query' => $query,
                'clips' => $clips,
            ];
        });
    }

    /**
     * Query every configured provider, catching each independently so one
     * provider's failure doesn't drop the other's results.
     *
     * @return array{0: list<array<string, mixed>>, 1: bool}
     */
    protected function fetchFromAllProviders(Politician $politician, string $query): array
    {
        $nameTokens = $this->nameTokens($politician);
        $clips = [];
        $anySucceeded = false;

        $providers = [
            'fetch_podcastindex' => fn () => $this->podcastIndexConfigured()
                ? $this->fetchFromPodcastIndex($query, $nameTokens)
                : null,
            'fetch_listennotes' => fn () => $this->listenNotesConfigured()
                ? $this->fetchFromListenNotes($query, $nameTokens)
                : null,
        ];

        foreach ($providers as $operation => $fetch) {
            try {
                $result = $fetch();
                if ($result === null) {
                    continue;
                }
                $clips = array_merge($clips, $result);
                $anySucceeded = true;
            } catch (\Throwable $e) {
                $this->logProviderException($operation, $e, ['politician_id' => $politician->id]);
            }
        }

        return [$clips, $anySucceeded];
    }

    public function clearCache(Politician $politician): void
    {
        Cache::forget("podcast.moments.{$politician->id}");
    }

    // ── Providers ─────────────────────────────────────────────────────────

    /**
     * Podcast Index /search/byperson — episodes tagged with this person.
     * Auth is a signed request, not a bearer key (see config/services.php).
     *
     * @param  list<string>  $nameTokens
     * @return list<array<string, mixed>>
     */
    protected function fetchFromPodcastIndex(string $query, array $nameTokens): array
    {
        $authDate = time();
        // sha1 here is mandated by Podcast Index's documented auth scheme
        // (https://podcastindex-org.github.io/docs-api/#authentication) — not
        // used for password/credential hashing, so the weak-hash warning is a
        // false positive for this call site.
        $authHeader = sha1($this->podcastIndexKey . $this->podcastIndexSecret . $authDate); // NOSONAR

        $response = Http::withHeaders([
            'X-Auth-Date' => (string) $authDate,
            'X-Auth-Key' => $this->podcastIndexKey,
            'Authorization' => $authHeader,
            'User-Agent' => 'u9itus.dev/1.0 (PodcastMomentFetcher)',
        ])->timeout(10)->get('https://api.podcastindex.org/api/1.0/search/byperson', [
            'q' => $query,
            'max' => $this->maxClips,
        ]);

        if (! $response->successful()) {
            Log::info('PodcastMomentFetcher: podcastindex non-2xx', ['status' => $response->status()]);

            return [];
        }

        $items = $response->json('items', []) ?: [];
        $out = [];

        foreach (array_slice($items, 0, $this->maxClips) as $item) {
            $id = trim((string) ($item['id'] ?? ''));
            $url = trim((string) ($item['link'] ?? ($item['enclosureUrl'] ?? '')));
            if ($id === '' || $url === '') {
                continue;
            }

            $publishedAt = null;
            $ts = (int) ($item['datePublished'] ?? 0);
            if ($ts > 0) {
                $publishedAt = Carbon::createFromTimestamp($ts);
            }

            $text = trim((string) ($item['title'] ?? '')) . ' ' . trim((string) ($item['description'] ?? ''));

            $out[] = [
                'source' => 'podcast',
                'source_id' => "podcastindex:{$id}",
                'title' => trim((string) ($item['title'] ?? '')),
                'url' => $url,
                'thumbnail_url' => ($item['image'] ?? $item['feedImage'] ?? null) ?: null,
                'published_at' => $publishedAt,
                'duration_seconds' => isset($item['duration']) ? (int) $item['duration'] : null,
                'view_count' => null,       // Podcast Index exposes no engagement counts
                'like_count' => null,
                'comment_count' => null,
                'match_confidence' => $this->matchConfidence($text, $nameTokens),
            ];
        }

        return $out;
    }

    /**
     * ListenNotes /search?type=episode. Stores ListenNotes' documented embed
     * URL directly as `url` (https://www.listennotes.com/e/{id}/embed/ —
     * https://www.listennotes.help/article/55, no API key required to embed)
     * so the frontend's toEmbedUrl() can pass it through unchanged.
     *
     * @param  list<string>  $nameTokens
     * @return list<array<string, mixed>>
     */
    protected function fetchFromListenNotes(string $query, array $nameTokens): array
    {
        $response = Http::withHeaders([
            'X-ListenAPI-Key' => $this->listenNotesKey,
        ])->timeout(10)->get('https://listen-api.listennotes.com/api/v2/search', [
            'q' => $query,
            'type' => 'episode',
            'only_in' => 'title,description',
        ]);

        if (! $response->successful()) {
            Log::info('PodcastMomentFetcher: listennotes non-2xx', ['status' => $response->status()]);

            return [];
        }

        $results = $response->json('results', []) ?: [];
        $out = [];

        foreach (array_slice($results, 0, $this->maxClips) as $item) {
            $id = trim((string) ($item['id'] ?? ''));
            if ($id === '') {
                continue;
            }

            $publishedAt = null;
            $ms = (int) ($item['pub_date_ms'] ?? 0);
            if ($ms > 0) {
                $publishedAt = Carbon::createFromTimestampMs($ms);
            }

            $title = trim((string) ($item['title_original'] ?? ($item['title'] ?? '')));
            $description = trim((string) ($item['description_original'] ?? ($item['description'] ?? '')));

            $out[] = [
                'source' => 'podcast',
                'source_id' => "listennotes:{$id}",
                'title' => $title,
                'url' => "https://www.listennotes.com/e/{$id}/embed/",
                'thumbnail_url' => $item['thumbnail'] ?? ($item['image'] ?? null) ?: null,
                'published_at' => $publishedAt,
                'duration_seconds' => isset($item['audio_length_sec']) ? (int) $item['audio_length_sec'] : null,
                'view_count' => null,       // ListenNotes exposes no engagement counts
                'like_count' => null,
                'comment_count' => null,
                'match_confidence' => $this->matchConfidence($title . ' ' . $description, $nameTokens),
            ];
        }

        return $out;
    }

    // ── Internals ─────────────────────────────────────────────────────────

    /**
     * Drop cross-provider duplicates of the same episode, keyed on a
     * normalized URL. Keeps the higher-match_confidence entry on collision.
     *
     * @param  list<array<string, mixed>>  $clips
     * @return list<array<string, mixed>>
     */
    protected function dedupe(array $clips): array
    {
        $byUrl = [];

        foreach ($clips as $clip) {
            $key = strtolower(trim((string) $clip['url']));
            if (! isset($byUrl[$key]) || $clip['match_confidence'] > $byUrl[$key]['match_confidence']) {
                $byUrl[$key] = $clip;
            }
        }

        return array_values($byUrl);
    }

    /**
     * Build a search query: politician name + office + state context — same
     * shaping as YouTubeMomentService/CspanMomentService.
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
     * Tokenize the politician's name into lowercase search tokens (drops tokens
     * shorter than 3 chars to avoid matching common words). Mirrors CspanMomentService.
     *
     * @return list<string>
     */
    protected function nameTokens(Politician $politician): array
    {
        $name = strtolower(trim((string) $politician->full_name));
        if ($name === '') {
            return [];
        }

        return array_values(array_filter(
            preg_split('/[^a-z0-9]+/', $name) ?: [],
            fn ($t) => strlen($t) >= 3,
        ));
    }

    /**
     * Fraction of the politician's name tokens present in the given text,
     * floored at 0.1 so a weak match still has a (small) score. Mirrors
     * CspanMomentService, but fed title+description since podcast episode
     * titles are often generic ("Episode 142") without the guest's name.
     */
    protected function matchConfidence(string $text, array $nameTokens): float
    {
        if (empty($nameTokens)) {
            return 0.1;
        }

        $text = ' ' . strtolower($text) . ' ';
        $hits = 0;
        foreach ($nameTokens as $token) {
            if (str_contains($text, " {$token}")) {
                $hits++;
            }
        }

        return max($hits / count($nameTokens), 0.1);
    }

    protected function logProviderException(string $operation, \Throwable $exception, array $context = []): void
    {
        Log::warning('PodcastMomentFetcher telemetry: provider exception', array_merge($context, [
            'operation' => $operation,
            'error' => $exception->getMessage(),
        ]));
    }
}
