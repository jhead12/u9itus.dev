<?php

namespace App\Services\Marketing;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Resolves a US ZIP code to a {lat, lng} centroid so the AudienceService can
 * apply citizen campaigns' target_zip_radius — the column that has been
 * stored-but-unapplied since citizen_campaigns shipped.
 *
 * Uses the free US Census Geocoder (the same provider DistrictLookupService
 * uses, no key required) by geocoding "<zip> USA" and reading the matched
 * coordinate. Results are cached indefinitely (zip centroids are effectively
 * static) so the per-zip API cost is paid once.
 *
 * Failures degrade to null → the AudienceService falls back to exact-zip
 * matching for that campaign, matching prior behavior rather than dropping
 * the campaign entirely.
 */
class ZipCentroidService
{
    protected string $geocodeUrl = 'https://geocoding.geo.census.gov/geocoder/geographies/onelineaddress';

    public function centroid(string $zip): ?array
    {
        $zip = trim($zip);
        if ($zip === '') {
            return null;
        }

        return Cache::rememberForever('zip.centroid.' . $zip, function () use ($zip): ?array {
            try {
                $response = Http::timeout(10)->get($this->geocodeUrl, [
                    'address'   => "{$zip}, USA",
                    'benchmark' => 'Public_AR_Current',
                    'format'    => 'json',
                ]);

                if (! $response->successful()) {
                    return null;
                }

                $match = $response->json('result.addressMatches.0');
                $x = data_get($match, 'coordinates.x'); // longitude
                $y = data_get($match, 'coordinates.y'); // latitude

                if ($x === null || $y === null) {
                    return null;
                }

                return ['lat' => (float) $y, 'lng' => (float) $x];
            } catch (\Throwable $e) {
                Log::warning('ZipCentroidService: geocode failed', [
                    'zip'   => $zip,
                    'error' => $e->getMessage(),
                ]);
                return null;
            }
        });
    }

    /**
     * Great-circle distance in miles between two {lat,lng} points. Haversine
     * with the 3959mi Earth radius — sufficient for radius-matching a voter's
     * zip centroid against a campaign's target_zip centroid.
     */
    public function distanceMiles(array $a, array $b): float
    {
        $lat1 = deg2rad((float) $a['lat']);
        $lat2 = deg2rad((float) $b['lat']);
        $dLat = deg2rad((float) $b['lat'] - (float) $a['lat']);
        $dLng = deg2rad((float) $b['lng'] - (float) $a['lng']);

        $h = sin($dLat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($dLng / 2) ** 2;

        return 2 * 3959.0 * asin(sqrt($h));
    }
}