<?php

namespace App\Http\Controllers\Api;

use App\Services\DistrictLookupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public reverse-geocode endpoint for the 3D map.
 *
 * Given a latitude/longitude, it asks the US Census Geocoder for the
 * containing congressional district and returns state + district metadata
 * so the map can fly directly to the user's representatives.
 */
class MapGeocodeController
{
    public function __construct(private DistrictLookupService $districtLookup)
    {
    }

    /**
     * GET /api/v1/map/geocode?lat=...&lng=...
     */
    public function __invoke(Request $request): JsonResponse
    {
        $lat = $request->query('lat');
        $lng = $request->query('lng');

        if (! is_numeric($lat) || ! is_numeric($lng)) {
            return response()->json([
                'ok' => false,
                'error' => 'Provide valid numeric lat and lng query parameters.',
            ], 422);
        }

        $lat = (float) $lat;
        $lng = (float) $lng;

        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            return response()->json([
                'ok' => false,
                'error' => 'Latitude and longitude are out of valid range.',
            ], 422);
        }

        $result = $this->districtLookup->lookupByCoordinates($lat, $lng);

        if ($result === null) {
            return response()->json([
                'ok' => false,
                'error' => 'We could not determine a congressional district for this location. Try an address search instead.',
            ], 404);
        }

        return response()->json([
            'ok' => true,
            'state' => $result['state'],
            'district_number' => $result['district_number'],
            'district_code' => $result['district_code'],
            'district_label' => $result['district_label'],
            'matched' => true,
        ]);
    }
}
