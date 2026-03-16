<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Google Civic Information API Integration Service
 *
 * Fetches elected officials, election information, and polling locations
 * for a given address. Perfect for local government representatives.
 *
 * API Documentation: https://developers.google.com/civic-information/docs/v2
 * Rate Limits: 10,000 queries per day (free tier)
 *
 * Setup:
 * 1. Create a Google Cloud API key at https://console.cloud.google.com
 * 2. Enable Civic Information API
 * 3. Add to .env: GOOGLE_CIVIC_API_KEY=your_api_key_here
 */
class GoogleCivicService
{
    protected string $baseUrl = 'https://www.googleapis.com/civicinfo/v2';
    protected ?string $apiKey;
    protected int $cacheDuration = 604800; // 7 days (not often changed)

    public function __construct()
    {
        $this->apiKey = config('services.google.civic_api_key');
    }

    /**
     * Check if service is properly configured
     */
    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }

    /**
    * Get elected officials for a given address
    *
     * Returns federal, state, and local representatives for an address.
    *
     * @param string $address Full address (e.g., "123 Main St, Austin, TX 78701")
     * @return array|null Array of officials or null on error
     */
    public function getOfficialsByAddress(string $address): ?array
    {
        if (!$this->isConfigured()) {
            Log::warning('GoogleCivicService: API key not configured');
            return null;
        }

        $cacheKey = 'google_civic.officials.' . md5(strtolower($address));

        return Cache::remember($cacheKey, $this->cacheDuration, function () use ($address) {
            try {
                $response = Http::timeout(10)
                    ->get("{$this->baseUrl}/representatives", [
                        'address' => $address,
                        'key' => $this->apiKey,
                    ]);

                if (!$response->successful()) {
                    Log::warning('GoogleCivicService: API request failed', [
                        'status' => $response->status(),
                        'address' => $address,
                    ]);
                    return null;
                }

                $data = $response->json();

                return $this->parseOfficials($data);

            } catch (\Exception $e) {
                Log::error('GoogleCivicService: Failed to fetch officials', [
                    'address' => $address,
                    'error' => $e->getMessage(),
                ]);
                return null;
            }
        });
    }

    /**
     * Resolve district metadata from Google Civic representatives endpoint.
     *
     * Useful fallback when geocoding-only services cannot resolve ZIP-only input.
     *
     * @return array<string, mixed>|null
     */
    public function resolveDistrictByAddress(string $address): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $cacheKey = 'google_civic.district.' . md5(strtolower($address));

        return Cache::remember($cacheKey, $this->cacheDuration, function () use ($address) {
            $resolved = null;

            try {
                $response = Http::timeout(10)
                    ->get("{$this->baseUrl}/representatives", [
                        'address' => $address,
                        'key' => $this->apiKey,
                    ]);

                if ($response->successful()) {
                    $data = $response->json();
                    $fromOffice = $this->extractDistrictFromOffices((array) ($data['offices'] ?? []));
                    $fromDivisions = $this->extractDistrictFromDivisionKeys(array_keys((array) ($data['divisions'] ?? [])));
                    $districtData = $fromOffice ?? $fromDivisions;

                    if (is_array($districtData) && ! empty($districtData['state'])) {
                        $districtNumber = $this->normalizeDistrictNumber($districtData['district_number'] ?? null);
                        $state = strtoupper((string) ($districtData['state'] ?? ''));

                        $resolved = [
                            'input_address' => trim($address),
                            'matched_address' => trim($address),
                            'state' => $state,
                            'district_number' => $districtNumber,
                            'district_code' => $this->buildDistrictCode($state, $districtNumber),
                            'district_label' => $this->buildDistrictLabel($state, $districtNumber),
                        ];
                    }
                }
            } catch (\Throwable $e) {
                Log::error('GoogleCivicService: Failed to resolve district', [
                    'address' => $address,
                    'error' => $e->getMessage(),
                ]);
            }

            return $resolved;
        });
    }

    /**
    * Get election information for an address
    *
     * @param string $address Full address
     * @return array|null Election data or null on error
     */
    public function getElectionsByAddress(string $address): ?array
    {
        if (!$this->isConfigured()) {
            return null;
        }

        $cacheKey = 'google_civic.elections.' . md5(strtolower($address));

        return Cache::remember($cacheKey, $this->cacheDuration, function () use ($address) {
            try {
                $response = Http::timeout(10)
                    ->get("{$this->baseUrl}/elections", [
                        'address' => $address,
                        'key' => $this->apiKey,
                    ]);

                if (!$response->successful()) {
                    return null;
                }

                return $response->json('elections', []);

            } catch (\Exception $e) {
                Log::error('GoogleCivicService: Failed to fetch elections', [
                    'address' => $address,
                    'error' => $e->getMessage(),
                ]);
                return null;
            }
        });
    }

    /**
    * Parse officials response into candidate-like records
    *
     * @param array $data API response from Google Civic
     * @return array Parsed officials as candidate records
     */
    protected function parseOfficials(array $data): array
    {
        $officials = $data['officials'] ?? [];
        $offices = $data['offices'] ?? [];
        $parsed = [];

        foreach ($offices as $office) {
            $name = $office['name'] ?? 'Unknown Office';
            $level = $office['level'] ?? null; // 'federal', 'state', 'local'
            if (is_array($level)) {
                $level = $level[0] ?? null;
            }
            $divisionId = $office['divisionId'] ?? null;
            $division = $this->parseDivision($divisionId);

            foreach ($office['officialIndices'] ?? [] as $idx) {
                if (!isset($officials[$idx])) {
                    continue;
                }

                $official = $officials[$idx];
                $parsed[] = [
                    'full_name' => $this->buildFullName($official),
                    'political_office' => $name,
                    'governance_level' => $this->mapGovernanceLevel($level),
                    'state' => $division['state'],
                    'district_number' => $division['district_number'],
                    'district_code' => $division['district_code'],
                    'party_affiliation' => $official['party'] ?? null,
                    'phone' => $official['phones'][0] ?? null,
                    'email' => $official['emails'][0] ?? null,
                    'website' => $official['urls'][0] ?? null,
                    'photo_url' => $official['photoUrl'] ?? null,
                    'address' => $this->formatAddress($official['address'][0] ?? []),
                    'source' => 'google_civic',
                    'external_id' => 'google_civic_' . md5($name . ($official['name'] ?? '')),
                ];
            }
        }

        return $parsed;
    }

    /**
     * Build full name from first/last name
     */
    protected function buildFullName(array $official): string
    {
        return trim(($official['name'] ?? ''));
    }

    /**
     * Map Google Civic governance level to app standards
     */
    protected function mapGovernanceLevel(?string $level): string
    {
        return match ($level) {
            'federal' => 'Federal',
            'state' => 'State',
            'local' => 'County',
            default => 'Local',
        };
    }

    /**
     * @return array{state: string|null, district_number: string|null, district_code: string|null}
     */
    protected function parseDivision(?string $divisionId): array
    {
        $state = $this->extractState($divisionId);
        $districtNumber = $this->extractDistrictToken($divisionId);
        $districtNumber = $this->normalizeDistrictNumber($districtNumber);

        return [
            'state' => $state,
            'district_number' => $districtNumber,
            'district_code' => $this->buildDistrictCode((string) $state, $districtNumber),
        ];
    }

    /**
     * Extract state from division ID (e.g., "ocd-division/country:us/state:tx")
     */
    protected function extractState(?string $divisionId): ?string
    {
        if (!$divisionId) {
            return null;
        }

        if (preg_match('/state:([a-z]{2})/', $divisionId, $matches)) {
            return strtoupper($matches[1]);
        }

        return null;
    }

    protected function extractDistrictToken(?string $divisionId): ?string
    {
        if (! $divisionId) {
            return null;
        }

        if (preg_match('/\/cd:([a-z0-9\-]+)/i', $divisionId, $matches)) {
            return strtoupper($matches[1]);
        }

        return null;
    }

    protected function normalizeDistrictNumber(?string $district): ?string
    {
        $district = strtoupper(trim((string) $district));
        $normalized = null;

        if ($district !== '') {
            if (in_array($district, ['AL', 'AT-LARGE', 'AT_LARGE', '00'], true)) {
                $normalized = 'AL';
            } elseif (preg_match('/^\d+$/', $district) === 1) {
                $normalized = (string) ((int) $district);
            }
        }

        return $normalized;
    }

    /**
     * @param array<int, array<string, mixed>> $offices
     * @return array{state: string, district_number: string|null}|null
     */
    protected function extractDistrictFromOffices(array $offices): ?array
    {
        foreach ($offices as $office) {
            $officeName = strtolower((string) ($office['name'] ?? ''));
            $divisionId = (string) ($office['divisionId'] ?? '');
            if ($divisionId === '') {
                continue;
            }

            if (str_contains($officeName, 'house of representatives') || str_contains($officeName, 'u.s. representative')) {
                $division = $this->parseDivision($divisionId);
                if (! empty($division['state'])) {
                    return [
                        'state' => (string) $division['state'],
                        'district_number' => $division['district_number'],
                    ];
                }
            }
        }

        return null;
    }

    /**
     * @param array<int, string> $divisionKeys
     * @return array{state: string, district_number: string|null}|null
     */
    protected function extractDistrictFromDivisionKeys(array $divisionKeys): ?array
    {
        foreach ($divisionKeys as $divisionKey) {
            $division = $this->parseDivision($divisionKey);
            if (! empty($division['state']) && $division['district_number'] !== null) {
                return [
                    'state' => (string) $division['state'],
                    'district_number' => $division['district_number'],
                ];
            }
        }

        return null;
    }

    protected function buildDistrictCode(string $state, ?string $districtNumber): ?string
    {
        $state = strtoupper(trim($state));
        if ($state === '' || $districtNumber === null) {
            return null;
        }

        if ($districtNumber === 'AL') {
            return $state . '-AL';
        }

        return sprintf('%s-%02d', $state, (int) $districtNumber);
    }

    protected function buildDistrictLabel(string $state, ?string $districtNumber): ?string
    {
        $state = strtoupper(trim($state));
        if ($state === '' || $districtNumber === null) {
            return null;
        }

        if ($districtNumber === 'AL') {
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

    /**
     * Format address from API response
     */
    protected function formatAddress(array $addr): ?string
    {
        $parts = array_filter([
            $addr['line1'] ?? '',
            $addr['line2'] ?? '',
            $addr['line3'] ?? '',
            $addr['city'] ?? '',
            $addr['state'] ?? '',
            $addr['zip'] ?? '',
        ]);

        return !empty($parts) ? implode(', ', $parts) : null;
    }
}
