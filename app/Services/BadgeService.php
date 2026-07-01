<?php

namespace App\Services;

use App\Models\PoliticianTopic;
use App\Models\ProfileBadge;
use App\Models\Voter;
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
    const VIEWS_THRESHOLD    = 5;
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
     * @param  Voter $voter
     * @param  int   $topicId
     * @return ProfileBadge|null  The newly-granted badge, or null if threshold not yet met
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
                'error'    => $e->getMessage(),
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
                'error'    => $e->getMessage(),
            ]);
        }

        return null;
    }
}
