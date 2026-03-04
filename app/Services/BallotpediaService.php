<?php

namespace App\Services;

use App\Models\Politician;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Ballotpedia API Integration Service
 * 
 * Fetches voting record and biographical data from Ballotpedia.
 * Data is cached for 24 hours to minimize API calls.
 * 
 * API Documentation: https://ballotpedia.org/API_documentation
 * Rate Limits: 1000 requests/day (free tier)
 * 
 * Setup:
 * 1. Register for API key at https://ballotpedia.org/api/request
 * 2. Add to .env: BALLOTPEDIA_API_KEY=your_api_key_here
 */
class BallotpediaService
{
    protected string $baseUrl = 'https://ballotpedia.org/api/v4';
    protected ?string $apiKey;
    protected int $cacheDuration = 86400; // 24 hours

    public function __construct()
    {
        $this->apiKey = config('services.ballotpedia.api_key');
    }

    /**
     * Check if service is properly configured
     */
    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }

    /**
     * Fetch politician data from Ballotpedia
     * 
     * @param Politician $politician
     * @return array|null
     */
    public function fetchPoliticianData(Politician $politician): ?array
    {
        if (!$this->isConfigured()) {
            Log::warning('BallotpediaService: API key not configured');
            return null;
        }

        if (!$politician->show_ballotpedia_data) {
            return null;
        }

        $cacheKey = "ballotpedia.politician.{$politician->id}";

        return Cache::remember($cacheKey, $this->cacheDuration, function () use ($politician) {
            try {
                // Search by name and state
                $searchResults = $this->searchCandidate(
                    $politician->user->first_name . ' ' . $politician->user->last_name,
                    $politician->state
                );

                if (empty($searchResults)) {
                    return null;
                }

                // Get the best match
                $candidateId = $searchResults[0]['id'] ?? null;
                
                if (!$candidateId) {
                    return null;
                }

                // Store the Ballotpedia ID for future lookups
                $politician->update(['ballotpedia_id' => $candidateId]);

                // Fetch detailed candidate data
                return $this->getCandidateDetails($candidateId);

            } catch (\Exception $e) {
                Log::error('BallotpediaService: Failed to fetch data', [
                    'politician_id' => $politician->id,
                    'error' => $e->getMessage(),
                ]);
                return null;
            }
        });
    }

    /**
     * Search for a candidate by name and state
     * 
     * @param string $name
     * @param string $state
     * @return array
     */
    protected function searchCandidate(string $name, string $state): array
    {
        $response = Http::timeout(10)
            ->withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
                'Accept' => 'application/json',
            ])
            ->get("{$this->baseUrl}/candidates/search", [
                'query' => $name,
                'state' => $state,
                'limit' => 5,
            ]);

        if (!$response->successful()) {
            Log::warning('BallotpediaService: Search API request failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return [];
        }

        return $response->json('data', []);
    }

    /**
     * Get detailed candidate information
     * 
     * @param string $candidateId
     * @return array|null
     */
    protected function getCandidateDetails(string $candidateId): ?array
    {
        $response = Http::timeout(10)
            ->withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
                'Accept' => 'application/json',
            ])
            ->get("{$this->baseUrl}/candidates/{$candidateId}");

        if (!$response->successful()) {
            return null;
        }

        $data = $response->json('data', []);

        return [
            'id' => $data['id'] ?? null,
            'name' => $data['name'] ?? null,
            'office' => $data['office'] ?? null,
            'party' => $data['party'] ?? null,
            'profile_url' => $data['url'] ?? null,
            'voting_record' => $data['voting_record'] ?? [],
            'committee_assignments' => $data['committees'] ?? [],
            'sponsored_legislation' => $data['sponsored_bills'] ?? [],
            'bio' => $data['biography'] ?? null,
            'source_url' => $data['url'] ?? null,
        ];
    }

    /**
     * Clear cached data for a politician
     * 
     * @param Politician $politician
     * @return void
     */
    public function clearCache(Politician $politician): void
    {
        Cache::forget("ballotpedia.politician.{$politician->id}");
    }

    /**
     * Get display-ready data for frontend
     * 
     * @param Politician $politician
     * @return array|null
     */
    public function getDisplayData(Politician $politician): ?array
    {
        $data = $this->fetchPoliticianData($politician);

        if (!$data) {
            return null;
        }

        return [
            'source' => 'Ballotpedia',
            'source_url' => $data['source_url'] ?? 'https://ballotpedia.org',
            'sections' => [
                [
                    'title' => 'Voting Record',
                    'items' => array_slice($data['voting_record'] ?? [], 0, 10),
                    'show_more_url' => $data['source_url'] ?? null,
                ],
                [
                    'title' => 'Committee Assignments',
                    'items' => $data['committee_assignments'] ?? [],
                ],
                [
                    'title' => 'Sponsored Legislation',
                    'items' => array_slice($data['sponsored_legislation'] ?? [], 0, 5),
                    'show_more_url' => $data['source_url'] ?? null,
                ],
            ],
        ];
    }
}
