<?php

namespace App\Services;

use Illuminate\Support\Carbon;

/**
 * Pure scoring function for a single viral/C-SPAN/popular moment clip.
 *
 * moment_score = log10(views) × view_velocity × recency_decay
 *                × authority_weight × match_confidence
 *
 *   log10(views)      — tames the long tail so a 10M-view clip beats 100k by
 *                       ~2× on this factor, not 100×, while still rewarding reach.
 *   view_velocity     — views per day since publication; the core "viral right
 *                       now" signal. A 2-day-old 1M-view clip outranks a
 *                       200-day-old 5M-view clip.
 *   recency_decay     — exp(-age_days / half_life), default half-life 30d. Older
 *                       clips fade so "featured" rotates naturally.
 *   authority_weight  — per-source weight from config('u9itus.moments.authority_weights').
 *   match_confidence  — 0–1 name+office+state match quality from the fetcher;
 *                       drops the score on weak matches so a homonym clip can't
 *                       outrank a real one.
 *
 * The service is stateless and side-effect-free so it can be unit-tested in
 * isolation (like FraudPreventionService's scoring). All components are
 * returned alongside the score so the enricher can store them in
 * politician_viral_moments.score_components and the formula can be retuned
 * later without re-scraping.
 */
class MomentScoreService
{
    /**
     * Score a clip from its raw engagement components.
     *
     * @param  int|null    $viewCount        Raw view count (null/0 → score 0).
     * @param  Carbon|null $publishedAt      When the clip was published.
     * @param  float        $authorityWeight  Per-source weight (0–1+).
     * @param  float        $matchConfidence  Name/office match quality (0–1).
     * @param  Carbon|null  $asOf             Reference time for age (default now).
     * @return array{
     *     moment_score: float,
     *     view_velocity: float,
     *     score_components: array{
     *         log_views: float, view_velocity: float, age_days: float,
     *         recency_decay: float, authority_weight: float, match_confidence: float
     *     }
     * }
     */
    public function score(
        ?int $viewCount,
        ?Carbon $publishedAt,
        float $authorityWeight,
        float $matchConfidence,
        ?Carbon $asOf = null,
    ): array {
        $asOf = $asOf ?? Carbon::now();

        $views = max((int) ($viewCount ?? 0), 0);
        $authority = max($authorityWeight, 0.0);
        $confidence = max(min($matchConfidence, 1.0), 0.0);

        // Age in fractional days (hour-level fidelity, not day-truncated).
        $ageDays = $this->ageInDays($publishedAt, $asOf);

        $logViews = $views > 0 ? log10((float) $views) : 0.0;

        // views/day since publication; denominator floored at 1 day so a clip
        // published hours ago doesn't get an infinite-velocity spike.
        $velocity = $views / max($ageDays, 1.0);

        $halfLife = (float) config('u9itus.moments.recency_decay_half_life_days', 30);
        $halfLife = $halfLife > 0 ? $halfLife : 30.0;
        $decay = exp(-$ageDays / $halfLife);

        $score = $logViews * $velocity * $decay * $authority * $confidence;

        return [
            'moment_score' => round($score, 4),
            'view_velocity' => round($velocity, 2),
            'score_components' => [
                'log_views' => round($logViews, 4),
                'view_velocity' => round($velocity, 2),
                'age_days' => round($ageDays, 4),
                'recency_decay' => round($decay, 4),
                'authority_weight' => round($authority, 4),
                'match_confidence' => round($confidence, 4),
            ],
        ];
    }

    /**
     * Resolve the per-source authority weight from config, with a safe default.
     */
    public function authorityWeightFor(string $source): float
    {
        $weights = config('u9itus.moments.authority_weights', []);

        return isset($weights[$source]) ? (float) $weights[$source] : 0.50;
    }

    /**
     * Fractional days between publication and the reference time, floored at 0
     * (future-dated clips — clock skew, timezone edges — count as 0 days old).
     */
    private function ageInDays(?Carbon $publishedAt, Carbon $asOf): float
    {
        if ($publishedAt === null) {
            return 0.0;
        }

        $delta = $asOf->getTimestamp() - $publishedAt->getTimestamp();

        return max($delta, 0) / 86400.0;
    }
}