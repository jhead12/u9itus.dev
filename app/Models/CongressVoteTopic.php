<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An editor's decision that a roll call is about a topic (e.g. a war powers resolution
 * and the Iran conflict), with what a yea vote means for it. The yes/no alone cannot
 * say: a yea on a war powers resolution is a vote to limit military action.
 * BadgeService::syncVoteBadges() turns these into roll_call_vote badges.
 */
class CongressVoteTopic extends Model
{
    protected $fillable = [
        'congress_vote_id', 'topic_id', 'yea_stance', 'yea_label', 'nay_label', 'note', 'tagged_by_user_id',
    ];

    public function congressVote(): BelongsTo
    {
        return $this->belongsTo(CongressVote::class);
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(PoliticianTopic::class, 'topic_id');
    }

    public function taggedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tagged_by_user_id');
    }

    /** The member's position on the topic for a yea or nay vote; null for present / not voting. */
    public function stanceFor(string $vote): ?string
    {
        return match ($vote) {
            'yea' => $this->yea_stance,
            'nay' => $this->yea_stance === 'support' ? 'oppose' : 'support',
            default => null,
        };
    }

    public function labelFor(string $vote): ?string
    {
        return match ($vote) {
            'yea' => $this->yea_label,
            'nay' => $this->nay_label,
            default => null,
        };
    }
}
