<?php

namespace App\Http\Controllers\Standalone;

use App\Http\Controllers\Controller;
use App\Jobs\NotifyVoterOfMatchingCampaigns;
use App\Models\Cause;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Manages a voter's favorited Causes (specific issues under a Topic).
 * JSON-only, mirroring BoundaryFavoriteController.
 */
class CauseFavoriteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $voter = $this->voterOrAbort($request);

        $causes = $voter->favoriteCauses()
            ->orderByPivot('favorited_at', 'desc')
            ->get();

        return response()->json([
            'ok' => true,
            'causes' => $causes->map(fn (Cause $cause) => [
                'id'       => $cause->id,
                'topic_id' => $cause->topic_id,
                'title'    => $cause->title,
                'state'    => $cause->state,
                'county'   => $cause->county,
            ]),
        ]);
    }

    public function store(Request $request, int $causeId): JsonResponse
    {
        $voter = $this->voterOrAbort($request);

        $cause = Cause::findOrFail($causeId);

        $exists = $voter->favoriteCauses()->where('cause_id', $cause->id)->exists();
        if (! $exists) {
            $voter->favoriteCauses()->attach($cause->id, ['favorited_at' => now()]);
            NotifyVoterOfMatchingCampaigns::dispatch($voter->id, $cause->id);
        }

        return response()->json([
            'ok'      => true,
            'id'      => $cause->id,
            'created' => ! $exists,
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $voter = $this->voterOrAbort($request);

        $deleted = $voter->favoriteCauses()->detach($id);

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
