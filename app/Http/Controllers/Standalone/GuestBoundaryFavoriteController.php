<?php

namespace App\Http\Controllers\Standalone;

use App\Http\Controllers\Controller;
use App\Models\Voter;
use App\Models\VoterFavoriteBoundary;
use App\Support\GuestBoundaryCookie;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Validation\Rule;

/**
 * Guest (unauthenticated) counterpart to BoundaryFavoriteController.
 *
 * Returns the same JSON shape as /voter/boundaries so favorites.js can point
 * at either transparently. Storage mode depends on whether this browser has
 * already opted into the weekly digest (see GuestDigestOptInController):
 *
 *  - No `u9_guest_voter_id` cookie yet → favorites live in the
 *    `u9_guest_boundaries` cookie as a capped JSON array.
 *  - Cookie present and its pending Voter row still exists → favorites are
 *    written straight to voter_favorite_boundaries under that row, exactly
 *    like a real voter, so they're already in the DB by the time the guest
 *    later registers (RegistrationController absorbs the row by email).
 *
 * /voter/boundaries (role:voter-gated) is untouched by this controller.
 */
class GuestBoundaryFavoriteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if ($pending = $this->resolvePendingVoter($request)) {
            $rows = $pending->favoriteBoundaries()->orderBy('favorited_at', 'desc')->get();

            return response()->json([
                'ok' => true,
                'boundaries' => $rows->map(fn (VoterFavoriteBoundary $b) => [
                    'id' => $b->id,
                    'type' => $b->boundary_type,
                    'state_abbr' => $b->state_abbr,
                    'district_number' => $b->district_number,
                    'city_name' => $b->city_name,
                    'label' => $b->label,
                    'lat' => $b->lat,
                    'lng' => $b->lng,
                ]),
            ]);
        }

        $items = GuestBoundaryCookie::readItems($request);

        return response()->json([
            'ok' => true,
            'boundaries' => array_map(fn (array $item) => [
                'id' => GuestBoundaryCookie::keyFor($item),
                'type' => $item['type'] ?? null,
                'state_abbr' => $item['state_abbr'] ?? null,
                'district_number' => $item['district_number'] ?? null,
                'city_name' => $item['city_name'] ?? null,
                'label' => $item['label'] ?? null,
                'lat' => $item['lat'] ?? null,
                'lng' => $item['lng'] ?? null,
            ], $items),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in([VoterFavoriteBoundary::TYPE_DISTRICT, VoterFavoriteBoundary::TYPE_CITY])],
            'state_abbr' => ['required', 'string', 'size:2'],
            'district_number' => ['nullable', 'string', 'max:4', 'required_if:type,district'],
            'city_name' => ['nullable', 'string', 'max:120', 'required_if:type,city'],
            'label' => ['required', 'string', 'max:160'],
            'lat' => ['nullable', 'numeric', 'between:-90,90', 'required_if:type,city'],
            'lng' => ['nullable', 'numeric', 'between:-180,180', 'required_if:type,city'],
        ]);

        $isCity = $data['type'] === VoterFavoriteBoundary::TYPE_CITY;
        $data['state_abbr'] = strtoupper($data['state_abbr']);

        if ($pending = $this->resolvePendingVoter($request)) {
            $boundary = $pending->favoriteBoundaries()->firstOrCreate(
                [
                    'boundary_type' => $data['type'],
                    'state_abbr' => $data['state_abbr'],
                    'district_number' => $isCity ? null : ($data['district_number'] ?? null),
                    'city_name' => $isCity ? ($data['city_name'] ?? null) : null,
                ],
                [
                    'label' => $data['label'],
                    'lat' => $isCity ? ($data['lat'] ?? null) : null,
                    'lng' => $isCity ? ($data['lng'] ?? null) : null,
                ]
            );

            return response()->json(['ok' => true, 'id' => $boundary->id, 'created' => $boundary->wasRecentlyCreated]);
        }

        $items = GuestBoundaryCookie::readItems($request);

        $key = GuestBoundaryCookie::keyFor($data);
        $existingIndex = null;

        foreach ($items as $i => $existing) {
            if (GuestBoundaryCookie::keyFor($existing) === $key) {
                $existingIndex = $i;
                break;
            }
        }

        if ($existingIndex !== null) {
            return response()->json(['ok' => true, 'id' => $key, 'created' => false]);
        }

        if (count($items) >= GuestBoundaryCookie::MAX_ITEMS) {
            return response()->json(['ok' => false, 'error' => 'limit_reached'], 422);
        }

        $items[] = [
            'type' => $data['type'],
            'state_abbr' => $data['state_abbr'],
            'district_number' => $isCity ? null : ($data['district_number'] ?? null),
            'city_name' => $isCity ? ($data['city_name'] ?? null) : null,
            'label' => $data['label'],
            'lat' => $isCity ? ($data['lat'] ?? null) : null,
            'lng' => $isCity ? ($data['lng'] ?? null) : null,
            'favorited_at' => now()->toIso8601String(),
        ];

        Cookie::queue(GuestBoundaryCookie::makeCookie($items, $request));

        return response()->json(['ok' => true, 'id' => $key, 'created' => true]);
    }

    public function destroy(Request $request, string $key): JsonResponse
    {
        if ($pending = $this->resolvePendingVoter($request)) {
            // DB mode: `key` is the numeric id BoundaryFavoriteController hands out.
            $deleted = ctype_digit($key)
                ? $pending->favoriteBoundaries()->where('id', (int) $key)->delete()
                : 0;

            return response()->json(['ok' => true, 'deleted' => (bool) $deleted]);
        }

        $items = GuestBoundaryCookie::readItems($request);
        $filtered = array_values(array_filter(
            $items,
            fn (array $item) => GuestBoundaryCookie::keyFor($item) !== $key
        ));

        $deleted = count($filtered) !== count($items);
        Cookie::queue(GuestBoundaryCookie::makeCookie($filtered, $request));

        return response()->json(['ok' => true, 'deleted' => $deleted]);
    }

    private function resolvePendingVoter(Request $request): ?Voter
    {
        $uuid = GuestBoundaryCookie::readVoterUuid($request);

        if ($uuid === null) {
            return null;
        }

        return Voter::whereNull('user_id')->where('uuid', $uuid)->first();
    }
}
