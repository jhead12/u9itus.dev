<?php

namespace App\Models;

use App\Traits\HasProfileBadges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A voter who watches political messages/live feeds and gets paid.
 */
class Voter extends Model
{
    use HasFactory, HasProfileBadges;

    protected $table = 'voters';

    protected $hidden = [
        'device_fingerprint',
        'ip_address',
        'paypal_email',
        'cashapp_tag',
        'stripe_account_id',
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
        'earlybank_member_id',
        'earlybank_linked_at',
        'earlybank_own_member_uuid',
        'earlybank_own_linked_at',
        'earlybank_payouts_enabled',
        'earlybank_stripe_connect_account_id',
        'earlybank_stripe_connect_onboarding_complete',
        'earlybank_subscription_status',
        'referral_code',
        'user_tier',               // 'early_adopter', 'regular', null
        'early_adopter_until',     // Expiry timestamp for early adopter status
        'payment_method',
        'paypal_email',
        'cashapp_tag',
        'stripe_account_id',
        'stripe_account_status',
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
            'stripe_account_status' => 'string',
            'last_view_at' => 'datetime',
            'early_adopter_until' => 'datetime',
            'earlybank_linked_at' => 'datetime',
            'earlybank_own_linked_at' => 'datetime',
            'earlybank_payouts_enabled' => 'boolean',
            'earlybank_stripe_connect_onboarding_complete' => 'boolean',
            'earlybank_subscription_status' => 'string',
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

    /**
     * SEC-4: opaque bearer tokens issued to this voter for stateless API auth.
     */
    public function apiTokens(): HasMany
    {
        return $this->hasMany(VoterApiToken::class);
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
     * Inbound Early-bank earnings reported for this voter.
     */
    public function earlybankEarnings(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(EarlyBankEarning::class, 'voter_id');
    }

    /**
     * Fraud signals raised against this voter (Phase 8).
     */
    public function fraudSignals(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\FraudSignal::class);
    }

    /**
     * Politicians this voter has favorited / follows.
     */
    public function favoritePoliticians(): BelongsToMany
    {
        return $this->belongsToMany(
            Politician::class,
            'voter_favorite_politicians',
            'voter_id',
            'politician_id'
        )->withPivot('favorited_at');
    }

    /**
     * News articles this voter has saved / liked.
     */
    public function savedArticles(): BelongsToMany
    {
        return $this->belongsToMany(
            \App\Models\CandidateNewsArticle::class,
            'voter_saved_articles',
            'voter_id',
            'candidate_news_article_id'
        )->withPivot('saved_at');
    }

    /**
     * Geographic boundaries (districts / top cities) this voter has saved
     * for quick access from the 3D map's Layers → Boundaries panel.
     */
    public function favoriteBoundaries(): HasMany
    {
        return $this->hasMany(VoterFavoriteBoundary::class, 'voter_id');
    }

    /**
     * Causes (specific issues under a Topic) this voter has favorited.
     */
    public function favoriteCauses(): BelongsToMany
    {
        return $this->belongsToMany(
            Cause::class,
            'voter_favorite_causes',
            'voter_id',
            'cause_id'
        )->withPivot('favorited_at');
    }

    /**
     * Ballot measures this voter has favorited.
     */
    public function favoriteBallotMeasures(): BelongsToMany
    {
        return $this->belongsToMany(
            BallotMeasure::class,
            'voter_favorite_ballot_measures',
            'voter_id',
            'ballot_measure_id'
        )->withPivot('favorited_at');
    }

    /**
     * This voter's personal notes on politicians (one running note each).
     */
    public function politicianNotes(): HasMany
    {
        return $this->hasMany(VoterPoliticianNote::class, 'voter_id');
    }

    /**
     * Route model binding key.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * Sum of Early-bank reported earnings (settled + pending). EB remains the
     * source of truth; this is for display only.
     */
    public function getEarlybankEarningsTotalAttribute(): float
    {
        if ($this->relationLoaded('earlybankEarnings')) {
            return (float) $this->earlybankEarnings->sum('payout_amount');
        }

        return (float) $this->earlybankEarnings()->sum('payout_amount');
    }

    /**
     * Check if voter can receive new view assignments today.
     */
    public function canViewToday(): bool
    {
        $viewsToday = $this->viewSessions()
            ->whereDate('created_at', today())
            ->count();

        // Phase A migration: allow either legacy manual verification,
        // Stripe Connect active status, or Id.me verification to unlock activity.
        $verificationEligible = $this->is_verified
            || $this->stripe_account_status === 'active'
            || optional($this->user)->idme_verified_at !== null;

        return $viewsToday < config('u9itus.fraud.max_views_per_voter_per_day', 50)
            && !$this->flagged_for_fraud
            && $this->is_active
            && $verificationEligible;
    }

    /**
     * Whether this voter has completed the Authentic User Verifier flow.
     */
    public function hasAuthenticUserVerifier(): bool
    {
        return ! empty($this->stripe_account_id)
            && $this->stripe_account_status === 'active';
    }

    /**
     * Whether this voter has legacy identity verification artifacts.
     */
    public function isLegacyVerificationHolder(): bool
    {
        $user = $this->relationLoaded('user')
            ? $this->user
            : $this->user()->first();

        if (! $user) {
            return false;
        }

        return ! empty($user->kyc_document_path)
            || in_array((string) ($user->kyc_status ?? ''), ['pending', 'approved', 'rejected'], true)
            || $user->idme_verified_at !== null;
    }

    /**
     * Whether this voter should be prompted to migrate to Authentic User Verifier.
     */
    public function needsAuthenticUserVerifierMigration(): bool
    {
        return $this->isLegacyVerificationHolder() && ! $this->hasAuthenticUserVerifier();
    }
}
