<?php

namespace App\Services;

use App\Models\CandidateNewsArticle;
use App\Models\Politician;
use App\Models\PoliticianEndorsement;
use App\Models\Voter;
use App\Models\VoterFavoriteBoundary;
use App\Services\Marketing\AudienceService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Builds the weekly "saved places" digest content for a voter — new-since-
 * $since candidate news, endorsements, and popular YouTube clips for every
 * district/city they've favorited on the map. Reuses
 * AudienceService::districtCodeVariants() for district-code normalization
 * instead of reimplementing it a third time (AudienceService itself and
 * CauseCampaignMatchService are the other two consumers).
 */
class BoundaryDigestMatchService
{
    /**
     * Hard cap on boundary sections per email so a voter who's favorited a
     * lot of active places doesn't get an unreadably long digest — the rest
     * are summarized as a single "+N more" line instead of dropped silently.
     */
    public const MAX_SECTIONS = 6;

    /**
     * @return array{
     *     sections: array<int, array{
     *         boundary: VoterFavoriteBoundary,
     *         candidates: Collection<int, array{politician: Politician, news: Collection, endorsements: Collection, videos: Collection}>,
     *         activity_count: int
     *     }>,
     *     remaining: int,
     *     total_item_count: int
     * }
     */
    public function contentForVoter(Voter $voter, Carbon $since): array
    {
        $sections = [];

        foreach ($voter->favoriteBoundaries as $boundary) {
            $politicians = $this->politiciansForBoundary($boundary);

            if ($politicians->isEmpty()) {
                continue;
            }

            $candidates = $politicians
                ->map(function ($politician) use ($since) {
                    $news = CandidateNewsArticle::query()
                        ->where('politician_id', $politician->id)
                        ->where('published_at', '>=', $since)
                        ->orderByDesc('published_at')
                        ->limit(3)
                        ->get();

                    $endorsements = PoliticianEndorsement::query()
                        ->active()
                        ->where('politician_id', $politician->id)
                        ->where('created_at', '>=', $since)
                        ->get();

                    // Popular YouTube clips only (not C-SPAN/podcast sources) —
                    // same ranking as the profile page's "Top This Week" panel
                    // (PublicProfileController::viralMoments()->eligible()->topByScore()),
                    // scoped to this week's window instead of a fixed 7 days.
                    $videos = $politician->viralMoments()
                        ->where('source', 'youtube')
                        ->where('published_at', '>=', $since)
                        ->topByScore()
                        ->limit(2)
                        ->get();

                    return [
                        'politician' => $politician,
                        'news' => $news,
                        'endorsements' => $endorsements,
                        'videos' => $videos,
                    ];
                })
                ->filter(fn (array $row) => $row['news']->isNotEmpty() || $row['endorsements']->isNotEmpty() || $row['videos']->isNotEmpty())
                ->values();

            if ($candidates->isEmpty()) {
                continue;
            }

            $activityCount = $candidates->sum(
                fn (array $row) => $row['news']->count() + $row['endorsements']->count() + $row['videos']->count()
            );

            $sections[] = [
                'boundary' => $boundary,
                'candidates' => $candidates,
                'activity_count' => $activityCount,
            ];
        }

        $totalItemCount = array_sum(array_column($sections, 'activity_count'));

        // Busiest saved places first, so a hard cap trims the quietest
        // sections rather than truncating arbitrarily (e.g. by favorite order).
        usort($sections, fn (array $a, array $b) => $b['activity_count'] <=> $a['activity_count']);

        $remaining = max(0, count($sections) - self::MAX_SECTIONS);
        $sections = array_slice($sections, 0, self::MAX_SECTIONS);

        return [
            'sections' => $sections,
            'remaining' => $remaining,
            'total_item_count' => $totalItemCount,
        ];
    }

    private function politiciansForBoundary(VoterFavoriteBoundary $boundary): Collection
    {
        $query = Politician::query()
            ->where('page_published', true)
            ->where('is_active', true)
            ->where('state', $boundary->state_abbr);

        if ($boundary->boundary_type === VoterFavoriteBoundary::TYPE_DISTRICT) {
            // politicians.district stores the canonical "ST-XX" code (see
            // NormalizeDistrictFormat's docblock) — districtCodeVariants()
            // needs that full code to produce matching variants, not the
            // bare district number alone (which it can't pair with a state).
            $variants = AudienceService::districtCodeVariants($boundary->state_abbr . '-' . $boundary->district_number);

            if ($variants === []) {
                return collect();
            }

            $query->whereNotNull('district')
                ->whereRaw('UPPER(district) IN (' . implode(', ', array_fill(0, count($variants), '?')) . ')', $variants);
        } else {
            $query->where('city', $boundary->city_name);
        }

        return $query->get();
    }
}
