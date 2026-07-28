<?php

namespace App\Http\Controllers\Standalone;

use App\Http\Controllers\Controller;
use App\Models\Politician;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Manages a voter's personal note on a politician — one running note per
 * voter+politician pair (upsert, not a timestamped journal). JSON-only,
 * mirroring BoundaryFavoriteController.
 */
class PoliticianNoteController extends Controller
{
    public function show(Request $request, int $politicianId): JsonResponse
    {
        $voter = $this->voterOrAbort($request);

        $note = $voter->politicianNotes()->where('politician_id', $politicianId)->first();

        return response()->json([
            'ok'   => true,
            'note' => $note ? [
                'body'       => $note->body,
                'updated_at' => $note->updated_at,
            ] : null,
        ]);
    }

    public function store(Request $request, int $politicianId): JsonResponse
    {
        $voter = $this->voterOrAbort($request);

        $politician = Politician::findOrFail($politicianId);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $note = $voter->politicianNotes()->updateOrCreate(
            ['politician_id' => $politician->id],
            ['body' => $data['body']]
        );

        return response()->json([
            'ok'   => true,
            'note' => [
                'body'       => $note->body,
                'updated_at' => $note->updated_at,
            ],
        ]);
    }

    public function destroy(Request $request, int $politicianId): JsonResponse
    {
        $voter = $this->voterOrAbort($request);

        $deleted = $voter->politicianNotes()->where('politician_id', $politicianId)->delete();

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
