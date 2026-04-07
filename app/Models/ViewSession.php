<?php

namespace App\Models;

use App\Enums\ViewPaymentStatus;
use App\Enums\ViewSessionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

/**
 * A single view session — a voter watching a politician's video or live feed.
 *
 * Tracks watch time, completion, fraud signals, and payment status.
 * Business logic for payout calculation lives in PoliticalViewService.
 */
class ViewSession extends Model
{
    use HasFactory;

    protected $table = 'view_sessions';

    protected $fillable = [
        'uuid',
        'political_campaign_id',
        'voter_id',
        'status',
        'started_at',
        'completed_at',
        'expires_at',
        'watch_time_seconds',
        'completion_percentage',
        'voter_payout_amount',
        'platform_revenue',
        'referral_commission',
        'payment_status',
        'paid_at',
        'processor_selected',
        'processor_executed',
        'processor_reference',
        'processor_fee',
        'ip_address',
        'device_fingerprint',
        'user_agent',
        'fraud_score',
        'fraud_flags',
        'reviewed_at',
        'reviewed_by',
        'review_action',
    ];

    protected $hidden = [
        'ip_address',
        'device_fingerprint',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'status'                => ViewSessionStatus::class,
            'payment_status'        => ViewPaymentStatus::class,
            'started_at'            => 'datetime',
            'completed_at'          => 'datetime',
            'expires_at'            => 'datetime',
            'paid_at'               => 'datetime',
            'processor_fee'         => 'decimal:2',
            'completion_percentage' => 'decimal:2',
            'voter_payout_amount'   => 'decimal:2',
            'platform_revenue'      => 'decimal:2',
            'referral_commission'   => 'decimal:2',
            'fraud_score'           => 'decimal:2',
            'fraud_flags'           => 'array',
            'reviewed_at'           => 'datetime',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (self $session): void {
            if (empty($session->uuid)) {
                $session->uuid = (string) Str::uuid();
            }
        });
    }

    // ── Relationships ───────────────────────────────────────

    public function campaign(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(PoliticalCampaign::class, 'political_campaign_id');
    }

    public function voter(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Voter::class);
    }

    public function surveyResponse(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(EngagementSurveyResponse::class);
    }

    // ── State Transitions ───────────────────────────────────

    /**
     * Mark as started — voter pressed play.
     */
    public function markStarted(): void
    {
        $this->update([
            'status'     => ViewSessionStatus::InProgress,
            'started_at' => now(),
        ]);
    }

    // ── Scopes ──────────────────────────────────────────────

    public function scopeCompleted($query)
    {
        return $query->where('status', ViewSessionStatus::Completed);
    }

    /**
     * Check if session is expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast()
            && !in_array($this->status, ['completed', 'flagged']);
    }

    // ── Additional Scopes ───────────────────────────────────

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['assigned', 'in_progress']);
    }

    /**
     * Group sessions by status value for analytics breakdowns.
     *
     * @return \Illuminate\Support\Collection<string, int>
     */
    public static function byStatus(int $campaignId): \Illuminate\Support\Collection
    {
        return static::where('political_campaign_id', $campaignId)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->get()
            ->mapWithKeys(fn ($row) => [
                ($row->status instanceof ViewSessionStatus
                    ? $row->status->value
                    : $row->status) => (int) $row->count,
            ]);
    }

    /**
     * Flag this session for fraud review.
     */
    public function flagForReview(array $flags = []): void
    {
        $merged = array_unique(array_merge((array) ($this->fraud_flags ?? []), $flags));
        $this->update([
            'status'      => ViewSessionStatus::Flagged,
            'fraud_flags' => $merged,
        ]);
    }

    /**
     * Payout eligibility: completed + payment pending or approved.
     */
    public function scopePendingPayout($query)
    {
        return $query->where('status', ViewSessionStatus::Completed)
                     ->whereIn('payment_status', [
                         ViewPaymentStatus::Pending,
                         ViewPaymentStatus::Approved,
                     ]);
    }
}
