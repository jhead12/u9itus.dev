<?php

namespace App\Http\Controllers\Api;

use App\Jobs\DispatchCensusSyncWorkflow;
use App\Models\CityDemographic;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Public endpoint powering the map's politician drawer city view (Economy
 * tab) — the most recent Census ACS demographics for a single city. Same
 * source table as MapRegionDemographicsController, just scoped to one city
 * instead of a whole region.
 *
 * When no row exists yet for the requested city, a sync of
 * sync-census-demographics.yml is dispatched for that state (deduped —
 * see DispatchCensusSyncWorkflow) so the data backfills without anyone
 * having to notice and trigger it manually.
 */
class MapCityCensusController
{
    /**
     * GET /api/v1/map/city-census?state=CA&city=Anaheim
     */
    public function __invoke(Request $request): JsonResponse
    {
        $state = strtoupper(trim((string) $request->query('state', '')));
        $city = trim((string) $request->query('city', ''));

        if (! preg_match('/^[A-Z]{2}$/', $state) || $city === '') {
            return response()->json(['error' => 'Provide a two-letter state and city.'], 422);
        }

        $cacheKey = 'map_city_census:' . $state . '|' . strtolower($city);

        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return response()->json($cached);
        }

        $row = CityDemographic::query()
            ->where('state', $state)
            ->where('city_name', $city)
            ->orderByDesc('census_year')
            ->first();

        if (! $row) {
            DispatchCensusSyncWorkflow::dispatch($state);

            return response()->json([
                'has_data' => false,
                'reason' => 'not_yet_synced',
                'city' => $city,
                'state' => $state,
            ]);
        }

        $data = [
            'has_data' => true,
            'city' => $row->city_name,
            'state' => $row->state,
            'population' => $row->population,
            'poverty_rate' => $row->poverty_rate !== null ? (float) $row->poverty_rate : null,
            'pct_bachelors_or_higher' => $row->pct_bachelors_or_higher !== null ? (float) $row->pct_bachelors_or_higher : null,
            'median_household_income' => $row->median_household_income,
            'census_year' => $row->census_year,
            'source' => $row->source,
        ];

        // Same TTL as the region panel's demographics cache — this data only
        // changes on the weekly sync.
        Cache::put($cacheKey, $data, 21_600);

        return response()->json($data);
    }
}
