<?php

namespace App\Http\Controllers\Standalone;

use App\Http\Controllers\Controller;
use App\Models\Politician;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Manages a voter's favorited politicians list.
 */
class FavoriteController extends Controller
{
    /**
     * GET /voter/favorites
     * Show the voter's full list of favorited politicians.
     */
    public function index(Request $request)
    {
        $voter = $request->user()->voter;

        if (! $voter) {
            abort(403, 'No voter profile found.');
        }

        $favorites = $voter->favoritePoliticians()
            ->with('badges.topic')
            ->orderByPivot('favorited_at', 'desc')
            ->paginate(20);

        return view('standalone.voter.favorites.index', compact('favorites'));
    }

    /**
     * POST /voter/favorites/{politician}
     * Add a politician to the voter's favorites.
     */
    public function store(Request $request, int $politicianId): RedirectResponse
    {
        $voter = $request->user()->voter;

        if (! $voter) {
            abort(403, 'No voter profile found.');
        }

        $politician = Politician::findOrFail($politicianId);

        // sync-style: attach only if not already present
        if (! $voter->favoritePoliticians()->where('politician_id', $politician->id)->exists()) {
            $voter->favoritePoliticians()->attach($politician->id, ['favorited_at' => now()]);
        }

        return back()->with('success', "You are now following {$politician->full_name}.");
    }

    /**
     * DELETE /voter/favorites/{politician}
     * Remove a politician from the voter's favorites.
     */
    public function destroy(Request $request, int $politicianId): RedirectResponse
    {
        $voter = $request->user()->voter;

        if (! $voter) {
            abort(403, 'No voter profile found.');
        }

        $voter->favoritePoliticians()->detach($politicianId);

        return back()->with('success', 'Removed from your followed politicians.');
    }
}
