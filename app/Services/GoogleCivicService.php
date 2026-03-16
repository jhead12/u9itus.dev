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
            $divisionId = $office['divisionId'] ?? null;

            foreach ($office['officialIndices'] ?? [] as $idx) {
                if (!isset($officials[$idx])) {
                    continue;
                }

                $official = $officials[$idx];
                $parsed[] = [
                    'full_name' => $this->buildFullName($official),
                    'political_office' => $name,
                    'governance_level' => $this->mapGovernanceLevel($level),
                    'state' => $this->extractState($divisionId),
                    'party_affiliation' => $official['party'][0] ?? null,
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
