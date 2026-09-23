<?php

namespace App\Services;

use App\Models\CongressMemberVote;
use App\Models\CongressVote;
use App\Models\Politician;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * A sitting member of Congress's roll-call record for the public profile: headline numbers
 * (votes cast, missed, how often they sided with their party) plus the latest votes.
 */
class PoliticianVotingRecord
{
    private const CACHE_SECONDS = 1800;

    public const FILTERS = ['all', 'yea', 'nay', 'not_voting'];

    /**
     * @return array<string, mixed>|null congress, chamber, total, yea, nay, present, not_voting, missed_pct, party_line_pct, party, as_of, recent
     */
    public function summary(Politician $politician, int $recent = 8): ?array
    {
        if (! $politician->bioguide_id) {
            return null;
        }

        return Cache::remember("voting-record.{$politician->bioguide_id}.{$recent}", self::CACHE_SECONDS, function () use ($politician, $recent) {
            $congress = CongressVote::query()
                ->join('congress_member_votes', 'congress_member_votes.congress_vote_id', '=', 'congress_votes.id')
                ->where('congress_member_votes.bioguide_id', $politician->bioguide_id)
                ->max('congress_votes.congress');

            if (! $congress) {
                return null;
            }

            $rows = $this->listQuery($politician->bioguide_id, (int) $congress)->get();

            $counts = $rows->countBy('member_vote');
            $total = $rows->count();

            $withParty = $rows->filter(fn (CongressVote $r) => in_array($r->member_vote, ['yea', 'nay'], true)
                && isset(($r->party_positions ?? [])[$r->member_party]));
            $sided = $withParty->filter(fn (CongressVote $r) => $r->party_positions[$r->member_party] === $r->member_vote)->count();

            return [
                'congress' => (int) $congress,
                'chamber' => (string) $rows->first()?->chamber,
                'total' => $total,
                'yea' => (int) ($counts['yea'] ?? 0),
                'nay' => (int) ($counts['nay'] ?? 0),
                'present' => (int) ($counts['present'] ?? 0),
                'not_voting' => (int) ($counts['not_voting'] ?? 0),
                'missed_pct' => $total > 0 ? round(($counts['not_voting'] ?? 0) / $total * 100, 1) : 0.0,
                'party_line_pct' => $withParty->count() >= 10 ? round($sided / $withParty->count() * 100, 1) : null,
                'party' => $rows->first()?->member_party,
                'as_of' => $rows->first()?->voted_at,
                'recent' => $this->attachPartyTallies($rows->take($recent)->values()),
            ];
        });
    }

    /**
     * Attaches each vote's party-by-party yea/nay breakdown (e.g. how many Democrats,
     * Republicans, and Independents voted each way) as $vote->party_tally, batched into
     * a single query rather than one per row.
     *
     * @param  Collection<int, CongressVote>  $votes
     * @return Collection<int, CongressVote>
     */
    public function attachPartyTallies(Collection $votes): Collection
    {
        $ids = $votes->pluck('id')->all();
        if ($ids === []) {
            return $votes;
        }

        $tallies = CongressMemberVote::query()
            ->whereIn('congress_vote_id', $ids)
            ->whereIn('vote', ['yea', 'nay'])
            ->select('congress_vote_id', 'party', 'vote', DB::raw('count(*) as total'))
            ->groupBy('congress_vote_id', 'party', 'vote')
            ->get()
            ->groupBy('congress_vote_id');

        return $votes->each(function (CongressVote $vote) use ($tallies) {
            $byParty = [];
            foreach ($tallies->get($vote->id, []) as $row) {
                $party = $row->party ?: '?';
                $byParty[$party][$row->vote] = (int) $row->total;
            }
            $vote->party_tally = $byParty;
        });
    }

    /**
     * A member's votes in one Congress, newest first. Each row is the vote itself plus
     * `member_vote` / `member_party`.
     *
     * @return Builder<CongressVote>
     */
    public function listQuery(string $bioguideId, int $congress, string $filter = 'all'): Builder
    {
        $query = CongressVote::query()
            ->join('congress_member_votes', 'congress_member_votes.congress_vote_id', '=', 'congress_votes.id')
            ->where('congress_member_votes.bioguide_id', $bioguideId)
            ->where('congress_votes.congress', $congress)
            ->orderByDesc('congress_votes.voted_at')
            ->orderByDesc('congress_votes.roll_number')
            ->select('congress_votes.*', 'congress_member_votes.vote as member_vote', 'congress_member_votes.party as member_party');

        if (in_array($filter, ['yea', 'nay', 'not_voting'], true)) {
            $query->where('congress_member_votes.vote', $filter);
        }

        return $query;
    }
}
