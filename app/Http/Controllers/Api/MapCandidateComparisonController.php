<?php

namespace App\Http\Controllers\Api;

use App\Support\PoliticianDataRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Same-seat comparison using the map's existing public, filtered candidate pool. */
class MapCandidateComparisonController
{
    public function __invoke(Request $request, MapStateCandidatesController $map, \App\Services\CandidateComparisonService $comparisons): JsonResponse
    {
        $input = $request->validate([
            'context' => ['nullable', Rule::in(['research'])],
            'state' => ['required', Rule::in(PoliticianDataRules::ALLOWED_STATES)],
            'full_name' => 'required_without:district|nullable|string|max:160',
            'id' => 'nullable|integer|min:1',
            'slug' => 'nullable|string|max:255',
            'office' => 'nullable|string|max:180',
            'district' => 'nullable|string|max:40',
            'city' => 'nullable|string|max:100',
        ]);
        return response()->json($comparisons->compare($input, $map));
    }

    /** Seats in a state with confirmed running candidates, for browsing races on /compare. */
    public function races(Request $request, MapStateCandidatesController $map, \App\Services\CandidateComparisonService $comparisons): JsonResponse
    {
        $input = $request->validate(['state' => ['required', Rule::in(PoliticianDataRules::ALLOWED_STATES)]]);

        return response()->json(['state' => $input['state'], 'races' => $comparisons->races($input['state'], $map)]);
    }
}
