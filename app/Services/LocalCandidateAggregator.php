<?php

namespace App\Services;

use App\Models\ElectionCandidateRecord;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Local Candidate Aggregator
 * 
 * Chains multiple data sources to find local candidates by address and filters.
 * Tries sources in priority order and deduplicates results.
 * 
 * Source Priority:
 * 1. Manually uploaded/imported candidates (highest priority)
 * 2. Google Civic API (for current office holders by address)
 * 3. Ballotpedia API (for upcoming elections by address/state)
 * 4. VoteSmart API (fallback search)
 * 5. State-specific APIs (if implemented)
 */
class LocalCandidateAggregator
{
    protected GoogleCivicService $googleCivic;
    protected BallotpediaService $ballotpedia;
    protected VoteSmartService $voteSmart;

    public function __construct()
    {
        $this->googleCivic = app(GoogleCivicService::class);
        $this->ballotpedia = app(BallotpediaService::class);
        $this->voteSmart = app(VoteSmartService::class);
    }

    /**
     * Find local candidates by address
     * 
     * Aggregates results from multiple sources and returns deduplicated candidates.
     * 
     * @param string $address Full address (e.g., "123 Main St, Austin, TX 78701")
     * @param array $options Filter options:
     *   - governance_levels: ['City', 'County', 'State'] 
     *   - exclude_federal: bool (default true for local searches)
     *   - election_only: bool (default false)
     * @return Collection Deduplicated candidate records
     */
    public function findByAddress(string $address, array $options = []): Collection
    {
        $candidates = collect();

        // Step 1: Check database for manually imported records (highest priority)
        $dbCandidates = $this->findInDatabase($address, $options);
        $candidates = $candidates->merge($dbCandidates);

        // Step 2: Try Google Civic API for current office holders
        if ($this->googleCivic->isConfigured()) {
            $civicOfficials = $this->googleCivic->getOfficialsByAddress($address);
            if ($civicOfficials) {
                $candidates = $candidates->merge($this->convertGoogleCivicToRecords($civicOfficials, $options));
            }
        }

        // Step 3: Try Ballotpedia for upcoming elections
        if ($this->ballotpedia->isConfigured()) {
            $ballotpediaRecords = $this->findFromBallotpedia($address, $options);
            $candidates = $candidates->merge($ballotpediaRecords);
        }

        // Step 4: Try VoteSmart as fallback
        if ($this->voteSmart->isConfigured()) {
            $voteSmartRecords = $this->findFromVoteSmart($address, $options);
            $candidates = $candidates->merge($voteSmartRecords);
        }

        // Deduplicate by name, office, and governance level
        return $candidates->unique(function ($item) {
            $key = ($item['full_name'] ?? '') . '|' .
                   ($item['political_office'] ?? '') . '|' .
                   ($item['governance_level'] ?? '');
            return md5($key);
        })->values();
    }

    /**
     * Find candidates by city and state
     */
    public function findByCity(string $city, string $state, array $options = []): Collection
    {
        $candidates = collect();

        // Check database first
        $dbCandidates = ElectionCandidateRecord::where('city', $city)
            ->where('state', $state)
            ->where('is_active', true)
            ->get()
            ->map(fn($r) => $r->toArray());
        $candidates = $candidates->merge($dbCandidates);

        // Try external sources if configured
        if ($this->ballotpedia->isConfigured()) {
            $ballotpedia = $this->findFromBallotpedia(null, array_merge($options, [
                'city' => $city,
                'state' => $state,
            ]));
            $candidates = $candidates->merge($ballotpedia);
        }

        return $candidates->unique(function ($item) {
            $key = ($item['full_name'] ?? '') . '|' . ($item['political_office'] ?? '');
            return md5($key);
        })->values();
    }

    /**
     * Find candidates by state and governance level
     */
    public function findByState(string $state, array $governanceLevels = [], array $options = []): Collection
    {
        $candidates = collect();

        // Database lookup
        $query = ElectionCandidateRecord::where('state', $state)
            ->where('is_active', true);

        if (!empty($governanceLevels)) {
            $query->whereIn('governance_level', $governanceLevels);
        }

        $dbCandidates = $query->get()->map(fn($r) => $r->toArray());
        $candidates = $candidates->merge($dbCandidates);

        return $candidates->unique(function ($item) {
            return md5($item['full_name'] . '|' . $item['political_office']);
        })->values();
    }

    /**
     * Find candidates in database by address
     */
    protected function findInDatabase(string $address, array $options = []): Collection
    {
        // Extract state from address (simple heuristic)
        $state = $this->extractStateFromAddress($address);
        
        if (!$state) {
            return collect();
        }

        $query = ElectionCandidateRecord::where('state', $state)
            ->where('is_active', true);

        // Filter by governance level
        if (!empty($options['governance_levels'])) {
            $exclude = $options['exclude_federal'] ?? true;
            $levels = $options['governance_levels'];

            if ($exclude) {
                $levels = array_filter($levels, fn($l) => $l !== 'Federal');
            }

            if (!empty($levels)) {
                $query->whereIn('governance_level', $levels);
            }
        }

        return $query->get()->map(fn($r) => $r->toArray());
    }

    /**
     * Convert Google Civic records to standard format
     */
    protected function convertGoogleCivicToRecords(array $officials, array $options = []): Collection
    {
        return collect($officials)
            ->filter(function ($official) use ($options) {
                if ($options['exclude_federal'] ?? true) {
                    return ($official['governance_level'] ?? '') !== 'Federal';
                }
                return true;
            })
            ->map(function ($official) {
                return [
                    'full_name' => $official['full_name'] ?? 'Unknown',
                    'political_office' => $official['political_office'] ?? 'Unknown Office',
                    'governance_level' => $official['governance_level'] ?? 'Local',
                    'state' => $official['state'],
                    'city' => $official['city'] ?? null,
                    'party_affiliation' => $official['party_affiliation'] ?? null,
                    'phone' => $official['phone'] ?? null,
                    'email' => $official['email'] ?? null,
                    'website' => $official['website'] ?? null,
                    'photo_url' => $official['photo_url'] ?? null,
                    'source' => 'google_civic',
                ];
            });
    }

    /**
     * Find candidates from Ballotpedia
     */
    protected function findFromBallotpedia(?string $address, array $options = []): Collection
    {
        // This would call enhanced Ballotpedia service methods
        // For now, return empty as Ballotpedia integration for local elections
        // requires additional filtering in BallotpediaService
        return collect();
    }

    /**
     * Find candidates from VoteSmart
     */
    protected function findFromVoteSmart(?string $address, array $options = []): Collection
    {
        // This would call VoteSmart service methods for local candidates
        // Requires state and potentially city parameters
        return collect();
    }

    /**
     * Extract state abbreviation from address string
     * 
     * Very simple heuristic — looks for 2-letter state code at end
     */
    protected function extractStateFromAddress(string $address): ?string
    {
        // Match patterns like "Austin, TX" or "TX 78701"
        if (preg_match('/,\s*([A-Z]{2})(?:\s|$)/', $address, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
