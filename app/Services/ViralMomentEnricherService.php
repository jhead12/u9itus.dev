<?php

namespace App\Services;

use App\Models\Politician;
use App\Models\PoliticianViralMoment;
use App\Models\ViralMomentEnrichmentRun;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Orchestrates the viral-moment enrichment pass for one politician:
 *   1. fetch  — YouTubeMomentService (24h-cached) returns normalized clips.
 *   2. score  — MomentScoreService computes moment_score from components.
 *   3. upsert — politician_viral_moments rows, keyed on (politician, source,
 *               source_id) so re-runs refresh engagement instead of duplicating.
 *   4. prune  — keep only the top N per politician (config: max_per_politician).
 *   5. feature — promote the single highest-scoring eligible clip (last 30d,
 *                above min_view_count) and mirror it onto politicians.featured_moment_*
 *                so the map pin reads one column.
 *
 * Mirrors ProfileEnricherService: enrich(Politician) is the entry point, a run
 * row records provenance, and a getDisplayData() read path serves the profile.
 */
class ViralMomentEnricherService
{
    public function __construct(
        private readonly YouTubeMomentService $youtube,
        private readonly MomentScoreService $scorer,
    ) {}

    /**
     * Run the full pass for a politician. Returns a summary, or null if the
     * fetch failed before any clips were retrieved.
     *
     * @return array{status: string, kept: int, featured: bool}|null
     */
    public function enrich(Politician $politician): ?array
    {
        $result = $this->youtube->fetchMoments($politician);

        $run = $this->recordRun($politician, $result);

        if ($result['status'] !== 'ok' || empty($result['clips'])) {
            // No new clips this run — still re-promote in case an older clip is
            // now the top eligible (e.g. the previous featured aged out).
            $this->promoteFeatured($politician);

            return ['status' => $result['status'], 'kept' => 0, 'featured' => $this->hasFeatured($politician)];
        }

        $kept = 0;
        foreach ($result['clips'] as $clip) {
            $this->upsertMoment($politician, $run, $clip);
            $kept++;
        }

        $this->prune($politician);
        $this->promoteFeatured($politician);

        return ['status' => 'ok', 'kept' => $kept, 'featured' => $this->hasFeatured($politician)];
    }

    // ── Persistence ───────────────────────────────────────────────────────

    protected function recordRun(Politician $politician, array $result): ViralMomentEnrichmentRun
    {
        $clips = $result['clips'] ?? [];
        $counts = [
            'clips' => count($clips),
            'kept' => count($clips),
            'featured' => 0, // filled in after promoteFeatured; run is a snapshot at fetch time
        ];

        return ViralMomentEnrichmentRun::create([
            'politician_id' => $politician->id,
            'source' => 'youtube',
            'fetch_status' => $result['status'],
            'http_status' => $result['http_status'],
            'query_string' => $result['query'],
            'extracted_counts' => $counts,
            'enriched_at' => now(),
        ]);
    }

    /**
     * Upsert a clip: refresh engagement + re-score on every run. The score is
     * recomputed from current components so view velocity/decay stay live
     * between fetches even if YouTube returns the same video IDs.
     */
    protected function upsertMoment(Politician $politician, ViralMomentEnrichmentRun $run, array $clip): PoliticianViralMoment
    {
        $authority = $this->scorer->authorityWeightFor((string) ($clip['source'] ?? 'youtube'));
        $scored = $this->scorer->score(
            $clip['view_count'] ?? null,
            $clip['published_at'] ?? null,
            $authority,
            (float) ($clip['match_confidence'] ?? 1.0),
        );

        return PoliticianViralMoment::updateOrCreate(
            [
                'politician_id' => $politician->id,
                'source' => $clip['source'],
                'source_id' => $clip['source_id'],
            ],
            [
                'run_id' => $run->id,
                'title' => $clip['title'] ?? '',
                'url' => $clip['url'] ?? '',
                'thumbnail_url' => $clip['thumbnail_url'] ?? null,
                'published_at' => $clip['published_at'] ?? null,
                'duration_seconds' => $clip['duration_seconds'] ?? null,
                'view_count' => $clip['view_count'] ?? null,
                'like_count' => $clip['like_count'] ?? null,
                'comment_count' => $clip['comment_count'] ?? null,
                'view_velocity' => $scored['view_velocity'],
                'authority_weight' => $authority,
                'match_confidence' => (float) ($clip['match_confidence'] ?? 1.0),
                'moment_score' => $scored['moment_score'],
                'score_components' => $scored['score_components'],
                'captured_at' => now(),
                'is_featured' => false, // reset; promoteFeatured re-sets the winner
            ],
        );
    }

    /**
     * Keep only the top N moments per politician by score (oldest/lowest-score
     * clips pruned). Keeps the table bounded for a national roster.
     */
    protected function prune(Politician $politician): void
    {
        $max = (int) config('u9itus.moments.max_per_politician', 10);
        if ($max <= 0) {
            return;
        }

        $idsToKeep = $politician->viralMoments()
            ->topByScore()
            ->limit($max)
            ->pluck('id');

        if ($idsToKeep->isEmpty()) {
            return;
        }

        $politician->viralMoments()
            ->whereKeyNot($idsToKeep)
            ->delete();
    }

    /**
     * Promote the single highest-scoring eligible clip to is_featured and
     * mirror it onto politicians.featured_moment_* for cheap map-pin reads.
     * Unfeatures everything first so the badge never lingers on a clip that
     * aged out or was pruned.
     */
    protected function promoteFeatured(Politician $politician): void
    {
        $windowDays = (int) config('u9itus.moments.eligibility_window_days', 30);
        $minViews = (int) config('u9itus.moments.min_view_count', 1000);

        $politician->viralMoments()->where('is_featured', true)->update(['is_featured' => false]);

        $winner = $politician->viralMoments()
            ->eligible($windowDays)
            ->where('view_count', '>=', $minViews)
            ->where('moment_score', '>', 0)
            ->topByScore()
            ->first();

        if (! $winner) {
            $politician->forceFill([
                'featured_moment' => null,
                'featured_moment_score' => null,
                'featured_moment_published_at' => null,
                'featured_moment_updated_at' => now(),
            ])->saveQuietly();

            return;
        }

        $winner->updateQuietly(['is_featured' => true]);

        $politician->forceFill([
            'featured_moment' => [
                'title' => $winner->title,
                'url' => $winner->url,
                'thumbnail_url' => $winner->thumbnail_url,
                'source' => $winner->source,
                'published_at' => optional($winner->published_at)?->toIso8601String(),
                'view_count' => $winner->view_count,
            ],
            'featured_moment_score' => $winner->moment_score,
            'featured_moment_published_at' => $winner->published_at,
            'featured_moment_updated_at' => now(),
        ])->saveQuietly();
    }

    protected function hasFeatured(Politician $politician): bool
    {
        return $politician->viralMoments()->featured()->exists();
    }

    // ── Display (read path for the profile page) ──────────────────────────

    /**
     * Return the ranked recent moments for a politician's public profile.
     * Featured clip first, then the rest by score. Returns null if nothing has
     * been enriched yet (the panel shows a "data not yet available" state).
     *
     * @return array<string, mixed>|null
     */
    public function getDisplayData(Politician $politician, int $limit = 5): ?array
    {
        $moments = $politician->viralMoments()
            ->with('run')
            ->topByScore()
            ->limit($limit)
            ->get();

        if ($moments->isEmpty()) {
            return null;
        }

        $featured = $moments->first(fn (PoliticianViralMoment $m) => $m->is_featured);

        return [
            'featured' => $featured ? $this->mapMoment($featured) : null,
            'moments' => $moments->map(fn (PoliticianViralMoment $m) => $this->mapMoment($m))->values()->all(),
            'source' => 'YouTube', // expand as more sources come online
            'enriched_at' => optional($politician->latestViralMomentRun?->enriched_at)?->toIso8601String(),
        ];
    }

    protected function mapMoment(PoliticianViralMoment $m): array
    {
        return [
            'title' => $m->title,
            'url' => $m->url,
            'thumbnail_url' => $m->thumbnail_url,
            'source' => $m->source,
            'published_at' => optional($m->published_at)?->toIso8601String(),
            'view_count' => $m->view_count,
            'like_count' => $m->like_count,
            'moment_score' => (float) $m->moment_score,
            'is_featured' => $m->is_featured,
        ];
    }

    // ── News-freshness gate (quota saver) ─────────────────────────────────

    /**
     * Whether this politician has had recent news coverage — used by the
     * command to skip quiet politicians and save YouTube quota. Delegates to
     * the existing candidate_news_articles pool via CandidateNewsService.
     */
    public function hasRecentNews(Politician $politician, int $withinHours = 48): bool
    {
        try {
            return DB::table('candidate_news_articles')
                ->where('politician_id', $politician->id)
                ->where('created_at', '>=', now()->subHours($withinHours))
                ->exists();
        } catch (\Throwable $e) {
            Log::info('ViralMomentEnricherService: news-freshness gate skipped', [
                'politician_id' => $politician->id,
                'error' => $e->getMessage(),
            ]);

            return true; // fail open — enrich rather than skip on a transient DB error
        }
    }
}