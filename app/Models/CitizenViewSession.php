<?php

namespace App\Models;

use App\Enums\ViewPaymentStatus;
use App\Enums\ViewSessionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

/**
 * A single view session for a citizen-paid campaign (community notice,
 * local business, ballot issue, etc.).
 *
 * Mirrors ViewSession but belongs to a CitizenCampaign instead of a
 * PoliticalCampaign. Payouts and campaign-spend tracking live in
 * CitizenViewService.
 */
class CitizenViewSession extends Model
{
    use HasFactory;

    protected $table = 'citizen_view_sessions';

    protected $fillable = [
        'uuid',
        'citizen_campaign_id',
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

    public function campaign(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(CitizenCampaign::class, 'citizen_campaign_id');
    }

    public function voter(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Voter::class);
    }

    public function markStarted(): void
    {
        $this->update([
            'status'     => ViewSessionStatus::InProgress,
            'started_at' => now(),
        ]);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', ViewSessionStatus::Completed);
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', [ViewSessionStatus::Assigned, ViewSessionStatus::InProgress]);
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
