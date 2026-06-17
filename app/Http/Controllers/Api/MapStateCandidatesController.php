<?php

namespace App\Http\Controllers\Api;

use App\Models\ElectionCandidateRecord;
use App\Models\Politician;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
    private const STATEWIDE_OFFICES = [
        'Governor',
        'Lieutenant Governor',
        'Attorney General',
        'State Treasurer',
        'State Controller',
        'Secretary of State',
    ];

    /**
     * Fuzzy aliases so we match however the data was imported.
     * Keyed by canonical label => array of partial strings to match.
     */
    private const OFFICE_ALIASES = [
        'Governor'             => ['governor'],
        'Lieutenant Governor'  => ['lieutenant governor', 'lt. governor', 'lt governor'],
        'Attorney General'     => ['attorney general'],
        'State Treasurer'      => ['treasurer'],
        'State Controller'     => ['controller', 'comptroller'],
        'Secretary of State'   => ['secretary of state'],
    ];

    /**
     * GET /api/v1/map/state-candidates?state=CA
     */
    public function __invoke(Request $request): JsonResponse
    {
        $state = strtoupper(trim((string) $request->query('state', '')));

        if ($state === '' || strlen($state) !== 2) {
            return response()->json(['error' => 'Provide a valid two-letter state code.'], 422);
        }

        // ── 1. Elected / registered politicians on the platform ───────────────
        $platformPoliticians = Politician::query()
            ->whereRaw('UPPER(COALESCE(state, \'\')) = ?', [$state])
            ->where('is_active', true)
            ->whereRaw('LOWER(COALESCE(governance_level, \'\')) = ?', ['state'])
            ->get(['uuid', 'full_name', 'political_office', 'party_affiliation',
                   'profile_photo_url', 'slug', 'is_running_candidate',
                   'term_status', 'verified_official', 'ballotpedia_id',
                   'website_url', 'bio']);

        // ── 2. Scraped ElectionCandidateRecords (not yet on platform) ─────────
        $scrapedRecords = ElectionCandidateRecord::query()
            ->whereRaw('UPPER(COALESCE(state, \'\')) = ?', [$state])
            ->whereRaw('LOWER(COALESCE(governance_level, \'\')) = ?', ['state'])
            ->get(['id', 'full_name', 'political_office', 'party_affiliation',
                   'election_date', 'source', 'external_candidate_id', 'payload']);

        // ── 2b. Federal (House) politicians for this state ────────────────────
        $housePoliticians = Politician::query()
            ->whereRaw('UPPER(COALESCE(state, \'\')) = ?', [$state])
            ->where('is_active', true)
            ->whereRaw('LOWER(COALESCE(governance_level, \'\')) = ?', ['federal'])
            ->get(['uuid', 'full_name', 'political_office', 'party_affiliation',
                   'profile_photo_url', 'slug', 'is_running_candidate',
                   'term_status', 'verified_official', 'ballotpedia_id',
                   'district', 'website_url', 'bio']);

        // ── 3. Bucket both into canonical office groups ────────────────────────
        $grouped = [];
        foreach (self::STATEWIDE_OFFICES as $office) {
            $grouped[$office] = ['office' => $office, 'candidates' => []];
        }
        $grouped['Other Statewide'] = ['office' => 'Other Statewide', 'candidates' => []];

        foreach ($platformPoliticians as $pol) {
            $canonical = $this->canonicalise($pol->political_office);
            $grouped[$canonical]['candidates'][] = [
                'source'          => 'platform',
                'uuid'            => $pol->uuid,
                'full_name'       => $pol->full_name,
                'party'           => $pol->party_affiliation,
                'photo'           => $pol->profile_photo_url
                    ? (str_starts_with($pol->profile_photo_url, 'http') ? $pol->profile_photo_url : url($pol->profile_photo_url))
                    : null,
                'slug'            => $pol->slug,
                'status'          => $pol->term_status,
                'is_running'      => (bool) $pol->is_running_candidate,
                'verified'        => (bool) $pol->verified_official,
                'ballotpedia_id'  => $pol->ballotpedia_id,
                'ballotpedia_url' => $pol->ballotpedia_id
                    ? 'https://ballotpedia.org/' . $pol->ballotpedia_id
                    : null,
                'website'         => $pol->website_url,
                'profile_url'     => $pol->slug ? url('/p/' . $pol->slug) : null,
                'bio_excerpt'     => $pol->bio ? Str::limit($pol->bio, 180) : null,
            ];
        }

        foreach ($scrapedRecords as $rec) {
            $canonical = $this->canonicalise($rec->political_office);
            // Skip if a platform politician with the same name already exists
            $alreadyListed = collect($grouped[$canonical]['candidates'])
                ->contains(fn($c) => strtolower($c['full_name']) === strtolower($rec->full_name));
            if ($alreadyListed) {
                continue;
            }
            $payload       = is_array($rec->payload) ? $rec->payload : [];
            $primaryResult = $payload['primary_result'] ?? null;

            // Exclude candidates eliminated in a primary — they are no longer active
            if ($primaryResult === 'eliminated') {
                continue;
            }

            $recStatus = $payload['status'] ?? 'running';
            $grouped[$canonical]['candidates'][] = [
                'source'          => 'scraped',
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
            ];
        }

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
        foreach ($housePoliticians as $pol) {
            $distKey = $pol->district ?? null;
            if (! $distKey) {
                continue;
            }
            $houseCandidates[$distKey][] = [
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
            ];
        }

        return response()->json([
            'state'              => $state,
            'region'             => $regionInfo['region'],
            'region_color'       => $regionInfo['color'],
            'total'              => $offices->sum(fn($g) => count($g['candidates'])),
            'offices'            => $offices,
            'house_candidates'   => $houseCandidates,
            'office_roles'       => $this->officeRoles(),
            'population'         => $statePopRow ? [
                'total'       => $statePopRow->total_population,
                'census_year' => $statePopRow->census_year,
                'formatted'   => number_format($statePopRow->total_population),
            ] : null,
            'district_populations' => $districtPops->map(fn($r) => [
                'district'    => $r->label,
                'total'       => $r->total_population,
                'census_year' => $r->census_year,
                'formatted'   => number_format($r->total_population),
            ]),
        ]);
    }

    /**
     * Map any political_office string to one of our canonical group keys.
     */
    private function canonicalise(?string $office): string
    {
        if ($office === null || $office === '') {
            return 'Other Statewide';
        }
        $lower = strtolower($office);
        foreach (self::OFFICE_ALIASES as $canonical => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($lower, $needle)) {
                    return $canonical;
                }
            }
        }
        return 'Other Statewide';
    }

    /**
     * Static civic-education blurbs for each office — used by the map panel.
     *
     * @return array<string, string>
     */
    private function officeRoles(): array
    {
        return [
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
