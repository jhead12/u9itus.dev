<?php

namespace App\Http\Controllers\Standalone;

use App\Http\Controllers\Controller;
use App\Models\VoterFavoriteBoundary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Manages a voter's saved map boundaries (congressional districts + top cities).
 *
 * The 3D map is JS-driven, so every action returns JSON (unlike
 * FavoriteController, which renders Blade for the politician follow list).
 * City label + lat/lng are supplied by the client (sourced from the TOP_CITIES
 * dataset) so this controller stays thin and we don't duplicate that dataset
 * server-side.
 */
class BoundaryFavoriteController extends Controller
{
    /**
     * GET /voter/boundaries — JSON list for the Layers panel to hydrate chips.
     */
    public function index(Request $request): JsonResponse
    {
        $voter = $this->voterOrAbort($request);

        $rows = $voter->favoriteBoundaries()
            ->orderBy('favorited_at', 'desc')
            ->get();

        return response()->json([
            'ok' => true,
            'boundaries' => $rows->map(fn (VoterFavoriteBoundary $b) => [
                'id'              => $b->id,
                'type'            => $b->boundary_type,
                'state_abbr'      => $b->state_abbr,
                'district_number' => $b->district_number,
                'city_name'       => $b->city_name,
                'label'           => $b->label,
                'lat'             => $b->lat,
                'lng'             => $b->lng,
            ]),
        ]);
    }

    /**
     * POST /voter/boundaries — save a boundary (idempotent).
     */
    public function store(Request $request): JsonResponse
    {
        $voter = $this->voterOrAbort($request);

        $data = $request->validate([
            'type'            => ['required', Rule::in([VoterFavoriteBoundary::TYPE_DISTRICT, VoterFavoriteBoundary::TYPE_CITY])],
            'state_abbr'      => ['required', 'string', 'size:2'],
            'district_number' => ['nullable', 'string', 'max:4', 'required_if:type,district'],
            'city_name'       => ['nullable', 'string', 'max:120', 'required_if:type,city'],
            'label'           => ['required', 'string', 'max:160'],
            'lat'             => ['nullable', 'numeric', 'between:-90,90', 'required_if:type,city'],
            'lng'             => ['nullable', 'numeric', 'between:-180,180', 'required_if:type,city'],
        ]);

        $isCity = $data['type'] === VoterFavoriteBoundary::TYPE_CITY;

        $boundary = $voter->favoriteBoundaries()->firstOrCreate(
            [
                'boundary_type'   => $data['type'],
                'state_abbr'      => strtoupper($data['state_abbr']),
                'district_number' => $isCity ? null : ($data['district_number'] ?? null),
                'city_name'       => $isCity ? ($data['city_name'] ?? null) : null,
            ],
            [
                'label'  => $data['label'],
                'lat'    => $isCity ? ($data['lat'] ?? null) : null,
                'lng'    => $isCity ? ($data['lng'] ?? null) : null,
            ]
        );

        return response()->json([
            'ok'       => true,
            'id'       => $boundary->id,
            'created'  => $boundary->wasRecentlyCreated,
        ]);
    }

    /**
     * DELETE /voter/boundaries/{id} — remove a saved boundary.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $voter = $this->voterOrAbort($request);

        // Scope to this voter so a voter can never touch another's rows.
        $deleted = $voter->favoriteBoundaries()->where('id', $id)->delete();

        return response()->json(['ok' => true, 'deleted' => (bool) $deleted]);
    }

    private function voterOrAbort(Request $request)
    {
        $voter = $request->user()?->voter;

        if (! $voter) {
            abort(403, 'No voter profile found.');
        }

        return $voter;
    }
}