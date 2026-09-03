<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
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
    protected string $baseUrl = 'https://civicinfo.googleapis.com/civicinfo/v2';

    protected ?string $apiKey;

    protected int $cacheDuration = 604800; // 7 days (not often changed)

    protected int $voterInfoCacheDuration = 86400; // 1 day — VIP data shifts as an election nears

    public function __construct()
    {
        $this->apiKey = config('services.google.civic_api_key');
    }

    /**
     * Check if service is properly configured
     */
    public function isConfigured(): bool
    {
        return ! empty($this->apiKey);
    }

    /**
     * Get elected officials for a given address
     *
     * Returns federal, state, and local representatives for an address.
     *
     * @param  string  $address  Full address (e.g., "123 Main St, Austin, TX 78701")
     * @return array|null Array of officials or null on error
     */
    public function getOfficialsByAddress(string $address): ?array
    {
        if (! $this->isConfigured()) {
            Log::warning('GoogleCivicService: API key not configured');

            return null;
        }

        $cacheKey = 'google_civic.officials.'.md5(strtolower($address));

        return Cache::remember($cacheKey, $this->cacheDuration, function () use ($address) {
            $parsedOfficials = null;

            try {
                $response = $this->requestRepresentatives($address, 'officials_lookup');

                if ($response !== null && ! $response->successful()) {
                    Log::warning('GoogleCivicService: API request failed', [
                        'status' => $response->status(),
                        'address' => $address,
                        'endpoint' => 'representatives',
                        'context' => 'officials_lookup',
                        'body_excerpt' => mb_substr($response->body(), 0, 500),
                    ]);
                }

                if ($response !== null && $response->successful()) {
                    $data = $response->json();
                    $parsedOfficials = $this->parseOfficials($data);
                }

            } catch (\Exception $e) {
                Log::error('GoogleCivicService: Failed to fetch officials', [
                    'address' => $address,
                    'error' => $e->getMessage(),
                ]);
            }

            return $parsedOfficials;
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

        $cacheKey = 'google_civic.district.'.md5(strtolower($address));

        return Cache::remember($cacheKey, $this->cacheDuration, function () use ($address) {
            $resolved = null;

            try {
                $response = $this->requestDivisionsByAddress($address, 'district_lookup');

                if ($response === null) {
                    return null;
                }

                if (! $response->successful()) {
                    Log::warning('GoogleCivicService: District lookup API request failed', [
                        'status' => $response->status(),
                        'address' => $address,
                        'endpoint' => 'divisionsByAddress',
                        'context' => 'district_lookup',
                        'body_excerpt' => mb_substr($response->body(), 0, 500),
                    ]);

                    return null;
                }

                $data = $response->json();
                $fromDivisions = $this->extractDistrictFromDivisionKeys(array_keys((array) ($data['divisions'] ?? [])));
                $districtData = $fromDivisions;

                if (is_array($districtData)) {
                    $districtData['state'] = strtoupper((string) ($districtData['state'] ?? data_get($data, 'normalizedInput.state', '')));
                }

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
                } else {
                    Log::info('GoogleCivicService: District lookup returned no congressional district', [
                        'address' => $address,
                    ]);
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

    protected function requestDivisionsByAddress(string $address, string $context): ?Response
    {
        try {
            return Http::timeout(10)
                ->get("{$this->baseUrl}/divisionsByAddress", [
                    'address' => $address,
                    'key' => $this->apiKey,
                ]);
        } catch (\Throwable $e) {
            Log::warning('GoogleCivicService: divisionsByAddress request threw exception', [
                'address' => $address,
                'context' => $context,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    protected function requestRepresentatives(string $address, string $context): ?Response
    {
        $urls = [
            'https://www.googleapis.com/civicinfo/v2',
            $this->baseUrl,
        ];
        $lastResponse = null;

        foreach ($urls as $url) {
            try {
                $response = Http::timeout(10)
                    ->get("{$url}/representatives", [
                        'address' => $address,
                        'key' => $this->apiKey,
                    ]);

                if ($response->successful()) {
                    if ($url !== $this->baseUrl) {
                        Log::info('GoogleCivicService: Using alternate Civic API host', [
                            'address' => $address,
                            'context' => $context,
                            'host' => $url,
                        ]);
                    }

                    return $response;
                }

                $lastResponse = $response;

                Log::warning('GoogleCivicService: Request attempt failed', [
                    'address' => $address,
                    'context' => $context,
                    'host' => $url,
                    'status' => $response->status(),
                    'body_excerpt' => mb_substr($response->body(), 0, 500),
                ]);
            } catch (\Throwable $e) {
                Log::warning('GoogleCivicService: Request attempt threw exception', [
                    'address' => $address,
                    'context' => $context,
                    'host' => $url,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $lastResponse;
    }

    /**
     * Get election information for an address
     *
     * @param  string  $address  Full address
     * @return array|null Election data or null on error
     */
    public function getElectionsByAddress(string $address): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $cacheKey = 'google_civic.elections.'.md5(strtolower($address));

        return Cache::remember($cacheKey, $this->cacheDuration, function () use ($address) {
            try {
                $response = Http::timeout(10)
                    ->get("{$this->baseUrl}/elections", [
                        'address' => $address,
                        'key' => $this->apiKey,
                    ]);

                if (! $response->successful()) {
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
     * Google Civic voterInfoQuery — per-address voter information for one
     * election: the official election-administration bodies (statewide plus
     * the local jurisdiction that actually runs the ballot) with their URLs,
     * and any ballot measures on that ballot (Referendum contests).
     *
     * Only the /representatives endpoint was retired (April 2025); /voterinfo,
     * backed by the Voting Information Project feed, is still live. It returns
     * data only when VIP has a feed covering that address for that election —
     * in practice the weeks around an election — otherwise the API responds
     * 400 with "Election unknown" / "No election data", which we treat as
     * "nothing available yet" and return null (not an error).
     *
     * @param  string  $address  A geocodable address; "City, ST" is enough.
     * @param  string|null  $electionId  Civic election id; omit to let Civic
     *                                   pick the next election for the address.
     * @return array{
     *   election: array{id: string, name: string, day: ?string, ocd_id: ?string}|null,
     *   admin_bodies: list<array<string, mixed>>,
     *   referendums: list<array<string, mixed>>,
     * }|null
     */
    public function voterInfoQuery(string $address, ?string $electionId = null): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $cacheKey = 'google_civic.voterinfo.'.md5(strtolower($address).'|'.($electionId ?? ''));

        return Cache::remember($cacheKey, $this->voterInfoCacheDuration, function () use ($address, $electionId) {
            try {
                $response = Http::timeout(15)->get("{$this->baseUrl}/voterinfo", array_filter([
                    'address' => $address,
                    'electionId' => $electionId,
                    'key' => $this->apiKey,
                ], fn ($v) => $v !== null && $v !== ''));

                if ($response->status() === 400) {
                    // "Election unknown" / no VIP feed for this address+election.
                    return null;
                }

                if (! $response->successful()) {
                    Log::warning('GoogleCivicService: voterInfoQuery failed', [
                        'status' => $response->status(),
                        'address' => $address,
                        'election_id' => $electionId,
                        'body_excerpt' => mb_substr($response->body(), 0, 300),
                    ]);

                    return null;
                }

                $data = $response->json();

                $election = null;
                if (! empty($data['election'])) {
                    $election = [
                        'id' => (string) ($data['election']['id'] ?? ''),
                        'name' => (string) ($data['election']['name'] ?? ''),
                        'day' => $data['election']['electionDay'] ?? null,
                        'ocd_id' => $data['election']['ocdDivisionId'] ?? null,
                    ];
                }

                $referendums = [];
                foreach ($data['contests'] ?? [] as $contest) {
                    if (($contest['type'] ?? '') !== 'Referendum') {
                        continue;
                    }
                    $referendums[] = [
                        'title' => $contest['referendumTitle'] ?? null,
                        'subtitle' => $contest['referendumSubtitle'] ?? null,
                        'text' => $contest['referendumText'] ?? null,
                        'url' => $contest['referendumUrl'] ?? null,
                        'ballot_responses' => $contest['referendumBallotResponses'] ?? [],
                        'pro_statement' => $contest['referendumProStatement'] ?? null,
                        'con_statement' => $contest['referendumConStatement'] ?? null,
                        'passage_threshold' => $contest['referendumPassageThreshold'] ?? null,
                        'district_name' => $contest['district']['name'] ?? null,
                        'district_scope' => $contest['district']['scope'] ?? null,
                    ];
                }

                return [
                    'election' => $election,
                    'admin_bodies' => $this->flattenAdministrationBodies($data['state'] ?? []),
                    'referendums' => $referendums,
                ];
            } catch (\Throwable $e) {
                Log::error('GoogleCivicService: voterInfoQuery threw', [
                    'address' => $address,
                    'election_id' => $electionId,
                    'error' => $e->getMessage(),
                ]);

                return null;
            }
        });
    }

    /**
     * Walk the state → local_jurisdiction chain Civic returns under `state`
     * and pull each level's electionAdministrationBody into a flat list,
     * tagged with the scope it came from.
     *
     * @param  array<int, array<string, mixed>>  $states
     * @return list<array<string, mixed>>
     */
    protected function flattenAdministrationBodies(array $states): array
    {
        $bodies = [];

        foreach ($states as $node) {
            $scope = 'state';
            while (is_array($node)) {
                $body = $node['electionAdministrationBody'] ?? null;
                if (is_array($body)) {
                    $bodies[] = [
                        'scope' => $scope,
                        'jurisdiction_name' => $node['name'] ?? null,
                        'ocd_id' => $node['id'] ?? null,
                        'name' => $body['name'] ?? null,
                        'election_info_url' => $body['electionInfoUrl'] ?? null,
                        'ballot_info_url' => $body['ballotInfoUrl'] ?? null,
                        'registration_url' => $body['electionRegistrationUrl'] ?? null,
                        'registration_confirmation_url' => $body['electionRegistrationConfirmationUrl'] ?? null,
                        'absentee_voting_info_url' => $body['absenteeVotingInfoUrl'] ?? null,
                        'voting_location_finder_url' => $body['votingLocationFinderUrl'] ?? null,
                        'ballot_tracking_url' => $body['ballotTrackingUrl'] ?? null,
                    ];
                }

                $node = $node['local_jurisdiction'] ?? null;
                $scope = 'local';
            }
        }

        return $bodies;
    }

    /**
     * Fetch the nationwide list of elections Google Civic (the Voting
     * Information Project feed) currently knows about, normalized into the
     * same per-state shape VoteSmartService::getElectionDates() returns —
     * a fallback source for state_election_dates when Vote Smart is
     * unavailable (unconfigured or erroring).
     *
     * Unlike getElectionsByAddress(), this hits the /elections endpoint
     * with no address: Civic's electionQuery ignores that param, so a
     * single call already covers every state, not just one voter's ballot.
     * State is parsed out of each election's ocdDivisionId (e.g.
     * "ocd-division/country:us/state:wy" → "WY"); non-state entries (the
     * nationwide "VIP Test Election" seed, etc.) are skipped. Civic has no
     * filing-deadline data, so that field is always null here.
     *
     * @return array<int, array{state: string, stage_name: string, election_date: ?string, civic_election_id: string}>
     */
    public function listUpcomingElections(): array
    {
        if (! $this->isConfigured()) {
            return [];
        }

        return Cache::remember('google_civic.elections.nationwide', $this->cacheDuration, function () {
            try {
                $response = Http::timeout(10)->get("{$this->baseUrl}/elections", [
                    'key' => $this->apiKey,
                ]);

                if (! $response->successful()) {
                    Log::warning('GoogleCivicService: elections query failed', [
                        'status' => $response->status(),
                    ]);

                    return [];
                }

                $normalized = [];

                foreach ($response->json('elections', []) as $election) {
                    $ocdId = (string) ($election['ocdDivisionId'] ?? '');
                    if (! preg_match('#country:us/state:([a-z]{2})(?:$|/)#i', $ocdId, $m)) {
                        continue;
                    }

                    $normalized[] = [
                        'state' => strtoupper($m[1]),
                        'stage_name' => $this->inferStageName((string) ($election['name'] ?? 'Election')),
                        'election_date' => $election['electionDay'] ?? null,
                        'civic_election_id' => (string) ($election['id'] ?? ''),
                    ];
                }

                return $normalized;
            } catch (\Exception $e) {
                Log::error('GoogleCivicService: Failed to fetch elections', ['error' => $e->getMessage()]);

                return [];
            }
        });
    }

    /** Infer a Primary/General/Runoff/Special stage_name from a Civic election name. */
    private function inferStageName(string $name): string
    {
        $lower = strtolower($name);

        return match (true) {
            str_contains($lower, 'general') => 'General',
            str_contains($lower, 'runoff') => 'Runoff',
            str_contains($lower, 'special') => 'Special',
            str_contains($lower, 'primary') => 'Primary',
            default => 'Election',
        };
    }

    /**
     * Parse officials response into candidate-like records
     *
     * @param  array  $data  API response from Google Civic
     * @return array Parsed officials as candidate records
     */
    protected function parseOfficials(array $data): array
    {
        $officials = $data['officials'] ?? [];
        $offices = $data['offices'] ?? [];
        $parsed = [];

        foreach ($offices as $office) {
            $name = $office['name'] ?? 'Unknown Office';
            // Google Civic returns 'levels' as an array (e.g. ['federal'], ['state'], ['locality'])
            // and optionally 'roles' (e.g. ['headOfGovernment', 'legislatorUpperBody'])
            $levels = $office['levels'] ?? $office['level'] ?? [];
            if (is_string($levels)) {
                $levels = [$levels];
            }
            $level = is_array($levels) ? ($levels[0] ?? null) : null;
            $roles = $office['roles'] ?? [];
            $divisionId = $office['divisionId'] ?? null;
            $division = $this->parseDivision($divisionId);

            foreach ($office['officialIndices'] ?? [] as $idx) {
                if (! isset($officials[$idx])) {
                    continue;
                }

                $official = $officials[$idx];
                $parsed[] = [
                    'full_name' => $this->buildFullName($official),
                    'political_office' => $name,
                    // If Google Civic didn't return a usable 'levels' value,
                    // mapGovernanceLevel() falls back to 'Local' — right for
                    // a mayor, but silently wrong for a Governor (same class
                    // of bug found corrupting U.S. Representatives via
                    // CongressGovService, which never set governance_level at
                    // all). Use the office title to catch that case rather
                    // than trusting an absent level by coincidence.
                    'governance_level' => $level !== null && $level !== ''
                        ? $this->mapGovernanceLevel($level)
                        : ($this->inferGovernanceLevelFromOfficeName($name) ?? $this->mapGovernanceLevel($level)),
                    'roles' => is_array($roles) ? $roles : [],
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
                    'external_id' => 'google_civic_'.md5($name.($official['name'] ?? '')),
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
     * Map Google Civic governance level to app standards.
     *
     * Google Civic returns levels as an array with values like:
     *   'federal', 'state', 'locality', 'administrativeArea1', 'administrativeArea2',
     *   'international', 'regional', 'special'
     */
    protected function mapGovernanceLevel(?string $level): string
    {
        return match (strtolower((string) $level)) {
            'federal' => 'Federal',
            'state',
            'administrativearea1' => 'State',
            'administrativearea2' => 'County',
            'locality', 'local', 'regional', 'special' => 'City',
            default => 'Local',
        };
    }

    /**
     * Narrow, unambiguous office-title override used only when Google Civic
     * gave us no 'levels' value to go on. Deliberately limited to titles that
     * can't mean anything else — unlike "Senator"/"Representative", which are
     * ambiguous between federal and state, "Governor" and "Mayor" are not.
     */
    protected function inferGovernanceLevelFromOfficeName(string $office): ?string
    {
        $office = strtolower($office);

        if (str_contains($office, 'governor')) {
            return 'State';
        }

        if (str_contains($office, 'mayor')) {
            return 'City';
        }

        return null;
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
        if (! $divisionId) {
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
     * @param  array<int, array<string, mixed>>  $offices
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
     * @param  array<int, string>  $divisionKeys
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
            return $state.'-AL';
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
            return $state.' At-Large Congressional District';
        }

        $num = (int) $districtNumber;

        return sprintf('%s %s Congressional District', $state, $this->ordinal($num));
    }

    protected function ordinal(int $number): string
    {
        $mod100 = $number % 100;
        if ($mod100 >= 11 && $mod100 <= 13) {
            return $number.'th';
        }

        return match ($number % 10) {
            1 => $number.'st',
            2 => $number.'nd',
            3 => $number.'rd',
            default => $number.'th',
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

        return ! empty($parts) ? implode(', ', $parts) : null;
    }
}
