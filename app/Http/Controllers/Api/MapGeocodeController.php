<?php

namespace App\Http\Controllers\Api;

use App\Services\DistrictLookupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Public reverse-geocode endpoint for the 3D map.
 *
 * Given a latitude/longitude, it asks the US Census Geocoder for the
 * containing congressional district and returns state + district metadata
 * so the map can fly directly to the user's representatives.
 */
class MapGeocodeController
{
    /**
     * Census reverse-geocode endpoint.
     */
    private const CENSUS_COORDINATES_URL =
        'https://geocoding.geo.census.gov/geocoder/geographies/coordinates';

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

        // Round to 3 decimal places (~110m precision) for cache key deduplification
        // so nearby taps share a cached result without sacrificing accuracy for
        // district lookups (congressional districts are never smaller than a city block).
        $latRounded = round($lat, 3);
        $lngRounded = round($lng, 3);
        $cacheKey = "map_geocode:{$latRounded}:{$lngRounded}";

        $result = Cache::remember($cacheKey, 86400, function () use ($lat, $lng) {
            return $this->lookupCoordinates($lat, $lng);
        });

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

    /**
     * Resolve lat/lng to state + district using the Census Geocoder.
     *
     * @return array<string, mixed>|null
     */
    private function lookupCoordinates(float $lat, float $lng): ?array
    {
        try {
            $response = Http::timeout(10)->get(self::CENSUS_COORDINATES_URL, [
                'x' => $lng,
                'y' => $lat,
                'benchmark' => 'Public_AR_Current',
                'vintage' => 'Census2020_Current',
                'format' => 'json',
            ]);

            if (! $response->successful()) {
                return null;
            }

            $geographies = $response->json('result.geographies');
            if (! is_array($geographies) || empty($geographies)) {
                return null;
            }

            $state = $this->extractState($geographies);
            $districtNumber = $this->extractDistrictNumber($geographies);

            if ($state === '' || $districtNumber === null) {
                return null;
            }

            return [
                'state' => $state,
                'district_number' => $districtNumber,
                'district_code' => $this->buildDistrictCode($state, $districtNumber),
                'district_label' => $this->buildDistrictLabel($state, $districtNumber),
            ];
        } catch (\Throwable $e) {
            Log::warning('MapGeocodeController Census lookup failed', [
                'lat' => $lat,
                'lng' => $lng,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Pull the two-letter state abbreviation from the Census response.
     */
    private function extractState(array $geographies): string
    {
        $rows = $geographies['States'] ?? [];
        if (is_array($rows) && ! empty($rows) && is_array($rows[0])) {
            $stateFips = trim((string) ($rows[0]['STATE'] ?? $rows[0]['STATEFP'] ?? ''));
            if ($stateFips !== '') {
                return $this->fipsToAbbreviation($stateFips);
            }
        }

        return '';
    }

    /**
     * Convert a FIPS state code to its two-letter abbreviation.
     */
    private function fipsToAbbreviation(string $fips): string
    {
        $map = [
            '01' => 'AL', '02' => 'AK', '04' => 'AZ', '05' => 'AR', '06' => 'CA',
            '08' => 'CO', '09' => 'CT', '10' => 'DE', '11' => 'DC', '12' => 'FL',
            '13' => 'GA', '15' => 'HI', '16' => 'ID', '17' => 'IL', '18' => 'IN',
            '19' => 'IA', '20' => 'KS', '21' => 'KY', '22' => 'LA', '23' => 'ME',
            '24' => 'MD', '25' => 'MA', '26' => 'MI', '27' => 'MN', '28' => 'MS',
            '29' => 'MO', '30' => 'MT', '31' => 'NE', '32' => 'NV', '33' => 'NH',
            '34' => 'NJ', '35' => 'NM', '36' => 'NY', '37' => 'NC', '38' => 'ND',
            '39' => 'OH', '40' => 'OK', '41' => 'OR', '42' => 'PA', '44' => 'RI',
            '45' => 'SC', '46' => 'SD', '47' => 'TN', '48' => 'TX', '49' => 'UT',
            '50' => 'VT', '51' => 'VA', '53' => 'WA', '54' => 'WV', '55' => 'WI',
            '56' => 'WY',
        ];

        return $map[str_pad($fips, 2, '0', STR_PAD_LEFT)] ?? '';
    }

    /**
     * Extract the district number from the first congressional geography layer.
     */
    private function extractDistrictNumber(array $geographies): ?string
    {
        $bestRow = null;
        $bestCongress = -1;

        foreach ($geographies as $layerName => $rows) {
            if (stripos((string) $layerName, 'congressional') === false) {
                continue;
            }

            if (! is_array($rows) || empty($rows) || ! is_array($rows[0] ?? null)) {
                continue;
            }

            if (preg_match('/^(\d+)/i', (string) $layerName, $m)) {
                $num = (int) $m[1];
                if ($num > $bestCongress) {
                    $bestCongress = $num;
                    $bestRow = $rows[0];
                }
            } elseif ($bestRow === null) {
                $bestRow = $rows[0];
            }
        }

        if (! is_array($bestRow)) {
            return null;
        }

        foreach (['CD119', 'CD118', 'CD117', 'CD119FP', 'CD118FP', 'CD117FP', 'DISTRICT', 'district'] as $key) {
            $candidate = trim((string) ($bestRow[$key] ?? ''));
            if ($candidate === '') {
                continue;
            }
            if ($candidate === '00' || strtoupper($candidate) === 'AL') {
                return 'AL';
            }
            if (preg_match('/^\d+$/', $candidate) === 1) {
                return (string) ((int) $candidate);
            }
        }

        foreach (['NAME', 'BASENAME', 'NAMELSAD'] as $nameKey) {
            $name = trim((string) ($bestRow[$nameKey] ?? ''));
            if (preg_match('/district\s+(\d+)/i', $name, $matches) === 1) {
                return (string) ((int) $matches[1]);
            }
        }

        return null;
    }

    private function buildDistrictCode(string $state, string $districtNumber): string
    {
        if (strtoupper($districtNumber) === 'AL') {
            return $state . '-AL';
        }

        return sprintf('%s-%02d', $state, (int) $districtNumber);
    }

    private function buildDistrictLabel(string $state, string $districtNumber): string
    {
        if (strtoupper($districtNumber) === 'AL') {
            return $state . ' At-Large Congressional District';
        }

        $num = (int) $districtNumber;

        return sprintf('%s %s Congressional District', $state, $this->ordinal($num));
    }

    private function ordinal(int $number): string
    {
        $mod100 = $number % 100;
        if ($mod100 >= 11 && $mod100 <= 13) {
            return $number . 'th';
        }

        return match ($number % 10) {
            1 => $number . 'st',
            2 => $number . 'nd',
            3 => $number . 'rd',
            default => $number . 'th',
        };
    }
}
