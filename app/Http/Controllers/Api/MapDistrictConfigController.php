<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * GET /api/v1/map/district-config
 *
 * Returns the active congressional boundary configuration so the 3D map
 * can resolve the correct TIGERweb layer, CD field name, party map, and
 * Congress label without a code deploy when a new Congress is seated.
 *
 * Response is cached for 1 hour; the cache is busted whenever
 * geo:sync-district-config writes a new config row.
 *
 * No authentication required — this is public civic data.
 */
class MapDistrictConfigController
{
    /**
     * Default config used when geo:sync-district-config has not yet been run.
     * Mirrors the values previously hardcoded in the blade template.
     */
    private const FALLBACK = [
        'congress_number' => 119,
        'tigerweb_layer'  => 0,
        'cd_field'        => 'CD119',
        'congress_label'  => '119th Congress (2025–2027)',
        'party_map'       => [],
        'synced_at'       => null,
    ];

    public function __invoke(): JsonResponse
    {
        $config = Cache::remember('district_config', 3600, function () {
            return DB::table('district_config')
                ->orderByDesc('synced_at')
                ->first();
        });

        if (! $config) {
            return response()->json(self::FALLBACK);
        }

        $partyMap = [];
        if ($config->party_map) {
            $decoded = json_decode($config->party_map, true);
            if (is_array($decoded)) {
                $partyMap = $decoded;
            }
        }

        return response()->json([
            'congress_number' => (int) $config->congress_number,
            'tigerweb_layer'  => (int) $config->tigerweb_layer,
            'cd_field'        => $config->cd_field,
            'congress_label'  => $config->congress_label,
            'party_map'       => $partyMap,
            'synced_at'       => $config->synced_at,
        ]);
    }
}
