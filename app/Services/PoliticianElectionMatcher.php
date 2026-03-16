<?php

namespace App\Services;

use App\Models\CandidateIdentityLink;
use App\Models\CandidateMatchReview;
use App\Models\ElectionCandidateRecord;
use App\Models\Politician;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PoliticianElectionMatcher
{
    public const AUTO_LINK_THRESHOLD = 0.90;
    public const REVIEW_THRESHOLD = 0.75;

    /**
     * Match a politician against the best available candidate record.
     *
     * @return array<string, mixed>
     */
    public function match(Politician $politician): array
    {
        $candidate = $this->bestCandidateFor($politician);
        $result = ['action' => 'none', 'reason' => 'no_candidates'];

        if (! $candidate) {
            return $result;
        }

        [$score, $breakdown] = $this->score($politician, $candidate);

        if ($score >= self::AUTO_LINK_THRESHOLD) {
            $link = $this->upsertLink($politician, $candidate, $score, $breakdown, 'system', null);

            CandidateMatchReview::where('politician_id', $politician->id)
                ->where('election_candidate_record_id', $candidate->id)
                ->delete();

            $result = [
                'action' => 'linked',
                'link_id' => $link->id,
                'score' => $score,
                'candidate_id' => $candidate->id,
            ];
        } elseif ($score >= self::REVIEW_THRESHOLD) {
            $review = CandidateMatchReview::updateOrCreate(
                [
                    'politician_id' => $politician->id,
                    'election_candidate_record_id' => $candidate->id,
                ],
                [
                    'match_score' => $score,
                    'match_breakdown' => $breakdown,
                    'status' => CandidateMatchReview::STATUS_PENDING,
                    'reason' => null,
                    'reviewed_by_user_id' => null,
                    'reviewed_at' => null,
                ]
            );

            $result = [
                'action' => 'review',
                'review_id' => $review->id,
                'score' => $score,
                'candidate_id' => $candidate->id,
            ];
        } else {
            $result = [
                'action' => 'none',
                'reason' => 'score_too_low',
                'score' => $score,
                'candidate_id' => $candidate->id,
            ];
        }

        return $result;
    }

    public function approveReview(CandidateMatchReview $review, User $admin, ?string $reason = null): CandidateIdentityLink
    {
        return DB::transaction(function () use ($review, $admin, $reason) {
            $review->loadMissing(['politician', 'candidateRecord']);

            $link = $this->upsertLink(
                $review->politician,
                $review->candidateRecord,
                (float) $review->match_score,
                (array) ($review->match_breakdown ?? []),
                'admin',
                $admin->id,
            );

            $review->update([
                'status' => CandidateMatchReview::STATUS_APPROVED,
                'reason' => $reason,
                'reviewed_by_user_id' => $admin->id,
                'reviewed_at' => now(),
            ]);

            return $link;
        });
    }

    public function rejectReview(CandidateMatchReview $review, User $admin, ?string $reason = null): void
    {
        $review->update([
            'status' => CandidateMatchReview::STATUS_REJECTED,
            'reason' => $reason,
            'reviewed_by_user_id' => $admin->id,
            'reviewed_at' => now(),
        ]);
    }

    protected function upsertLink(
        Politician $politician,
        ElectionCandidateRecord $candidate,
        float $score,
        array $breakdown,
        string $source,
        ?int $linkedByUserId
    ): CandidateIdentityLink {
        return CandidateIdentityLink::updateOrCreate(
            [
                'politician_id' => $politician->id,
                'election_candidate_record_id' => $candidate->id,
            ],
            [
                'match_score' => $score,
                'link_source' => $source,
                'linked_by_user_id' => $linkedByUserId,
                'match_breakdown' => $breakdown,
                'linked_at' => now(),
            ]
        );
    }

    protected function bestCandidateFor(Politician $politician): ?ElectionCandidateRecord
    {
        $query = ElectionCandidateRecord::query();

        if (! empty($politician->state)) {
            $query->whereRaw('UPPER(state) = ?', [strtoupper((string) $politician->state)]);
        }

        if (! empty($politician->governance_level)) {
            $query->where(function ($q) use ($politician) {
                $q->whereNull('governance_level')
                    ->orWhereRaw('LOWER(governance_level) = ?', [strtolower((string) $politician->governance_level)]);
            });
        }

        $candidates = $query->limit(200)->get();

        $best = null;
        $bestScore = 0.0;

        foreach ($candidates as $candidate) {
            [$score] = $this->score($politician, $candidate);
            if ($score > $bestScore) {
                $best = $candidate;
                $bestScore = $score;
            }
        }

        return $best;
    }

    /**
     * @return array{0: float, 1: array<string, float>}
     */
    protected function score(Politician $politician, ElectionCandidateRecord $candidate): array
    {
        $name = $this->stringSimilarity($politician->full_name, $candidate->full_name);
        $office = $this->fieldScore($politician->political_office, $candidate->political_office);
        $state = $this->fieldScore($politician->state, $candidate->state);
        $district = $this->fieldScore($politician->district, $candidate->district);
        $city = $this->fieldScore($politician->city, $candidate->city);
        $party = $this->fieldScore($politician->party_affiliation, $candidate->party_affiliation);

        $geo = max($district, $city);

        $breakdown = [
            'name' => $name,
            'office' => $office,
            'state' => $state,
            'geo' => $geo,
            'party' => $party,
        ];

        $score =
            ($name * 0.50) +
            ($office * 0.20) +
            ($state * 0.10) +
            ($geo * 0.10) +
            ($party * 0.10);

        return [round($score, 4), $breakdown];
    }

    protected function fieldScore(?string $left, ?string $right): float
    {
        $ln = $this->normalize($left);
        $rn = $this->normalize($right);

        if ($ln === '' || $rn === '') {
            return 0.5;
        }

        return $ln === $rn ? 1.0 : $this->stringSimilarity($ln, $rn);
    }

    protected function stringSimilarity(?string $left, ?string $right): float
    {
        $ln = $this->normalize($left);
        $rn = $this->normalize($right);

        if ($ln === '' || $rn === '') {
            return 0.0;
        }

        similar_text($ln, $rn, $percent);

        return max(0.0, min(1.0, $percent / 100));
    }

    protected function normalize(?string $value): string
    {
        $value = strtolower(trim((string) $value));

        if ($value === '') {
            return '';
        }

        $value = preg_replace('/[^a-z0-9\s]/', ' ', $value) ?? '';
        $value = preg_replace('/\s+/', ' ', $value) ?? '';

        return trim($value);
    }
}
