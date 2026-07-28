<?php

namespace App\Traits;

use App\Models\ProfileBadge;
use App\Models\PoliticianTopic;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Shared badge behavior for Politician, Voter, and Citizen models.
 *
 * Usage:
 *   class Politician extends Model {
 *       use HasProfileBadges;
 *   }
 */
trait HasProfileBadges
{
    /**
     * All badges on this profile (public + private).
     */
    public function badges(): MorphMany
    {
        return $this->morphMany(ProfileBadge::class, 'badgeable');
    }

    /**
     * Only publicly-visible badges, with topic preloaded.
     */
    public function publicBadges(): MorphMany
    {
        return $this->badges()
            ->where('is_public', true)
            ->with('topic');
    }

    /**
     * Add or retrieve a badge for a given topic.
     * Idempotent — calling twice for the same topic does not create a duplicate.
     *
     * @param  int    $topicId
     * @param  string $type    self_declared|earned_views|earned_referral|token_holder
     * @param  array  $extra   Additional attributes (earned_threshold, earned_at, etc.)
     */
    public function addBadge(int $topicId, string $type = 'self_declared', array $extra = []): ProfileBadge
    {
        $defaults = ['badge_type' => $type, 'earned_at' => now()];

        // Self-declared badges are private by default — the profile owner
        // must explicitly opt in via setBadgeVisibility(). Callers can still
        // override by passing 'is_public' in $extra.
        if ($type === 'self_declared' && ! array_key_exists('is_public', $extra)) {
            $defaults['is_public'] = false;
        }

        return $this->badges()->firstOrCreate(
            ['topic_id' => $topicId],
            array_merge($defaults, $extra)
        );
    }

    /**
     * Remove a self-declared badge. Earned badges cannot be removed by users.
     */
    public function removeSelfDeclaredBadge(int $topicId): bool
    {
        return (bool) $this->badges()
            ->where('topic_id', $topicId)
            ->where('badge_type', 'self_declared')
            ->delete();
    }

    /**
     * Update the visibility of a self-declared badge. Earned and inferred
     * badges cannot have their visibility changed here — returns false if
     * the badge doesn't exist or isn't self-declared.
     */
    public function setBadgeVisibility(int $topicId, bool $isPublic): bool
    {
        return (bool) $this->badges()
            ->where('topic_id', $topicId)
            ->where('badge_type', 'self_declared')
            ->update(['is_public' => $isPublic]);
    }

    /**
     * Check whether this profile holds a badge for a given topic.
     */
    public function hasBadgeForTopic(int $topicId): bool
    {
        return $this->badges()->where('topic_id', $topicId)->exists();
    }

    /**
     * Grant an earned badge when a threshold is crossed.
     * Skips if the badge already exists (idempotent).
     *
     * @param  int    $topicId
     * @param  string $type          earned_views|earned_referral|token_holder
     * @param  int    $threshold     The count that was reached
     */
    public function grantEarnedBadge(int $topicId, string $type, int $threshold): ProfileBadge
    {
        return $this->badges()->firstOrCreate(
            ['topic_id' => $topicId],
            [
                'badge_type'       => $type,
                'earned_threshold' => $threshold,
                'earned_at'        => now(),
                'is_public'        => true,
            ]
        );
    }
}
