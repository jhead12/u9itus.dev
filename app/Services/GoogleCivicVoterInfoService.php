<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleCivicVoterInfoService
{
    protected string $baseUrl = 'https://civicinfo.googleapis.com/civicinfo/v2';
    protected ?string $apiKey;
    protected int $cacheDuration = 86400; // 24 hours

    public function __construct()
    {
        $this->apiKey = config('services.google.civic_api_key');
    }

    public function isConfigured(): bool
    {
        return ! empty($this->apiKey);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getByAddress(string $address): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $cacheKey = 'google_civic.voter_info.' . md5(strtolower($address));

        return Cache::remember($cacheKey, $this->cacheDuration, function () use ($address) {
            $responseModel = null;

            try {
                $initialResponse = $this->requestVoterInfo($address, null, 'voter_info_initial');

                if ($initialResponse !== null && $initialResponse->successful()) {
                    $responseModel = $this->buildFromInitialResponse($address, (array) $initialResponse->json());
                } elseif ($initialResponse !== null) {
                    Log::warning('GoogleCivicVoterInfoService: voterinfo request failed', [
                        'address' => $address,
                        'status' => $initialResponse->status(),
                        'body_excerpt' => mb_substr($initialResponse->body(), 0, 500),
                    ]);
                }
            } catch (\Throwable $e) {
                Log::error('GoogleCivicVoterInfoService: Failed to fetch voter info', [
                    'address' => $address,
                    'error' => $e->getMessage(),
                ]);
            }

            return $responseModel;
        });
    }

    /**
     * @param array<string, mixed> $initialData
     * @return array<string, mixed>
     */
    protected function buildFromInitialResponse(string $address, array $initialData): array
    {
        $selectedElectionId = null;
        $selectionReason = 'initial_response';
        $selectedData = $initialData;
        $otherElections = (array) ($initialData['otherElections'] ?? []);

        if ($otherElections !== []) {
            $selection = $this->resolveOtherElectionSelection($otherElections);
            $selectedElectionId = $selection['election_id'];
            $selectionReason = $selection['reason'];

            if ($selectedElectionId !== null && $selectedElectionId !== '') {
                $selectedData = $this->requestSelectedElectionData($address, $selectedElectionId, $initialData, $selectionReason);
                if ($selectedData === $initialData) {
                    $selectionReason = 'fallback_initial_response';
                }
            }
        }

        return $this->normalizeVoterInfoResponse($selectedData, $selectedElectionId, $selectionReason);
    }

    /**
     * @param array<int, mixed> $otherElections
     * @return array{election_id: string|null, reason: string}
     */
    protected function resolveOtherElectionSelection(array $otherElections): array
    {
        $earliest = $this->selectEarliestElection($otherElections);
        $electionId = isset($earliest['id']) ? (string) $earliest['id'] : null;

        return [
            'election_id' => $electionId,
            'reason' => $electionId !== null && $electionId !== ''
                ? 'earliest_other_election'
                : 'other_elections_no_valid_id',
        ];
    }

    /**
     * @param array<string, mixed> $initialData
     * @return array<string, mixed>
     */
    protected function requestSelectedElectionData(string $address, string $selectedElectionId, array $initialData, string $selectionReason): array
    {
        $selectedData = $initialData;
        $selectedResponse = $this->requestVoterInfo($address, $selectedElectionId, 'voter_info_selected_election');

        if ($selectedResponse !== null && $selectedResponse->successful()) {
            $selectedData = (array) $selectedResponse->json();
        } else {
            Log::warning('GoogleCivicVoterInfoService: Selected election voterinfo request failed, using initial response', [
                'address' => $address,
                'selected_election_id' => $selectedElectionId,
                'selection_reason' => $selectionReason,
                'status' => $selectedResponse?->status(),
                'body_excerpt' => mb_substr((string) $selectedResponse?->body(), 0, 500),
            ]);
        }

        return $selectedData;
    }

    protected function requestVoterInfo(string $address, ?string $electionId, string $context): ?Response
    {
        $urls = [
            'https://www.googleapis.com/civicinfo/v2',
            $this->baseUrl,
        ];
        $lastResponse = null;

        foreach ($urls as $url) {
            try {
                $params = [
                    'address' => $address,
                    'key' => $this->apiKey,
                ];

                if ($electionId !== null && $electionId !== '') {
                    $params['electionId'] = $electionId;
                }

                $response = Http::timeout(10)->get("{$url}/voterinfo", $params);

                if ($response->successful()) {
                    if ($url !== $this->baseUrl) {
                        Log::info('GoogleCivicVoterInfoService: Using alternate Civic API host for voterinfo', [
                            'address' => $address,
                            'context' => $context,
                            'host' => $url,
                            'election_id' => $electionId,
                        ]);
                    }

                    return $response;
                }

                $lastResponse = $response;

                Log::warning('GoogleCivicVoterInfoService: voterinfo request attempt failed', [
                    'address' => $address,
                    'context' => $context,
                    'host' => $url,
                    'election_id' => $electionId,
                    'status' => $response->status(),
                    'body_excerpt' => mb_substr($response->body(), 0, 500),
                ]);
            } catch (\Throwable $e) {
                Log::warning('GoogleCivicVoterInfoService: voterinfo request attempt threw exception', [
                    'address' => $address,
                    'context' => $context,
                    'host' => $url,
                    'election_id' => $electionId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $lastResponse;
    }

    /**
     * @param array<int, mixed> $elections
     * @return array<string, mixed>|null
     */
    protected function selectEarliestElection(array $elections): ?array
    {
        $candidates = array_values(array_filter($elections, fn ($entry) => is_array($entry)));
        $selected = null;

        if ($candidates !== []) {
            usort($candidates, fn (array $left, array $right) => $this->compareElectionEntries($left, $right));
            $selected = (array) ($candidates[0] ?? []);
        }

        return $selected;
    }

    /**
     * @param array<string, mixed> $left
     * @param array<string, mixed> $right
     */
    protected function compareElectionEntries(array $left, array $right): int
    {
        $leftDate = isset($left['electionDay']) ? strtotime((string) $left['electionDay']) : false;
        $rightDate = isset($right['electionDay']) ? strtotime((string) $right['electionDay']) : false;

        $leftWeight = $leftDate === false ? PHP_INT_MAX : (int) $leftDate;
        $rightWeight = $rightDate === false ? PHP_INT_MAX : (int) $rightDate;

        if ($leftWeight === $rightWeight) {
            return ((int) ($left['id'] ?? PHP_INT_MAX)) <=> ((int) ($right['id'] ?? PHP_INT_MAX));
        }

        return $leftWeight <=> $rightWeight;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function normalizeVoterInfoResponse(array $data, ?string $selectedElectionId, string $selectionReason): array
    {
        $election = (array) ($data['election'] ?? []);
        $normalizedInput = (array) ($data['normalizedInput'] ?? []);
        $otherElections = array_values(array_filter((array) ($data['otherElections'] ?? []), fn ($entry) => is_array($entry)));

        return [
            'source' => 'google_civic_voterinfo',
            'selection_reason' => $selectionReason,
            'selected_election_id' => $selectedElectionId,
            'election' => [
                'id' => isset($election['id']) ? (string) $election['id'] : null,
                'name' => $election['name'] ?? null,
                'election_day' => $election['electionDay'] ?? null,
            ],
            'other_elections' => array_map(function (array $entry) {
                return [
                    'id' => isset($entry['id']) ? (string) $entry['id'] : null,
                    'name' => $entry['name'] ?? null,
                    'election_day' => $entry['electionDay'] ?? null,
                ];
            }, $otherElections),
            'normalized_input' => [
                'location_name' => $normalizedInput['locationName'] ?? null,
                'line1' => $normalizedInput['line1'] ?? null,
                'line2' => $normalizedInput['line2'] ?? null,
                'line3' => $normalizedInput['line3'] ?? null,
                'city' => $normalizedInput['city'] ?? null,
                'state' => isset($normalizedInput['state']) ? strtoupper((string) $normalizedInput['state']) : null,
                'zip' => $normalizedInput['zip'] ?? null,
            ],
            'polling_locations' => $this->normalizeVoterLocations((array) ($data['pollingLocations'] ?? [])),
            'early_vote_sites' => $this->normalizeVoterLocations((array) ($data['earlyVoteSites'] ?? [])),
            'drop_off_locations' => $this->normalizeVoterLocations((array) ($data['dropOffLocations'] ?? [])),
            'contests' => $this->normalizeVoterContests((array) ($data['contests'] ?? [])),
        ];
    }

    /**
     * @param array<int, mixed> $locations
     * @return array<int, array<string, mixed>>
     */
    protected function normalizeVoterLocations(array $locations): array
    {
        $normalized = [];

        foreach ($locations as $location) {
            if (! is_array($location)) {
                continue;
            }

            $address = (array) ($location['address'] ?? []);

            $normalized[] = [
                'name' => $location['name'] ?? null,
                'notes' => $location['notes'] ?? null,
                'polling_hours' => $location['pollingHours'] ?? null,
                'voter_services' => $location['voterServices'] ?? null,
                'start_date' => $location['startDate'] ?? null,
                'end_date' => $location['endDate'] ?? null,
                'latitude' => $location['latitude'] ?? null,
                'longitude' => $location['longitude'] ?? null,
                'address' => [
                    'location_name' => $address['locationName'] ?? null,
                    'line1' => $address['line1'] ?? null,
                    'line2' => $address['line2'] ?? null,
                    'line3' => $address['line3'] ?? null,
                    'city' => $address['city'] ?? null,
                    'state' => isset($address['state']) ? strtoupper((string) $address['state']) : null,
                    'zip' => $address['zip'] ?? null,
                ],
            ];
        }

        return $normalized;
    }

    /**
     * @param array<int, mixed> $contests
     * @return array<int, array<string, mixed>>
     */
    protected function normalizeVoterContests(array $contests): array
    {
        $normalized = [];

        foreach ($contests as $contest) {
            if (! is_array($contest)) {
                continue;
            }

            $district = (array) ($contest['district'] ?? []);
            $candidates = [];

            foreach ((array) ($contest['candidates'] ?? []) as $candidate) {
                if (! is_array($candidate)) {
                    continue;
                }

                $candidates[] = [
                    'name' => $candidate['name'] ?? null,
                    'party' => $candidate['party'] ?? null,
                    'candidate_url' => $candidate['candidateUrl'] ?? null,
                    'phone' => $candidate['phone'] ?? null,
                    'photo_url' => $candidate['photoUrl'] ?? null,
                    'email' => $candidate['email'] ?? null,
                    'order_on_ballot' => $candidate['orderOnBallot'] ?? null,
                ];
            }

            $normalized[] = [
                'type' => $contest['type'] ?? null,
                'office' => $contest['office'] ?? null,
                'ballot_title' => $contest['ballotTitle'] ?? null,
                'level' => array_values((array) ($contest['level'] ?? [])),
                'roles' => array_values((array) ($contest['roles'] ?? [])),
                'district' => [
                    'name' => $district['name'] ?? null,
                    'scope' => $district['scope'] ?? null,
                    'id' => $district['id'] ?? null,
                ],
                'candidates' => $candidates,
            ];
        }

        return $normalized;
    }
}
