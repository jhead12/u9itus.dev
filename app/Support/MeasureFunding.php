<?php

namespace App\Support;

use App\Models\BallotMeasureCommittee;
use App\Models\CommitteeDonor;
use App\Models\CommitteeFiler;
use App\Models\CommitteeFinanceSnapshot;
use Illuminate\Support\Collection;

/**
 * Money on each side of a ballot measure, from its verified committee links.
 *
 * Committees pass money to each other — Building a Better California gave the "No on
 * Prop 40" committee $56.5M in 2026 — and each reports it: the receiver as raised, the
 * giver as spent before the receiver spends it again. So a side's totals count that money
 * once: whatever a committee received from another committee on the same side (from its
 * itemized donors) is subtracted from both raised and spent, and those committees are left
 * out of the side's top donors in favor of the people and organizations who gave the money.
 */
class MeasureFunding
{
    /**
     * @param  Collection<int, BallotMeasureCommittee>  $verified
     * @return array<string, array<string, mixed>> keyed by position (support|oppose)
     */
    public static function forCommittees(Collection $verified): array
    {
        $snapshots = CommitteeFinanceSnapshot::latestForLinks($verified);
        $filers = CommitteeFiler::query()
            ->whereIn('committee_id', $verified->pluck('committee_id')->unique()->values())
            ->get()->keyBy(fn (CommitteeFiler $f) => CommitteeFinanceSnapshot::keyFor($f->state, $f->committee_id));

        $sides = [];
        foreach (MeasureCommitteeRules::POSITIONS as $position) {
            $committees = $verified->where('position', $position)->values();
            $sides[$position] = self::side($committees, $snapshots, $filers);
        }

        return $sides;
    }

    /**
     * @param  Collection<int, BallotMeasureCommittee>  $committees
     * @param  Collection<string, CommitteeFinanceSnapshot>  $snapshots
     * @param  Collection<string, CommitteeFiler>  $filers
     * @return array<string, mixed>
     */
    private static function side(Collection $committees, Collection $snapshots, Collection $filers): array
    {
        $rows = $committees->map(function (BallotMeasureCommittee $link) use ($snapshots, $filers) {
            $key = CommitteeFinanceSnapshot::keyFor($link->state, $link->committee_id);
            $snapshot = $snapshots->get($key);
            $filer = $filers->get($key);

            return [
                'link' => $link,
                'snapshot' => $snapshot,
                'late' => $snapshot !== null ? (float) ($filer?->late_contributions ?? 0) : 0.0,
                'late_since' => $filer?->late_since,
            ];
        });

        $withMoney = $rows->filter(fn ($r) => $r['snapshot'] !== null);
        $donors = self::donors($withMoney);
        $sideIds = $committees->map(fn ($l) => strtoupper($l->state).'|'.$l->committee_id)->flip();

        // Money a committee on this side received from another committee on this side.
        $internal = $donors->filter(fn (CommitteeDonor $d) => $d->donor_committee_id !== null
            && $sideIds->has(strtoupper($d->state).'|'.$d->donor_committee_id));

        // Rounded to cents: these are sums of currency amounts stored as floats.
        $raised = round($withMoney->sum(fn ($r) => (float) $r['snapshot']->contributions_ytd + $r['late']), 2);
        $transfers = round((float) $internal->sum('amount'), 2);

        $internalIds = $internal->pluck('id')->flip();
        $top = $donors->reject(fn (CommitteeDonor $d) => $internalIds->has($d->id))
            ->groupBy(fn (CommitteeDonor $d) => $d->donor_committee_id !== null ? 'cmte:'.$d->donor_committee_id : mb_strtoupper($d->entity_type.'|'.$d->donor_name))
            ->map(fn (Collection $group) => [
                'name' => $group->first()->donor_name,
                'employer' => $group->first()->employer,
                'is_committee' => $group->first()->donor_committee_id !== null,
                'amount' => round((float) $group->sum('amount'), 2),
            ])
            ->sortByDesc('amount')->take(10)->values();

        return [
            'committees' => $rows,
            'has_money' => $withMoney->isNotEmpty(),
            'raised' => $raised,
            'transfers' => $transfers,
            'net_raised' => round($raised - $transfers, 2),
            'late' => round((float) $withMoney->sum('late'), 2),
            'nonmonetary' => round((float) $withMoney->sum(fn ($r) => (float) $r['snapshot']->nonmonetary_ytd), 2),
            // A transfer is also the giver's expenditure, which the receiver spends again.
            'spent' => round((float) $withMoney->sum(fn ($r) => (float) $r['snapshot']->expenditures_ytd) - $transfers, 2),
            'cash' => round((float) $withMoney->sum(fn ($r) => (float) $r['snapshot']->cash_on_hand), 2),
            'top_donors' => $top,
        ];
    }

    /**
     * Donors for each committee's latest statement year.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, CommitteeDonor>
     */
    private static function donors(Collection $rows): Collection
    {
        if ($rows->isEmpty()) {
            return collect();
        }

        return CommitteeDonor::query()
            ->where(function ($q) use ($rows) {
                foreach ($rows as $row) {
                    $q->orWhere(fn ($q) => $q->where('state', strtoupper($row['link']->state))
                        ->where('committee_id', $row['link']->committee_id)
                        ->where('year', $row['snapshot']->period_end?->year));
                }
            })
            ->get();
    }
}
