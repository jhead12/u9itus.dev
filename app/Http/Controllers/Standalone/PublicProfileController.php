<?php

namespace App\Http\Controllers\Standalone;

use App\Http\Controllers\Controller;
use App\Enums\ApprovalStatus;
use App\Enums\CampaignStatus;
use App\Models\DistrictLookupSearch;
use App\Models\ElectionCandidateRecord;
use App\Models\Politician;
use App\Models\VoterWatchReport;
use App\Services\CongressGovService;
use App\Services\GoogleCivicService;
use App\Services\GoogleCivicVoterInfoService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Phase 13 — Public Politician Profile Pages
 *
 * Serves the public-facing campaign page at /p/{slug}.
 * No authentication required.
 */
class PublicProfileController extends Controller
{
    /**
     * Public district lookup by address.
     *
     * Lets a voter enter a street address and find their district
     * plus currently published candidates in that district/state.
     */
    public function districtLookup(Request $request)
    {
        $states = config('u9itus.us_states', []);

        $address = (string) $request->query('address', '');
        $lookupResult = null;
        $candidates = collect();
        $runningCandidates = collect();
        $topContenders = collect();
        $currentOfficials = collect();
        $discoveredOfficials = collect();
        $voterInfo = null;
        $error = null;

        if ($address !== '') {
            $validator = Validator::make($request->query(), [
                'address' => ['required', 'string', 'max:255'],
            ]);

            if ($validator->fails()) {
                $error = 'Please enter a valid street address.';
            } else {
                $lookupService = app(\App\Services\DistrictLookupService::class);
                $lookupResult = $lookupService->lookup($address);

                if (! $lookupResult) {
                    $error = $this->unresolvedLookupMessage($address);
                } else {
                    $lookupState = strtoupper((string) ($lookupResult['state'] ?? ''));
                    $voterInfo = $this->fetchVoterInfoFromGoogleCivic($address);
                    $districtHints = $this->extractDistrictHintsFromVoterInfo($voterInfo, $lookupState);

                    $currentOfficials = $this->fetchCurrentOfficialsForLocation($address);
                    if ($currentOfficials->isEmpty()) {
                        $currentOfficials = $this->findCurrentOfficialsForDistrictFromCongress($lookupResult);
                    }
                    if ($currentOfficials->isEmpty()) {
                        $currentOfficials = $this->findCurrentOfficialsForDistrictFromRecords($lookupResult, $states);
                    }
                    $candidates = $this->findCandidatesForDistrict($lookupResult, $states, $districtHints);
                    $runningCandidates = $this->findRunningCandidatesForDistrict($lookupResult, $states, $districtHints);
                    $candidates = $this->attachFinanceSnapshotsToPublishedCandidates($candidates);
                    $topContenders = $runningCandidates->take(3)->values();

                    // Discover and persist officials not yet in the local profile set.
                    $discoveredOfficials = $this->discoverCandidatesFromGoogleCivic($address, $lookupResult);
                    if ($discoveredOfficials->isNotEmpty()) {
                        $candidates = $this->mergeCandidates($candidates, $discoveredOfficials);
                    }

                    // ── Deduplication ────────────────────────────────────────
                    // Build a name-keyed lookup for everything already in $candidates
                    // so the two sections below don't repeat the same person.
                    $shownNames = $candidates
                        ->map(fn ($c) => strtolower(trim((string) ($c->full_name ?? ''))))
                        ->filter()
                        ->values()
                        ->flip()
                        ->all();

                    // Remove officials whose name already appears in $candidates.
                    $currentOfficials = $currentOfficials->reject(
                        fn ($o) => isset($shownNames[strtolower(trim((string) ($o['full_name'] ?? '')))])
                    )->values();

                    // Build combined shown-name set (candidates + deduplicated officials).
                    $shownNames = $shownNames + $currentOfficials
                        ->map(fn ($o) => strtolower(trim((string) ($o['full_name'] ?? ''))))
                        ->filter()
                        ->values()
                        ->flip()
                        ->all();

                    // Top contender names — rendered in the amber highlight row.
                    $topContenderNames = $topContenders
                        ->map(fn ($c) => strtolower(trim((string) ($c['full_name'] ?? ''))))
                        ->filter()
                        ->values()
                        ->flip()
                        ->all();

                    // Remove running candidates already shown above, then strip top contenders
                    // from the regular grid (they appear in the amber row instead).
                    $runningCandidates = $runningCandidates
                        ->reject(fn ($c) => isset($shownNames[strtolower(trim((string) ($c['full_name'] ?? '')))]))
                        ->values();

                    $runningGrid = $runningCandidates
                        ->reject(fn ($c) => isset($topContenderNames[strtolower(trim((string) ($c['full_name'] ?? '')))]))
                        ->values();
                }

                $this->recordDistrictLookupSearch(
                    request: $request,
                    address: $address,
                    lookupResult: $lookupResult,
                    error: $error,
                    discoveredOfficialsCount: $discoveredOfficials->count(),
                    voterInfo: $voterInfo,
                );
            }
        }

        return view('standalone.public.district-lookup', [
            'address'          => $address,
            'lookupResult'     => $lookupResult,
            'candidates'       => $candidates,
            'runningCandidates'=> $runningCandidates,
            'runningGrid'      => $runningGrid ?? collect(),
            'topContenders'    => $topContenders,
            'currentOfficials' => $currentOfficials,
            'states'           => $states,
            'error'            => $error,
        ]);
    }

    /**
     * Fetches voter info enrichment data. Failures are non-fatal to district lookup UX.
     *
     * @return array<string, mixed>|null
     */
    protected function fetchVoterInfoFromGoogleCivic(string $address): ?array
    {
        /** @var GoogleCivicVoterInfoService $googleCivicVoterInfo */
        $googleCivicVoterInfo = app(GoogleCivicVoterInfoService::class);

        if (! $googleCivicVoterInfo->isConfigured()) {
            return null;
        }

        $result = $googleCivicVoterInfo->getByAddress($address);

        return is_array($result) ? $result : null;
    }

    /**
     * Fetch current officeholders for the exact searched location from Google Civic.
     *
     * @return Collection<int, array<string, mixed>>
     */
    protected function fetchCurrentOfficialsForLocation(string $address): Collection
    {
        /** @var GoogleCivicService $googleCivic */
        $googleCivic = app(GoogleCivicService::class);

        if (! $googleCivic->isConfigured()) {
            return collect();
        }

        $officials = $googleCivic->getOfficialsByAddress($address);

        if (! is_array($officials) || empty($officials)) {
            return collect();
        }

        return collect($officials)
            ->map(function (array $official): array {
                $fullName = trim((string) ($official['full_name'] ?? ''));
                $office = trim((string) ($official['political_office'] ?? ''));
                $state = strtoupper(trim((string) ($official['state'] ?? '')));

                return [
                    'full_name'         => $fullName,
                    'political_office'  => $office,
                    'governance_level'  => trim((string) ($official['governance_level'] ?? 'Local')),
                    'party_affiliation' => trim((string) ($official['party_affiliation'] ?? '')),
                    'state'             => $state,
                    'district_code'     => trim((string) ($official['district_code'] ?? '')),
                    'website'           => $this->sanitizePublicWebsiteUrl($official['website'] ?? null) ?? '',
                    'source'            => trim((string) ($official['source'] ?? 'google_civic')),
                    'discovery_links'   => $this->buildDiscoveryLinks($fullName, $office, $state),
                ];
            })
            ->filter(function (array $official): bool {
                return ($official['full_name'] ?? '') !== '';
            })
            ->unique(function (array $official): string {
                return strtolower(($official['full_name'] ?? '') . '|' . ($official['political_office'] ?? ''));
            })
            ->values();
    }

    /**
     * Fallback current officeholders from Congress.gov for the resolved district.
     *
     * @param array<string, mixed> $lookupResult
     * @return Collection<int, array<string, mixed>>
     */
    protected function findCurrentOfficialsForDistrictFromCongress(array $lookupResult): Collection
    {
        $state = strtoupper((string) ($lookupResult['state'] ?? ''));
        $districtNumber = trim((string) ($lookupResult['district_number'] ?? ''));

        if ($state === '' || $districtNumber === '') {
            return collect();
        }

        /** @var CongressGovService $congress */
        $congress = app(CongressGovService::class);

        if (! $congress->isConfigured()) {
            return collect();
        }

        $officials = $congress->getCurrentHouseMembersByDistrict($state, $districtNumber);

        if (! is_array($officials) || empty($officials)) {
            return collect();
        }

        foreach ($officials as $official) {
            if (! is_array($official)) {
                continue;
            }

            $this->persistDiscoveredOfficial($official, $lookupResult, (string) ($lookupResult['matched_address'] ?? 'district-lookup'));
        }

        return collect($officials)
            ->map(function (array $official): array {
                $fullName = trim((string) ($official['full_name'] ?? ''));
                $office = trim((string) ($official['political_office'] ?? ''));
                $state = strtoupper(trim((string) ($official['state'] ?? '')));

                return [
                    'full_name' => $fullName,
                    'political_office' => $office,
                    'party_affiliation' => trim((string) ($official['party_affiliation'] ?? '')),
                    'state' => $state,
                    'district_code' => trim((string) ($official['district_code'] ?? '')),
                    'website' => $this->sanitizePublicWebsiteUrl($official['website'] ?? null) ?? '',
                    'source' => trim((string) ($official['source'] ?? 'congress_gov')),
                    'discovery_links' => $this->buildDiscoveryLinks($fullName, $office, $state),
                ];
            })
            ->filter(function (array $official): bool {
                return trim((string) ($official['full_name'] ?? '')) !== '';
            })
            ->unique(function (array $official): string {
                return strtolower((string) ($official['full_name'] ?? ''));
            })
            ->values();
    }

    /**
     * Fallback current officeholders from local election records for the resolved district.
     *
     * @param array<string, mixed> $lookupResult
     * @param array<string, string> $states
     * @return Collection<int, array<string, mixed>>
     */
    protected function findCurrentOfficialsForDistrictFromRecords(array $lookupResult, array $states): Collection
    {
        $state = strtoupper((string) ($lookupResult['state'] ?? ''));
        $districtNumber = trim((string) ($lookupResult['district_number'] ?? ''));

        if ($state === '' || $districtNumber === '') {
            return collect();
        }

        $stateName = $states[$state] ?? null;
        $variants = $this->districtVariants($state, $districtNumber);
        $recentThreshold = now()->subYears(2)->toDateString();

        $records = ElectionCandidateRecord::query()
            ->where(function ($q) use ($state, $stateName) {
                $q->whereRaw('UPPER(state) = ?', [$state]);

                if ($stateName) {
                    $q->orWhereRaw('LOWER(state) = ?', [strtolower($stateName)]);
                }
            })
            ->where(function ($q) use ($variants) {
                foreach ($variants as $variant) {
                    $q->orWhere('district', 'like', '%' . $variant . '%');
                }
            })
            ->where(function ($q) use ($recentThreshold) {
                $q->whereNull('election_date')
                    ->orWhereDate('election_date', '>=', $recentThreshold);
            })
            ->orderByDesc('last_seen_at')
            ->orderBy('election_date')
            ->limit(100)
            ->get();

        return $records
            ->map(function (ElectionCandidateRecord $record): array {
                return [
                    'full_name' => trim((string) $record->full_name),
                    'political_office' => trim((string) ($record->political_office ?? '')),
                    'party_affiliation' => trim((string) ($record->party_affiliation ?? '')),
                    'state' => strtoupper(trim((string) ($record->state ?? ''))),
                    'district_code' => trim((string) ($record->district ?? '')),
                    'website' => '',
                    'source' => (string) ($record->source ?? 'local_record'),
                ];
            })
            ->filter(function (array $official): bool {
                return ($official['full_name'] ?? '') !== '';
            })
            ->unique(function (array $official): string {
                return strtolower(($official['full_name'] ?? '') . '|' . ($official['political_office'] ?? ''));
            })
            ->values();
    }

    protected function unresolvedLookupMessage(string $address): string
    {
        if ($this->isZipOnlyInput($address)) {
            return 'We found your city/state from ZIP, but could not determine a congressional district from ZIP alone. Please enter your full street address to see complete district details.';
        }

        return 'We could not resolve that address. Try including street, city, state, and ZIP.';
    }

    protected function isZipOnlyInput(string $address): bool
    {
        return preg_match('/^\d{5}(?:-\d{4})?$/', trim($address)) === 1;
    }

    /**
     * @param array<string, mixed> $lookupResult
     */
    protected function discoverCandidatesFromGoogleCivic(string $address, array $lookupResult): Collection
    {
        /** @var GoogleCivicService $googleCivic */
        $googleCivic = app(GoogleCivicService::class);
        $officials = [];

        if ($googleCivic->isConfigured()) {
            $result = $googleCivic->getOfficialsByAddress($address);
            if (is_array($result)) {
                $officials = $result;
            }
        }

        if (empty($officials)) {
            return collect();
        }

        $profileIds = [];

        foreach ($officials as $official) {
            $profileId = $this->persistDiscoveredOfficial($official, $lookupResult, $address);
            if ($profileId !== null) {
                $profileIds[] = $profileId;
            }
        }

        if (empty($profileIds)) {
            return collect();
        }

        return Politician::query()
            ->whereIn('id', array_values(array_unique($profileIds)))
            ->where('page_published', true)
            ->where('is_active', true)
            ->withCount([
                'campaigns as active_campaigns_count' => function ($q) {
                    $q->where('status', 'active')
                      ->where('approval_status', 'approved');
                },
            ])
            ->get();
    }

    /**
     * @param array<string, mixed> $official
     * @param array<string, mixed> $lookupResult
     */
    protected function persistDiscoveredOfficial(array $official, array $lookupResult, string $address): ?int
    {
        $fullName = trim((string) ($official['full_name'] ?? ''));
        $state = strtoupper((string) ($official['state'] ?? ($lookupResult['state'] ?? '')));
        if ($fullName === '' || $state === '') {
            return null;
        }

        $office = trim((string) ($official['political_office'] ?? 'Public Official'));
        $districtCode = trim((string) ($official['district_code'] ?? ($lookupResult['district_code'] ?? '')));
        $district = $districtCode !== '' ? $districtCode : null;
        if ($district === null && ($official['district_number'] ?? null) !== null) {
            $district = $state . '-' . str_pad((string) ((int) $official['district_number']), 2, '0', STR_PAD_LEFT);
        }

        $externalId = trim((string) ($official['external_id'] ?? ''));
        if ($externalId === '') {
            $externalId = 'google_civic_' . md5(strtolower($fullName . '|' . $office . '|' . $state));
        }

        ElectionCandidateRecord::updateOrCreate(
            [
                'source' => (string) ($official['source'] ?? 'google_civic'),
                'external_candidate_id' => $externalId,
            ],
            [
                'full_name' => $fullName,
                'political_office' => $office,
                'governance_level' => (string) ($official['governance_level'] ?? 'Local'),
                'state' => $state,
                'district' => $district,
                'party_affiliation' => $official['party_affiliation'] ?? null,
                'payload' => [
                    'official' => $official,
                    'research_links' => $this->buildDiscoveryLinks($fullName, $office, $state),
                    'lookup_context' => [
                        'input_address' => $address,
                        'lookup_result' => $lookupResult,
                    ],
                ],
                'last_seen_at' => now(),
            ],
        );

        $profileData = [
            'full_name' => $fullName,
            'political_office' => $office,
            'governance_level' => (string) ($official['governance_level'] ?? 'Local'),
            'district' => $district,
            'party_affiliation' => $official['party_affiliation'] ?? null,
            'state' => $state,
            'website_url' => $this->sanitizePublicWebsiteUrl($official['website'] ?? null),
            'profile_photo_url' => $official['photo_url'] ?? null,
            'bio' => 'Imported from Google Civic Information API based on district lookup discovery.',
            'verified_official' => true,
            'is_active' => true,
            'page_published' => true,
        ];

        $existing = Politician::query()
            ->whereNull('user_id')
            ->whereRaw('LOWER(full_name) = ?', [strtolower($fullName)])
            ->whereRaw("LOWER(COALESCE(political_office, '')) = ?", [strtolower($office)])
            ->whereRaw("UPPER(COALESCE(state, '')) = ?", [$state])
            ->first();

        $profileId = null;

        if ($existing) {
            if (empty(trim((string) ($profileData['website_url'] ?? '')))) {
                unset($profileData['website_url']);
            }

            if (empty(trim((string) ($profileData['profile_photo_url'] ?? '')))) {
                unset($profileData['profile_photo_url']);
            }

            // Keep richer existing bios; district lookup discovery should not replace them.
            $incomingBio = trim((string) ($profileData['bio'] ?? ''));
            $existingBio = trim((string) ($existing->bio ?? ''));
            if ($existingBio !== '' && str_contains($incomingBio, 'Imported from Google Civic Information API based on district lookup discovery.')) {
                unset($profileData['bio']);
            }

            $existing->fill($profileData);
            $existing->save();
            $profileId = $existing->id;
        } else {
            $profileId = Politician::create($profileData)->id;
        }

        return $profileId;
    }

    /**
     * Build public research links for newly discovered officials.
     *
     * @return array{wikipedia:string,youtube:string,cspan:string}
     */
    protected function buildDiscoveryLinks(string $fullName, ?string $office = null, ?string $state = null): array
    {
        $queryParts = array_values(array_filter([
            trim($fullName),
            trim((string) $office),
            trim((string) $state),
        ], fn ($value) => $value !== ''));

        $query = implode(' ', $queryParts);

        return [
            'wikipedia' => 'https://en.wikipedia.org/w/index.php?search=' . rawurlencode($query),
            'youtube' => 'https://www.youtube.com/results?search_query=' . rawurlencode($query),
            'cspan' => 'https://www.c-span.org/search/?searchtype=Videos&ssearch=' . rawurlencode($query),
        ];
    }

    protected function mergeCandidates(Collection $existingCandidates, Collection $discoveredCandidates): Collection
    {
        return $existingCandidates
            ->merge($discoveredCandidates)
            ->unique('id')
            ->sort(function ($left, $right) {
                $leftActive = (int) ($left->active_campaigns_count ?? 0);
                $rightActive = (int) ($right->active_campaigns_count ?? 0);
                if ($leftActive !== $rightActive) {
                    return $rightActive <=> $leftActive;
                }

                $leftVerified = (int) ($left->verified_official ?? 0);
                $rightVerified = (int) ($right->verified_official ?? 0);
                if ($leftVerified !== $rightVerified) {
                    return $rightVerified <=> $leftVerified;
                }

                return strcasecmp((string) ($left->full_name ?? ''), (string) ($right->full_name ?? ''));
            })
            ->values();
    }

    /**
     * @param array<string, mixed>|null $lookupResult
     */
    protected function recordDistrictLookupSearch(
        Request $request,
        string $address,
        ?array $lookupResult,
        ?string $error,
        int $discoveredOfficialsCount,
        ?array $voterInfo = null
    ): void {
        $foundLocations = $this->extractFoundLocationsFromVoterInfo($voterInfo);

        try {
            DistrictLookupSearch::create([
                'query_address' => $address,
                'matched_address' => $lookupResult['matched_address'] ?? null,
                'state' => $lookupResult['state'] ?? null,
                'district_number' => $lookupResult['district_number'] ?? null,
                'district_code' => $lookupResult['district_code'] ?? null,
                'resolved' => $lookupResult !== null,
                'source' => $lookupResult['source'] ?? null,
                'error_message' => $error,
                'discovered_officials_count' => max(0, $discoveredOfficialsCount),
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 65535),
                'payload' => [
                    'lookup_result' => $lookupResult,
                    'voter_info' => $voterInfo,
                    'found_locations' => $foundLocations,
                    'integrations' => [
                        'google_civic_voterinfo' => [
                            'configured' => ! empty(config('services.google.civic_api_key')),
                            'has_response' => is_array($voterInfo),
                            'found_locations_count' => count($foundLocations),
                            'found_location_types' => array_values(array_unique(array_map(
                                fn (array $location): string => (string) ($location['type'] ?? ''),
                                $foundLocations,
                            ))),
                        ],
                    ],
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to record district lookup search', [
                'address' => $address,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Display a directory of all active politicians on the platform.
        *
        * Public browsing is view-only. Guests may research profiles and transparency
        * data without entering any earning flow.
     */
    public function index(Request $request)
    {
        $isGuestBrowsing = ! auth()->check();
        // This route is intentionally public, so rely on user_type for layout selection
        // instead of Spatie role lookups that can throw when role seed data is out of sync.
        $useVoterLayout = auth()->check() && auth()->user()?->user_type === 'voter';
        $zipInput = trim((string) $request->input('zip', ''));

        if ($useVoterLayout && $zipInput === '') {
            $zipInput = trim((string) (auth()->user()?->voter?->zip_code ?? ''));
        }

        $normalizedZip = preg_replace('/\D+/', '', $zipInput ?? '') ?? '';
        $zipValidationError = null;

        if ($useVoterLayout) {
            if ($zipInput === '') {
                $zipValidationError = 'ZIP code is required to browse the voter directory.';
            } elseif (! preg_match('/^\d{5}(?:-\d{4})?$/', $zipInput)) {
                $zipValidationError = 'Please enter a valid US ZIP code (e.g. 90210 or 90210-1234).';
            }
        }

        $politicianTable = (new Politician())->getTable();
        $hasZipColumn = Schema::hasColumn($politicianTable, 'zip_code');

        $query = Politician::query()
            ->where('page_published', true)
            ->where('is_active', true)
            ->with(['campaigns' => function($q) {
                $q->where('status', 'active')->where('approval_status', 'approved');
            }]);

        if ($zipInput !== '' && $zipValidationError === null && $hasZipColumn) {
            $zipPrefix = substr($normalizedZip, 0, 5);
            $query->where('zip_code', 'like', $zipPrefix . '%');
        }

        // Search filter
        if ($search = $request->input('q')) {
            $query->where(function($q) use ($search) {
                $q->where('full_name', 'like', '%' . $search . '%')
                  ->orWhere('political_office', 'like', '%' . $search . '%')
                  ->orWhere('bio', 'like', '%' . $search . '%');
            });
        }

        // District filter (free-text, tolerant of formatting differences)
        if ($district = trim((string) $request->input('district', ''))) {
            $query->where('district', 'like', '%' . $district . '%');
        }

        // Topic filter: match profile bio + campaign titles/summaries + initiative text
        if ($topic = trim((string) $request->input('topic', ''))) {
            $query->where(function ($q) use ($topic) {
                $q->where('bio', 'like', '%' . $topic . '%')
                  ->orWhereHas('campaigns', function ($cq) use ($topic) {
                      $cq->where('approval_status', 'approved')
                         ->where(function ($sq) use ($topic) {
                             $sq->where('title', 'like', '%' . $topic . '%')
                                ->orWhere('message_summary', 'like', '%' . $topic . '%');
                         });
                  })
                  ->orWhereHas('initiatives', function ($iq) use ($topic) {
                      $iq->where('is_published', true)
                         ->where(function ($sq) use ($topic) {
                             $sq->where('title', 'like', '%' . $topic . '%')
                                ->orWhere('description', 'like', '%' . $topic . '%');
                         });
                  });
            });
        }

        // Governance level filter
        if ($level = $request->input('level')) {
            $query->where('governance_level', $level);
        }

        // State filter
        if ($state = $request->input('state')) {
            $query->where('state', $state);
        }

        // Party filter
        if ($party = $request->input('party')) {
            $query->where('party_affiliation', $party);
        }

        // Unclaimed-only filter
        if ($request->boolean('unclaimed')) {
            $query->whereNull('user_id');
        }

        // Sorting
        $sortBy = $request->input('sort', 'name');
        try {
            if ($zipValidationError !== null) {
                $politicians = $this->paginateDirectoryResults(
                    politicians: collect(),
                    request: $request,
                );
            } else {
                $politicians = $this->paginateDirectoryResults(
                    politicians: $this->sortDirectoryResults(
                        politicians: $this->collapseDirectoryDuplicates($query->get()),
                        sortBy: $sortBy,
                    ),
                    request: $request,
                );
            }
        } catch (\Throwable $e) {
            Log::warning('Politicians directory failed to load; returning empty results fallback', [
                'error_class' => get_class($e),
                'error' => $e->getMessage(),
                'is_voter_layout' => $useVoterLayout,
            ]);

            $politicians = $this->paginateDirectoryResults(
                politicians: collect(),
                request: $request,
            );
        }

        $states = config('u9itus.us_states', []);
        $governanceLevels = ['Federal', 'State', 'County', 'City', 'School Board', 'Judicial'];
        $parties = ['Democratic', 'Republican', 'Independent', 'Libertarian', 'Green'];

        // Load latest news headlines for each politician in ONE query (prevents N+1).
        $latestNewsMap = [];
        try {
            if (Schema::hasTable('candidate_news_articles')) {
                $politicianIds = collect($politicians->items())->pluck('id')->filter()->all();
                if (!empty($politicianIds)) {
                    \App\Models\CandidateNewsArticle::query()
                        ->whereIn('politician_id', $politicianIds)
                        ->where('scraped_at', '>=', now()->subHours(24))
                        ->orderByDesc('published_at')
                        ->get()
                        ->groupBy('politician_id')
                        ->each(function ($articles, $politicianId) use (&$latestNewsMap) {
                            $latestNewsMap[$politicianId] = $articles->first();
                        });
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to load directory news headlines', ['error' => $e->getMessage()]);
        }

        $view = $useVoterLayout
            ? 'standalone.voter.politicians-directory'
            : 'standalone.public.politicians-directory';

        return view($view, compact(
            'politicians',
            'states',
            'governanceLevels',
            'parties',
            'isGuestBrowsing',
            'zipInput',
            'zipValidationError',
            'latestNewsMap'
        ));
    }

    /**
     * Collapse obvious duplicate imported federal profiles for the same unclaimed official.
     *
     * We only collapse rows when they share a strong public identity signal
     * (same normalized name + state + profile photo or website), which avoids
     * merging unrelated local/state officials who happen to share a name.
     *
     * @param Collection<int, Politician> $politicians
     * @return Collection<int, Politician>
     */
    protected function collapseDirectoryDuplicates(Collection $politicians): Collection
    {
        return $politicians
            ->groupBy(fn (Politician $politician): string => $this->directoryIdentityKey($politician))
            ->map(function (Collection $group): Politician {
                return $group->reduce(function (?Politician $preferred, Politician $candidate): Politician {
                    if ($preferred === null) {
                        return $candidate;
                    }

                    return $this->preferDirectoryPolitician($preferred, $candidate);
                });
            })
            ->values();
    }

    protected function isFederalOfficial(Politician $politician): bool
    {
        if (strcasecmp((string) $politician->governance_level, 'Federal') === 0) {
            return true;
        }

        // Some records are imported with incorrect governance_level (e.g. 'Local') but
        // have a clearly federal office title. Detect those to avoid duplicate display.
        $office = strtolower(trim((string) ($politician->political_office ?? '')));
        $federalKeywords = ['united states', 'u.s. senator', 'u.s. representative', 'us senator', 'us representative'];
        foreach ($federalKeywords as $keyword) {
            if (str_contains($office, $keyword)) {
                return true;
            }
        }

        return false;
    }

    protected function directoryIdentityKey(Politician $politician): string
    {
        if ($politician->user_id !== null) {
            return 'claimed:' . $politician->id;
        }

        if (! $this->isFederalOfficial($politician)) {
            return 'non-federal:' . $politician->id;
        }

        $name  = strtolower(trim((string) $politician->full_name));
        $state = strtoupper(trim((string) $politician->state));

        if ($name === '' || $state === '') {
            return 'fallback:' . $politician->id;
        }

        // Primary key: ballotpedia_id is the strongest identity signal — two rows
        // with the same ballotpedia_id are always the same person regardless of
        // office title, photo URL, or governance_level discrepancies.
        $bpId = trim((string) ($politician->ballotpedia_id ?? ''));
        if ($bpId !== '') {
            return 'federal-bp:' . $bpId;
        }

        // Secondary: collapse by name + state alone for unclaimed federal officials.
        // This handles the common case where the same person is imported multiple
        // times under different office titles (House→Senate transition, or
        // governance_level=Local vs Federal mismatch from different import sources).
        // Photo/website are intentionally NOT used as sub-keys because they often
        // differ between import sources for the same real person.
        return 'federal:' . $name . '|' . $state;
    }

    protected function preferDirectoryPolitician(Politician $preferred, Politician $candidate): Politician
    {
        // 1. Prefer the record whose office matches the person's most recent role.
        //    A U.S. Senator record beats a U.S. Representative record (House→Senate transition).
        $senateKeywords = ['senator', 'senate'];
        $prefIsSenate  = $this->officeContainsKeyword($preferred, $senateKeywords);
        $candIsSenate  = $this->officeContainsKeyword($candidate, $senateKeywords);
        if ($candIsSenate !== $prefIsSenate) {
            return $candIsSenate ? $candidate : $preferred;
        }

        // 2. Prefer verified_official
        if ((bool) $candidate->verified_official !== (bool) $preferred->verified_official) {
            return $candidate->verified_official ? $candidate : $preferred;
        }

        // 3. Prefer the record with an active campaign (most engaged on platform)
        $preferredCampaigns = $preferred->campaigns->count();
        $candidateCampaigns = $candidate->campaigns->count();
        if ($candidateCampaigns !== $preferredCampaigns) {
            return $candidateCampaigns > $preferredCampaigns ? $candidate : $preferred;
        }

        // 4. Prefer correct governance_level (Federal > Local for federal officials)
        $prefFederal = strcasecmp((string) $preferred->governance_level, 'Federal') === 0;
        $candFederal = strcasecmp((string) $candidate->governance_level, 'Federal') === 0;
        if ($candFederal !== $prefFederal) {
            return $candFederal ? $candidate : $preferred;
        }

        // 5. Prefer more recently updated record
        $candidateUpdated = $candidate->updated_at?->getTimestamp() ?? 0;
        $preferredUpdated = $preferred->updated_at?->getTimestamp() ?? 0;
        if ($candidateUpdated !== $preferredUpdated) {
            return $candidateUpdated > $preferredUpdated ? $candidate : $preferred;
        }

        return $candidate->id > $preferred->id ? $candidate : $preferred;
    }

    private function officeContainsKeyword(Politician $politician, array $keywords): bool
    {
        $office = strtolower(trim((string) ($politician->political_office ?? '')));
        foreach ($keywords as $kw) {
            if (str_contains($office, $kw)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param Collection<int, Politician> $politicians
     * @return Collection<int, Politician>
     */
    protected function sortDirectoryResults(Collection $politicians, string $sortBy): Collection
    {
        return match ($sortBy) {
            'recent' => $politicians->sortByDesc(fn (Politician $politician) => $politician->created_at?->getTimestamp() ?? 0)->values(),
            'verified' => $politicians
                ->sortBy(fn (Politician $politician) => [
                    $politician->verified_official ? 0 : 1,
                    strtolower((string) $politician->full_name),
                ])
                ->values(),
            default => $politicians->sortBy(fn (Politician $politician) => strtolower((string) $politician->full_name))->values(),
        };
    }

    /**
     * @param Collection<int, Politician> $politicians
     */
    protected function paginateDirectoryResults(Collection $politicians, Request $request, int $perPage = 24): LengthAwarePaginator
    {
        $page = max(1, (int) $request->integer('page', 1));
        $items = $politicians->slice(($page - 1) * $perPage, $perPage)->values();

        return new LengthAwarePaginator(
            items: $items,
            total: $politicians->count(),
            perPage: $perPage,
            currentPage: $page,
            options: [
                'path' => $request->url(),
                'query' => $request->query(),
            ],
        );
    }

    /**
     * Display the politician's public profile page.
     *
     * Slug format: {5-char-uuid-prefix}-{seo-readable-name}
     * e.g. /p/a3f9b-mayor-john-smith-chicago
     */
    public function show(Request $request, string $slug)
    {
        $politician = $this->resolvePublicPolitician($slug);

        // If still not found, throw 404
        if (!$politician) {
            abort(404);
        }

        $isGuestBrowsing = ! auth()->check();

        // Eager-load what we need for the public page
        $politician->load(['page', 'initiatives' => fn($q) => $q->published()->ordered()]);

        // Page config (use defaults if politician hasn't saved one yet)
        $page = $politician->page ?? new \App\Models\PoliticianPage(\App\Models\PoliticianPage::defaults($politician->id));

        // Separate current campaign activity from archived campaign history.
        $runningCampaigns = $politician->campaigns()
            ->with('topics')
            ->where('approval_status', ApprovalStatus::Approved)
            ->whereIn('status', [
                CampaignStatus::Active,
                CampaignStatus::Scheduled,
                CampaignStatus::Paused,
            ])
            ->orderByRaw("case when status = ? then 0 when status = ? then 1 else 2 end", [
                CampaignStatus::Active->value,
                CampaignStatus::Scheduled->value,
            ])
            ->orderByDesc('updated_at')
            ->take(6)
            ->get();

        $pastCampaigns = $politician->campaigns()
            ->with('topics')
            ->where('approval_status', ApprovalStatus::Approved)
            ->whereIn('status', [
                CampaignStatus::Completed,
                CampaignStatus::Cancelled,
            ])
            ->orderByDesc('completed_at')
            ->orderByDesc('updated_at')
            ->take(8)
            ->get();

        $publicBoardQuestions = VoterWatchReport::query()
            ->messages()
            ->where(function ($query) {
                $query->where(function ($approved) {
                    $approved->where('public_visibility', 'approved')
                        ->where('is_public_board', true);
                })->orWhere(function ($legacy) {
                    $legacy->where('status', 'resolved')
                        ->whereNotNull('admin_notes');
                });
            })
            ->whereHas('campaign', function ($query) use ($politician) {
                $query->where('politician_id', $politician->id)
                    ->where('approval_status', ApprovalStatus::Approved);
            })
            ->with([
                'campaign:id,title',
                'campaignRepliedBy:id,name',
            ])
            ->orderByDesc('campaign_replied_at')
            ->orderByDesc('published_at')
            ->orderByDesc('updated_at')
            ->take(12)
            ->get();

        $initiatives = $politician->initiatives;

        $transparencyData = $this->buildTransparencyData($politician);
        $digDeeperData = $this->buildDigDeeperData($politician, $transparencyData);

        // Load candidate record (e.g. congress_legislators import) to show term/election status
        $termInfo = null;
        $candidateRecord = \App\Models\ElectionCandidateRecord::where('state', $politician->state)
            ->whereRaw('LOWER(full_name) = ?', [strtolower((string) $politician->full_name)])
            ->orderByDesc('last_seen_at')
            ->first();

        if ($candidateRecord && is_array($candidateRecord->payload['terms'] ?? null)) {
            $terms = collect($candidateRecord->payload['terms'])
                ->sortByDesc('start')
                ->first();
            if ($terms) {
                $termInfo = [
                    'start' => $terms['start'] ?? null,
                    'end'   => $terms['end']   ?? null,
                    'type'  => $terms['type']  ?? null,
                ];
            }
        }

        // Build Open Graph meta
        $ogTitle       = $politician->full_name . ' — ' . ($politician->political_office ?? 'Politician');
        $ogDescription = $politician->bio
            ? \Illuminate\Support\Str::limit($politician->bio, 160)
            : "Research {$politician->full_name}'s campaign messages, profile, and public records on U9itus.";
        $ogImage       = $page->hero_banner_url ?? $politician->profile_photo_url ?? null;
        $ogUrl         = route('politician.public.show', $slug);

        // Load cached news articles only — never trigger live RSS fetching on a web request.
        // The artisan command (candidates:refresh-news) handles background fetching on a schedule.
        $newsArticles = collect();
        try {
            if (Schema::hasTable('candidate_news_articles')) {
                $newsArticles = \App\Models\CandidateNewsArticle::query()
                    ->where('politician_id', $politician->id)
                    ->orderByDesc('published_at')
                    ->limit(60)
                    ->get();
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to load news articles for profile', [
                'politician_id' => $politician->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Build source map for the news filter pills.
        $nationalSources = config('news_sources.national', []);
        $stateSources    = config('news_sources.state.' . strtoupper((string) ($politician->state ?? '')), []);
        $sourceMap = [];
        foreach (array_merge($nationalSources, $stateSources) as $src) {
            $sourceMap[$src['id']] = ['label' => $src['label'], 'icon' => $src['icon']];
        }
        $sourceMap['newsapi'] = ['label' => 'NewsAPI', 'icon' => '📰'];
        $sourceMap['gnews']   = ['label' => 'GNews',   'icon' => '📰'];

        $activeProviders = $newsArticles->pluck('provider')->unique()->values()->all();
        $articlesJson    = $newsArticles->map(fn($a) => [
            'id'           => $a->id,
            'provider'     => $a->provider,
            'headline'     => $a->headline,
            'source_name'  => $a->source_name,
            'source_url'   => $a->source_url,
            'snippet'      => $a->snippet,
            'image_url'    => $a->image_url,
            'published_at' => $a->published_at?->diffForHumans(),
        ])->values()->toJson();

        return view('standalone.public.profile', compact(
            'politician',
            'page',
            'runningCampaigns',
            'pastCampaigns',
            'publicBoardQuestions',
            'initiatives',
            'transparencyData',
            'digDeeperData',
            'termInfo',
            'ogTitle',
            'ogDescription',
            'ogImage',
            'ogUrl',
            'isGuestBrowsing',
            'newsArticles',
            'sourceMap',
            'activeProviders',
            'articlesJson'
        ));
    }

    protected function resolvePublicPolitician(string $slug): ?Politician
    {
        $politician = Politician::where('slug', $slug)
            ->where('page_published', true)
            ->where('is_active', true)
            ->first();

        if ($politician || ! auth()->check()) {
            return $politician;
        }

        $user = auth()->user();

        if ($user->user_type !== 'politician' || ! $user->politician) {
            return null;
        }

        return Politician::where('slug', $slug)
            ->where('id', $user->politician->id)
            ->where('is_active', true)
            ->first();
    }

    protected function buildTransparencyData(Politician $politician): array
    {
        // Donor/finance data is public record — show for all profiles including unclaimed.
        // For unclaimed federal profiles we always attempt OpenSecrets + FEC even if the
        // politician hasn't explicitly enabled those toggles, so voters can see funding data.
        $isUnclaimed = $politician->user_id === null;

        $services = [
            'ballotpedia' => [$politician->show_ballotpedia_data || $isUnclaimed, \App\Services\BallotpediaService::class],
            'opensecrets' => [$politician->show_opensecrets_data || $isUnclaimed, \App\Services\OpenSecretsService::class],
            'votesmart'   => [$politician->show_votesmart_data   || $isUnclaimed, \App\Services\VoteSmartService::class],
            'fec'         => [$politician->show_fec_data         || $isUnclaimed, \App\Services\FECService::class],
        ];

        $transparencyData = [];

        foreach ($services as $key => [$enabled, $serviceClass]) {
            if (! $enabled) {
                continue;
            }

            try {
                $data = app($serviceClass)->getDisplayData($politician);
            } catch (\Throwable $e) {
                Log::warning('Transparency provider failed for public profile', [
                    'provider' => $key,
                    'politician_id' => $politician->id,
                    'error' => $e->getMessage(),
                ]);
                $data = null;
            }

            if (is_array($data) && ! empty($data['source'])) {
                $transparencyData[$key] = $data;
            }
        }

        return $transparencyData;
    }

    protected function sanitizePublicWebsiteUrl(mixed $value): ?string
    {
        $url = trim((string) $value);
        if ($url === '') {
            return null;
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $path = strtolower((string) parse_url($url, PHP_URL_PATH));

        // Never expose API endpoints as public "website" links.
        if ($host === 'api.congress.gov' || str_starts_with($path, '/v3')) {
            return null;
        }

        return $url;
    }

    /**
     * Build voter-friendly transparency summaries and source panel states.
     *
     * @param array<string, array<string, mixed>> $transparencyData
     * @return array<string, mixed>
     */
    protected function buildDigDeeperData(Politician $politician, array $transparencyData): array
    {
        // Show the Dig Deeper panel whenever:
        //  (a) the politician is fully verified, OR
        //  (b) the profile is unclaimed and we actually have transparency data
        //      (enrich-statewide auto-created profiles for public figures like
        //       governors should show donor/finance info even without platform
        //       verification).
        $isUnclaimed = $politician->user_id === null;
        $hasData     = !empty($transparencyData);
        if ($politician->verification_status !== 'verified' && ! ($isUnclaimed && $hasData)) {
            return [];
        }

        // Unclaimed auto-imported public profiles (e.g. scraped governors) show all
        // sources so voters can see donor/finance data without needing the politician
        // to configure their show_* flags. This only applies to UNVERIFIED unclaimed
        // profiles — if verification_status='verified', the politician has explicitly
        // chosen which sources to show and we must respect those flags.
        $enableByDefault = $isUnclaimed && $politician->verification_status !== 'verified';

        $fecService = app(\App\Services\FECService::class);
        $sources = [
            'ballotpedia' => [
                'label' => 'Ballotpedia',
                'enabled' => (bool) $politician->show_ballotpedia_data || $enableByDefault,
                'unavailable_reason' => 'No public profile data was available from Ballotpedia yet.',
            ],
            'opensecrets' => [
                'label' => 'OpenSecrets',
                'enabled' => (bool) $politician->show_opensecrets_data || $enableByDefault,
                'unavailable_reason' => 'No campaign finance summary was available from OpenSecrets yet.',
            ],
            'votesmart' => [
                'label' => 'Vote Smart',
                'enabled' => (bool) $politician->show_votesmart_data || $enableByDefault,
                'unavailable_reason' => 'No issue positions or ratings were available from Vote Smart yet.',
            ],
            'fec' => [
                'label' => 'Federal Election Commission',
                'enabled' => (bool) $politician->show_fec_data || $enableByDefault,
                'unavailable_reason' => $fecService->isFederalCandidate($politician)
                    ? 'No current filing summary was available from the FEC yet.'
                    : 'FEC reporting applies to federal offices only.',
            ],
        ];

        $panels = [];
        $enabledCount = 0;
        $availableCount = 0;

        foreach ($sources as $key => $source) {
            if (! $source['enabled']) {
                continue;
            }

            $enabledCount++;
            $details = $transparencyData[$key] ?? null;

            if (is_array($details)) {
                $availableCount++;
            }

            $panels[] = [
                'key' => $key,
                'label' => $source['label'],
                'status' => is_array($details) ? 'available' : 'unavailable',
                'source_url' => is_array($details) ? ($details['source_url'] ?? null) : null,
                'summary' => is_array($details) ? $this->buildDigDeeperSummary($details) : null,
                'section_count' => is_array($details) ? count($details['sections'] ?? []) : 0,
                'sections' => is_array($details) ? ($details['sections'] ?? []) : [],
                'unavailable_reason' => is_array($details) ? null : $source['unavailable_reason'],
            ];
        }

        $localCandidateContext = $this->buildLocalCandidateDigDeeperContext($politician);

        return [
            'enabled_sources_count' => $enabledCount,
            'available_sources_count' => $availableCount,
            'panels' => $panels,
            'local_candidate_context' => $localCandidateContext,
        ];
    }

    /**
     * Build optional local candidate context for Dig Deeper.
     *
     * This enrichment is fail-safe and will never block profile rendering.
     *
     * @return array<string, mixed>|null
     */
    protected function buildLocalCandidateDigDeeperContext(Politician $politician): ?array
    {
        if ($politician->state === null || $politician->state === '') {
            return null;
        }

        try {
            /** @var \App\Services\LocalCandidateAggregator $aggregator */
            $aggregator = app(\App\Services\LocalCandidateAggregator::class);

            $records = $aggregator->findByState(
                state: (string) $politician->state,
                governanceLevels: [],
                options: [
                    'exclude_federal' => false,
                ],
            );

            return [
                'state' => strtoupper((string) $politician->state),
                'candidate_count' => $records->count(),
                'sources' => $records
                    ->pluck('source')
                    ->filter(fn ($source) => is_string($source) && $source !== '')
                    ->unique()
                    ->values()
                    ->all(),
            ];
        } catch (\Throwable $e) {
            Log::info('Local candidate Dig Deeper context unavailable', [
                'politician_id' => $politician->id,
                'state' => $politician->state,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @param array<string, mixed> $details
     */
    protected function buildDigDeeperSummary(array $details): string
    {
        $summaryValues = collect($details['summary'] ?? [])
            ->filter(fn ($value) => is_scalar($value) && trim((string) $value) !== '')
            ->take(2)
            ->map(fn ($value) => (string) $value)
            ->values();

        if ($summaryValues->isNotEmpty()) {
            return $summaryValues->implode(' • ');
        }

        $itemCount = collect($details['sections'] ?? [])
            ->sum(function ($section) {
                if (! is_array($section)) {
                    return 0;
                }

                $items = $section['items'] ?? [];

                return is_array($items) ? count($items) : 0;
            });

        if ($itemCount > 0) {
            return $itemCount . ' public records available';
        }

        return 'Source connected';
    }

    /**
     * @param array<string, mixed> $lookupResult
     * @param array<string, string> $states
     */
    protected function findCandidatesForDistrict(array $lookupResult, array $states, array $districtHints = []): Collection
    {
        $state = strtoupper((string) ($lookupResult['state'] ?? ''));
        $districtNumber = $lookupResult['district_number'] ?? null;

        $query = Politician::query()
            ->where('page_published', true)
            ->where('is_active', true)
            ->withCount([
                'campaigns as active_campaigns_count' => function ($q) {
                    $q->where('status', 'active')
                      ->where('approval_status', 'approved');
                },
            ]);

        if ($state !== '') {
            $stateName = $states[$state] ?? null;

            $query->where(function ($q) use ($state, $stateName) {
                $q->whereRaw('UPPER(state) = ?', [$state]);

                if ($stateName) {
                    $q->orWhereRaw('LOWER(state) = ?', [strtolower($stateName)]);
                }
            });
        }

        $variants = [];

        if ($districtNumber) {
            $variants = array_merge($variants, $this->districtVariants($state, (string) $districtNumber));
        }

        if ($districtHints !== []) {
            $variants = array_merge($variants, $districtHints);
        }

        $variants = array_values(array_unique(array_filter($variants, fn ($value) => trim((string) $value) !== '')));

        if ($variants !== []) {
            $query->where(function ($q) use ($variants) {
                foreach ($variants as $variant) {
                    $q->orWhere('district', 'like', '%' . $variant . '%');
                }
            });
        }

        return $query
            ->orderByDesc('active_campaigns_count')
            ->orderByDesc('verified_official')
            ->orderBy('full_name')
            ->limit(50)
            ->get();
    }

    /**
     * Build flexible district match strings to handle inconsistent formatting.
     *
     * @return array<int, string>
     */
    protected function districtVariants(string $state, string $districtNumber): array
    {
        $districtNumber = strtoupper(trim($districtNumber));
        $variants = [$districtNumber];

        if ($districtNumber !== 'AL') {
            $numeric = (string) ((int) $districtNumber);
            $padded = str_pad($numeric, 2, '0', STR_PAD_LEFT);

            $variants = array_merge($variants, [
                $numeric,
                $padded,
                'District ' . $numeric,
                'CD ' . $numeric,
                'CD-' . $numeric,
            ]);

            if ($state !== '') {
                $variants[] = $state . '-' . $padded;
                $variants[] = $state . '-' . $numeric;
            }
        } else {
            $variants = array_merge($variants, ['At-Large', 'At Large']);

            if ($state !== '') {
                $variants[] = $state . '-AL';
            }
        }

        return array_values(array_unique($variants));
    }

    /**
     * @param Collection<int, Politician> $candidates
     * @return Collection<int, Politician>
     */
    protected function attachFinanceSnapshotsToPublishedCandidates(Collection $candidates): Collection
    {
        if ($candidates->isEmpty()) {
            return $candidates;
        }

        return $candidates->map(function (Politician $candidate): Politician {
            $candidate->setAttribute('finance_snapshot', $this->buildFinanceSnapshotForPolitician($candidate));

            return $candidate;
        });
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function buildFinanceSnapshotForPolitician(Politician $politician): ?array
    {
        $sources = [];

        if ((bool) $politician->show_fec_data) {
            try {
                $fecDetails = app(\App\Services\FECService::class)->getDisplayData($politician);
                $summary = $this->normalizeFinanceSummary((array) ($fecDetails['summary'] ?? []));

                if ($summary !== []) {
                    $sources[] = [
                        'key' => 'fec',
                        'label' => 'FEC',
                        'summary' => $summary,
                        'source_url' => $fecDetails['source_url'] ?? null,
                    ];
                }
            } catch (\Throwable $e) {
                Log::info('District lookup finance snapshot unavailable from FEC', [
                    'politician_id' => $politician->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ((bool) $politician->show_opensecrets_data && $politician->user_id !== null) {
            try {
                $openSecretsDetails = app(\App\Services\OpenSecretsService::class)->getDisplayData($politician);
                $summary = $this->normalizeFinanceSummary((array) ($openSecretsDetails['summary'] ?? []));

                if ($summary !== []) {
                    $sources[] = [
                        'key' => 'opensecrets',
                        'label' => 'OpenSecrets',
                        'summary' => $summary,
                        'source_url' => $openSecretsDetails['source_url'] ?? null,
                    ];
                }
            } catch (\Throwable $e) {
                Log::info('District lookup finance snapshot unavailable from OpenSecrets', [
                    'politician_id' => $politician->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($sources === []) {
            return null;
        }

        return [
            'sources' => $sources,
            'primary' => $sources[0],
        ];
    }

    /**
     * @param array<string, mixed> $summary
     * @return array<string, string>
     */
    protected function normalizeFinanceSummary(array $summary): array
    {
        $receipts = $this->firstPresentSummaryValue($summary, ['receipts', 'total_raised']);
        $disbursements = $this->firstPresentSummaryValue($summary, ['disbursements', 'total_spent']);

        $normalized = [
            'cycle' => $this->firstPresentSummaryValue($summary, ['cycle']),
            'receipts' => $receipts,
            'disbursements' => $disbursements,
            'cash_on_hand' => $this->firstPresentSummaryValue($summary, ['cash_on_hand']),
            'debt' => $this->firstPresentSummaryValue($summary, ['debt']),
        ];

        return collect($normalized)
            ->filter(fn (?string $value): bool => $value !== null && trim($value) !== '')
            ->map(fn (string $value): string => trim($value))
            ->all();
    }

    /**
     * @param array<string, mixed> $summary
     * @param array<int, string> $keys
     */
    protected function firstPresentSummaryValue(array $summary, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $summary) || $summary[$key] === null) {
                continue;
            }

            return (string) $summary[$key];
        }

        return null;
    }

    /**
     * @param array<string, mixed>|null $voterInfo
     * @return array<int, array<string, mixed>>
     */
    protected function extractFoundLocationsFromVoterInfo(?array $voterInfo): array
    {
        if (! is_array($voterInfo)) {
            return [];
        }

        $groups = [
            'polling_location' => (array) ($voterInfo['polling_locations'] ?? []),
            'early_vote_site' => (array) ($voterInfo['early_vote_sites'] ?? []),
            'drop_off_location' => (array) ($voterInfo['drop_off_locations'] ?? []),
        ];

        $flattened = [];

        foreach ($groups as $type => $locations) {
            foreach ($locations as $location) {
                if (! is_array($location)) {
                    continue;
                }

                $address = isset($location['address']) && is_array($location['address'])
                    ? $location['address']
                    : [];

                $flattened[] = [
                    'type' => $type,
                    'name' => $location['name'] ?? null,
                    'notes' => $location['notes'] ?? null,
                    'polling_hours' => $location['polling_hours'] ?? null,
                    'voter_services' => $location['voter_services'] ?? null,
                    'start_date' => $location['start_date'] ?? null,
                    'end_date' => $location['end_date'] ?? null,
                    'latitude' => $location['latitude'] ?? null,
                    'longitude' => $location['longitude'] ?? null,
                    'address' => [
                        'line1' => $address['line1'] ?? null,
                        'line2' => $address['line2'] ?? null,
                        'line3' => $address['line3'] ?? null,
                        'city' => $address['city'] ?? null,
                        'state' => $address['state'] ?? null,
                        'zip' => $address['zip'] ?? null,
                    ],
                ];
            }
        }

        return $flattened;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>|null
     */
    protected function financeSnapshotFromRecordPayload(array $payload): ?array
    {
        $summaryCandidates = [
            data_get($payload, 'financial_summary'),
            data_get($payload, 'campaign_finance.summary'),
            data_get($payload, 'campaign_finance'),
            data_get($payload, 'finance.summary'),
            data_get($payload, 'finance'),
            data_get($payload, 'fec.financial_summary'),
            data_get($payload, 'opensecrets.candidate_summary'),
        ];

        $summary = [];
        foreach ($summaryCandidates as $candidateSummary) {
            if (is_array($candidateSummary)) {
                $summary = $this->normalizeFinanceSummary($candidateSummary);
                if ($summary !== []) {
                    break;
                }
            }
        }

        $sourceUrl = collect([
            data_get($payload, 'source_url'),
            data_get($payload, 'finance.source_url'),
            data_get($payload, 'fec.source_url'),
            data_get($payload, 'opensecrets.source_url'),
            data_get($payload, 'fec_url'),
        ])
            ->filter(fn ($value): bool => is_string($value) && trim($value) !== '')
            ->map(fn (string $value): string => trim($value))
            ->first();

        if ($summary === [] && ! is_string($sourceUrl)) {
            return null;
        }

        return [
            'summary' => $summary,
            'source_url' => is_string($sourceUrl) ? $sourceUrl : null,
        ];
    }

    /**
     * @param array<string, mixed> $lookupResult
     * @param array<string, string> $states
     * @return Collection<int, array<string, mixed>>
     */
    protected function findRunningCandidatesForDistrict(array $lookupResult, array $states, array $districtHints = []): Collection
    {
        $state = strtoupper((string) ($lookupResult['state'] ?? ''));
        $districtNumber = trim((string) ($lookupResult['district_number'] ?? ''));

        if ($state === '' || ($districtNumber === '' && $districtHints === [])) {
            return collect();
        }

        $stateName = $states[$state] ?? null;
        $variants = [];

        if ($districtNumber !== '') {
            $variants = array_merge($variants, $this->districtVariants($state, $districtNumber));
        }

        if ($districtHints !== []) {
            $variants = array_merge($variants, $districtHints);
        }

        $variants = array_values(array_unique(array_filter($variants, fn ($value) => trim((string) $value) !== '')));

        if ($variants === []) {
            return collect();
        }

        $recentThreshold = now()->subDays(30)->toDateString();
        // ECRs with no election_date are only shown if last_seen_at is within 180 days,
        // preventing stale imports (e.g. retired members) from surfacing indefinitely.
        $lastSeenCutoff = now()->subDays(180);

        $records = ElectionCandidateRecord::query()
            ->where(function ($q) use ($state, $stateName) {
                $q->whereRaw('UPPER(state) = ?', [$state]);

                if ($stateName) {
                    $q->orWhereRaw('LOWER(state) = ?', [strtolower($stateName)]);
                }
            })
            ->where(function ($q) use ($variants) {
                foreach ($variants as $variant) {
                    $q->orWhere('district', 'like', '%' . $variant . '%');
                }
            })
            ->where(function ($q) use ($recentThreshold, $lastSeenCutoff) {
                $q->where(function ($inner) use ($lastSeenCutoff) {
                    // NULL election_date only valid if the record was seen recently
                    $inner->whereNull('election_date')
                          ->where('last_seen_at', '>=', $lastSeenCutoff);
                })->orWhereDate('election_date', '>=', $recentThreshold);
            })
            // Exclude records marked as eliminated by reconcile-status
            ->whereRaw("COALESCE(payload->>'$.primary_result', '') != 'eliminated'")
            ->orderBy('election_date')
            ->orderByDesc('last_seen_at')
            ->limit(150)
            ->get();

        return $records
            ->map(function (ElectionCandidateRecord $record): array {
                $party = strtolower(trim((string) ($record->party_affiliation ?? '')));
                $payload = is_array($record->payload) ? $record->payload : [];
                $isIncumbent = (bool) data_get($payload, 'incumbent', false)
                    || (bool) data_get($payload, 'is_incumbent', false);

                $score = 0;
                if ($isIncumbent) {
                    $score += 35;
                }

                if (in_array($party, ['democratic', 'republican'], true)) {
                    $score += 25;
                }

                if (! empty($record->election_date)) {
                    $score += 10;
                }

                if (($record->source ?? '') === 'google_civic') {
                    $score += 8;
                }

                $financeSnapshot = $this->financeSnapshotFromRecordPayload($payload);

                return [
                    'full_name' => $record->full_name,
                    'political_office' => $record->political_office,
                    'district' => $record->district,
                    'party_affiliation' => $record->party_affiliation,
                    'election_date' => optional($record->election_date)->toDateString(),
                    'source' => $record->source,
                    'source_label' => $this->formatCandidateSourceLabel($record->source),
                    'finance_snapshot' => $financeSnapshot,
                    'contender_score' => $score,
                ];
            })
            ->unique(function (array $candidate): string {
                return strtolower(trim((string) ($candidate['full_name'] ?? '')))
                    . '|' . strtolower(trim((string) ($candidate['political_office'] ?? '')))
                    . '|' . strtolower(trim((string) ($candidate['district'] ?? '')));
            })
            ->sortByDesc('contender_score')
            ->values();
    }

    /**
     * Build additional district match hints from Google Civic voter contests.
     *
     * Supports state legislative districts so AD-xx / SD-xx profiles can appear
     * alongside congressional district matches.
     *
     * @param array<string, mixed>|null $voterInfo
     * @return array<int, string>
     */
    protected function extractDistrictHintsFromVoterInfo(?array $voterInfo, string $state): array
    {
        if (! is_array($voterInfo) || ! isset($voterInfo['contests']) || ! is_array($voterInfo['contests'])) {
            return [];
        }

        $hints = [];

        foreach ($voterInfo['contests'] as $contest) {
            if (! is_array($contest)) {
                continue;
            }

            $district = isset($contest['district']) && is_array($contest['district'])
                ? $contest['district']
                : [];

            $scope = strtolower(trim((string) ($district['scope'] ?? '')));
            $id = trim((string) ($district['id'] ?? ''));

            if ($id === '' || preg_match('/^\d+$/', $id) !== 1) {
                continue;
            }

            $numeric = (int) $id;
            $padded = str_pad((string) $numeric, 2, '0', STR_PAD_LEFT);
            $statePrefix = $state !== '' ? strtoupper($state) . '-' : '';

            if ($scope === 'statelower') {
                $hints[] = 'AD-' . $numeric;
                $hints[] = 'AD-' . $padded;
                $hints[] = 'Assembly District ' . $numeric;
                $hints[] = 'State Assembly District ' . $numeric;
                $hints[] = $statePrefix . 'AD-' . $numeric;
                $hints[] = $statePrefix . 'AD-' . $padded;
                continue;
            }

            if ($scope === 'stateupper') {
                $hints[] = 'SD-' . $numeric;
                $hints[] = 'SD-' . $padded;
                $hints[] = 'Senate District ' . $numeric;
                $hints[] = 'State Senate District ' . $numeric;
                $hints[] = $statePrefix . 'SD-' . $numeric;
                $hints[] = $statePrefix . 'SD-' . $padded;
            }
        }

        return array_values(array_unique($hints));
    }

    protected function formatCandidateSourceLabel(?string $source): string
    {
        return match ($source) {
            'census_geocoder' => 'Census Geocoder',
            'google_civic' => 'Google Civic',
            'google_civic_voterinfo' => 'Google Civic Voter Info',
            'congress_gov' => 'Congress.gov',
            null, '' => 'Unknown',
            default => ucwords(str_replace('_', ' ', $source)),
        };
    }
}
