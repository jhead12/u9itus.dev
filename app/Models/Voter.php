<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

/**
 * A voter who watches political messages/live feeds and gets paid.
 */
class Voter extends Model
{
    use HasFactory;

    protected $table = 'voters';

    protected $hidden = [
        'device_fingerprint',
        'ip_address',
        'paypal_email',
        'cashapp_tag',
    ];

    protected $fillable = [
        'user_id',
        'uuid',
        'full_name',
        'email',
        'phone',
        'state',
        'city',
        'zip_code',
        'congressional_district',
        'preferred_governance_levels',
        'referred_by_voter_id',
        'referred_by_politician_id',
        'referral_code',
        'payment_method',
        'paypal_email',
        'cashapp_tag',
        'wallet_balance',
        'total_earned',
        'pending_earnings',
        'total_views',
        'trust_score',
        'device_fingerprint',
        'is_verified',
        'is_active',
        'flagged_for_fraud',
        'is_registered_voter',
        'last_view_at',
    ];

    protected function casts(): array
    {
        return [
            'preferred_governance_levels' => 'array',
            'wallet_balance' => 'decimal:2',
            'total_earned' => 'decimal:2',
            'pending_earnings' => 'decimal:2',
            'trust_score' => 'decimal:2',
            'is_verified' => 'boolean',
            'is_active' => 'boolean',
            'flagged_for_fraud' => 'boolean',
            'is_registered_voter' => 'boolean',
            'last_view_at' => 'datetime',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (Voter $voter): void {
            if (empty($voter->uuid)) {
                $voter->uuid = (string) Str::uuid();
            }
            if (empty($voter->referral_code)) {
                $voter->referral_code = strtoupper(Str::random(8));
            }
        });
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function viewSessions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ViewSession::class);
    }

    /**
     * The voter who referred this voter.
     */
    public function referrer(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Voter::class, 'referred_by_voter_id');
    }

    /**
     * The politician who referred this voter (via politician referral link).
     */
    public function politicianReferrer(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Politician::class, 'referred_by_politician_id');
    }

    /**
     * Voters that this voter has referred.
     */
    public function referrals(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Voter::class, 'referred_by_voter_id');
    }

    /**
     * Commission earnings from referrals (10% of referred voters' view payouts).
     */
    public function referralEarnings(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ReferralEarning::class, 'referrer_voter_id');
    }

    /**
     * Fraud signals raised against this voter (Phase 8).
     */
    public function fraudSignals(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\FraudSignal::class);
    }

    /**
     * Route model binding key.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * Check if voter can receive new view assignments today.
     */
    public function canViewToday(): bool
    {
        $viewsToday = $this->viewSessions()
            ->whereDate('created_at', today())
            ->count();

        return $viewsToday < config('u9itus.fraud.max_views_per_voter_per_day', 50)
            && !$this->flagged_for_fraud
            && $this->is_active
            && $this->is_verified;
    }
}
