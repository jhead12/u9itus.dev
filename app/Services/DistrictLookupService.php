<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Resolves a US street address to congressional district metadata
 * using the US Census Geocoder.
 */
class DistrictLookupService
{
    protected string $baseUrl = 'https://geocoding.geo.census.gov/geocoder/geographies/onelineaddress';

    protected string $coordinatesUrl = 'https://geocoding.geo.census.gov/geocoder/geographies/coordinates';

    /**
     * Resolve a lat/lng into district metadata. Used by both the map's
     * reverse-geocode endpoint (MapGeocodeController) and the census
     * demographics sync (which precomputes each city's district instead of
     * resolving it live on every click) — kept in-process rather than one
     * calling the other over HTTP, which is fragile from a console command
     * (no guarantee the app is reachable at its own APP_URL when running).
     *
     * @return array{state:string, district_number:string, district_code:?string, district_label:?string}|null
     */
    public function lookupByCoordinates(float $lat, float $lng): ?array
    {
        // Round to ~110m precision so nearby points share a cached result.
        $latRounded = round($lat, 3);
        $lngRounded = round($lng, 3);
        $cacheKey = "district.lookup_coords.{$latRounded}.{$lngRounded}";

        return Cache::remember($cacheKey, now()->addDay(), function () use ($lat, $lng) {
            try {
                $response = Http::timeout(10)->get($this->coordinatesUrl, [
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

                $state = $this->extractStateFromGeographies($geographies);
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
                Log::warning('DistrictLookupService coordinates lookup failed', [
                    'lat' => $lat,
                    'lng' => $lng,
                    'error' => $e->getMessage(),
                ]);

                return null;
            }
        });
    }

    /**
     * Pull the two-letter state abbreviation from a coordinates-lookup response.
     *
     * @param array<string, mixed> $geographies
     */
    protected function extractStateFromGeographies(array $geographies): string
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
    protected function fipsToAbbreviation(string $fips): string
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
     * Resolve address into district metadata.
     *
     * @return array<string, mixed>|null
     */
    public function lookup(string $address): ?array
    {
        $normalizedAddress = trim($address);

        if ($normalizedAddress === '') {
            return null;
        }

        $cacheKey = 'district.lookup.' . md5(strtolower($normalizedAddress));

        return Cache::remember($cacheKey, now()->addHours(12), function () use ($normalizedAddress) {
            $resolved = $this->lookupViaCensus($normalizedAddress);

            if ($resolved !== null) {
                $resolved['source'] = 'census_geocoder';

                return $resolved;
            }

            $fallback = $this->lookupViaGoogleCivic($normalizedAddress);
            if ($fallback !== null) {
                $fallback['source'] = 'google_civic';
            }

            return $fallback;
        });
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function lookupViaCensus(string $normalizedAddress): ?array
    {
        $resolved = null;

        try {
            $response = Http::timeout(10)->get($this->baseUrl, [
                'address'   => $normalizedAddress,
                'benchmark' => 'Public_AR_Current',
                'vintage'   => 'Census2020_Current',
                'format'    => 'json',
            ]);

            if ($response->successful()) {
                $match = $response->json('result.addressMatches.0');
                if (is_array($match) && ! empty($match)) {
                    $geographies = (array) data_get($match, 'geographies', []);
                    $state = strtoupper((string) data_get($match, 'addressComponents.state', ''));
                    $districtNumber = $this->extractDistrictNumber($geographies);

                    $resolved = [
                        'input_address' => $normalizedAddress,
                        'matched_address' => (string) ($match['matchedAddress'] ?? $normalizedAddress),
                        'state' => $state,
                        'district_number' => $districtNumber,
                        'district_code' => $this->buildDistrictCode($state, $districtNumber),
                        'district_label' => $this->buildDistrictLabel($state, $districtNumber),
                        // State legislative chambers, so district-lookup can surface
                        // state candidates alongside the federal congressional match.
                        'sldl_district' => $this->extractStateLegislativeDistrict($geographies, 'lower'),
                        'sldu_district' => $this->extractStateLegislativeDistrict($geographies, 'upper'),
                    ];
                }
            }
        } catch (\Throwable $e) {
            Log::warning('DistrictLookupService Census lookup failed', [
                'address' => $normalizedAddress,
                'error' => $e->getMessage(),
            ]);
        }

        return $resolved;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function lookupViaGoogleCivic(string $normalizedAddress): ?array
    {
        try {
            /** @var GoogleCivicService $googleCivic */
            $googleCivic = app(GoogleCivicService::class);
            if (! $googleCivic->isConfigured()) {
                return null;
            }

            return $googleCivic->resolveDistrictByAddress($normalizedAddress);
        } catch (\Throwable $e) {
            Log::warning('DistrictLookupService Google Civic fallback failed', [
                'address' => $normalizedAddress,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @param array<string, mixed> $geographies
     */
    protected function extractDistrictNumber(array $geographies): ?string
    {
        $row = $this->firstCongressionalGeoRow($geographies);

        if ($row === null) {
            return null;
        }

        $fromKeys = $this->extractDistrictFromNumericKeys($row);
        if ($fromKeys !== null) {
            return $fromKeys;
        }

        return $this->extractDistrictFromNameFields($row);
    }

    /**
     * Prefers the layer with the highest Congress number so redistricted
     * addresses resolve to the most current district assignment.
     *
     * @param array<string, mixed> $geographies
     * @return array<string, mixed>|null
     */
    protected function firstCongressionalGeoRow(array $geographies): ?array
    {
        $bestRow      = null;
        $bestCongress = -1;

        foreach ($geographies as $layerName => $rows) {
            if (stripos((string) $layerName, 'congressional') === false) {
                continue;
            }

            if (! is_array($rows) || empty($rows) || ! is_array($rows[0] ?? null)) {
                continue;
            }

            // Extract the Congress session number from the layer name
            // e.g. "119th Congressional Districts" → 119
            if (preg_match('/^(\d+)/i', (string) $layerName, $m)) {
                $num = (int) $m[1];
                if ($num > $bestCongress) {
                    $bestCongress = $num;
                    /** @var array<string, mixed> */
                    $bestRow = $rows[0];
                }
            } elseif ($bestRow === null) {
                /** @var array<string, mixed> */
                $bestRow = $rows[0];
            }
        }

        return $bestRow;
    }

    /**
     * @param array<string, mixed> $geographies
     */
    protected function extractStateLegislativeDistrict(array $geographies, string $chamber): ?string
    {
        $row = $this->firstStateLegislativeGeoRow($geographies, $chamber);

        if ($row === null) {
            return null;
        }

        $fromKeys = $this->extractDistrictFromKeyPrefix($row, $chamber === 'upper' ? 'SLDU' : 'SLDL');
        if ($fromKeys !== null) {
            return $fromKeys;
        }

        return $this->extractDistrictFromNameFields($row);
    }

    /**
     * @param array<string, mixed> $geographies
     * @return array<string, mixed>|null
     */
    protected function firstStateLegislativeGeoRow(array $geographies, string $chamber): ?array
    {
        foreach ($geographies as $layerName => $rows) {
            $layerName = (string) $layerName;
            if (stripos($layerName, 'state legislative') === false || stripos($layerName, $chamber) === false) {
                continue;
            }

            if (! is_array($rows) || empty($rows) || ! is_array($rows[0] ?? null)) {
                continue;
            }

            /** @var array<string, mixed> */
            return $rows[0];
        }

        return null;
    }

    /**
     * @param array<string, mixed> $row
     */
    protected function extractDistrictFromKeyPrefix(array $row, string $prefix): ?string
    {
        $candidateKeys = array_filter(
            array_keys($row),
            fn ($key) => preg_match('/^'.preg_quote($prefix, '/').'\d*(FP|ST)?$/i', (string) $key) === 1,
        );

        foreach ($candidateKeys as $key) {
            $candidate = trim((string) $row[$key]);
            if ($candidate === '' || preg_match('/^\d+$/', $candidate) !== 1) {
                continue;
            }

            return (string) ((int) $candidate);
        }

        return null;
    }

    /**
     * @param array<string, mixed> $row
     */
    protected function extractDistrictFromNumericKeys(array $row): ?string
    {
        // Match any CD<congress-number>[FP] key dynamically — the Census
        // Geocoder has returned district fields under Congress sessions older
        // than the hardcoded list here for at-large states, silently failing
        // the lookup. See MapGeocodeController::extractDistrictNumber() for
        // the same fix.
        $candidateKeys = array_filter(
            array_keys($row),
            fn ($key) => preg_match('/^CD\d+(FP)?$/i', (string) $key) === 1,
        );
        usort($candidateKeys, fn ($a, $b) => (int) preg_replace('/\D/', '', $b) <=> (int) preg_replace('/\D/', '', $a));

        foreach ([...$candidateKeys, 'DISTRICT', 'district'] as $key) {
            if (! isset($row[$key])) {
                continue;
            }

            $candidate = trim((string) $row[$key]);
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

        return null;
    }

    /**
     * @param array<string, mixed> $row
     */
    protected function extractDistrictFromNameFields(array $row): ?string
    {
        foreach (['NAME', 'BASENAME', 'NAMELSAD'] as $nameKey) {
            $name = (string) ($row[$nameKey] ?? '');
            if ($name === '') {
                continue;
            }

            if (preg_match('/district\s+(\d+)/i', $name, $matches) === 1) {
                return (string) ((int) $matches[1]);
            }
        }

        return null;
    }

    protected function buildDistrictCode(string $state, ?string $districtNumber): ?string
    {
        if ($state === '' || $districtNumber === null) {
            return null;
        }

        if (strtoupper($districtNumber) === 'AL') {
            return $state . '-AL';
        }

        return sprintf('%s-%02d', $state, (int) $districtNumber);
    }

    protected function buildDistrictLabel(string $state, ?string $districtNumber): ?string
    {
        if ($state === '' || $districtNumber === null) {
            return null;
        }

        if (strtoupper($districtNumber) === 'AL') {
            return $state . ' At-Large Congressional District';
        }

        $num = (int) $districtNumber;

        return sprintf('%s %s Congressional District', $state, $this->ordinal($num));
    }

    protected function ordinal(int $number): string
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
