<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

/**
 * A single view session — a voter watching a politician's video or live feed.
 * Tracks watch time, completion, fraud signals, and payment status.
 */
class ViewSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'political_campaign_id',
        'voter_id',
        'status',                   // assigned | in_progress | completed | expired | flagged
        'started_at',
        'completed_at',
        'expires_at',
        'watch_time_seconds',
        'completion_percentage',
        'voter_payout_amount',
        'platform_revenue',
        'referral_commission',
        'payment_status',           // pending | approved | paid | held | rejected
        'paid_at',
        'ip_address',
        'device_fingerprint',
        'user_agent',
        'fraud_score',
        'fraud_flags',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'expires_at' => 'datetime',
            'paid_at' => 'datetime',
            'completion_percentage' => 'decimal:2',
            'voter_payout_amount' => 'decimal:2',
            'platform_revenue' => 'decimal:2',
            'referral_commission' => 'decimal:2',
            'fraud_score' => 'decimal:2',
            'fraud_flags' => 'array',
        ];
    }

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($session) {
            if (empty($session->uuid)) {
                $session->uuid = (string) Str::uuid();
            }
        });
    }

    public function campaign()
    {
        return $this->belongsTo(PoliticalCampaign::class, 'political_campaign_id');
    }

    public function voter()
    {
        return $this->belongsTo(Voter::class);
    }

    /**
     * Start watching.
     */
    public function markStarted()
    {
        $this->update([
            'status' => 'in_progress',
            'started_at' => now(),
        ]);
    }

    /**
     * Mark as completed and calculate payouts.
     */
    public function markCompleted(int $watchTimeSeconds)
    {
        $campaign = $this->campaign;
        $completionPct = $campaign->media_duration > 0
            ? ($watchTimeSeconds / $campaign->media_duration) * 100
            : 100;

        $qualifies = $completionPct >= $campaign->min_watch_time_percent;

        $voterPayout = $qualifies ? $campaign->voter_payout_per_view : 0;
        $platformRevenue = $qualifies ? ($campaign->revenue_per_view - $voterPayout) : 0;

        // Referral commission: 10 % of voter payout if the voter was referred
        $referralCommission = 0;
        if ($qualifies && $this->voter->referred_by_voter_id) {
            $referralCommission = $voterPayout * (config('dial4dough.referral_commission_percent', 10) / 100);
            $platformRevenue -= $referralCommission;
        }

        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
            'watch_time_seconds' => $watchTimeSeconds,
            'completion_percentage' => $completionPct,
            'voter_payout_amount' => $voterPayout,
            'platform_revenue' => $platformRevenue,
            'referral_commission' => $referralCommission,
            'payment_status' => $qualifies ? 'approved' : 'rejected',
        ]);

        if ($qualifies) {
            // Credit voter
            $this->voter->increment('pending_earnings', $voterPayout);
            $this->voter->increment('total_views');

            // Credit referrer
            if ($referralCommission > 0 && $this->voter->referrer) {
                ReferralEarning::create([
                    'referrer_voter_id' => $this->voter->referred_by_voter_id,
                    'referred_voter_id' => $this->voter->id,
                    'view_session_id' => $this->id,
                    'commission_amount' => $referralCommission,
                ]);
                $this->voter->referrer->increment('pending_earnings', $referralCommission);
            }

            // Update campaign spend
            $campaign->increment('views_completed');
            $campaign->increment('amount_spent', $campaign->revenue_per_view);
        }
    }

    /**
     * Check if session is expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast()
            && !in_array($this->status, ['completed', 'flagged']);
    }

    // ── Scopes ──────────────────────────────────────────────
    public function scopeActive($query)
    {
        return $query->whereIn('status', ['assigned', 'in_progress']);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }
}
