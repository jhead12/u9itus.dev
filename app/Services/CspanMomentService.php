<?php

namespace App\Services;

use App\Contracts\MomentFetcher;
use App\Models\Politician;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

/**
 * C-SPAN fetcher for the viral-moment feature.
 *
 * C-SPAN has no public video API and renders its search results client-side
 * (the search page returns a "Not yet implemented" shell + JS bundle, with zero
 * server-rendered video links). So, like App\Services\OpenSecretsService, this
 * service shells out to a Playwright scraper (scripts/scrape-cspan.js) that
 * drives real Chromium, waits for results to render, and emits JSON to stdout.
 *
 * Returns normalized clip candidates with the same shape as YouTubeMomentService
 * — scoring/upsert/prune/feature stay in ViralMomentEnricherService. C-SPAN
 * exposes no view/like/comment counts, so those are null; that yields
 * moment_score = 0 and C-SPAN clips surface in the list by recency rather than
 * featuring (intentional — see config/u9itus.php moments.cspan).
 *
 * Setup: `npm install playwright && npx playwright install chromium` where the
 * command runs (local / Railway / GitHub Actions). Gate with CSPAN_MOMENTS_ENABLED.
 */
class CspanMomentService implements MomentFetcher
{
    /** Path to the Playwright scraper script, relative to base_path(). */
    protected string $scraperScript = 'scripts/scrape-cspan.js';

    protected int $cacheMinutes;

    /** Max clips to request from the scraper per politician. */
    protected int $maxClips;

    public function __construct()
    {
        $this->cacheMinutes = (int) config('u9itus.moments.cspan.cache_minutes', 1440);
        $this->maxClips = (int) config('u9itus.moments.cspan.max_clips', 10);
    }

    public function source(): string
    {
        return 'cspan';
    }

    /**
     * Always "configured" when the source is enabled — no API key needed. The
     * scraper fails gracefully (status 'failed') if Node/Playwright is missing.
     */
    public function isConfigured(): bool
    {
        return (bool) config('u9itus.moments.cspan.enabled', true);
    }

    /**
     * Search C-SPAN for clips about the politician and return normalized
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
            Log::warning('CspanMomentService: source disabled (CSPAN_MOMENTS_ENABLED=false)');
            return ['status' => 'failed', 'http_status' => null, 'query' => null, 'clips' => []];
        }

        $cacheKey = "cspan.moments.{$politician->id}";

        return Cache::remember($cacheKey, $this->cacheMinutes * 60, function () use ($politician) {
            $query = $this->buildQuery($politician);
            if ($query === '') {
                return ['status' => 'empty', 'http_status' => null, 'query' => null, 'clips' => []];
            }

            try {
                $raw = $this->runScraper($politician, $query);

                if ($raw === null) {
                    // Scraper errored / no usable JSON — recorded via runScraper logs.
                    return ['status' => 'failed', 'http_status' => null, 'query' => $query, 'clips' => []];
                }

                $clips = $this->normalize($raw['clips'] ?? [], $politician);

                return [
                    'status' => $clips === [] ? 'empty' : 'ok',
                    'http_status' => 200,
                    'query' => $query,
                    'clips' => $clips,
                ];
            } catch (\Throwable $e) {
                $this->logProviderException('fetch_moments', $e, ['politician_id' => $politician->id]);

                return ['status' => 'failed', 'http_status' => null, 'query' => $query, 'clips' => []];
            }
        });
    }

    public function clearCache(Politician $politician): void
    {
        Cache::forget("cspan.moments.{$politician->id}");
    }

    // ── Internals ─────────────────────────────────────────────────────────

    /**
     * Invoke scripts/scrape-cspan.js via Node.js and return parsed JSON, or
     * null on any failure (missing script, non-zero exit, bad JSON). Mirrors
     * OpenSecretsService::runScraper.
     */
    protected function runScraper(Politician $politician, string $query): ?array
    {
        $scriptPath = base_path($this->scraperScript);
        if (! file_exists($scriptPath)) {
            Log::warning('CspanMomentService: scraper script not found', ['path' => $scriptPath]);

            return null;
        }

        $cmd = ['node', $scriptPath, "--name={$query}", "--max={$this->maxClips}"];
        $process = new Process($cmd, base_path(), null, null, 60);

        try {
            $process->run();
        } catch (\Throwable $e) {
            Log::warning('CspanMomentService: scraper process error', [
                'politician_id' => $politician->id,
                'error'         => $e->getMessage(),
            ]);

            return null;
        }

        if (! $process->isSuccessful()) {
            Log::info('CspanMomentService: scraper returned non-zero', [
                'politician_id' => $politician->id,
                'stderr'        => substr($process->getErrorOutput(), 0, 2000),
            ]);

            return null;
        }

        $json = json_decode($process->getOutput(), true);
        if (! is_array($json)) {
            Log::info('CspanMomentService: scraper returned no usable JSON', [
                'politician_id' => $politician->id,
                'stderr'        => substr($process->getErrorOutput(), 0, 2000),
            ]);

            return null;
        }

        return $json;
    }

    /**
     * Normalize the scraper's clip list into the fetcher contract shape, stamping
     * source + null engagement and computing match_confidence from the title.
     *
     * @param  list<array<string, mixed>>  $clips
     * @return list<array<string, mixed>>
     */
    protected function normalize(array $clips, Politician $politician): array
    {
        $nameTokens = $this->nameTokens($politician);
        $out = [];

        foreach ($clips as $clip) {
            $id = trim((string) ($clip['source_id'] ?? ''));
            $url = trim((string) ($clip['url'] ?? ''));
            if ($id === '' || $url === '') {
                continue;
            }

            $publishedAt = isset($clip['published_at']) && $clip['published_at'] !== ''
                ? $this->parseDate((string) $clip['published_at'])
                : null;

            $out[] = [
                'source' => 'cspan',
                'source_id' => $id,
                'title' => trim((string) ($clip['title'] ?? '')),
                'url' => $url,
                'thumbnail_url' => isset($clip['thumbnail_url']) && $clip['thumbnail_url'] !== ''
                    ? (string) $clip['thumbnail_url']
                    : null,
                'published_at' => $publishedAt,
                'duration_seconds' => isset($clip['duration_seconds']) ? (int) $clip['duration_seconds'] : null,
                'view_count' => null,    // C-SPAN exposes no engagement counts
                'like_count' => null,
                'comment_count' => null,
                'match_confidence' => $this->matchConfidence((string) ($clip['title'] ?? ''), $nameTokens),
            ];
        }

        return $out;
    }

    /**
     * Build a search query: politician name + office + state context — same
     * shaping as YouTubeMomentService so results are about, not by, the politician.
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
     * shorter than 3 chars to avoid matching common words). Mirrors YouTubeMomentService.
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
     * Fraction of the politician's name tokens present in the clip title,
     * floored at 0.1 so a weak match still has a (small) score. Mirrors YouTubeMomentService.
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

        return max($hits / count($nameTokens), 0.1);
    }

    /**
     * Parse a date string from the scraper into a Carbon instance, tolerating
     * ISO-8601 and common C-SPAN air-date formats. Returns null if unparseable.
     */
    protected function parseDate(string $date): ?Carbon
    {
        try {
            return Carbon::parse($date);
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function logProviderException(string $operation, \Throwable $exception, array $context = []): void
    {
        Log::warning('CspanMomentService telemetry: provider exception', array_merge($context, [
            'operation' => $operation,
            'error' => $exception->getMessage(),
        ]));
    }
}