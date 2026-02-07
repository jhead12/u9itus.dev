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
        'ip_address',
        'device_fingerprint',
        'user_agent',
        'fraud_score',
        'fraud_flags',
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
            'completion_percentage' => 'decimal:2',
            'voter_payout_amount'   => 'decimal:2',
            'platform_revenue'      => 'decimal:2',
            'referral_commission'   => 'decimal:2',
            'fraud_score'           => 'decimal:2',
            'fraud_flags'           => 'array',
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
}
