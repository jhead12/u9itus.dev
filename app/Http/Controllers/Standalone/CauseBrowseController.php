<?php

namespace App\Http\Controllers\Standalone;

use App\Http\Controllers\Controller;
use App\Models\Cause;
use App\Models\PoliticianTopic;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Voter-facing Causes browse pages (directory + show). The JSON
 * favorite store/destroy endpoints live in CauseFavoriteController and are
 * called from the show page's favorite toggle.
 *
 * "People near you" = count of *other* voters in the current voter's state
 * who favorited the same cause. State-only scoping: voters have no county
 * column and congressional_district is messy free-text. National causes
 * (state = null) show a nationwide total instead.
 */
class CauseBrowseController extends Controller
{
    public function index(Request $request): View
    {
        $voter = $request->user()?->voter;
        $voterState = $voter?->state;

        $causes = Cause::active()
            ->when($request->filled('q'), fn ($q, $v) => $q->where('title', 'like', "%{$v}%"))
            ->when($request->filled('state'), fn ($q, $s) => $q->where('state', $s))
            ->when($request->filled('topic_id'), fn ($q, $t) => $q->where('topic_id', $t))
            ->withCount([
                'favoriteVoters as nearby_supporters_count' => fn ($q) => $q
                    ->when($voterState, fn ($q) => $q->where('voters.state', $voterState))
                    ->when($voter, fn ($q) => $q->where('voters.id', '!=', $voter->id)),
                'favoriteVoters as supporters_total_count',
            ])
            ->when($voter, fn ($q) => $q->withExists([
                'favoriteVoters as favorited_by_voter' => fn ($q) => $q->where('voters.id', $voter->id),
            ]))
            ->orderByDesc('nearby_supporters_count')
            ->orderBy('title')
            ->paginate(24)
            ->withQueryString();

        $states = config('u9itus.us_states', []);
        $topics = PoliticianTopic::active()->pluck('name', 'id');

        return view('standalone.voter.causes.directory', compact('causes', 'states', 'topics'));
    }

    public function show(Request $request, Cause $cause): View
    {
        $voter = $request->user()?->voter;

        $cause->loadCount([
            'favoriteVoters as nearby_supporters_count' => fn ($q) => $q
                ->when($voter?->state, fn ($q) => $q->where('voters.state', $voter->state))
                ->when($voter, fn ($q) => $q->where('voters.id', '!=', $voter->id)),
            'favoriteVoters as supporters_total_count',
        ]);

        $isFavorited = $voter
            ? $voter->favoriteCauses()->where('cause_id', $cause->id)->exists()
            : false;

        return view('standalone.voter.causes.show', compact('cause', 'isFavorited'));
    }
}