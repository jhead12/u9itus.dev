<?php

namespace App\Http\Controllers\Api;

use App\Models\CityDemographic;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Public endpoint powering the map's region panel — cities within a region
 * plus their Census ACS demographics (poverty rate, educational attainment,
 * median household income). No authentication required — voter-facing civic
 * data, same trust model as MapStateCandidatesController.
 */
class MapRegionDemographicsController
{
    /**
     * Mirrors REGIONS in resources/js/map/config/constants.js.
     */
    private const REGIONS = [
        'Northeast' => ['CT', 'DE', 'ME', 'MD', 'MA', 'NH', 'NJ', 'NY', 'PA', 'RI', 'VT'],
        'Southeast' => ['AL', 'AR', 'FL', 'GA', 'KY', 'LA', 'MS', 'NC', 'SC', 'TN', 'VA', 'WV'],
        'Midwest' => ['IL', 'IN', 'IA', 'KS', 'MI', 'MN', 'MO', 'NE', 'ND', 'OH', 'SD', 'WI'],
        'Southwest' => ['AZ', 'CO', 'NV', 'NM', 'OK', 'TX', 'UT'],
        'West' => ['AK', 'CA', 'HI', 'ID', 'MT', 'OR', 'WA', 'WY'],
    ];

    /** Max cities returned per state, to keep large-region payloads reasonable. */
    private const CITIES_PER_STATE = 8;

    /**
     * GET /api/v1/map/region-demographics?region=Northeast
     */
    public function __invoke(Request $request): JsonResponse
    {
        $region = trim((string) $request->query('region', ''));
        $states = self::REGIONS[$region] ?? null;

        if ($states === null) {
            return response()->json(['error' => 'Unknown region.'], 422);
        }

        $data = Cache::remember("map_region_demographics_{$region}", 21_600, function () use ($region, $states) {
            return $this->buildRegionData($region, $states);
        });

        return response()->json($data);
    }

    /**
     * @param array<int, string> $states
     * @return array<string, mixed>
     */
    private function buildRegionData(string $region, array $states): array
    {
        $cities = CityDemographic::query()
            ->whereIn('state', $states)
            ->orderByDesc('census_year')
            ->orderByDesc('population')
            ->get();

        $byState = [];
        foreach ($cities as $city) {
            // Data is upserted per (state, city_name, census_year) — keep only
            // the most recent census_year per city (rows already sorted for it).
            $seenKey = $city->state . '|' . $city->city_name;
            if (isset($byState[$city->state]['_seen'][$seenKey])) {
                continue;
            }
            $byState[$city->state]['_seen'][$seenKey] = true;
            $byState[$city->state]['cities'][] = [
                'city' => $city->city_name,
                'population' => $city->population,
                'poverty_rate' => $city->poverty_rate !== null ? (float) $city->poverty_rate : null,
                'pct_bachelors_or_higher' => $city->pct_bachelors_or_higher !== null ? (float) $city->pct_bachelors_or_higher : null,
                'median_household_income' => $city->median_household_income,
            ];
        }

        $result = [];
        foreach ($states as $state) {
            $stateCities = $byState[$state]['cities'] ?? [];
            $result[] = [
                'state' => $state,
                'cities' => array_slice($stateCities, 0, self::CITIES_PER_STATE),
            ];
        }

        return [
            'region' => $region,
            'states' => $result,
        ];
    }
}
