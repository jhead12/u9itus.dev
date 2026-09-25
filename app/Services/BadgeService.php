<?php

namespace App\Services;

use App\Models\CongressVoteTopic;
use App\Models\Politician;
use App\Models\PoliticianTopic;
use App\Models\PoliticianTopicSignal;
use App\Models\ProfileBadge;
use App\Models\Voter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Handles badge award logic, including:
 *   - Self-declaration (user chooses a topic badge)
 *   - Auto-earning (triggered by view-session completion)
 *
 * Threshold constants:
 *   VIEWS_THRESHOLD   — completed views on a topic required for earned_views badge
 *   REFERRAL_THRESHOLD— referred voters required for earned_referral badge
 */
class BadgeService
{
    const VIEWS_THRESHOLD = 5;

    const REFERRAL_THRESHOLD = 3;

    /**
     * Validate that a topic is eligible for self-declaration.
     * Returns the topic or throws a \InvalidArgumentException.
     */
    public function resolveSelectableTopic(int $topicId): PoliticianTopic
    {
        $topic = PoliticianTopic::find($topicId);

        if (! $topic || ! $topic->is_active) {
            throw new \InvalidArgumentException('Topic not found or inactive.');
        }

        if ($topic->auto_earned_only || ! $topic->voter_selectable) {
            throw new \InvalidArgumentException('This badge can only be earned, not self-declared.');
        }

        return $topic;
    }

    /**
     * Check view-completion count against the topic threshold and
     * grant an earned_views badge if the threshold is reached.
     *
     * Called from PoliticalViewService::completeView() for each topic
     * tagged on the completed campaign.
     *
     * @return ProfileBadge|null The newly-granted badge, or null if threshold not yet met
     */
    public function checkAndGrantViewBadge(Voter $voter, int $topicId): ?ProfileBadge
    {
        // Skip if already awarded
        if ($voter->hasBadgeForTopic($topicId)) {
            return null;
        }

        try {
            $count = $voter->viewSessions()
                ->whereHas('campaign.topics', fn ($q) => $q->where('politician_topics.id', $topicId))
                ->where('status', 'completed')
                ->count();

            if ($count >= self::VIEWS_THRESHOLD) {
                return $voter->grantEarnedBadge($topicId, 'earned_views', self::VIEWS_THRESHOLD);
            }
        } catch (\Throwable $e) {
            Log::warning('BadgeService: view badge check failed', [
                'voter_id' => $voter->id,
                'topic_id' => $topicId,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * Check referral count and grant earned_referral badge if threshold is met.
     * Called after a voter's referral is confirmed active.
     */
    public function checkAndGrantReferralBadge(Voter $voter, int $topicId): ?ProfileBadge
    {
        if ($voter->hasBadgeForTopic($topicId)) {
            return null;
        }

        try {
            $referralCount = $voter->referrals()->count();

            if ($referralCount >= self::REFERRAL_THRESHOLD) {
                return $voter->grantEarnedBadge($topicId, 'earned_referral', self::REFERRAL_THRESHOLD);
            }
        } catch (\Throwable $e) {
            Log::warning('BadgeService: referral badge check failed', [
                'voter_id' => $voter->id,
                'topic_id' => $topicId,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * Grant inferred issue-discourse badges to a politician from their computed
     * topic signals. For each signal whose total_score crosses the configured
     * threshold, add a badge_type='inferred_discourse' row (is_public=true so it
     * surfaces in publicBadges / map cards).
     *
     * Idempotent via the trait's firstOrCreate (match key = topic_id), so:
     *   - a self-declared badge for the same topic is NOT overwritten, and
     *   - re-running the enricher never duplicates.
     *
     * No auto-revoke in v1: signals refresh nightly, but a granted inferred
     * badge persists (avoids flapping as recency decays a topic's score).
     *
     * @param  Collection<int, PoliticianTopicSignal>  $signals
     * @return int Number of badges granted this run (0 if all already existed)
     */
    public function grantInferredBadges(Politician $politician, Collection $signals): int
    {
        $threshold = (float) config('u9itus.issues.signal_threshold', 1.0);
        $granted = 0;

        foreach ($signals as $signal) {
            if ((float) $signal->total_score < $threshold) {
                continue;
            }

            $before = $politician->badges()->where('topic_id', $signal->topic_id)->exists();
            $badge = $politician->addBadge((int) $signal->topic_id, 'inferred_discourse', [
                'is_public' => true,
                'earned_at' => now(),
            ]);

            // The position follows the speeches on every run; a vote-based or
            // self-declared badge for the topic keeps its own.
            if ($badge->badge_type === 'inferred_discourse') {
                $badge->update($this->speechStance($signal));
            }

            if (! $before) {
                $granted++;
            }
        }

        return $granted;
    }

    /**
     * Supports / Opposes from the member's floor speeches, only when enough of them
     * take a position on the topic's own labels and they clearly lean one way.
     *
     * @return array{stance: ?string, stance_label: ?string}
     */
    public function speechStance(PoliticianTopicSignal $signal): array
    {
        $none = ['stance' => null, 'stance_label' => null];
        $topic = $signal->topic;
        $support = (int) $signal->support_count;
        $oppose = (int) $signal->oppose_count;
        $total = $support + $oppose;

        if (! $topic?->hasStanceLabels() || $total < (int) config('u9itus.issues.stance_min_statements', 2)) {
            return $none;
        }

        $minShare = (float) config('u9itus.issues.stance_min_share', 0.75);
        $stance = match (true) {
            $support / $total >= $minShare => 'support',
            $oppose / $total >= $minShare => 'oppose',
            default => null,
        };

        return $stance ? ['stance' => $stance, 'stance_label' => $topic->stanceLabel($stance)] : $none;
    }

    /**
     * Badges from roll calls an editor tied to a topic (congress_vote_topics). The badge
     * describes the vote in the editor's words, e.g. "Voted to limit military action
     * against Iran". A member whose votes on the topic point both ways gets a "split
     * record" badge rather than one side.
     *
     * A vote record replaces an inferred badge for the same topic but never a
     * self-declared or earned one. Unlike inferred badges these are kept in step with
     * the tags: removing a tag removes the badges it produced.
     *
     * @return int Number of vote-based badges the politician holds after the sync
     */
    public function syncVoteBadges(Politician $politician): int
    {
        $records = $politician->bioguide_id
            ? CongressVoteTopic::query()
                ->with('topic')
                ->join('congress_member_votes as mv', 'mv.congress_vote_id', '=', 'congress_vote_topics.congress_vote_id')
                ->join('congress_votes as v', 'v.id', '=', 'congress_vote_topics.congress_vote_id')
                ->where('mv.bioguide_id', $politician->bioguide_id)
                ->whereIn('mv.vote', ['yea', 'nay'])
                ->orderByDesc('v.voted_at')
                ->get(['congress_vote_topics.*', 'mv.vote as member_vote', 'v.voted_at as member_voted_at'])
            : collect();

        $held = [];
        foreach ($records->groupBy('topic_id') as $topicId => $votes) {
            $latest = $votes->first();
            $stances = $votes->map(fn (CongressVoteTopic $v) => $v->stanceFor($v->member_vote))->unique();

            if ($stances->count() > 1) {
                $stance = 'mixed';
                $label = "Split record across {$votes->count()} ".($latest->topic?->name ?? 'tagged').' votes';
            } else {
                $stance = $stances->first();
                $label = $latest->labelFor($latest->member_vote).($votes->count() > 1 ? " ({$votes->count()} votes)" : '');
            }

            $existing = $politician->badges()->where('topic_id', $topicId)->first();
            if ($existing && ! in_array($existing->badge_type, ['inferred_discourse', 'roll_call_vote'], true)) {
                continue;
            }

            $politician->badges()->updateOrCreate(['topic_id' => $topicId], [
                'badge_type' => 'roll_call_vote',
                'stance' => $stance,
                'stance_label' => $label,
                'is_public' => true,
                'earned_at' => $latest->member_voted_at ?? now(),
            ]);
            $held[] = (int) $topicId;
        }

        $politician->badges()
            ->where('badge_type', 'roll_call_vote')
            ->whereNotIn('topic_id', $held ?: [0])
            ->delete();

        return count($held);
    }
}
