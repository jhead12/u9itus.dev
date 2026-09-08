<?php

namespace App\Http\Controllers\Api;

use App\Models\Citizen;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Map-visible local businesses (Citizen::mappable()) for one state — powers
 * the "Local Businesses" panel opened from the state stat card on the 3D map.
 * Mirrors MapBusinessSearchController's row shape, minus the typeahead.
 *
 * GET /api/v1/map/state-businesses?state=CA
 */
class MapStateBusinessesController
{
    public function __invoke(Request $request): JsonResponse
    {
        $state = strtoupper(trim((string) $request->query('state', '')));

        if (strlen($state) !== 2) {
            return response()->json(['error' => 'Provide a valid two-letter state code.'], 422);
        }

        $data = Cache::remember("map_state_businesses_{$state}", 900, function () use ($state) {
            return Citizen::query()
                ->mappable()
                ->where('state', $state)
                ->whereNotNull('business_name')
                ->orderBy('business_name')
                ->limit(250)
                ->get(['uuid', 'business_name', 'full_name', 'business_category',
                       'address_line_1', 'city', 'state', 'zip',
                       'latitude', 'longitude', 'website_url', 'verified_at', 'stripe_verified_at'])
                ->map(fn (Citizen $c) => [
                    'uuid'     => $c->uuid,
                    'name'     => $c->business_name ?: $c->full_name,
                    'category' => $c->business_category,
                    'address'  => collect([$c->address_line_1, $c->city, $c->state, $c->zip])->filter()->implode(', '),
                    'city'     => $c->city,
                    'lat'      => $c->latitude !== null ? (float) $c->latitude : null,
                    'lng'      => $c->longitude !== null ? (float) $c->longitude : null,
                    'website'  => $c->website_url,
                    'verified' => $c->isIdentityVerified(),
                ])
                ->values()
                ->all();
        });

        return response()->json(['state' => $state, 'total' => count($data), 'businesses' => $data]);
    }
}
