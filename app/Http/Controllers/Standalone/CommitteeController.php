<?php

namespace App\Http\Controllers\Standalone;

use App\Http\Controllers\Controller;
use App\Models\Committee;
use App\Models\CommitteeProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Public PAC / committee directory — /pacs and /pacs/{fec_id}.
 *
 * Read-only, no auth. Everything is served from `committee_profiles` (built
 * nightly by committees:enrich-profiles); this controller never touches the
 * FEC API. Every query is wrapped in Cache::remember — uncached queries on
 * public directory/profile pages are a known 502 source on this codebase.
 */
class CommitteeController extends Controller
{
    private const LIST_TTL = 900;   // 15 min
    private const SHOW_TTL = 1800;  // 30 min

    public function index(Request $request)
    {
        $filters = [
            'type' => $request->query('type'),       // 'super_pac' | 'pac' | 'party' | 'other'
            'party' => strtoupper((string) $request->query('party', '')) ?: null,
            'state' => strtoupper((string) $request->query('state', '')) ?: null,
            'q' => trim((string) $request->query('q', '')) ?: null,
            'page' => max(1, (int) $request->query('page', 1)),
        ];

        $cacheKey = 'pacs:index:' . md5(json_encode($filters));

        [$committees, $total, $facets] = Cache::remember($cacheKey, self::LIST_TTL, function () use ($filters) {
            $query = Committee::query()
                ->select('committees.*')
                ->join('committee_profiles', 'committee_profiles.committee_id', '=', 'committees.id')
                ->whereNotNull('committee_profiles.enriched_at')
                ->with('profile');

            if ($filters['q']) {
                $query->where('committees.name', 'like', '%' . $filters['q'] . '%');
            }
            if ($filters['party']) {
                $query->where('committee_profiles.party', 'like', $filters['party'] . '%');
            }
            if ($filters['state']) {
                $query->where('committee_profiles.state', $filters['state']);
            }
            if ($filters['type'] === 'super_pac') {
                $query->where('committee_profiles.is_super_pac', true);
            } elseif ($filters['type'] === 'party') {
                $query->whereIn('committee_profiles.committee_type', ['X', 'Y', 'Z']);
            } elseif ($filters['type'] === 'pac') {
                $query->where('committee_profiles.is_super_pac', false)
                      ->whereIn('committee_profiles.committee_type', ['N', 'Q']);
            }

            // Order by how much outside money this committee moves — most
            // consequential first. NULLs (no IE total filed) sort last.
            $query->orderByRaw('COALESCE(committee_profiles.independent_expenditures, committee_profiles.total_disbursements, 0) DESC')
                  ->orderBy('committees.name');

            $total = (clone $query)->toBase()->getCountForPagination();

            $rows = $query->forPage($filters['page'], 30)->get();

            $facets = [
                'states' => CommitteeProfile::query()
                    ->whereNotNull('enriched_at')
                    ->whereNotNull('state')
                    ->distinct()
                    ->orderBy('state')
                    ->pluck('state')
                    ->all(),
            ];

            return [$rows, $total, $facets];
        });

        return view('standalone.public.pac-directory', [
            'committees' => $committees,
            'total' => $total,
            'facets' => $facets,
            'filters' => $filters,
            'perPage' => 30,
            'ogTitle' => 'PAC & Committee Directory',
            'ogDescription' => 'Independent-expenditure committees, Super PACs and party committees — who they are, what they raise, and which races they spend for and against.',
            'ogUrl' => route('pacs.directory'),
        ]);
    }

    public function show(Request $request, string $committee)
    {
        $fecId = strtoupper($committee);
        // The path may carry a decorative "name-slug-C00…" prefix; the FEC ID
        // is the trailing token and the only part we resolve on.
        if (preg_match('/([A-Z]\d{8})$/', $fecId, $m)) {
            $fecId = $m[1];
        }

        $data = Cache::remember("pacs:show:{$fecId}", self::SHOW_TTL, function () use ($fecId) {
            $committee = Committee::query()
                ->where('fec_committee_id', $fecId)
                ->with(['profile', 'organization'])
                ->first();

            if (! $committee || ! $committee->profile || ! $committee->profile->enriched_at) {
                return null;
            }

            return ['committee' => $committee, 'profile' => $committee->profile];
        });

        if ($data === null) {
            abort(404);
        }

        /** @var Committee $committeeModel */
        $committeeModel = $data['committee'];
        /** @var CommitteeProfile $profile */
        $profile = $data['profile'];

        // Canonicalise to the slugged URL for SEO / clean sharing.
        $canonicalPath = '/pacs/' . $committeeModel->publicSlug();
        if ($request->path() !== ltrim($canonicalPath, '/')) {
            return redirect($canonicalPath, 301);
        }

        $name = $committeeModel->name ?: $fecId;

        return view('standalone.public.pac-profile', [
            'committee' => $committeeModel,
            'profile' => $profile,
            'ogTitle' => $name,
            'ogDescription' => trim(sprintf(
                '%s — %s. %s in independent expenditures reported to the FEC for the %s cycle.',
                $name,
                $profile->kindLabel(),
                $profile->independent_expenditures ? '$' . number_format((float) $profile->independent_expenditures) : 'Spending data',
                $profile->cycle ?: ''
            )),
            'ogUrl' => url($canonicalPath),
        ]);
    }
}
