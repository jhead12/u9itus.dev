<?php

namespace App\Support;

use App\Models\BallotMeasure;
use App\Models\BallotMeasureCommittee;
use Illuminate\Support\Facades\DB;

/**
 * Picks one upcoming ballot measure for the home page's "Follow the Money" section, to show
 * what campaign money is trying to do — which way each committee wants a vote to go, what
 * that vote would mean, and who funds the push — rather than only how much is spent.
 *
 * Only measures with at least one verified committee link qualify, so nothing unchecked
 * reaches the home page. Local measures (city, county, district) come first, since that's
 * where a voter can least tell who's behind the ads; a statewide measure is the fallback.
 * Within that, the visitor's state wins, then a measure with committees on both sides,
 * then the soonest election.
 */
class MeasureSpotlight
{
    /** @return array{measure: BallotMeasure, funding: array<string, array<string, mixed>>, is_local: bool}|null */
    public static function pick(?string $visitorState = null): ?array
    {
        $measure = self::candidates(local: true, visitorState: $visitorState)
            ?? self::candidates(local: false, visitorState: $visitorState);

        if ($measure === null) {
            return null;
        }

        $verified = $measure->committees()->verified()->orderBy('committee_name')->get();

        return [
            'measure' => $measure,
            'funding' => MeasureFunding::forCommittees($verified, $measure),
            'is_local' => $measure->level !== 'state',
        ];
    }

    private static function candidates(bool $local, ?string $visitorState): ?BallotMeasure
    {
        $state = $visitorState !== null ? strtoupper($visitorState) : null;

        return BallotMeasure::query()
            ->when($local, fn ($q) => $q->where('level', '!=', 'state'), fn ($q) => $q->where('level', 'state'))
            ->whereNotIn('status', ['passed', 'failed'])
            ->where(fn ($q) => $q->whereNull('election_date')->orWhere('election_date', '>=', now()->toDateString()))
            ->whereHas('committees', fn ($q) => $q->where('status', BallotMeasureCommittee::STATUS_VERIFIED))
            ->withCount(['committees as sides_count' => fn ($q) => $q->where('status', BallotMeasureCommittee::STATUS_VERIFIED)->select(DB::raw('COUNT(DISTINCT position)'))])
            ->when($state !== null, fn ($q) => $q->orderByRaw('CASE WHEN state = ? THEN 0 ELSE 1 END', [$state]))
            ->orderByDesc('sides_count')
            ->orderByRaw('election_date IS NULL, election_date ASC')
            ->first();
    }
}
