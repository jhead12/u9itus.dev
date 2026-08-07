<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Politician;
use App\Models\StateDemographic;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * GET /api/v1/map/state-overlays
 *
 * All-50-states-at-once summary data for the map's overview-zoom choropleth
 * layers (Party Control, Poverty Rate) — as opposed to MapStateCandidatesController,
 * which requires a single state and returns that state's full candidate detail.
 * Those two are deliberately separate: patching the single-state controller to
 * also handle an all-states mode would risk its heavily-used per-state cache
 * entries, so this is its own small cached endpoint.
 */
class MapStateOverlaysController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $data = Cache::remember('map_state_overlays', 3600, function () {
            return [
                'governor_parties' => $this->governorParties(),
                'poverty_rate' => StateDemographic::query()
                    ->whereNotNull('poverty_rate')
                    ->pluck('poverty_rate', 'state')
                    ->map(fn ($v) => (float) $v)
                    ->all(),
            ];
        });

        return response()->json($data);
    }

    /**
     * One seated governor's party per state, keyed by 2-letter state code.
     * Mirrors the office-matching MapStateCandidatesController does per-state
     * (fuzzy-match "governor", excluding "lieutenant governor"), aggregated
     * across all states in one query instead of one state at a time.
     *
     * @return array<string, string>
     */
    private function governorParties(): array
    {
        $rows = Politician::query()
            ->whereRaw("LOWER(COALESCE(governance_level, '')) = ?", ['state'])
            ->where('is_active', true)
            ->whereIn('term_status', ['seated', 'active'])
            ->whereRaw("LOWER(COALESCE(political_office, '')) LIKE ?", ['%governor%'])
            ->whereRaw("LOWER(COALESCE(political_office, '')) NOT LIKE ?", ['%lieutenant%'])
            ->orderByRaw("CASE WHEN term_status = 'seated' THEN 0 ELSE 1 END")
            ->get(['state', 'party_affiliation', 'term_status']);

        $byState = [];
        foreach ($rows as $row) {
            $abbr = strtoupper((string) $row->state);
            if ($abbr === '' || isset($byState[$abbr])) {
                continue; // first match wins per state (seated ordered before active via the query below)
            }
            $party = strtoupper(substr((string) $row->party_affiliation, 0, 1));
            $byState[$abbr] = $party ?: 'U';
        }

        return $byState;
    }
}
