<?php

namespace App\Http\Controllers\Api;

use App\Models\Citizen;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Live typeahead search over map-visible (Citizen::mappable()) businesses,
 * powering the "Local Businesses" result group in the 3D map's search
 * palette. Mirrors MapPoliticianSearchController's shape/conventions.
 *
 * GET /api/v1/map/business-search?q=bakery
 */
class MapBusinessSearchController
{
    public function __invoke(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        if (mb_strlen($q) < 2) {
            return response()->json(['results' => []]);
        }

        $businesses = Citizen::query()
            ->mappable()
            ->where(function ($query) use ($q) {
                $query->where('business_name', 'like', '%'.$q.'%')
                    ->orWhere('full_name', 'like', '%'.$q.'%');
            })
            ->orderByRaw('CASE WHEN LOWER(COALESCE(business_name, full_name)) LIKE ? THEN 0 ELSE 1 END', [mb_strtolower($q).'%'])
            ->orderByDesc('verified_at')
            ->limit(8)
            ->get(['uuid', 'business_name', 'full_name', 'business_category', 'address_line_1', 'city', 'state', 'zip', 'latitude', 'longitude', 'verified_at']);

        $results = $businesses->map(fn (Citizen $citizen) => [
            'uuid'     => $citizen->uuid,
            'name'     => $citizen->business_name ?: $citizen->full_name,
            'category' => $citizen->business_category,
            'address'  => collect([$citizen->address_line_1, $citizen->city, $citizen->state, $citizen->zip])
                ->filter()
                ->implode(', '),
            'state'    => $citizen->state,
            'lat'      => (float) $citizen->latitude,
            'lng'      => (float) $citizen->longitude,
            'verified' => $citizen->isIdentityVerified(),
        ]);

        return response()->json(['results' => $results->values()]);
    }
}
