<?php

namespace App\Http\Controllers\Standalone;

use App\Http\Controllers\Controller;
use App\Models\BallotMeasure;
use App\Models\BallotMeasureCommittee;
use App\Models\CommitteeTransfer;
use App\Models\ElectionDataSource;
use App\Services\CampaignFinance\CalAccessExport;
use App\Support\MeasureCommitteePriority;
use App\Support\MeasureCommitteeRules;
use App\Support\PoliticianDataRules;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Admin: link campaign committees to the ballot measures they support or oppose, and set
 * each state's official campaign finance site. A link is only shown to voters once it is
 * verified; flagged links (MeasureCommitteeRules::flags) wait in the review queue, riskiest
 * first, for a reviewer to accept or reject.
 */
class AdminBallotMeasureCommitteeController extends Controller
{
    /** Pending links across every measure, highest risk priority first. */
    public function index(): View
    {
        $pending = BallotMeasureCommittee::query()
            ->where('status', BallotMeasureCommittee::STATUS_PENDING)
            ->with(['ballotMeasure:id,state,county,locality,level,measure_number,title', 'verifiedBy:id,name'])
            ->orderByRaw('priority_score IS NULL, priority_score DESC')
            ->orderBy('created_at')
            ->paginate(50);

        return view('standalone.admin.ballot-measures.committee-queue', compact('pending'));
    }

    public function show(BallotMeasure $ballotMeasure): View
    {
        $committees = $ballotMeasure->committees()
            ->with('verifiedBy:id,name')
            ->orderByRaw("CASE status WHEN 'pending' THEN 0 WHEN 'verified' THEN 1 ELSE 2 END")
            ->orderBy('position')
            ->get();

        $financeUrl = MeasureCommitteeRules::financeRegistryUrl((string) $ballotMeasure->state);

        return view('standalone.admin.ballot-measures.committees', [
            'measure' => $ballotMeasure,
            'committees' => $committees,
            'financeUrl' => $financeUrl,
            'suggestions' => $this->suggestions($ballotMeasure, $committees),
        ]);
    }

    /**
     * Link a committee that a linked committee funded for this measure. The link starts
     * pending, like any other, and goes through the same integrity checks.
     */
    public function linkSuggestion(Request $request, BallotMeasure $ballotMeasure, string $committeeId): RedirectResponse
    {
        $position = $request->validate(['position' => ['required', Rule::in(MeasureCommitteeRules::POSITIONS)]])['position'];
        $transfer = MeasureCommitteeRules::transfersToMeasure($ballotMeasure)->where('to_committee_id', $committeeId)->latest('latest_on')->firstOrFail();

        $link = new BallotMeasureCommittee([
            'ballot_measure_id' => $ballotMeasure->id,
            'state' => $ballotMeasure->state,
            'committee_id' => $committeeId,
            'committee_name' => $transfer->to_committee_name,
            'position' => $position,
            'source_url' => sprintf(CalAccessExport::COMMITTEE_URL, rawurlencode($committeeId)),
            'status' => BallotMeasureCommittee::STATUS_PENDING,
            'created_by_user_id' => $request->user()->id,
        ]);
        $link->setRelation('ballotMeasure', $ballotMeasure);
        $link->integrity_flags = MeasureCommitteeRules::flags($link);

        try {
            $link->save();
        } catch (UniqueConstraintViolationException) {
            return back()->with('error', "Committee {$committeeId} is already linked to this measure.");
        }

        MeasureCommitteePriority::scorePending();

        return back()->with('success', "Linked {$transfer->to_committee_name} for review. Check its filing, then verify it.");
    }

    public function dismissSuggestion(BallotMeasure $ballotMeasure, string $committeeId): RedirectResponse
    {
        MeasureCommitteeRules::transfersToMeasure($ballotMeasure)->where('to_committee_id', $committeeId)->update(['dismissed_at' => now()]);

        return back()->with('success', 'Suggestion dismissed.');
    }

    /**
     * Committees that linked committees gave money to for this measure, and that aren't
     * linked yet — the money path CAL-ACCESS late contribution reports reveal (e.g. a
     * general-purpose committee funding the "No on" committee).
     *
     * @param  Collection<int, BallotMeasureCommittee>  $committees
     * @return Collection<int, array<string, mixed>>
     */
    private function suggestions(BallotMeasure $measure, $committees)
    {
        $linked = $committees->keyBy('committee_id');

        return MeasureCommitteeRules::transfersToMeasure($measure)->open()->get()
            ->reject(fn (CommitteeTransfer $t) => $linked->has($t->to_committee_id))
            ->groupBy('to_committee_id')
            ->map(function ($transfers, $toCommitteeId) use ($linked) {
                $first = $transfers->sortByDesc('latest_on')->first();
                $funder = $linked->get($first->from_committee_id);

                return [
                    'committee_id' => (string) $toCommitteeId,
                    'name' => $first->to_committee_name,
                    'reference' => $first->measure_reference,
                    'amount' => (float) $transfers->sum('amount'),
                    'latest_on' => $first->latest_on,
                    'funders' => $transfers->map(fn ($t) => $linked->get($t->from_committee_id)?->committee_name ?? $t->from_committee_id)->unique()->values(),
                    'position' => $first->position ?? MeasureCommitteeRules::positionFromName($first->to_committee_name) ?? $funder?->position,
                ];
            })
            ->sortByDesc('amount')->values();
    }

    public function store(Request $request, BallotMeasure $ballotMeasure): RedirectResponse
    {
        $data = $request->validate([
            'committee_id' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9][A-Za-z0-9\-]*$/'],
            'committee_name' => ['required', 'string', 'max:255'],
            'position' => ['required', Rule::in(MeasureCommitteeRules::POSITIONS)],
            'source_url' => ['required', 'url', 'max:2048'],
            'confirmed' => ['nullable', 'boolean'],
        ]);

        $link = new BallotMeasureCommittee([
            'ballot_measure_id' => $ballotMeasure->id,
            'state' => $ballotMeasure->state,
            'committee_id' => $data['committee_id'],
            'committee_name' => $data['committee_name'],
            'position' => $data['position'],
            'source_url' => $data['source_url'],
            'created_by_user_id' => $request->user()->id,
        ]);
        $link->setRelation('ballotMeasure', $ballotMeasure);

        $flags = MeasureCommitteeRules::flags($link);
        $link->integrity_flags = $flags;

        // Verified on entry only when the admin confirmed it against the filing and nothing
        // looks mis-assigned; anything else goes to the review queue.
        $verifyNow = ! empty($data['confirmed']) && $flags === [];
        $link->status = $verifyNow ? BallotMeasureCommittee::STATUS_VERIFIED : BallotMeasureCommittee::STATUS_PENDING;
        if ($verifyNow) {
            $link->acknowledged_flags = [];
            $link->verified_by_user_id = $request->user()->id;
            $link->verified_at = now();
        }

        try {
            $link->save();
        } catch (UniqueConstraintViolationException) {
            return back()->withInput()->with('error', "Committee {$data['committee_id']} is already linked to this measure. Edit or remove the existing link instead.");
        }

        MeasureCommitteePriority::scorePending();

        $message = match (true) {
            $verifyNow => 'Committee linked and verified — it now shows on the measure page.',
            $flags !== [] => 'Committee linked, but held for review: '.implode('; ', array_map(fn ($f) => MeasureCommitteeRules::FLAG_LABELS[$f] ?? $f, $flags)).'.',
            default => 'Committee linked and waiting for review. Tick "I checked this against the filing" to verify on entry.',
        };

        return back()->with($verifyNow ? 'success' : 'warning', $message);
    }

    /** A reviewer accepts the link, including any soft flags it currently has. */
    public function verify(Request $request, BallotMeasureCommittee $committee): RedirectResponse
    {
        $flags = MeasureCommitteeRules::flags($committee);
        if (MeasureCommitteeRules::hasHardFlag($flags)) {
            return back()->with('error', "This link can't be verified: the committee's state doesn't match the measure's state. Reject it and link the right committee.");
        }

        $committee->forceFill([
            'status' => BallotMeasureCommittee::STATUS_VERIFIED,
            'integrity_flags' => $flags,
            'acknowledged_flags' => $flags,
            'verified_by_user_id' => $request->user()->id,
            'verified_at' => now(),
            'severity' => null,
            'occurrence' => null,
            'detectability' => null,
            'priority_score' => null,
        ])->save();

        MeasureCommitteePriority::scorePending();

        return back()->with('success', "Verified {$committee->committee_name}.");
    }

    public function reject(Request $request, BallotMeasureCommittee $committee): RedirectResponse
    {
        $note = trim((string) $request->validate(['review_note' => ['nullable', 'string', 'max:1000']])['review_note'] ?? '');

        $committee->forceFill([
            'status' => BallotMeasureCommittee::STATUS_REJECTED,
            'review_note' => $note !== '' ? $note : $committee->review_note,
            'verified_by_user_id' => null,
            'verified_at' => null,
            'priority_score' => null,
        ])->save();

        MeasureCommitteePriority::scorePending();

        return back()->with('success', "Rejected {$committee->committee_name}.");
    }

    public function destroy(BallotMeasureCommittee $committee): RedirectResponse
    {
        $committee->delete();
        MeasureCommitteePriority::scorePending();

        return back()->with('success', 'Committee link removed.');
    }

    /**
     * Sets the measure's state's official campaign finance site on its statewide civic
     * source registry row (created if the state has none yet). Voters see it as
     * "View official filings"; the audit uses it to check evidence links.
     */
    public function updateFinanceUrl(Request $request, BallotMeasure $ballotMeasure): RedirectResponse
    {
        $url = $request->validate(['campaign_finance_url' => ['nullable', 'url', 'max:2048']])['campaign_finance_url'] ?? null;
        $state = strtoupper((string) $ballotMeasure->state);

        $source = ElectionDataSource::query()->where('state', $state)->where('level', 'state')->first()
            ?? new ElectionDataSource([
                'ocd_id' => 'ocd-division/country:us/state:'.strtolower($state),
                'level' => 'state',
                'state' => $state,
                'jurisdiction_name' => ucwords(strtolower((string) (array_search($state, PoliticianDataRules::stateNameToCode(), true) ?: $state))),
                'source_of_record' => 'manual',
            ]);

        $source->campaign_finance_url = $url ?: null;
        $source->save();

        return back()->with('success', $url ? "Saved {$state}'s campaign finance site." : "Cleared {$state}'s campaign finance site.");
    }
}
