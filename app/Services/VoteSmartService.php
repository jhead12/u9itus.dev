<?php

namespace App\Services;

use App\Models\Politician;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Vote Smart (Project Vote Smart) API Integration Service
 * 
 * Fetches issue ratings, voting positions, and interest group scores.
 * Data is cached for 24 hours to minimize API calls.
 * 
 * API Documentation: https://justfacts.votesmart.org/api-documentation
 * Rate Limits: 1000 requests/day (free tier)
 * 
 * Setup:
 * 1. Register for API key at https://justfacts.votesmart.org/api/register
 * 2. Add to .env: VOTESMART_API_KEY=your_api_key_here
 */
class VoteSmartService
{
    protected string $baseUrl = 'http://api.votesmart.org';
    protected ?string $apiKey;
    protected int $cacheDuration = 86400; // 24 hours

    public function __construct()
    {
        $this->apiKey = config('services.votesmart.api_key');
    }

    /**
     * Check if service is properly configured
     */
    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }

    /**
     * Fetch politician ratings and positions from Vote Smart
     * 
     * @param Politician $politician
     * @return array|null
     */
    public function fetchPoliticianRatings(Politician $politician): ?array
    {
        if (!$this->isConfigured()) {
            Log::warning('VoteSmartService: API key not configured');
            return null;
        }

        if (!$politician->show_votesmart_data) {
            return null;
        }

        $cacheKey = "votesmart.politician.{$politician->id}";

        return Cache::remember($cacheKey, $this->cacheDuration, function () use ($politician) {
            try {
                // Find candidate ID by name and state.
                // Use full_name directly — $politician->user is null for unclaimed profiles.
                $nameParts   = explode(' ', trim((string) $politician->full_name), 2);
                $firstName   = optional($politician->user)->first_name ?? $nameParts[0];
                $lastName    = optional($politician->user)->last_name  ?? ($nameParts[1] ?? '');
                $candidateId = $politician->votesmart_id ?? $this->findCandidateId(
                    $firstName,
                    $lastName,
                    $politician->state
                );

                if (!$candidateId) {
                    return null;
                }

                // Store the Vote Smart candidate ID
                $politician->update(['votesmart_id' => $candidateId]);

                // Fetch ratings and positions
                return [
                    'candidate' => $this->getCandidateBio($candidateId),
                    'ratings' => $this->getRatings($candidateId),
                    'issue_positions' => $this->getIssuePositions($candidateId),
                    'key_votes' => $this->getKeyVotes($candidateId),
                ];

            } catch (\Throwable $e) {
                $this->logProviderException('fetch_politician_ratings', $e, [
                    'politician_id' => $politician->id,
                ]);

                return null;
            }
        });
    }

    /**
     * Find candidate ID by name and state
     * 
     * @param string $firstName
     * @param string $lastName
     * @param string $state
     * @return string|null
     */
    protected function findCandidateId(string $firstName, string $lastName, string $state): ?string
    {
        $response = Http::timeout(10)->get("{$this->baseUrl}/Candidates.getByLastname", [
            'key' => $this->apiKey,
            'lastName' => $lastName,
            'o' => 'JSON',
        ]);

        if (!$response->successful()) {
            $this->logHttpFailure('find_candidate_id', $response->status(), [
                'state' => $state,
            ]);

            return null;
        }

        $candidates = $response->json('candidateList.candidate', []);

        // Ensure it's an array
        if (!is_array($candidates)) {
            return null;
        }

        // If single result, wrap in array
        if (isset($candidates['candidateId'])) {
            $candidates = [$candidates];
        }

        // Find best match by first name and state
        foreach ($candidates as $candidate) {
            $matchesFirstName = stripos($candidate['firstName'] ?? '', $firstName) !== false;
            $matchesState = ($candidate['electionStateId'] ?? '') === $state;

            if ($matchesFirstName && $matchesState) {
                return $candidate['candidateId'] ?? null;
            }
        }

        // Fallback: return first candidate with matching state
        foreach ($candidates as $candidate) {
            if (($candidate['electionStateId'] ?? '') === $state) {
                return $candidate['candidateId'] ?? null;
            }
        }

        return null;
    }

    /**
     * Get candidate biography
     * 
     * @param string $candidateId
     * @return array
     */
    protected function getCandidateBio(string $candidateId): array
    {
        $response = Http::timeout(10)->get("{$this->baseUrl}/CandidateBio.getBio", [
            'key' => $this->apiKey,
            'candidateId' => $candidateId,
            'o' => 'JSON',
        ]);

        if (!$response->successful()) {
            $this->logHttpFailure('get_candidate_bio', $response->status(), [
                'candidate_id' => $candidateId,
            ]);

            return [];
        }

        $bio = $response->json('bio.candidate', []);

        return [
            'photo_url' => $bio['photo'] ?? null,
            'birth_date' => $bio['birthDate'] ?? null,
            'education' => $bio['education'] ?? null,
            'profession' => $bio['profession'] ?? null,
            'political_experience' => $bio['political'] ?? null,
        ];
    }

    /**
     * Get interest group ratings
     * 
     * @param string $candidateId
     * @return array
     */
    protected function getRatings(string $candidateId): array
    {
        $response = Http::timeout(10)->get("{$this->baseUrl}/Rating.getCandidateRating", [
            'key' => $this->apiKey,
            'candidateId' => $candidateId,
            'o' => 'JSON',
        ]);

        if (!$response->successful()) {
            $this->logHttpFailure('get_ratings', $response->status(), [
                'candidate_id' => $candidateId,
            ]);

            return [];
        }

        $ratings = $response->json('candidateRating.rating', []);

        // Ensure it's an array
        if (!is_array($ratings)) {
            return [];
        }

        // If single rating, wrap in array
        if (isset($ratings['ratingId'])) {
            $ratings = [$ratings];
        }

        return array_map(function ($rating) {
            return [
                'organization' => $rating['ratingName'] ?? 'Unknown',
                'score' => $rating['rating'] ?? 'N/A',
                'year' => $rating['timespan'] ?? null,
                'description' => $rating['ratingText'] ?? null,
            ];
        }, array_slice($ratings, 0, 10));
    }

    /**
     * Get issue positions
     * 
     * @param string $candidateId
     * @return array
     */
    protected function getIssuePositions(string $candidateId): array
    {
        $response = Http::timeout(10)->get("{$this->baseUrl}/Npat.getNpat", [
            'key' => $this->apiKey,
            'candidateId' => $candidateId,
            'o' => 'JSON',
        ]);

        if (!$response->successful()) {
            $this->logHttpFailure('get_issue_positions', $response->status(), [
                'candidate_id' => $candidateId,
            ]);

            return [];
        }

        $npat = $response->json('npat', []);

        if (empty($npat)) {
            return [];
        }

        // Parse issue positions from NPAT (National Political Awareness Test)
        $positions = [];
        foreach ($npat as $key => $value) {
            if (is_string($value) && !empty($value) && $key !== 'candidateId') {
                $positions[] = [
                    'issue' => $this->formatIssueKey($key),
                    'position' => $value,
                ];
            }
        }

        return array_slice($positions, 0, 10);
    }

    /**
     * Get key votes
     * 
     * @param string $candidateId
     * @return array
     */
    protected function getKeyVotes(string $candidateId): array
    {
        $response = Http::timeout(10)->get("{$this->baseUrl}/Votes.getByOfficial", [
            'key' => $this->apiKey,
            'candidateId' => $candidateId,
            'o' => 'JSON',
        ]);

        if (!$response->successful()) {
            $this->logHttpFailure('get_key_votes', $response->status(), [
                'candidate_id' => $candidateId,
            ]);

            return [];
        }

        $votes = $response->json('votes.vote', []);

        // Ensure it's an array
        if (!is_array($votes)) {
            return [];
        }

        // If single vote, wrap in array
        if (isset($votes['billId'])) {
            $votes = [$votes];
        }

        return array_map(function ($vote) {
            return [
                'bill' => $vote['billNumber'] ?? 'Unknown',
                'title' => $vote['billTitle'] ?? null,
                'vote' => $vote['vote'] ?? 'N/A',
                'date' => $vote['date'] ?? null,
            ];
        }, array_slice($votes, 0, 10));
    }

    /**
     * Format issue key to readable title
     * 
     * @param string $key
     * @return string
     */
    protected function formatIssueKey(string $key): string
    {
        return ucwords(str_replace('_', ' ', $key));
    }

    protected function logHttpFailure(string $operation, int $status, array $context = []): void
    {
        Log::warning('VoteSmartService telemetry: HTTP request failed', array_merge($context, [
            'operation' => $operation,
            'status' => $status,
            'is_rate_limited' => $status === 429,
        ]));
    }

    protected function logProviderException(string $operation, \Throwable $exception, array $context = []): void
    {
        Log::warning('VoteSmartService telemetry: provider exception', array_merge($context, [
            'operation' => $operation,
            'error' => $exception->getMessage(),
        ]));
    }

    /**
     * Clear cached data for a politician
     * 
     * @param Politician $politician
     * @return void
     */
    public function clearCache(Politician $politician): void
    {
        Cache::forget("votesmart.politician.{$politician->id}");
    }

    /**
     * Get display-ready data for frontend
     * 
     * @param Politician $politician
     * @return array|null
     */
    public function getDisplayData(Politician $politician): ?array
    {
        $data = $this->fetchPoliticianRatings($politician);

        if (!$data) {
            return null;
        }

        return [
            'source' => 'Vote Smart',
            'source_url' => "https://justfacts.votesmart.org/candidate/{$politician->votesmart_id}",
            'sections' => [
                [
                    'title' => 'Interest Group Ratings',
                    'items' => $data['ratings'] ?? [],
                ],
                [
                    'title' => 'Issue Positions',
                    'items' => $data['issue_positions'] ?? [],
                ],
                [
                    'title' => 'Key Votes',
                    'items' => $data['key_votes'] ?? [],
                ],
            ],
        ];
    }
}
