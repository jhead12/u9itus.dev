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
        $request->validate([
            'state' => ['nullable', \Illuminate\Validation\Rule::in(\App\Support\PoliticianDataRules::ALLOWED_STATES)],
            'mode' => ['nullable', \Illuminate\Validation\Rule::in(['name', 'district', 'address'])],
            'q' => ['nullable', 'string', 'max:160'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:25'],
        ]);
        $q = trim((string) $request->query('q', ''));
        $mode = $request->query('mode', 'name');
        $state = $request->query('state');
        $districtLabel = null;
        $district = null;
        if ($mode === 'name' && mb_strlen($q) < 2) {
            return response()->json(['results' => []]);
        }
        if ($mode === 'district') {
            if (! preg_match('/^(?:((?!CD)[A-Z]{2})[- ]?)?(?:DISTRICT\s*|CD[- ]?)?(\d{1,2}|AL|AT[- ]LARGE)$/i', $q, $match)) {
                return response()->json(['results' => [], 'message' => 'Enter a congressional district, such as CA-03, 3, or AL.']);
            }
            $prefix = strtoupper($match[1]);
            if ($prefix && $state && $prefix !== $state) {
                return response()->json(['results' => [], 'message' => 'The district and selected state do not match.']);
            }
            $state = $prefix ?: $state;
            if (! in_array($state, \App\Support\PoliticianDataRules::ALLOWED_STATES, true)) {
                return response()->json(['results' => [], 'message' => 'Select a state or enter a district with its state, such as CA-03.']);
            }
            $district = is_numeric($match[2]) ? (string) (int) $match[2] : '0';
        } elseif ($mode === 'address') {
            if ($q === '') return response()->json(['results' => [], 'message' => 'Enter a street address.']);
            // Each address search calls the external Census geocoder; keep the
            // same per-IP budget as the map's other geocoding endpoint.
            $limiter = 'map-geocode:'.$request->ip();
            if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($limiter, 30)) {
                return response()->json(['results' => [], 'message' => 'Too many address searches. Please wait a minute and try again.'], 429);
            }
            \Illuminate\Support\Facades\RateLimiter::hit($limiter);
            $lookup = app(\App\Services\DistrictLookupService::class)->lookup($q.($state ? ', '.$state : ''));
            if (! $lookup || ! in_array($lookup['state'] ?? null, \App\Support\PoliticianDataRules::ALLOWED_STATES, true)
                || ! preg_match('/^(?:\d{1,2}|AL)$/i', (string) ($lookup['district_number'] ?? ''))) {
                return response()->json(['results' => [], 'message' => 'Could not resolve that address. Try a full street address; a city can span several districts.']);
            }
            if ($state && $state !== $lookup['state']) {
                return response()->json(['results' => [], 'message' => 'The address resolved outside the selected state. Check the address or state.']);
            }
            $state = $lookup['state'];
            $district = strtoupper((string) $lookup['district_number']) === 'AL' ? '0' : (string) (int) $lookup['district_number'];
        }

        $query = Politician::query()->where('page_published', true)->where('is_active', true)
            ->when($state, fn ($query) => $query->where('state', $state));
        if ($district !== null) {
            $padded = str_pad($district, 2, '0', STR_PAD_LEFT);
            $variants = [$district, $padded, "$state-$district", "$state-$padded", "DISTRICT $district", "DISTRICT $padded", "CD $district", "CD-$district", "CD $padded", "CD-$padded"];
            if ($district === '0') $variants = array_merge($variants, ['AL', 'AT-LARGE', 'AT LARGE', "$state-AL"]);
            $query->whereIn(\Illuminate\Support\Facades\DB::raw('UPPER(TRIM(district))'), $variants)
                ->where(function ($query) {
                    foreach (['U.S. Representative%', 'US Representative%', 'United States Representative%', 'U.S. House%', 'US House%', 'United States House%'] as $office) {
                        $query->orWhere('political_office', 'like', $office);
                    }
                });
            $districtLabel = $state.'-'.($district === '0' ? 'AL' : $padded);
        } else {
            // Like the Web Reporter picker: every word must appear in the
            // name, office, party, or state ("jamie ca democrat").
            foreach (array_slice(preg_split('/\s+/', $q), 0, 6) as $word) {
                $like = '%'.addcslashes($word, '%_\\').'%';
                $query->where(fn ($query) => $query->where('full_name', 'like', $like)
                    ->orWhere('political_office', 'like', $like)
                    ->orWhere('party_affiliation', 'like', $like)
                    ->orWhere('state', 'like', $like));
            }
            $query->orderByRaw('CASE WHEN LOWER(full_name) LIKE ? THEN 0 ELSE 1 END', [mb_strtolower($q).'%']);
        }
        $politicians = $query->orderByDesc('verified_official')->orderBy('full_name')
            ->limit($district !== null ? 50 : (int) $request->query('limit', 8))
            ->get([
                'id', 'full_name', 'political_office', 'party_affiliation',
                'profile_photo_url', 'slug', 'state', 'city', 'district',
                'governance_level', 'term_status', 'is_running_candidate',
                'verified_official', 'ballotpedia_id', 'website_url', 'bio',
            ]);

        $results = $politicians->map(fn (Politician $pol) => [
            'id'               => $pol->id,
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

        return response()->json(['results' => $results->values(), 'district_label' => $districtLabel,
            'message' => $results->isEmpty() && $districtLabel ? 'No published candidates are recorded for '.$districtLabel.' yet.' : null]);
    }
}
