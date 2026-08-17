<?php

namespace App\Http\Controllers\Api;

use App\Models\CityDemographic;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

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
     * Rows pulled per state before dedup — generous headroom over
     * CITIES_PER_STATE so multiple census_year rows per city still leave
     * enough unique cities after dedup, without loading a state's entire
     * demographic history into memory (see buildRegionData()).
     */
    private const ROWS_PER_STATE = 60;

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

        try {
            $data = Cache::remember("map_region_demographics_{$region}", 21_600, function () use ($region, $states) {
                return $this->buildRegionData($region, $states);
            });
        } catch (\Throwable $e) {
            Log::error('MapRegionDemographicsController: failed to build region data', [
                'region' => $region,
                'error'  => $e->getMessage(),
            ]);

            return response()->json([
                'region' => $region,
                'states' => array_map(fn (string $state) => ['state' => $state, 'cities' => []], $states),
            ]);
        }

        return response()->json($data);
    }

    /**
     * @param array<int, string> $states
     * @return array<string, mixed>
     */
    private function buildRegionData(string $region, array $states): array
    {
        // Bounded per-state instead of one whereIn(...)->get() across the
        // whole region — a region can span a dozen states, and pulling every
        // census_year row for every city in all of them into memory at once
        // just to keep the top 8 per state is unnecessary memory pressure.
        $result = [];
        foreach ($states as $state) {
            $rows = CityDemographic::query()
                ->where('state', $state)
                ->orderByDesc('census_year')
                ->orderByDesc('population')
                ->limit(self::ROWS_PER_STATE)
                ->get();

            $seen = [];
            $cities = [];
            foreach ($rows as $city) {
                // Data is upserted per (state, city_name, census_year) — keep
                // only the most recent census_year per city (rows already
                // sorted for it).
                if (isset($seen[$city->city_name])) {
                    continue;
                }
                $seen[$city->city_name] = true;

                $cities[] = [
                    'city' => $city->city_name,
                    'district_number' => $city->district_number,
                    'district_code' => $city->district_code,
                    'population' => $city->population,
                    'poverty_rate' => $city->poverty_rate !== null ? (float) $city->poverty_rate : null,
                    'pct_bachelors_or_higher' => $city->pct_bachelors_or_higher !== null ? (float) $city->pct_bachelors_or_higher : null,
                    'median_household_income' => $city->median_household_income,
                ];

                if (count($cities) >= self::CITIES_PER_STATE) {
                    break;
                }
            }

            $result[] = ['state' => $state, 'cities' => $cities];
        }

        return [
            'region' => $region,
            'states' => $result,
        ];
    }
}
