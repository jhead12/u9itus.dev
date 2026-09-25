<?php

namespace App\Http\Controllers\Standalone;

use App\Http\Controllers\Controller;
use App\Models\BallotMeasure;
use App\Support\MeasureCommitteeRules;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Voter-facing Ballot Measures browse pages (directory + show). The JSON
 * favorite store/destroy endpoints live in BallotMeasureFavoriteController.
 *
 * Ballot measures are never national — they are statewide or scoped to a county,
 * city or district (`level`) — so the directory defaults to the voter's state. "People near you" is the
 * count of *other* voters in the current voter's state who favorited the
 * same measure (state-only, since voters have no county column).
 */
class BallotMeasureBrowseController extends Controller
{
    public function index(Request $request): View
    {
        $voter = $request->user()?->voter;

        // Defaults: voter's state + upcoming measures (ballot measures are never national).
        $state = $request->input('state', $voter?->state);
        $status = $request->input('status', 'upcoming');

        $measures = BallotMeasure::query()
            ->when($request->filled('q'), function ($q) use ($request) {
                $v = (string) $request->input('q');
                $q->where(function ($q) use ($v) {
                    $q->where('title', 'like', "%{$v}%")
                        ->orWhere('measure_number', 'like', "%{$v}%")
                        ->orWhere('county', 'like', "%{$v}%")
                        ->orWhere('locality', 'like', "%{$v}%");
                });
            })
            ->when($state, fn ($q, $s) => $q->where('state', $s))
            ->when(array_key_exists((string) $request->input('level'), BallotMeasure::LEVELS), fn ($q) => $q->where('level', $request->input('level')))
            ->when($status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->filled('year'), fn ($q, $y) => $q->whereYear('election_date', $y))
            ->withCount([
                'favoriteVoters as nearby_supporters_count' => fn ($q) => $q
                    ->when($voter?->state, fn ($q) => $q->where('voters.state', $voter->state))
                    ->when($voter, fn ($q) => $q->where('voters.id', '!=', $voter->id)),
            ])
            ->when($voter, fn ($q) => $q->withExists([
                'favoriteVoters as favorited_by_voter' => fn ($q) => $q->where('voters.id', $voter->id),
            ]))
            ->orderBy('election_date')
            ->paginate(24)
            ->withQueryString();

        $states = config('u9itus.us_states', []);
        $statuses = ['upcoming' => 'Upcoming', 'passed' => 'Passed', 'failed' => 'Failed'];
        $levels = BallotMeasure::LEVELS;

        return view('standalone.voter.ballot-measures.directory', compact('measures', 'states', 'statuses', 'status', 'state', 'levels'));
    }

    public function show(Request $request, BallotMeasure $measure): View
    {
        $voter = $request->user()?->voter;

        $measure->loadCount([
            'favoriteVoters as nearby_supporters_count' => fn ($q) => $q
                ->when($voter?->state, fn ($q) => $q->where('voters.state', $voter->state))
                ->when($voter, fn ($q) => $q->where('voters.id', '!=', $voter->id)),
            'favoriteVoters as supporters_total_count',
        ]);

        $isFavorited = $voter
            ? $voter->favoriteBallotMeasures()->where('ballot_measure_id', $measure->id)->exists()
            : false;

        // Only verified links: a committee name rarely says which side it's on, so an
        // unchecked link could tell voters the wrong thing.
        $committees = $measure->committees()->verified()->orderBy('committee_name')->get()->groupBy('position');
        $financeUrl = MeasureCommitteeRules::financeRegistryUrl((string) $measure->state);

        return view('standalone.voter.ballot-measures.show', compact('measure', 'isFavorited', 'committees', 'financeUrl'));
    }
}