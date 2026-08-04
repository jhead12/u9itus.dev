<?php

namespace App\Http\Controllers\Api;

use App\Services\GoogleCivicVoterInfoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public endpoint returning polling places / early-vote sites / drop-off
 * locations for an address, for the map's district-panel "Find Your Polling
 * Place" widget. Thin wrapper — GoogleCivicVoterInfoService already does all
 * the work and is used unmodified (it also backs the standalone
 * /district-lookup page).
 */
class MapPollingLocationsController
{
    public function __construct(private GoogleCivicVoterInfoService $voterInfo)
    {
    }

    /**
     * GET /api/v1/map/polling-locations?address=...
     */
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'address' => ['required', 'string', 'max:255'],
        ]);

        if (! $this->voterInfo->isConfigured()) {
            return response()->json(['configured' => false]);
        }

        $result = $this->voterInfo->getByAddress($data['address']);

        return response()->json([
            'configured' => true,
            'election' => $result['election'] ?? null,
            'polling_locations' => $result['polling_locations'] ?? [],
            'early_vote_sites' => $result['early_vote_sites'] ?? [],
            'drop_off_locations' => $result['drop_off_locations'] ?? [],
        ]);
    }
}
