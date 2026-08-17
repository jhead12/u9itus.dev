<?php

namespace App\Http\Controllers\Standalone;

use App\Http\Controllers\Controller;
use App\Models\BallotMeasure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * Admin: manage ballot measures. Complements the existing Ballotpedia
 * import pipeline (ImportBallotMeasures / BallotpediaService) with a manual
 * create/edit path for staff.
 */
class AdminBallotMeasureController extends Controller
{
    public function index(Request $request): View
    {
        $query = BallotMeasure::query()->orderByDesc('election_date');

        if ($request->filled('q')) {
            $q = $request->q;
            $query->where(function ($builder) use ($q) {
                $builder->where('title', 'like', "%{$q}%")
                        ->orWhere('state', 'like', "%{$q}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $ballotMeasures = $query->paginate(40)->withQueryString();

        return view('standalone.admin.ballot-measures.index', compact('ballotMeasures'));
    }

    public function create(): View
    {
        $ballotMeasure = new BallotMeasure();

        return view('standalone.admin.ballot-measures.create', compact('ballotMeasure'));
    }

    public function store(Request $request): RedirectResponse
    {
        // Manual admin entries are distinguished from the Ballotpedia/CSV import.
        $validated = array_merge($this->validated($request), ['source' => 'manual']);

        try {
            // Same identity key ImportBallotMeasures dedupes on (state + title +
            // election date — whereDate() since election_date is stored as a
            // datetime). Without this, a double form-submit silently created two
            // identical rows (e.g. CA "Prop 5 — Bond for Schools" showing twice
            // on the public map) since the table had no unique constraint.
            $existing = BallotMeasure::query()
                ->where('state', $validated['state'])
                ->where('title', $validated['title'])
                ->when(
                    $validated['election_date'] !== null,
                    fn ($q) => $q->whereDate('election_date', $validated['election_date']),
                    fn ($q) => $q->whereNull('election_date'),
                )
                ->first();

            if ($existing) {
                $existing->update($validated);

                return redirect()->route('admin.ballot-measures.index')
                    ->with('success', "A matching measure already existed for {$validated['state']} — updated it instead of creating a duplicate.");
            }

            BallotMeasure::create($validated);
        } catch (\Throwable $e) {
            Log::error('AdminBallotMeasureController: failed to create ballot measure', ['error' => $e->getMessage()]);

            return back()->withInput()->with('error', 'Failed to create ballot measure. Please try again.');
        }

        return redirect()->route('admin.ballot-measures.index')->with('success', 'Ballot measure created successfully.');
    }

    public function edit(BallotMeasure $ballotMeasure): View
    {
        return view('standalone.admin.ballot-measures.edit', compact('ballotMeasure'));
    }

    public function update(Request $request, BallotMeasure $ballotMeasure): RedirectResponse
    {
        $validated = $this->validated($request);

        try {
            $ballotMeasure->update($validated);
        } catch (\Throwable $e) {
            Log::error('AdminBallotMeasureController: failed to update ballot measure', ['ballot_measure_id' => $ballotMeasure->id, 'error' => $e->getMessage()]);

            return back()->withInput()->with('error', 'Failed to save ballot measure. Please try again.');
        }

        return redirect()->route('admin.ballot-measures.index')->with('success', "\"{$ballotMeasure->title}\" saved successfully.");
    }

    public function destroy(BallotMeasure $ballotMeasure): RedirectResponse
    {
        $ballotMeasure->delete();

        return redirect()->route('admin.ballot-measures.index')->with('success', 'Ballot measure deleted.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'state'          => ['required', 'string', 'size:2'],
            'county'         => ['nullable', 'string', 'max:100'],
            'measure_number' => ['nullable', 'string', 'max:20'],
            'title'          => ['required', 'string', 'max:255'],
            'summary'        => ['nullable', 'string'],
            'yes_meaning'    => ['nullable', 'string'],
            'no_meaning'     => ['nullable', 'string'],
            'election_date'  => ['nullable', 'date'],
            'status'         => ['required', 'string', 'max:20'],
            'source_url'     => ['nullable', 'url', 'max:2048'],
        ]);
    }
}
