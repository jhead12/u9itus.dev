<?php

namespace App\Http\Controllers\Api;

use App\Models\CityDemographic;
use App\Services\DistrictNewsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public endpoint returning election/civic-administration news for a
 * clicked congressional district (polling place changes, ballot measures,
 * redistricting, etc.) — a locality-scoped sibling to
 * MapCandidateOverviewController's name-scoped candidate news.
 */
class MapDistrictNewsController
{
    /** Top N cities (by population) used as the search locality. */
    private const MAX_LOCALITIES = 3;

    public function __construct(private DistrictNewsService $newsService)
    {
    }

    /**
     * GET /api/v1/map/district-news?district_code=TX-12&district_label=District 12&state_name=Texas
     */
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'district_code' => ['required', 'string', 'regex:/^[A-Z]{2}-(\d{1,2}|AL)$/i'],
            'district_label' => ['nullable', 'string', 'max:120'],
            'state_name' => ['nullable', 'string', 'max:60'],
        ]);

        $districtCode = strtoupper($data['district_code']);
        $state = explode('-', $districtCode)[0];

        $localities = CityDemographic::query()
            ->where('district_code', $districtCode)
            ->orderByDesc('population')
            ->limit(self::MAX_LOCALITIES)
            ->pluck('city_name')
            ->all();

        if ($localities === [] && ! empty($data['state_name']) && ! empty($data['district_label'])) {
            $localities = [$data['state_name'] . ' ' . $data['district_label']];
        }

        if ($localities === []) {
            return response()->json(['news' => []]);
        }

        // Fetch a wider pool than we display — same "cast wide via RSS, filter
        // to verified for display" split MapCandidateOverviewController uses
        // for candidate news, since not every row in the cache window will
        // have passed the locality+keyword relevance gate.
        $articles = $this->newsService->getForDistrict($districtCode, $state, $localities, 30);

        return response()->json([
            'news' => $articles
                ->where('verification_status', 'verified')
                ->sortByDesc('published_at')
                ->take(8)
                ->values()
                ->map(fn ($item) => [
                    'headline' => $item->headline,
                    'source_name' => $item->source_name,
                    'source_url' => $item->source_url,
                    'snippet' => $item->snippet,
                    'published_at' => optional($item->published_at)?->toIso8601String(),
                    'provider' => $item->provider,
                ]),
        ]);
    }
}
