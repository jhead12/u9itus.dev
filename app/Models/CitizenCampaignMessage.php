<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A voter interaction from the citizen campaign watch page:
 *   - type='issue'   → a reported problem (video not playing, incorrect info, etc.)
 *   - type='message' → a voter-to-sponsor question
 *
 * Mirrors the politician flow's VoterWatchReport, but isolated to citizen campaigns
 * so the politician report queue (voter_watch_reports → political_campaigns) stays clean.
 */
class CitizenCampaignMessage extends Model
{
    protected $table = 'citizen_campaign_messages';

    protected $fillable = [
        'voter_id',
        'citizen_campaign_id',
        'type',
        'issue_category',
        'body',
        'reference_url',
        'reference_start_seconds',
        'reference_end_seconds',
        'reference_note',
        'status',
    ];

    protected $casts = [
        'reference_start_seconds' => 'integer',
        'reference_end_seconds'   => 'integer',
    ];

    // ── Relationships ──────────────────────────────────────────────

    public function voter(): BelongsTo
    {
        return $this->belongsTo(Voter::class);
    }

    public function citizenCampaign(): BelongsTo
    {
        return $this->belongsTo(CitizenCampaign::class, 'citizen_campaign_id');
    }

    // ── Helpers ────────────────────────────────────────────────────

    public function isIssue(): bool
    {
        return $this->type === 'issue';
    }

    public function isMessage(): bool
    {
        return $this->type === 'message';
    }

    public function hasReference(): bool
    {
        return ! empty($this->reference_url);
    }

    public function categoryLabel(): string
    {
        return match ($this->issue_category) {
            'video_not_playing'  => 'Video Not Playing',
            'incorrect_info'     => 'Incorrect Information',
            'offensive_content'  => 'Offensive Content',
            default              => 'Other / General',
        };
    }
};