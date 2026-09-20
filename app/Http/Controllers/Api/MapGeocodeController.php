<?php

namespace App\Http\Controllers\Api;

use App\Services\DistrictLookupService;
use App\Services\GoogleCivicService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public geocode endpoint for the 3D map.
 *
 * Resolves either a browser location (lat/lng) or a typed street address /
 * ZIP code to a congressional district, so the map can fly directly to the
 * visitor's representatives. Nothing the visitor types is stored or logged
 * here; the lookup services only cache by a hash of the input.
 */
class MapGeocodeController
{
    public function __construct(
        private DistrictLookupService $districtLookup,
        private GoogleCivicService $googleCivic,
    ) {
    }

    /**
     * GET /api/v1/map/geocode?lat=...&lng=...
     * GET /api/v1/map/geocode?address=...
     */
    public function __invoke(Request $request): JsonResponse
    {
        $address = $request->query('address');

        if ($address !== null) {
            return $this->lookupAddress(is_string($address) ? trim($address) : '');
        }

        return $this->lookupCoordinates($request);
    }

    private function lookupCoordinates(Request $request): JsonResponse
    {
        $lat = $request->query('lat');
        $lng = $request->query('lng');

        if (! is_numeric($lat) || ! is_numeric($lng)) {
            return response()->json([
                'ok' => false,
                'error' => 'Provide an address or ZIP code, or valid numeric lat and lng query parameters.',
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

        return response()->json($this->resolved($result, 'location'));
    }

    private function lookupAddress(string $address): JsonResponse
    {
        if ($address === '' || mb_strlen($address) > 200) {
            return response()->json([
                'ok' => false,
                'error' => 'Enter a street address or a 5-digit ZIP code.',
            ], 422);
        }

        if ($this->isZip($address)) {
            return $this->lookupZip(substr($address, 0, 5));
        }

        $result = $this->districtLookup->lookup($address);

        if ($result === null || empty($result['state']) || empty($result['district_number'])) {
            return response()->json([
                'ok' => false,
                'error' => 'We could not match that address to a district. Include the street, city, state and ZIP, or try your location instead.',
            ], 404);
        }

        return response()->json($this->resolved($result, 'address'));
    }

    /**
     * A ZIP often spans more than one district, so it is never resolved by
     * picking the first match: one district is a confident answer, several
     * are returned for the visitor to choose from, and none is reported as
     * unresolved (including when the ZIP lookup isn't configured).
     */
    private function lookupZip(string $zip): JsonResponse
    {
        $districts = $this->googleCivic->districtsForZip($zip);

        if ($districts === []) {
            return response()->json([
                'ok' => false,
                'needs_address' => true,
                'error' => 'We couldn’t determine a district from that ZIP code alone. Enter your full street address for an exact match.',
            ], 404);
        }

        if (count($districts) === 1) {
            return response()->json($this->resolved($districts[0], 'zip'));
        }

        return response()->json([
            'ok' => true,
            'ambiguous' => true,
            'precision' => 'zip',
            'message' => "ZIP code {$zip} covers more than one congressional district. Enter your full street address for an exact match, or choose one to explore.",
            'candidates' => array_map(fn (array $d) => [
                'state' => $d['state'],
                'district_number' => $d['district_number'],
                'district_code' => $d['district_code'],
                'district_label' => $d['district_label'],
            ], $districts),
        ]);
    }

    private function isZip(string $input): bool
    {
        return preg_match('/^\d{5}(?:-\d{4})?$/', $input) === 1;
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function resolved(array $result, string $precision): array
    {
        return [
            'ok' => true,
            'state' => $result['state'],
            'district_number' => $result['district_number'],
            'district_code' => $result['district_code'],
            'district_label' => $result['district_label'],
            'matched' => true,
            // How the district was found: an address or the device location pins it
            // exactly; a ZIP that maps to a single district is a weaker claim.
            'precision' => $precision,
            'matched_address' => $precision === 'address' ? ($result['matched_address'] ?? null) : null,
        ];
    }
}
