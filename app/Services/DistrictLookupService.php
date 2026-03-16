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
            $resolved = null;

            try {
                $response = Http::timeout(10)->get($this->baseUrl, [
                    'address' => $normalizedAddress,
                    'benchmark' => 'Public_AR_Current',
                    'vintage' => 'Current_Current',
                    'format' => 'json',
                ]);

                if ($response->successful()) {
                    $match = $response->json('result.addressMatches.0');
                    if (is_array($match) && ! empty($match)) {
                        $state = strtoupper((string) data_get($match, 'addressComponents.state', ''));
                        $districtNumber = $this->extractDistrictNumber((array) data_get($match, 'geographies', []));

                        $resolved = [
                            'input_address' => $normalizedAddress,
                            'matched_address' => (string) ($match['matchedAddress'] ?? $normalizedAddress),
                            'state' => $state,
                            'district_number' => $districtNumber,
                            'district_code' => $this->buildDistrictCode($state, $districtNumber),
                            'district_label' => $this->buildDistrictLabel($state, $districtNumber),
                        ];
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('DistrictLookupService lookup failed', [
                    'address' => $normalizedAddress,
                    'error' => $e->getMessage(),
                ]);
            }

            return $resolved;
        });
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
     * @param array<string, mixed> $geographies
     * @return array<string, mixed>|null
     */
    protected function firstCongressionalGeoRow(array $geographies): ?array
    {
        foreach ($geographies as $layerName => $rows) {
            if (stripos((string) $layerName, 'congressional') === false) {
                continue;
            }

            if (is_array($rows) && ! empty($rows) && is_array($rows[0] ?? null)) {
                /** @var array<string, mixed> */
                return $rows[0];
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $row
     */
    protected function extractDistrictFromNumericKeys(array $row): ?string
    {
        foreach (['CD119FP', 'CD118FP', 'CD117FP', 'CD116FP', 'DISTRICT', 'district'] as $key) {
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
