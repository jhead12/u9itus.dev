<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/** Map-only lookups: cache district metadata, never street addresses or coordinates. */
class MapDistrictLookupService extends DistrictLookupService
{
    private function congress(): int
    {
        $config = Cache::remember('district_config', 3600, fn () => DB::table('district_config')->orderByDesc('synced_at')->first());
        return (int) ($config->congress_number ?? 119);
    }

    private function inputHash(string $input): string
    {
        return hash_hmac('sha256', strtolower(trim($input)), (string) config('app.key'));
    }

    public function lookup(string $address): ?array
    {
        return $this->census(['address' => trim($address)], $this->baseUrl, trim($address), false);
    }

    public function lookupByCoordinates(float $lat, float $lng): ?array
    {
        return $this->census(['x' => $lng, 'y' => $lat], $this->coordinatesUrl, "$lat,$lng", true);
    }

    private function census(array $parameters, string $url, string $input, bool $coordinates): ?array
    {
        $congress = $this->congress();
        $hash = $this->inputHash($input);
        $cacheKey = "map.district.v1.$congress.$hash";
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) return $cached;

        try {
            // Current_Current may already contain the NEXT Congress; use the
            // ACS vintage for the seated Congress, matching the map layer.
            $year = 1787 + 2 * $congress;
            $response = Http::timeout(15)->get($url, $parameters + [
                'benchmark' => 'Public_AR_Current',
                'vintage' => "ACS{$year}_Current",
                'format' => 'json',
            ]);
            if (! $response->successful()) {
                Log::warning('Map Census lookup failed', ['input_hash' => $hash, 'status' => $response->status()]);
                return null;
            }
            $match = $coordinates ? $response->json('result') : $response->json('result.addressMatches.0');
            $geographies = (array) ($match['geographies'] ?? []);
            // Reject mismatched vintages rather than display another Congress's district.
            $districtGeographies = array_filter($geographies, fn ($key) => preg_match('/^'.$congress.'(?:st|nd|rd|th) Congressional Districts$/i', $key), ARRAY_FILTER_USE_KEY);
            $state = $coordinates ? $this->extractStateFromGeographies($geographies) : strtoupper((string) ($match['addressComponents']['state'] ?? ''));
            $number = $this->extractDistrictNumber($districtGeographies);
            if ($state === '' || $number === null) return null;
            $result = [
                'state' => $state, 'district_number' => $number,
                'district_code' => $this->buildDistrictCode($state, $number),
                'district_label' => $this->buildDistrictLabel($state, $number),
                'boundary_congress' => $congress,
            ];
            Cache::put($cacheKey, $result, now()->addHours(12));
            return $result;
        } catch (\Throwable $e) {
            // Exception messages can include the request URL and raw location.
            Log::warning('Map Census lookup unavailable', ['input_hash' => $hash, 'failure_type' => get_class($e)]);
            return null;
        }
    }

    public function districtsForZip(string $zip): array
    {
        $key = config('services.google.civic_api_key');
        if (! $key) return [];
        $hash = $this->inputHash($zip);
        $cacheKey = "map.zip.v1.$hash";
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) return $cached;
        try {
            $response = Http::timeout(15)->get('https://civicinfo.googleapis.com/civicinfo/v2/divisionsByAddress', ['address' => $zip, 'key' => $key]);
            if (! $response->successful()) {
                Log::warning('Map ZIP lookup failed', ['input_hash' => $hash, 'status' => $response->status()]);
                return [];
            }
            $districts = [];
            foreach (array_keys((array) $response->json('divisions')) as $division) {
                if (! preg_match('~^ocd-division/country:us/state:([a-z]{2})/cd:(\d+)$~i', $division, $m)) continue;
                $state = strtoupper($m[1]);
                $number = (string) (int) $m[2];
                $code = $this->buildDistrictCode($state, $number);
                $districts[$code] = ['state' => $state, 'district_number' => $number, 'district_code' => $code, 'district_label' => $this->buildDistrictLabel($state, $number)];
            }
            $districts = array_values($districts);
            if ($districts !== []) Cache::put($cacheKey, $districts, now()->addHours(12));
            return $districts;
        } catch (\Throwable $e) {
            Log::warning('Map ZIP lookup unavailable', ['input_hash' => $hash, 'failure_type' => get_class($e)]);
            return [];
        }
    }
}
