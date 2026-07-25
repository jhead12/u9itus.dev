<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A badge awarded to a politician, voter, or citizen profile.
 * Backed by a PoliticianTopic (the badge catalog).
 */
class ProfileBadge extends Model
{
    protected $table = 'profile_badges';

    protected $fillable = [
        'badgeable_type',
        'badgeable_id',
        'topic_id',
        'badge_type',
        'earned_threshold',
        'is_public',
        'earned_at',
    ];

    protected function casts(): array
    {
        return [
            'is_public'        => 'boolean',
            'earned_threshold' => 'integer',
            'earned_at'        => 'datetime',
        ];
    }

    // ── Relations ──────────────────────────────────────────────────────────

    public function badgeable(): MorphTo
    {
        return $this->morphTo();
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(PoliticianTopic::class, 'topic_id');
    }

    // ── Scopes ─────────────────────────────────────────────────────────────

    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    public function scopeEarned($query)
    {
        return $query->whereIn('badge_type', ['earned_views', 'earned_referral', 'token_holder']);
    }

    public function scopeSelfDeclared($query)
    {
        return $query->where('badge_type', 'self_declared');
    }

    /**
     * System-inferred issue labels (from news/viral-moment/Vote Smart signals).
     */
    public function scopeInferred($query)
    {
        return $query->where('badge_type', 'inferred_discourse');
    }
}
