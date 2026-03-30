<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Voter Watch Report
 *
 * Represents a voter interaction from the watch page:
 *  - type='issue'   → error / problem report (visible to admin)
 *  - type='message' → direct message dispatched to the politician
 */
class VoterWatchReport extends Model
{
    protected $fillable = [
        'voter_id',
        'campaign_id',
        'view_session_uuid',
        'type',
        'issue_category',
        'body',
        'media_url',
        'media_duration',
        'message_type',
        'status',
        'admin_notes',
        'resolved_by',
        'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
        'media_duration' => 'integer',
    ];

    // ── Relationships ──────────────────────────────────────────────

    public function voter(): BelongsTo
    {
        return $this->belongsTo(Voter::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(PoliticalCampaign::class);
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    // ── Scopes ─────────────────────────────────────────────────────

    public function scopeIssues($query)
    {
        return $query->where('type', 'issue');
    }

    public function scopeMessages($query)
    {
        return $query->where('type', 'message');
    }

    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    public function scopeTextQuestions($query)
    {
        return $query->where('message_type', 'text');
    }

    public function scopeVideoQuestions($query)
    {
        return $query->where('message_type', 'video');
    }

    // ── Helpers ────────────────────────────────────────────────────

    public function isIssue(): bool  { return $this->type === 'issue'; }
    public function isMessage(): bool { return $this->type === 'message'; }
    public function isOpen(): bool   { return $this->status === 'open'; }

    public function isTextQuestion(): bool { return $this->message_type === 'text'; }
    public function isVideoQuestion(): bool { return $this->message_type === 'video'; }
    public function hasMedia(): bool { return !empty($this->media_url); }

    public function categoryLabel(): string
    {
        return match ($this->issue_category) {
            'video_not_playing' => 'Video Not Playing',
            'incorrect_info'    => 'Incorrect Information',
            'offensive_content' => 'Offensive Content',
            default             => 'Other / General',
        };
    }
}
