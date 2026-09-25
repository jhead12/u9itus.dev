<?php

namespace App\Support;

use App\Models\Committee;
use App\Models\Politician;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Candidates for the home page's "Follow the Money" rotation: for each, the outside groups
 * spending to elect them and the ones spending to defeat them, and what that money pays
 * for (the purposes on their FEC independent-expenditure line items, e.g. "digital ads").
 *
 * Built from committee_profiles (EnrichCommitteeProfiles, nightly): spending_by_race rolls
 * each committee's independent expenditures up by candidate with support and oppose
 * totals, already resolved to a Politician where one exists. Only running candidates with a
 * published profile qualify. Candidates with money on both sides come first — that's where
 * outside money is fighting — then the visitor's state, then total outside spending.
 */
class CandidateSpotlight
{
    /** Committees shown per side. */
    private const PER_SIDE = 4;

    /**
     * @return list<array{politician: Politician, support: Collection<int, array<string, mixed>>, oppose: Collection<int, array<string, mixed>>, total: float}>
     */
    public static function pool(?string $visitorState = null, int $limit = 3): array
    {
        $byPolitician = [];

        $committees = Committee::query()
            ->whereHas('profile', fn ($q) => $q->whereNotNull('enriched_at')->whereNotNull('spending_by_race'))
            ->with('profile')
            ->get();

        foreach ($committees as $committee) {
            foreach ($committee->profile->spending_by_race ?? [] as $race) {
                $politicianId = $race['politician_id'] ?? null;
                if ($politicianId === null) {
                    continue;
                }

                foreach (['support', 'oppose'] as $side) {
                    $amount = (float) ($race[$side] ?? 0);
                    if ($amount <= 0) {
                        continue;
                    }

                    $byPolitician[$politicianId][$side][] = [
                        'committee' => $committee,
                        'amount' => $amount,
                        'purposes' => self::purposes($committee, $race['candidate_fec_id'] ?? null, $side === 'support' ? 'S' : 'O'),
                    ];
                }
            }
        }

        if ($byPolitician === []) {
            return [];
        }

        $politicians = Politician::query()
            ->publiclyVisible()
            ->where('is_running_candidate', true)
            ->whereIn('id', array_keys($byPolitician))
            ->get()
            ->keyBy('id');

        $state = $visitorState !== null ? strtoupper($visitorState) : null;

        return collect($byPolitician)
            ->filter(fn ($sides, $id) => $politicians->has($id))
            ->map(function ($sides, $id) use ($politicians) {
                $support = collect($sides['support'] ?? [])->sortByDesc('amount')->values();
                $oppose = collect($sides['oppose'] ?? [])->sortByDesc('amount')->values();

                return [
                    'politician' => $politicians->get($id),
                    'support' => $support->take(self::PER_SIDE),
                    'oppose' => $oppose->take(self::PER_SIDE),
                    'total' => (float) $support->sum('amount') + (float) $oppose->sum('amount'),
                ];
            })
            ->sortBy([
                fn ($a, $b) => ($b['support']->isNotEmpty() && $b['oppose']->isNotEmpty()) <=> ($a['support']->isNotEmpty() && $a['oppose']->isNotEmpty()),
                fn ($a, $b) => ($state !== null && $b['politician']->state === $state) <=> ($state !== null && $a['politician']->state === $state),
                fn ($a, $b) => $b['total'] <=> $a['total'],
            ])
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * What a committee's spending for or against this candidate paid for, from its recent
     * line items: short, de-duplicated purposes like "Digital advertising".
     *
     * @return list<string>
     */
    private static function purposes(Committee $committee, ?string $candidateFecId, string $supportOppose): array
    {
        if ($candidateFecId === null) {
            return [];
        }

        return collect($committee->profile->recent_expenditures ?? [])
            ->filter(fn ($item) => ($item['candidate_fec_id'] ?? null) === $candidateFecId && ($item['support_oppose'] ?? null) === $supportOppose)
            ->pluck('purpose')
            ->filter()
            ->map(fn ($purpose) => Str::of((string) $purpose)->lower()->squish()->ucfirst()->limit(40)->toString())
            ->unique()
            ->take(2)
            ->values()
            ->all();
    }
}
