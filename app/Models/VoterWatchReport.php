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
        'reference_platform',
        'reference_url',
        'reference_start_seconds',
        'reference_end_seconds',
        'reference_note',
        'status',
        'public_visibility',
        'is_public_board',
        'public_alias',
        'admin_notes',
        'campaign_reply',
        'campaign_replied_by',
        'campaign_replied_at',
        'published_by',
        'published_at',
        'resolved_by',
        'resolved_at',
    ];

    protected $casts = [
        'is_public_board' => 'boolean',
        'published_at' => 'datetime',
        'campaign_replied_at' => 'datetime',
        'resolved_at' => 'datetime',
        'reference_start_seconds' => 'integer',
        'reference_end_seconds' => 'integer',
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

    public function campaignRepliedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'campaign_replied_by');
    }

    public function publishedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
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

    public function scopeApprovedForPublicBoard($query)
    {
        return $query
            ->messages()
            ->where('public_visibility', 'approved')
            ->where('is_public_board', true);
    }

    public function scopePendingPublicApproval($query)
    {
        return $query
            ->messages()
            ->where('public_visibility', 'pending');
    }

    // ── Helpers ────────────────────────────────────────────────────

    public function isIssue(): bool  { return $this->type === 'issue'; }
    public function isMessage(): bool { return $this->type === 'message'; }
    public function isOpen(): bool   { return $this->status === 'open'; }
    public function hasCampaignReply(): bool { return !empty($this->campaign_reply); }
    public function hasReference(): bool { return !empty($this->reference_url); }

    public function referencePlatformLabel(): string
    {
        return match ((string) $this->reference_platform) {
            'youtube' => 'YouTube',
            'facebook' => 'Facebook',
            'instagram' => 'Instagram',
            'tiktok' => 'TikTok',
            'twitter' => 'X / Twitter',
            default => 'External Video',
        };
    }

    public function referenceTimeRangeLabel(): ?string
    {
        $start = $this->reference_start_seconds;
        $end = $this->reference_end_seconds;
        $label = null;

        $format = function (?int $seconds): ?string {
            if (!is_int($seconds)) {
                return null;
            }

            $safe = max(0, $seconds);
            $mins = intdiv($safe, 60);
            $secs = $safe % 60;
            return sprintf('%d:%02d', $mins, $secs);
        };

        $startLabel = $format($start);
        $endLabel = $format($end);

        if ($startLabel && $endLabel) {
            $label = $startLabel . ' - ' . $endLabel;
        } elseif ($startLabel) {
            $label = 'From ' . $startLabel;
        } elseif ($endLabel) {
            $label = 'Until ' . $endLabel;
        }

        return $label;
    }

    public function isPubliclyVisible(): bool
    {
        return $this->is_public_board && $this->public_visibility === 'approved';
    }

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
