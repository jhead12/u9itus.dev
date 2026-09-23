<?php

namespace App\Console\Commands;

use App\Models\CandidateMatchReview;
use App\Models\ElectionCandidateRecord;
use App\Models\Politician;
use App\Models\PoliticianCleanupReview;
use App\Support\MapVisibility;
use Illuminate\Console\Command;

/**
 * Recomputes an FMEA-style Risk Priority Number (severity × occurrence × detectability,
 * each 1-5, so priority_score ranges 1-125) for every PENDING row in the two admin
 * data-quality review queues, so the admin UI can sort highest-impact findings first
 * instead of a flat created_at queue.
 *
 * Stored, not computed on read: occurrence is a set-level statistic (expensive to
 * recompute per row across a paginated 30-250 row list) and severity depends on
 * politicians.page_published/is_active, which drifts independently of the review row —
 * both need periodic refresh, not live computation. Safe to re-run any time; it only
 * touches pending rows and is idempotent for unchanged state.
 *
 * Runs as a step in politicians:cleanup-workflow (after flag-suspect-profiles /
 * dedupe-by-fec, which populate/resolve these queues); also runnable standalone.
 */
class ScoreReviewPriority extends Command
{
    protected $signature = 'politicians:score-review-priority';

    protected $description = 'Recompute severity/occurrence/detectability/priority_score for pending politician_cleanup_reviews and candidate_match_reviews rows.';

    /**
     * How easily a finding of this kind hides from existing automation (1 = a hard,
     * near-certain rule; 5 = a soft, absence-of-evidence signal that needs a human eye).
     * Keyed by payload['source'] for PoliticianCleanupReview, or a fixed key for
     * CandidateMatchReview (whose findings are all "medium-confidence match score").
     */
    private const DETECTABILITY = [
        'dedupe-by-fec' => 2,
        // Covers every FlagSuspectProfiles branch except the federal-collision one below:
        // holderElsewhere/holderInState (a hard rule) as well as the softer surnameStub/
        // uncorroborated/headline-text branches, which don't tag a more specific source
        // (see FlagSuspectProfiles::retireResolved(), which matches on this prefix).
        'flag-suspect-profiles' => 2,
        'name_reject' => 2,
        'flag-suspect-profiles-federal-collision' => 3,
        'dedupe' => 3,
        'candidate_match_review' => 3,
    ];

    private const DEFAULT_DETECTABILITY = 3;

    public function handle(): int
    {
        $politicianScored = $this->scorePoliticianCleanupReviews();
        $matchScored = $this->scoreCandidateMatchReviews();

        $this->info("Scored {$politicianScored} pending politician_cleanup_reviews row(s) and {$matchScored} pending candidate_match_reviews row(s).");

        return self::SUCCESS;
    }

    private function scorePoliticianCleanupReviews(): int
    {
        $pending = PoliticianCleanupReview::query()
            ->where('status', PoliticianCleanupReview::STATUS_PENDING)
            ->with('politician:id,full_name,is_active,page_published')
            ->get();

        $occurrenceKeys = $pending->map(fn (PoliticianCleanupReview $r) => $this->cleanupOccurrenceKey($r));

        foreach ($pending as $review) {
            $severity = $this->politicianSeverity($review->politician);
            $occurrence = min(5, $occurrenceKeys->filter(fn ($k) => $k === $this->cleanupOccurrenceKey($review))->count());
            $detectability = self::DETECTABILITY[(string) ($review->payload['source'] ?? '')] ?? self::DEFAULT_DETECTABILITY;

            $review->forceFill([
                'severity' => $severity,
                'occurrence' => $occurrence,
                'detectability' => $detectability,
                'priority_score' => $severity * $occurrence * $detectability,
            ])->save();
        }

        return $pending->count();
    }

    private function scoreCandidateMatchReviews(): int
    {
        $pending = CandidateMatchReview::query()
            ->where('status', CandidateMatchReview::STATUS_PENDING)
            ->with(['politician:id,full_name,is_active,page_published', 'candidateRecord:id,full_name,source,payload'])
            ->get();

        $occurrenceKeys = $pending->map(fn (CandidateMatchReview $r) => $this->matchOccurrenceKey($r));

        foreach ($pending as $review) {
            $severity = $this->matchSeverity($review->politician, $review->candidateRecord);
            $occurrence = min(5, $occurrenceKeys->filter(fn ($k) => $k === $this->matchOccurrenceKey($review))->count());
            $detectability = self::DETECTABILITY['candidate_match_review'];

            $review->forceFill([
                'severity' => $severity,
                'occurrence' => $occurrence,
                'detectability' => $detectability,
                'priority_score' => $severity * $occurrence * $detectability,
            ])->save();
        }

        return $pending->count();
    }

    private function politicianSeverity(?Politician $politician): int
    {
        if ($politician === null) {
            return 1; // orphaned finding — low urgency
        }
        if ($politician->page_published) {
            return 5; // live public profile page
        }

        return $politician->is_active ? 3 : 1;
    }

    /**
     * A pending match isn't linked yet, so its blast radius is about what approving it
     * would expose: a published profile is already the worst case (5); short of that,
     * approving would set the politician active and could flip an otherwise-hidden
     * discovery-sourced ECR into map-visible (see MapVisibility::discoveryVisible()).
     */
    private function matchSeverity(?Politician $politician, ?ElectionCandidateRecord $ecr): int
    {
        if ($politician === null) {
            return 1;
        }
        if ($politician->page_published) {
            return 5;
        }

        $wouldBecomeVisible = $ecr !== null
            && MapVisibility::discoveryVisible($ecr, true)
            && ! MapVisibility::discoveryVisible($ecr, false);

        return ($politician->is_active || $wouldBecomeVisible) ? 3 : 1;
    }

    /**
     * Same root-cause signature: the finding's declared source (or review_type as a
     * fallback) plus the politician's name — this is what turns e.g. five near-identical
     * phantom "Bernie Sanders" profiles into one visible Pareto bucket instead of five
     * anonymous rows.
     */
    private function cleanupOccurrenceKey(PoliticianCleanupReview $review): string
    {
        $source = (string) ($review->payload['source'] ?? $review->review_type);
        $name = mb_strtolower(trim((string) ($review->politician->full_name ?? $review->payload['full_name'] ?? '')));

        return $source.'|'.$name;
    }

    private function matchOccurrenceKey(CandidateMatchReview $review): string
    {
        $source = (string) ($review->candidateRecord->source ?? 'unknown');
        $name = mb_strtolower(trim((string) ($review->politician->full_name ?? $review->candidateRecord->full_name ?? '')));

        return $source.'|'.$name;
    }
}
