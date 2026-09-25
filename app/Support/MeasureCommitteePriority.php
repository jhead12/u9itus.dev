<?php

namespace App\Support;

use App\Models\BallotMeasureCommittee;

/**
 * FMEA-style Risk Priority Number (severity × occurrence × detectability, each 1-5) for
 * pending committee links — the same scoring politicians:score-review-priority applies to
 * the politician review queues, so the admin queue shows the riskiest links first.
 */
class MeasureCommitteePriority
{
    /** Scores every pending link; returns how many were scored. */
    public static function scorePending(): int
    {
        $pending = BallotMeasureCommittee::query()
            ->where('status', BallotMeasureCommittee::STATUS_PENDING)
            ->with('ballotMeasure:id,state,status,election_date')
            ->get();

        $keys = $pending->map(fn (BallotMeasureCommittee $link) => self::occurrenceKey($link));

        foreach ($pending as $link) {
            $key = self::occurrenceKey($link);
            $severity = self::severity($link);
            $occurrence = min(5, $keys->filter(fn ($k) => $k === $key)->count());
            $detectability = MeasureCommitteeRules::DETECTABILITY[self::primaryFlag($link)];

            $link->forceFill([
                'severity' => $severity,
                'occurrence' => $occurrence,
                'detectability' => $detectability,
                'priority_score' => $severity * $occurrence * $detectability,
            ])->saveQuietly();
        }

        return $pending->count();
    }

    /**
     * Blast radius if the link is wrong: voters deciding the measure within 60 days (5),
     * an upcoming measure (3), or one already decided (1).
     */
    private static function severity(BallotMeasureCommittee $link): int
    {
        $measure = $link->ballotMeasure;
        if ($measure === null || in_array($measure->status, ['passed', 'failed'], true)
            || ($measure->election_date !== null && $measure->election_date->isPast() && ! $measure->election_date->isToday())) {
            return 1;
        }

        return ($measure->election_date !== null && $measure->election_date->lte(now()->addDays(60))) ? 5 : 3;
    }

    /** The most certain open flag, or 'unreviewed' when nothing looks wrong yet. */
    private static function primaryFlag(BallotMeasureCommittee $link): string
    {
        $open = $link->openFlags();
        foreach (array_keys(MeasureCommitteeRules::DETECTABILITY) as $flag) {
            if (in_array($flag, $open, true)) {
                return $flag;
            }
        }

        return 'unreviewed';
    }

    /** Same root-cause signature: flag + state, so a bad source for one state is one Pareto bucket. */
    private static function occurrenceKey(BallotMeasureCommittee $link): string
    {
        return self::primaryFlag($link).'|'.$link->state;
    }
}
