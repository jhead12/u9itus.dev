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
 * $since candidate news + endorsements for every district/city they've
 * favorited on the map. Reuses AudienceService::districtCodeVariants() for
 * district-code normalization instead of reimplementing it a third time
 * (AudienceService itself and CauseCampaignMatchService are the other two
 * consumers).
 */
class BoundaryDigestMatchService
{
    /**
     * @return array<int, array{
     *     boundary: VoterFavoriteBoundary,
     *     candidates: Collection<int, array{politician: Politician, news: Collection, endorsements: Collection}>
     * }>
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

                    return [
                        'politician' => $politician,
                        'news' => $news,
                        'endorsements' => $endorsements,
                    ];
                })
                ->filter(fn (array $row) => $row['news']->isNotEmpty() || $row['endorsements']->isNotEmpty())
                ->values();

            if ($candidates->isEmpty()) {
                continue;
            }

            $sections[] = [
                'boundary' => $boundary,
                'candidates' => $candidates,
            ];
        }

        return $sections;
    }

    private function politiciansForBoundary(VoterFavoriteBoundary $boundary): Collection
    {
        $query = Politician::query()
            ->where('page_published', true)
            ->where('is_active', true)
            ->where('state', $boundary->state_abbr);

        if ($boundary->boundary_type === VoterFavoriteBoundary::TYPE_DISTRICT) {
            $variants = AudienceService::districtCodeVariants((string) $boundary->district_number);

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
