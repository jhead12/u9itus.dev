<?php

namespace App\Http\Controllers\Api;

use App\Models\Politician;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Live typeahead search over published politician profiles, powering the
 * "Politicians" result group in the 3D map's search palette.
 *
 * GET /api/v1/map/politician-search?q=warren
 */
class MapPoliticianSearchController
{
    public function __invoke(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        if (mb_strlen($q) < 2) {
            return response()->json(['results' => []]);
        }

        $politicians = Politician::query()
            ->where('page_published', true)
            ->where('is_active', true)
            ->where('full_name', 'like', '%' . $q . '%')
            ->orderByRaw('CASE WHEN LOWER(full_name) LIKE ? THEN 0 ELSE 1 END', [mb_strtolower($q) . '%'])
            ->orderByDesc('verified_official')
            ->orderBy('full_name')
            ->limit(8)
            ->get([
                'full_name', 'political_office', 'party_affiliation',
                'profile_photo_url', 'slug', 'state', 'city', 'district',
                'governance_level', 'term_status', 'is_running_candidate',
                'verified_official', 'ballotpedia_id', 'website_url', 'bio',
            ]);

        $results = $politicians->map(fn (Politician $pol) => [
            'full_name'        => $pol->full_name,
            'office'           => $pol->political_office,
            'party'            => $pol->party_affiliation,
            'state'            => $pol->state,
            'city'             => $pol->city,
            'district'         => $pol->district,
            'governance_level' => $pol->governance_level,
            'photo'            => $pol->profile_photo_url
                ? (str_starts_with($pol->profile_photo_url, 'http') ? $pol->profile_photo_url : url($pol->profile_photo_url))
                : null,
            'slug'             => $pol->slug,
            'status'           => $pol->term_status,
            'is_running'       => (bool) $pol->is_running_candidate,
            'verified'         => (bool) $pol->verified_official,
            'ballotpedia_url'  => $pol->ballotpedia_id ? 'https://ballotpedia.org/' . $pol->ballotpedia_id : null,
            'website'          => $pol->website_url,
            'profile_url'      => $pol->slug ? url('/p/' . $pol->slug) : null,
            'bio_excerpt'      => $pol->bio ? Str::limit($pol->bio, 180) : null,
        ]);

        return response()->json(['results' => $results->values()]);
    }
}
