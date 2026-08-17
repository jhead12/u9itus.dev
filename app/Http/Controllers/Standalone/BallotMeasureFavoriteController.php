<?php

namespace App\Http\Controllers\Standalone;

use App\Http\Controllers\Controller;
use App\Models\BallotMeasure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Manages a voter's favorited Ballot Measures. JSON-only, mirroring
 * BoundaryFavoriteController.
 */
class BallotMeasureFavoriteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $voter = $this->voterOrAbort($request);

        $measures = $voter->favoriteBallotMeasures()
            ->orderByPivot('favorited_at', 'desc')
            ->get();

        return response()->json([
            'ok' => true,
            'ballot_measures' => $measures->map(fn (BallotMeasure $measure) => [
                'id'    => $measure->id,
                'state' => $measure->state,
                'county' => $measure->county,
                'title' => $measure->title,
                'status' => $measure->status,
            ]),
        ]);
    }

    public function store(Request $request, int $ballotMeasureId): JsonResponse
    {
        $voter = $this->voterOrAbort($request);

        $measure = BallotMeasure::findOrFail($ballotMeasureId);

        $exists = $voter->favoriteBallotMeasures()->where('ballot_measure_id', $measure->id)->exists();
        if (! $exists) {
            $voter->favoriteBallotMeasures()->attach($measure->id, ['favorited_at' => now()]);
        }

        return response()->json([
            'ok'      => true,
            'id'      => $measure->id,
            'created' => ! $exists,
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $voter = $this->voterOrAbort($request);

        $deleted = $voter->favoriteBallotMeasures()->detach($id);

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
