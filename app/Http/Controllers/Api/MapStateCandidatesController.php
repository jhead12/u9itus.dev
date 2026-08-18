<?php

namespace App\Http\Controllers\Api;

use App\Models\BallotMeasure;
use App\Models\Citizen;
use App\Models\ElectionCandidateRecord;
use App\Models\Politician;
use App\Models\StateElectionDate;
use App\Support\OfficeCanonicalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Public endpoint powering the interactive 3D U.S. map.
 *
 * Returns gubernatorial and statewide-executive candidates / current
 * officeholders for a given two-letter state code.  No authentication
 * is required — this is voter-facing civic data.
 */
class MapStateCandidatesController
{
    /**
     * Maps two-letter state codes to region names and hex colors.
     * Mirrors the REGIONS constant in the JS map so candidate cards
     * inherit the correct region accent color.
     */
    private const STATE_REGIONS = [
        // Northeast — indigo
        'CT'=>['region'=>'Northeast','color'=>'#6366f1'],
        'ME'=>['region'=>'Northeast','color'=>'#6366f1'],
        'MA'=>['region'=>'Northeast','color'=>'#6366f1'],
        'NH'=>['region'=>'Northeast','color'=>'#6366f1'],
        'NJ'=>['region'=>'Northeast','color'=>'#6366f1'],
        'NY'=>['region'=>'Northeast','color'=>'#6366f1'],
        'PA'=>['region'=>'Northeast','color'=>'#6366f1'],
        'RI'=>['region'=>'Northeast','color'=>'#6366f1'],
        'VT'=>['region'=>'Northeast','color'=>'#6366f1'],
        // Midwest — amber
        'IL'=>['region'=>'Midwest','color'=>'#f59e0b'],
        'IN'=>['region'=>'Midwest','color'=>'#f59e0b'],
        'IA'=>['region'=>'Midwest','color'=>'#f59e0b'],
        'KS'=>['region'=>'Midwest','color'=>'#f59e0b'],
        'MI'=>['region'=>'Midwest','color'=>'#f59e0b'],
        'MN'=>['region'=>'Midwest','color'=>'#f59e0b'],
        'MO'=>['region'=>'Midwest','color'=>'#f59e0b'],
        'NE'=>['region'=>'Midwest','color'=>'#f59e0b'],
        'ND'=>['region'=>'Midwest','color'=>'#f59e0b'],
        'OH'=>['region'=>'Midwest','color'=>'#f59e0b'],
        'SD'=>['region'=>'Midwest','color'=>'#f59e0b'],
        'WI'=>['region'=>'Midwest','color'=>'#f59e0b'],
        // South — red
        'AL'=>['region'=>'South','color'=>'#ef4444'],
        'AR'=>['region'=>'South','color'=>'#ef4444'],
        'DE'=>['region'=>'South','color'=>'#ef4444'],
        'FL'=>['region'=>'South','color'=>'#ef4444'],
        'GA'=>['region'=>'South','color'=>'#ef4444'],
        'KY'=>['region'=>'South','color'=>'#ef4444'],
        'LA'=>['region'=>'South','color'=>'#ef4444'],
        'MD'=>['region'=>'South','color'=>'#ef4444'],
        'MS'=>['region'=>'South','color'=>'#ef4444'],
        'NC'=>['region'=>'South','color'=>'#ef4444'],
        'OK'=>['region'=>'South','color'=>'#ef4444'],
        'SC'=>['region'=>'South','color'=>'#ef4444'],
        'TN'=>['region'=>'South','color'=>'#ef4444'],
        'TX'=>['region'=>'South','color'=>'#ef4444'],
        'VA'=>['region'=>'South','color'=>'#ef4444'],
        'WV'=>['region'=>'South','color'=>'#ef4444'],
        'DC'=>['region'=>'South','color'=>'#ef4444'],
        // West — emerald
        'AK'=>['region'=>'West','color'=>'#10b981'],
        'AZ'=>['region'=>'West','color'=>'#10b981'],
        'CA'=>['region'=>'West','color'=>'#10b981'],
        'CO'=>['region'=>'West','color'=>'#10b981'],
        'HI'=>['region'=>'West','color'=>'#10b981'],
        'ID'=>['region'=>'West','color'=>'#10b981'],
        'MT'=>['region'=>'West','color'=>'#10b981'],
        'NV'=>['region'=>'West','color'=>'#10b981'],
        'NM'=>['region'=>'West','color'=>'#10b981'],
        'OR'=>['region'=>'West','color'=>'#10b981'],
        'UT'=>['region'=>'West','color'=>'#10b981'],
        'WA'=>['region'=>'West','color'=>'#10b981'],
        'WY'=>['region'=>'West','color'=>'#10b981'],
    ];

    /**
     * Offices we surface on the map panel, in display order.
     */
    private const STATEWIDE_OFFICES = OfficeCanonicalizer::STATEWIDE_OFFICES;

    /**
     * GET /api/v1/map/state-candidates?state=CA
     */
    public function __invoke(Request $request): JsonResponse
    {
        $state = strtoupper(trim((string) $request->query('state', '')));

        if ($state === '' || strlen($state) !== 2) {
            return response()->json(['error' => 'Provide a valid two-letter state code.'], 422);
        }

        try {
            $data = Cache::remember("map_state_candidates_{$state}", 3600, function () use ($state) {
                return $this->buildStateData($state);
            });
        } catch (\Throwable $e) {
            Log::error('MapStateCandidatesController: failed to build state data', [
                'state' => $state,
                'error' => $e->getMessage(),
            ]);

            $regionInfo = self::STATE_REGIONS[$state] ?? ['region' => 'Unknown', 'color' => '#64748b'];

            return response()->json([
                'state' => $state,
                'region' => $regionInfo['region'],
                'region_color' => $regionInfo['color'],
                'total' => 0,
                'offices' => [],
                'house_candidates' => [],
                'city_officials' => [],
                'ballot_measures' => [],
                'election_dates' => [],
                'office_roles' => $this->officeRoles(),
                'population' => null,
                'district_populations' => [],
            ]);
        }

        return response()->json($data);
    }

    /**
     * Build the full state data payload (result is cached by __invoke).
     *
     * @return array<string, mixed>
     */
    private function buildStateData(string $state): array
    {
        if ($state === '' || strlen($state) !== 2) {
            // Should never happen — validated before cache call.
            return [];
        }

        // ── 1. Seated statewide officeholders on the platform ─────────────────
        // Only pull SEATED politicians from the platform table for statewide offices.
        // Running candidates for statewide races come exclusively from
        // election_candidate_records (which have primary_result tracking).
        // This avoids showing every primary candidate that the sync workflow
        // imported but never reconciled after the primary.
        $platformPoliticians = Politician::query()
            ->whereRaw('UPPER(COALESCE(state, \'\')) = ?', [$state])
            ->where('is_active', true)
            ->whereRaw('LOWER(COALESCE(governance_level, \'\')) = ?', ['state'])
            ->whereIn('term_status', ['seated', 'active'])
            ->with(['publicBadges.topic'])
            ->get(['id', 'uuid', 'full_name', 'political_office', 'party_affiliation',
                   'profile_photo_url', 'slug', 'is_running_candidate',
                   'term_status', 'verified_official', 'ballotpedia_id',
                   'website_url', 'bio', 'term_ends_on']);

        // Lost statewide candidates on the platform should also suppress matching
        // scraped running rows when payload.primary_result is missing/stale.
        $lostPlatformKeys = [];
        Politician::query()
            ->whereRaw('UPPER(COALESCE(state, \'\')) = ?', [$state])
            ->whereRaw('LOWER(COALESCE(governance_level, \'\')) = ?', ['state'])
            ->whereRaw('LOWER(COALESCE(term_status, \'\')) = ?', ['lost'])
            ->get(['full_name', 'political_office'])
            ->each(function ($pol) use (&$lostPlatformKeys) {
                $canonical = $this->canonicalise($pol->political_office);
                $key = strtolower(trim((string) $pol->full_name)) . '|' . strtolower($canonical);
                $lostPlatformKeys[$key] = true;
            });

        // ── 2. Scraped ElectionCandidateRecords (not yet on platform) ─────────
        $scrapedRecords = ElectionCandidateRecord::query()
            ->whereRaw('UPPER(COALESCE(state, \'\')) = ?', [$state])
            ->whereRaw('LOWER(COALESCE(governance_level, \'\')) = ?', ['state'])
            ->get(['id', 'full_name', 'political_office', 'party_affiliation',
                   'election_date', 'source', 'external_candidate_id', 'payload']);

        // If a scraped candidate row has already been marked eliminated,
        // suppress matching platform "running" cards for the same person+office.
        $eliminatedRunningKeys = [];
        foreach ($scrapedRecords as $rec) {
            $payload = is_array($rec->payload) ? $rec->payload : [];
            $primaryResult = strtolower(trim((string) ($payload['primary_result'] ?? '')));
            $resultStatus = strtolower(trim((string) ($payload['result_status'] ?? '')));
            $status = strtolower(trim((string) ($payload['status'] ?? '')));
            if (
                $primaryResult === 'eliminated'
                || in_array($resultStatus, ['lost', 'eliminated', 'defeated'], true)
                || in_array($status, ['lost', 'eliminated', 'defeated'], true)
            ) {
                $canonical = $this->canonicalise($rec->political_office);
                $key = strtolower(trim((string) $rec->full_name)) . '|' . strtolower($canonical);
                $eliminatedRunningKeys[$key] = true;
            }
        }

        // Merge explicit ECR eliminations with platform-lost candidates.
        $excludedRunningKeys = $eliminatedRunningKeys + $lostPlatformKeys;

        // ── 2b. Federal (House + Senate) politicians for this state ───────────
        $housePoliticians = Politician::query()
            ->whereRaw('UPPER(COALESCE(state, \'\')) = ?', [$state])
            ->where('is_active', true)
            ->whereRaw('LOWER(COALESCE(governance_level, \'\')) = ?', ['federal'])
            ->where(fn($q) => $q->where('term_status', '!=', 'lost')->orWhereNull('term_status'))
            ->with(['publicBadges.topic'])
            ->get(['id', 'uuid', 'full_name', 'political_office', 'party_affiliation',
                   'profile_photo_url', 'slug', 'is_running_candidate',
                   'term_status', 'verified_official', 'ballotpedia_id',
                   'district', 'website_url', 'bio', 'term_ends_on']);

        // ── 2c. Split U.S. Senators out of the federal set ─────────────────────
        // Senators represent the whole state (no congressional district), so
        // they don't fit the district-keyed house_candidates bucket below —
        // whatever's left in $housePoliticians after this is House members.
        $senatorPoliticians = $housePoliticians->filter(
            fn (Politician $pol) => str_contains(strtolower((string) $pol->political_office), 'senator')
        );
        $housePoliticians = $housePoliticians->reject(
            fn (Politician $pol) => str_contains(strtolower((string) $pol->political_office), 'senator')
        );

        // ── 2d. City / local officials (mayors etc.) for this state ───────────
        // Seated officeholders AND declared/running candidates for city, county,
        // and judicial (e.g. Superior Court Judge) seats — mirrors the House
        // candidates query above (term_status != 'lost', seated or null both
        // included) rather than only ever showing incumbents.
        $cityOfficials = Politician::query()
            ->whereRaw('UPPER(COALESCE(state, \'\')) = ?', [$state])
            ->where('is_active', true)
            ->whereIn('governance_level', ['City', 'County', 'Local'])
            ->where(fn($q) => $q->where('term_status', '!=', 'lost')->orWhereNull('term_status'))
            ->whereNull('user_id')   // exclude claimed/personal accounts
            ->whereNotNull('city')   // must have a city name set
            ->orderBy('city')
            ->orderBy('full_name')
            // Bounded like ballotMeasures below — a state with dense local
            // government (many city/county seats imported) could otherwise
            // return an unbounded row set for one request.
            ->limit(500)
            ->with(['publicBadges.topic'])
            ->get(['id', 'uuid', 'full_name', 'political_office', 'party_affiliation',
                   'profile_photo_url', 'slug', 'is_running_candidate',
                   'term_status', 'verified_official', 'ballotpedia_id',
                   'city', 'governance_level', 'website_url', 'bio', 'term_ends_on']);

        // ── 3. Bucket both into canonical office groups ────────────────────────
        $grouped = [];
        $grouped['U.S. Senators'] = ['office' => 'U.S. Senators', 'candidates' => []];
        foreach (self::STATEWIDE_OFFICES as $office) {
            $grouped[$office] = ['office' => $office, 'candidates' => []];
        }
        $grouped['Other Statewide'] = ['office' => 'Other Statewide', 'candidates' => []];

        // Global dedup set: prevents the same person appearing in multiple buckets
        // (e.g. once as a platform Politician and again as an ECR with a slightly
        // different office label, or as both a Senate ECR and a House Politician).
        $seenGlobal = [];

        foreach ($senatorPoliticians as $pol) {
            $grouped['U.S. Senators']['candidates'][] = $this->formatPlatformCandidate($pol);
            $seenGlobal[strtolower($pol->full_name)] = true;
        }

        foreach ($platformPoliticians as $pol) {
            $canonical = $this->canonicalise($pol->political_office);

            $platformKey = strtolower(trim((string) $pol->full_name)) . '|' . strtolower($canonical);
            if ((bool) $pol->is_running_candidate && isset($excludedRunningKeys[$platformKey])) {
                continue;
            }

            $seenGlobal[strtolower($pol->full_name)] = true;
            $grouped[$canonical]['candidates'][] = $this->formatPlatformCandidate($pol);
        }

        foreach ($scrapedRecords as $rec) {
            $canonical  = $this->canonicalise($rec->political_office);
            $nameLower  = strtolower($rec->full_name);
            $payload    = is_array($rec->payload) ? $rec->payload : [];
            $primaryResult = strtolower(trim((string) ($payload['primary_result'] ?? '')));
            $resultStatus = strtolower(trim((string) ($payload['result_status'] ?? '')));
            $payloadStatus = strtolower(trim((string) ($payload['status'] ?? '')));
            $suppressionKey = $nameLower . '|' . strtolower($canonical);

            if ($primaryResult === '') {
                $primaryResult = null;
            }

            // Exclude candidates with any explicit "lost/eliminated" signal.
            if (
                $primaryResult === 'eliminated'
                || in_array($resultStatus, ['lost', 'eliminated', 'defeated'], true)
                || in_array($payloadStatus, ['lost', 'eliminated', 'defeated'], true)
                || isset($excludedRunningKeys[$suppressionKey])
            ) {
                continue;
            }

            // Skip globally if this person already appears under ANY office bucket
            // (prevents duplicates like "Adam Schiff" as both a Senate ECR and
            // a House/Platform Politician, or two ECRs with different office labels)
            if (isset($seenGlobal[$nameLower])) {
                continue;
            }

            $recStatus     = $payload['status'] ?? null;

            // If the primary election date has passed, only show candidates who
            // explicitly advanced to the general. Records with no primary_result
            // yet are hidden — they'll be resolved by the weekly sync.
            // Seated officeholders bypass this check (they are not on the ballot).
            $electionDate = $rec->election_date ? trim((string) $rec->election_date) : null;
            if ($recStatus !== 'seated' && $electionDate && $electionDate < now()->toDateString()) {
                if ($primaryResult !== 'advanced_to_general') {
                    continue;
                }
            }

            $seenGlobal[$nameLower] = true;
            $recStatus = $payload['status'] ?? 'running';
            $grouped[$canonical]['candidates'][] = [
                'source'          => 'scraped',
                'scrape_source'   => $rec->source,
                'external_candidate_id' => $rec->external_candidate_id,
                'uuid'            => null,
                'full_name'       => $rec->full_name,
                'party'           => $rec->party_affiliation,
                'photo'           => $payload['photo'] ?? null,
                'slug'            => null,
                'status'          => $recStatus,
                'is_running'      => $recStatus !== 'seated',
                'verified'        => $recStatus === 'seated',
                'primary_result'  => $primaryResult,
                'general_date'    => $payload['general_date'] ?? null,
                'term_end'        => $payload['term_end'] ?? null,
                'term_note'       => $payload['term_note'] ?? null,
                'ballotpedia_id'  => $rec->external_candidate_id ?? null,
                'ballotpedia_url' => ($rec->source === 'ballotpedia' && $rec->external_candidate_id)
                    ? 'https://ballotpedia.org/' . $rec->external_candidate_id
                    : null,
                'website'         => $payload['website'] ?? null,
                'profile_url'     => null,
                'bio_excerpt'     => null,
                'badges'          => [],
            ];
        }

        // ── Deduplicate candidates within each office group by name ─────────
        // Multiple import sources (platform Politician + ECR scrape + enrichment)
        // can write separate rows for the same person. Keep the highest-quality
        // record: platform > scraped, seated > running, verified > unverified.
        foreach ($grouped as $canonical => &$group) {
            $seen  = [];
            $deduped = [];
            foreach ($group['candidates'] as $cand) {
                $key = strtolower(trim((string) ($cand['full_name'] ?? '')));
                if ($key === '') {
                    $deduped[] = $cand;
                    continue;
                }
                if (! isset($seen[$key])) {
                    $seen[$key] = $cand;
                    continue;
                }
                // Score: platform=3, scraped=1; seated=2, running=1; verified=1
                $score = fn($c) =>
                    (($c['source'] ?? '') === 'platform' ? 3 : 1)
                    + (($c['status'] ?? '') === 'seated' ? 2 : 1)
                    + ((bool)($c['verified'] ?? false) ? 1 : 0);
                if ($score($cand) > $score($seen[$key])) {
                    $seen[$key] = $cand;
                }
            }
            $group['candidates'] = array_values(array_merge($deduped, array_values($seen)));
        }
        unset($group);

        // Compute election phase per office group
        foreach ($grouped as $canonical => &$group) {
            $phase = 'pre_primary';
            foreach ($group['candidates'] as $cand) {
                $pr = $cand['primary_result'] ?? null;
                if ($pr !== null) {
                    $phase = 'post_primary';
                }
                $genDate = $cand['general_date'] ?? null;
                if ($genDate && strtotime($genDate) < time()) {
                    $phase = 'post_general';
                    break;
                }
            }
            $group['election_phase'] = $phase;
        }
        unset($group);

        // Remove empty office groups for cleaner response
        $offices = collect($grouped)
            ->filter(fn($g) => count($g['candidates']) > 0)
            ->values();

        $regionInfo = self::STATE_REGIONS[$state] ?? ['region' => 'Unknown', 'color' => '#64748b'];

        // ── 4. Population data ─────────────────────────────────────────────────
        $statePopRow = DB::table('district_populations')
            ->where('state', $state)
            ->whereNull('district_number')
            ->orderByDesc('census_year')
            ->first(['total_population', 'census_year']);

        $districtPops = DB::table('district_populations')
            ->where('state', $state)
            ->whereNotNull('district_number')
            ->orderByDesc('census_year')
            ->orderBy('district_number')
            ->get(['district_number', 'label', 'total_population', 'census_year'])
            ->keyBy('label');

        // ── 5. House candidates keyed by district label (e.g. "CA-33") ──────
        $houseCandidates = [];
        $seenHouseNames = [];
        foreach ($housePoliticians as $pol) {
            $distKey = $pol->district ?? null;
            if (! $distKey) {
                continue;
            }
            $seenHouseNames[strtolower(trim((string) $pol->full_name))] = true;
            $houseCandidates[$distKey][] = [
                'id'              => $pol->id,
                'source'          => 'platform',
                'scrape_source'   => null,
                'external_candidate_id' => null,
                'full_name'       => $pol->full_name,
                'party'           => $pol->party_affiliation,
                'photo'           => $pol->profile_photo_url
                    ? (str_starts_with($pol->profile_photo_url, 'http') ? $pol->profile_photo_url : url($pol->profile_photo_url))
                    : null,
                'slug'            => $pol->slug,
                'status'          => $pol->term_status,
                'is_running'      => (bool) $pol->is_running_candidate,
                'verified'        => (bool) $pol->verified_official,
                'ballotpedia_url' => $pol->ballotpedia_id
                    ? 'https://ballotpedia.org/' . $pol->ballotpedia_id
                    : null,
                'website'         => $pol->website_url,
                'profile_url'     => $pol->slug ? url('/p/' . $pol->slug) : null,
                'bio_excerpt'     => $pol->bio ? Str::limit($pol->bio, 180) : null,
                'badges'          => $this->formatBadges($pol),
            ];
        }

        // ── 5b. Merge scraped House challengers not yet promoted to a platform
        // Politician row — mirrors the statewide ECR merge above (§2), keyed
        // by district instead of office. Without this, a challenger only
        // appears once ReconcileMissingCandidateProfiles promotes them.
        $scrapedHouseRecords = ElectionCandidateRecord::query()
            ->whereRaw('UPPER(COALESCE(state, \'\')) = ?', [$state])
            ->whereRaw('LOWER(COALESCE(governance_level, \'\')) = ?', ['federal'])
            ->whereNotNull('district')
            ->whereRaw('LOWER(COALESCE(political_office, \'\')) NOT LIKE ?', ['%senat%'])
            ->get(['id', 'full_name', 'political_office', 'party_affiliation',
                   'district', 'election_date', 'source', 'external_candidate_id', 'payload']);

        foreach ($scrapedHouseRecords as $rec) {
            $nameLower = strtolower(trim((string) $rec->full_name));
            $distKey = $rec->district;

            $payload = is_array($rec->payload) ? $rec->payload : [];
            $primaryResult = strtolower(trim((string) ($payload['primary_result'] ?? '')));
            $resultStatus = strtolower(trim((string) ($payload['result_status'] ?? '')));
            $payloadStatus = strtolower(trim((string) ($payload['status'] ?? '')));

            // Exclude anyone already showing as a platform House member/candidate,
            // and anyone with an explicit "lost/eliminated" signal.
            if (
                isset($seenHouseNames[$nameLower])
                || $primaryResult === 'eliminated'
                || in_array($resultStatus, ['lost', 'eliminated', 'defeated'], true)
                || in_array($payloadStatus, ['lost', 'eliminated', 'defeated'], true)
            ) {
                continue;
            }

            // Same primary-date gate as the statewide merge: once the primary
            // has passed, only show candidates who explicitly advanced.
            $recStatus = $payload['status'] ?? null;
            $electionDate = $rec->election_date ? trim((string) $rec->election_date) : null;
            if ($recStatus !== 'seated' && $electionDate && $electionDate < now()->toDateString()) {
                if ($primaryResult !== 'advanced_to_general') {
                    continue;
                }
            }

            $seenHouseNames[$nameLower] = true;
            $recStatus = $payload['status'] ?? 'running';
            $houseCandidates[$distKey][] = [
                'source'          => 'scraped',
                'scrape_source'   => $rec->source,
                'external_candidate_id' => $rec->external_candidate_id,
                'full_name'       => $rec->full_name,
                'party'           => $rec->party_affiliation,
                'photo'           => $payload['photo'] ?? null,
                'slug'            => null,
                'status'          => $recStatus,
                'is_running'      => $recStatus !== 'seated',
                'verified'        => $recStatus === 'seated',
                'ballotpedia_url' => ($rec->source === 'ballotpedia' && $rec->external_candidate_id)
                    ? 'https://ballotpedia.org/' . $rec->external_candidate_id
                    : null,
                'website'         => $payload['website'] ?? null,
                'profile_url'     => null,
                'bio_excerpt'     => null,
                'badges'          => [],
            ];
        }

        // ── 6. City officials grouped by city name, then by exact office/seat ──
        // Nested { city: { officeKey: {office, candidates: [...]} } } so multiple
        // people running for the *same* seat (e.g. two "Superior Court Judge,
        // Seat 2" candidates) group together instead of listing as unrelated
        // flat cards — same {office, candidates} shape renderOfficeGroup() (JS)
        // already knows how to render with seated/running splitting.
        $cityOfficialsGrouped = [];
        foreach ($cityOfficials as $pol) {
            $cityKey = $pol->city ?? 'Unknown City';
            $officeKey = $pol->political_office ?: 'Other Local Office';

            $cityOfficialsGrouped[$cityKey][$officeKey] ??= [
                'office'     => $officeKey,
                'candidates' => [],
            ];

            $cityOfficialsGrouped[$cityKey][$officeKey]['candidates'][] = [
                'id'              => $pol->id,
                'source'          => 'platform',
                'scrape_source'   => null,
                'external_candidate_id' => null,
                'full_name'       => $pol->full_name,
                'political_office'=> $pol->political_office,
                'governance_level'=> $pol->governance_level,
                'party'           => $pol->party_affiliation,
                'photo'           => $pol->profile_photo_url
                    ? (str_starts_with($pol->profile_photo_url, 'http') ? $pol->profile_photo_url : url($pol->profile_photo_url))
                    : null,
                'slug'            => $pol->slug,
                'status'          => ($pol->term_status === 'active' && ! $pol->is_running_candidate)
                    ? 'seated'
                    : $pol->term_status,
                'is_running'      => $pol->term_status !== 'seated',
                'verified'        => (bool) $pol->verified_official,
                'ballotpedia_url' => $pol->ballotpedia_id
                    ? 'https://ballotpedia.org/' . $pol->ballotpedia_id
                    : null,
                'website'         => $pol->website_url,
                'profile_url'     => $pol->slug ? url('/p/' . $pol->slug) : null,
                'bio_excerpt'     => $pol->bio ? Str::limit($pol->bio, 180) : null,
                'term_end'        => optional($pol->term_ends_on)?->toDateString(),
                'badges'          => $this->formatBadges($pol),
            ];
        }
        // Flatten each city's office-keyed map into a plain list of office groups.
        // (Named $cityOfficeGroups, not $offices, to avoid shadowing the
        // unrelated statewide-$offices Collection used later in this method.)
        foreach ($cityOfficialsGrouped as $cityKey => $cityOfficeGroups) {
            $cityOfficialsGrouped[$cityKey] = array_values($cityOfficeGroups);
        }

        // ── 7. Upcoming ballot measures for this state ─────────────────────────
        // Admin/CSV-curated for now (php artisan ballot-measures:import) — no
        // automated per-state Secretary-of-State scraper yet.
        $ballotMeasures = BallotMeasure::query()
            ->where('state', $state)
            ->where(function ($q) {
                $q->whereNull('election_date')->orWhere('election_date', '>=', now()->toDateString());
            })
            ->orderBy('election_date')
            ->limit(10)
            ->get(['id', 'measure_number', 'title', 'summary', 'yes_meaning', 'no_meaning', 'election_date', 'status', 'source_url'])
            ->map(fn (BallotMeasure $m) => [
                'measure_number' => $m->measure_number,
                'title'          => $m->title,
                'summary'        => $m->summary,
                'yes_meaning'    => $m->yes_meaning,
                'no_meaning'     => $m->no_meaning,
                'election_date'  => optional($m->election_date)?->toDateString(),
                'status'         => $m->status,
                'source_url'     => $m->source_url,
                'detail_url'     => route('voter.ballot-measures.show', $m->id),
            ]);

        // ── 8. Upcoming election dates for this state ──────────────────────────
        // Real primary/general dates + candidate filing deadlines, synced
        // from Vote Smart (php artisan elections:sync-dates) — see
        // StateElectionDate::upcomingForState() for the shared query used by
        // the map, public profile, and voter dashboard alike.
        $electionDates = StateElectionDate::upcomingForState($state);

        // ── 9. Mapped local businesses in this state ────────────────────────────
        // Reuses the same Citizen rows that power the map's business pins/search
        // (Citizen::mappable()) — a count, not a new data source.
        $businessCount = Citizen::query()
            ->mappable()
            ->where('state', $state)
            ->whereNotNull('business_name')
            ->count();

        return [
            'state'              => $state,
            'region'             => $regionInfo['region'],
            'region_color'       => $regionInfo['color'],
            'total'              => $offices->sum(fn($g) => count($g['candidates'])),
            'offices'            => $offices,
            'house_candidates'   => $houseCandidates,
            'city_officials'     => $cityOfficialsGrouped,
            'ballot_measures'    => $ballotMeasures->values()->all(),
            'election_dates'     => $electionDates,
            'office_roles'       => $this->officeRoles(),
            'population'         => $statePopRow ? [
                'total'       => $statePopRow->total_population,
                'census_year' => $statePopRow->census_year,
                'formatted'   => number_format($statePopRow->total_population),
            ] : null,
            'business_count'     => $businessCount,
            'district_populations' => $districtPops->map(fn($r) => [
                'district'    => $r->label,
                'total'       => $r->total_population,
                'census_year' => $r->census_year,
                'formatted'   => number_format($r->total_population),
            ])->values()->all(),
        ];
    }

    /**
     * Format a seated/platform Politician into the candidate-card shape
     * shared by the statewide-office and Senator buckets.
     *
     * @return array<string, mixed>
     */
    private function formatPlatformCandidate(Politician $pol): array
    {
        return [
            'id'              => $pol->id,
            'source'          => 'platform',
            'scrape_source'   => null,
            'external_candidate_id' => null,
            'uuid'            => $pol->uuid,
            'full_name'       => $pol->full_name,
            'party'           => $pol->party_affiliation,
            'photo'           => $pol->profile_photo_url
                ? (str_starts_with($pol->profile_photo_url, 'http') ? $pol->profile_photo_url : url($pol->profile_photo_url))
                : null,
            'slug'            => $pol->slug,
            // Normalize legacy 'active' records written by enrich-statewide
            // before the seated/active distinction was clarified. A non-running
            // incumbent should always render as "Current Officeholder" in the map.
            'status'          => ($pol->term_status === 'active' && ! $pol->is_running_candidate)
                ? 'seated'
                : $pol->term_status,
            'is_running'      => (bool) $pol->is_running_candidate,
            'verified'        => (bool) $pol->verified_official,
            'ballotpedia_id'  => $pol->ballotpedia_id,
            'ballotpedia_url' => $pol->ballotpedia_id
                ? 'https://ballotpedia.org/' . $pol->ballotpedia_id
                : null,
            'website'         => $pol->website_url,
            'profile_url'     => $pol->slug ? url('/p/' . $pol->slug) : null,
            'bio_excerpt'     => $pol->bio ? Str::limit($pol->bio, 180) : null,
            // Same field name/semantics as the scraped-ECR branch above
            // ("Term ends {date}", rendered in panel-state.js) — statewide
            // offices (Senators, Governor, etc.) have no district/general_date
            // of their own, so this is their only source of a term-end date.
            'term_end'        => optional($pol->term_ends_on)?->toDateString(),
            'badges'          => $this->formatBadges($pol),
        ];
    }

    /**
     * Format a politician's public badges for the map candidate drawer.
     * Limited to 4 to keep the badge row compact.
     *
     * @return array<int, array{name: string, icon: ?string, color: string}>
     */
    private function formatBadges(Politician $pol): array
    {
        return $pol->publicBadges
            ->take(4)
            ->map(fn ($badge) => [
                'name'  => $badge->topic->name ?? '',
                'icon'  => $badge->topic->badge_icon_url ?? $badge->topic->icon ?? null,
                'color' => $badge->topic->badge_color ?? '#6366f1',
            ])
            ->values()
            ->all();
    }

    /**
     * Map any political_office string to one of our canonical group keys.
     */
    private function canonicalise(?string $office): string
    {
        return OfficeCanonicalizer::canonicaliseStatewide($office) ?? 'Other Statewide';
    }

    /**
     * Static civic-education blurbs for each office — used by the map panel.
     *
     * @return array<string, string>
     */
    private function officeRoles(): array
    {
        return [
            'U.S. Senators' =>
                'U.S. Senators represent the entire state in the U.S. Senate, serving 6-year ' .
                'terms. Each state elects two, who vote on federal legislation, confirm ' .
                'presidential nominees, and ratify treaties.',
            'Governor' =>
                'The Governor is the chief executive of the state. They sign or veto legislation, ' .
                'command the state National Guard, and oversee all executive state agencies.',
            'Lieutenant Governor' =>
                'The Lieutenant Governor acts as second-in-command to the Governor, presides over ' .
                'the state senate in many states, and assumes the governorship if needed.',
            'Attorney General' =>
                'The Attorney General is the state\'s chief law-enforcement officer and top legal ' .
                'advisor, representing the state in litigation and leading consumer-protection efforts.',
            'State Treasurer' =>
                'The State Treasurer manages the state\'s financial assets, oversees investments of ' .
                'public funds, and is responsible for debt management and cash flow.',
            'State Controller' =>
                'The State Controller (or Comptroller) audits state spending, issues warrants for ' .
                'payments from the state treasury, and oversees accounting of public funds.',
            'Secretary of State' =>
                'The Secretary of State manages elections, maintains official state records and ' .
                'business filings, and certifies election results.',
            'Other Statewide' =>
                'Other statewide executive offices vary by state and may include commissioners, ' .
                'auditors, and other elected or appointed officials.',
        ];
    }
}
