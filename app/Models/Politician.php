<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;
use App\Models\ReferralEarning;

/**
 * A politician or local governance official who creates campaigns
 * (video messages / live feeds) to reach voters.
 */
class Politician extends Model
{
    use HasFactory;

    protected $table = 'politicians';

    protected $fillable = [
        'user_id',
        'uuid',
        'referral_code',
        'full_name',
        'political_office',
        'governance_level',
        'district',
        'party_affiliation',
        'state',
        'city',
        'website_url',
        'bio',
        'profile_photo_url',
        'verified_official',
        'kyc_status',
        'stripe_customer_id',
        'credit_balance',
        'pending_earnings',
        'total_spent',
        'total_campaigns',
        'total_views_received',
        'is_active',
        'referred_by_voter_id',
        'referred_by_politician_id',
    ];

    protected function casts(): array
    {
        return [
            'credit_balance'   => 'decimal:2',
            'pending_earnings' => 'decimal:4',
            'total_spent'      => 'decimal:2',
            'verified_official'=> 'boolean',
            'is_active'        => 'boolean',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (Politician $politician): void {
            if (empty($politician->uuid)) {
                $politician->uuid = (string) Str::uuid();
            }
            if (empty($politician->referral_code)) {
                // Generate a unique 8-char alphanumeric referral code (e.g. POL3X9KW)
                do {
                    $code = 'P' . strtoupper(Str::random(7));
                } while (self::where('referral_code', $code)->exists());
                $politician->referral_code = $code;
            }
        });
    }

    /**
     * Re-derive credit_balance from the ledger and persist it.
     * Call this after any credit transaction to keep the denormalized
     * column in sync.
     */
    public function syncCreditBalance(): void
    {
        $latest = $this->credits()->latest('created_at')->first();
        $this->credit_balance = $latest ? $latest->balance_after : 0;
        $this->saveQuietly();
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The voter who referred this politician to the platform.
     */
    public function voterReferrer(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Voter::class, 'referred_by_voter_id');
    }

    /**
     * Alias kept for backwards compatibility with CampaignBillingService.
     */
    public function referrer(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->voterReferrer();
    }

    /**
     * The politician who referred this politician to the platform.
     */
    public function politicianReferrer(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Politician::class, 'referred_by_politician_id');
    }

    /**
     * Procurement referral earning record for this politician (one-time).
     */
    public function procurementReferralEarning(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(ReferralEarning::class, 'politician_id')
                    ->where('referral_type', ReferralEarning::TYPE_POLITICIAN_PROCUREMENT);
    }

    // ── Politician-as-referrer relationships ──────────────────────────────

    /**
     * Voters that this politician recruited via their referral link.
     */
    public function referredVoters(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Voter::class, 'referred_by_politician_id');
    }

    /**
     * Politicians that this politician recruited via their referral link.
     */
    public function referredPoliticians(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Politician::class, 'referred_by_politician_id');
    }

    /**
     * Commission earnings generated from this politician's referral activity.
     */
    public function referralEarnings(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ReferralEarning::class, 'referrer_politician_id');
    }

    public function campaigns(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PoliticalCampaign::class);
    }

    public function credits(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PoliticianCredit::class);
    }

    /**
     * View sessions across all of this politician's campaigns.
     */
    public function viewSessions(): \Illuminate\Database\Eloquent\Relations\HasManyThrough
    {
        return $this->hasManyThrough(ViewSession::class, PoliticalCampaign::class);
    }

    /**
     * Route model binding key.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
