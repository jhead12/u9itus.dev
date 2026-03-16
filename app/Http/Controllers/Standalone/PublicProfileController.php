<?php

namespace App\Http\Controllers\Standalone;

use App\Http\Controllers\Controller;
use App\Enums\ApprovalStatus;
use App\Enums\CampaignStatus;
use App\Models\Politician;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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
                    $error = 'We could not resolve that address. Try including street, city, state, and ZIP.';
                } else {
                    $candidates = $this->findCandidatesForDistrict($lookupResult, $states);
                }
            }
        }

        return view('standalone.public.district-lookup', [
            'address' => $address,
            'lookupResult' => $lookupResult,
            'candidates' => $candidates,
            'states' => $states,
            'error' => $error,
        ]);
    }

    /**
     * Display a directory of all active politicians on the platform.
        *
        * Public browsing is view-only. Guests may research profiles and transparency
        * data without entering any earning flow.
     */
    public function index(Request $request)
    {
        $query = Politician::where('page_published', true)
            ->where('is_active', true)
            ->with(['campaigns' => function($q) {
                $q->where('status', 'active')->where('approval_status', 'approved');
            }]);

        // Search filter
        if ($search = $request->input('q')) {
            $query->where(function($q) use ($search) {
                $q->where('full_name', 'like', '%' . $search . '%')
                  ->orWhere('political_office', 'like', '%' . $search . '%')
                  ->orWhere('bio', 'like', '%' . $search . '%');
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
        switch ($sortBy) {
            case 'name':
                $query->orderBy('full_name', 'asc');
                break;
            case 'recent':
                $query->orderByDesc('created_at');
                break;
            case 'verified':
                $query->orderByDesc('verified_official')->orderBy('full_name', 'asc');
                break;
            default:
                $query->orderBy('full_name', 'asc');
        }

        $politicians = $query->paginate(24);

        $states = config('u9itus.us_states', []);
        $governanceLevels = ['Federal', 'State', 'County', 'City', 'School Board', 'Judicial'];
        $parties = ['Democratic', 'Republican', 'Independent', 'Libertarian', 'Green'];
        $isGuestBrowsing = ! auth()->check();

        return view('standalone.public.politicians-directory', compact(
            'politicians',
            'states',
            'governanceLevels',
            'parties',
            'isGuestBrowsing'
        ));
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
            ->where('approval_status', ApprovalStatus::Approved)
            ->whereIn('status', [
                CampaignStatus::Completed,
                CampaignStatus::Cancelled,
            ])
            ->orderByDesc('completed_at')
            ->orderByDesc('updated_at')
            ->take(8)
            ->get();

        $initiatives = $politician->initiatives;

        $transparencyData = $this->buildTransparencyData($politician);

        // Build Open Graph meta
        $ogTitle       = $politician->full_name . ' — ' . ($politician->political_office ?? 'Politician');
        $ogDescription = $politician->bio
            ? \Illuminate\Support\Str::limit($politician->bio, 160)
            : "Research {$politician->full_name}'s campaign messages, profile, and public records on U9itus.";
        $ogImage       = $page->hero_banner_url ?? $politician->profile_photo_url ?? null;
        $ogUrl         = route('politician.public.show', $slug);

        return view('standalone.public.profile', compact(
            'politician',
            'page',
            'runningCampaigns',
            'pastCampaigns',
            'initiatives',
            'transparencyData',
            'ogTitle',
            'ogDescription',
            'ogImage',
            'ogUrl',
            'isGuestBrowsing'
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
        if ($politician->verification_status !== 'verified') {
            return [];
        }

        $services = [
            'ballotpedia' => [$politician->show_ballotpedia_data, \App\Services\BallotpediaService::class],
            'opensecrets' => [$politician->show_opensecrets_data, \App\Services\OpenSecretsService::class],
            'votesmart' => [$politician->show_votesmart_data, \App\Services\VoteSmartService::class],
            'fec' => [$politician->show_fec_data, \App\Services\FECService::class],
        ];

        $transparencyData = [];

        foreach ($services as $key => [$enabled, $serviceClass]) {
            if (! $enabled) {
                continue;
            }

            $transparencyData[$key] = app($serviceClass)->getDisplayData($politician);
        }

        return $transparencyData;
    }

    /**
     * @param array<string, mixed> $lookupResult
     * @param array<string, string> $states
     */
    protected function findCandidatesForDistrict(array $lookupResult, array $states): Collection
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

        if ($districtNumber) {
            $variants = $this->districtVariants($state, (string) $districtNumber);

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
}
