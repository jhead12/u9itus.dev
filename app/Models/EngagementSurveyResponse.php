<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Voter responses to post-view engagement surveys.
 * One response per completed view session (enforced by unique index).
 */
class EngagementSurveyResponse extends Model
{
    use HasFactory;

    protected $table = 'engagement_survey_responses';

    protected $fillable = [
        'view_session_id',
        'campaign_id',
        'voter_id',
        'response_value',
        'response_text',
        'responded_at',
    ];

    protected function casts(): array
    {
        return [
            'responded_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * The view session this response belongs to.
     */
    public function viewSession(): BelongsTo
    {
        return $this->belongsTo(ViewSession::class);
    }

    /**
     * The campaign this survey was for.
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(PoliticalCampaign::class);
    }

    /**
     * The voter who responded.
     */
    public function voter(): BelongsTo
    {
        return $this->belongsTo(Voter::class);
    }

    /**
     * Record a survey response for a completed session.
     * Enforces one-per-session idempotency via unique constraint.
     */
    public static function recordResponse(
        ViewSession $session,
        Voter $voter,
        PoliticalCampaign $campaign,
        string $responseValue,
        ?string $responseText = null
    ): self {
        return self::updateOrCreate(
            ['view_session_id' => $session->id],
            [
                'campaign_id' => $campaign->id,
                'voter_id' => $voter->id,
                'response_value' => $responseValue,
                'response_text' => $responseText,
                'responded_at' => now(),
            ]
        );
    }
}
