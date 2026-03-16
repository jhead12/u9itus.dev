<?php

namespace App\Http\Controllers\Standalone;

use App\Http\Controllers\Controller;
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
     * Allows voters to browse and research politicians before watching their campaigns.
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

        return view('standalone.public.politicians-directory', compact(
            'politicians',
            'states',
            'governanceLevels',
            'parties'
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
        // Try to find a published page first
        $politician = Politician::where('slug', $slug)
            ->where('page_published', true)
            ->where('is_active', true)
            ->first();

        // If not published, check if the authenticated user owns this page (preview mode)
        if (!$politician && auth()->check()) {
            $user = auth()->user();
            if ($user->user_type === 'politician' && $user->politician) {
                $politician = Politician::where('slug', $slug)
                    ->where('id', $user->politician->id)
                    ->where('is_active', true)
                    ->first();
            }
        }

        // If still not found, throw 404
        if (!$politician) {
            abort(404);
        }

        // Eager-load what we need for the public page
        $politician->load(['page', 'initiatives' => fn($q) => $q->published()->ordered()]);

        // Page config (use defaults if politician hasn't saved one yet)
        $page = $politician->page ?? new \App\Models\PoliticianPage(\App\Models\PoliticianPage::defaults($politician->id));

        // Active campaigns to feature on the page
        $campaigns = $politician->campaigns()
            ->where('status', 'active')
            ->orderByDesc('created_at')
            ->take(6)
            ->get();

        $initiatives = $politician->initiatives;

        // Phase 16: Fetch transparency data if politician is verified
        $transparencyData = [];
        if ($politician->verification_status === 'verified') {
            if ($politician->show_ballotpedia_data) {
                $ballotpediaService = app(\App\Services\BallotpediaService::class);
                $transparencyData['ballotpedia'] = $ballotpediaService->getDisplayData($politician);
            }
            if ($politician->show_opensecrets_data) {
                $openSecretsService = app(\App\Services\OpenSecretsService::class);
                $transparencyData['opensecrets'] = $openSecretsService->getDisplayData($politician);
            }
            if ($politician->show_votesmart_data) {
                $voteSmartService = app(\App\Services\VoteSmartService::class);
                $transparencyData['votesmart'] = $voteSmartService->getDisplayData($politician);
            }
            if ($politician->show_fec_data) {
                $fecService = app(\App\Services\FECService::class);
                $transparencyData['fec'] = $fecService->getDisplayData($politician);
            }
        }

        // Build Open Graph meta
        $ogTitle       = $politician->full_name . ' — ' . ($politician->political_office ?? 'Politician');
        $ogDescription = $politician->bio
            ? \Illuminate\Support\Str::limit($politician->bio, 160)
            : "Watch {$politician->full_name}'s political messages and earn money on U9itus.";
        $ogImage       = $page->hero_banner_url ?? $politician->profile_photo_url ?? null;
        $ogUrl         = route('politician.public.show', $slug);

        return view('standalone.public.profile', compact(
            'politician',
            'page',
            'campaigns',
            'initiatives',
            'transparencyData',
            'ogTitle',
            'ogDescription',
            'ogImage',
            'ogUrl'
        ));
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
