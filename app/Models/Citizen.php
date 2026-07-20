<?php

namespace App\Models;

use App\Traits\HasProfileBadges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

/**
 * A citizen — pays to distribute local/community/ballot-issue content
 * without the FEC/election-commission burden that applies to Politicians.
 */
class Citizen extends Model
{
    use HasFactory, HasProfileBadges;

    protected $table = 'citizens';

    protected $fillable = [
        'user_id',
        'uuid',
        'full_name',
        'business_name',
        'state',
        'city',
        'address_line_1',
        'address_line_2',
        'zip',
        'bio',
        'profile_photo_url',
        'is_active',
        'referral_code',
        'slug',
        'referred_by_voter_id',
        'referred_by_politician_id',
        'neighborhood_group_id',
        'stripe_verification_session_id',
        'stripe_verified_at',
        'verified_at',
        'credit_balance',
        'stripe_customer_id',
        'receipt_email',
        // Early-bank own membership (set via member-enrolled webhook)
        'earlybank_own_member_uuid',
        'earlybank_own_linked_at',
        'earlybank_payouts_enabled',
        'earlybank_stripe_connect_account_id',
        'earlybank_stripe_connect_onboarding_complete',
        'earlybank_subscription_status',
    ];

    protected function casts(): array
    {
        return [
            'is_active'          => 'boolean',
            'stripe_verified_at' => 'datetime',
            'verified_at'        => 'datetime',
            'credit_balance'     => 'decimal:2',
            'earlybank_own_linked_at' => 'datetime',
            'earlybank_payouts_enabled' => 'boolean',
            'earlybank_stripe_connect_onboarding_complete' => 'boolean',
            'earlybank_subscription_status' => 'string',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Citizen $citizen): void {
            if (empty($citizen->uuid)) {
                $citizen->uuid = (string) Str::uuid();
            }
            if (empty($citizen->referral_code)) {
                do {
                    $code = 'C' . strtoupper(Str::random(7));
                } while (self::where('referral_code', $code)->exists());
                $citizen->referral_code = $code;
            }
            if (empty($citizen->slug)) {
                $citizen->slug = static::generateSlug($citizen);
            }
        });

        static::updating(function (Citizen $citizen): void {
            if ($citizen->isDirty(['full_name', 'city']) && ! $citizen->isDirty('slug')) {
                $citizen->slug = static::generateSlug($citizen);
            }
        });
    }

    /**
     * Generate a unique slug: {5-char-uuid-prefix}-{seo-readable-name}
     * e.g. a3f9b-jane-doe-bakery-chicago
     */
    public static function generateSlug(Citizen $citizen): string
    {
        $uuid   = $citizen->uuid ?: (string) Str::uuid();
        $prefix = substr($uuid, 0, 5);
        $name   = $citizen->business_name ?: $citizen->full_name;
        $city   = $citizen->city ?? '';
        $base   = Str::slug("{$name} {$city}");
        $cand   = "{$prefix}-{$base}";

        $counter = 0;
        $exists = fn(string $s) => static::where('slug', $s)
            ->when($citizen->id, fn($q) => $q->where('id', '!=', $citizen->id))
            ->exists();

        while ($exists($cand)) {
            $counter++;
            $cand = "{$prefix}-{$base}-{$counter}";
        }
        return $cand;
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The voter who referred this citizen to the platform.
     */
    public function voterReferrer(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Voter::class, 'referred_by_voter_id');
    }

    /**
     * The politician who referred this citizen to the platform.
     */
    public function politicianReferrer(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Politician::class, 'referred_by_politician_id');
    }

    public function campaigns(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CitizenCampaign::class);
    }

    /**
     * Re-derive credit_balance from the ledger and persist it.
     */
    public function syncCreditBalance(): void
    {
        $latest = $this->credits()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();
        $this->credit_balance = $latest ? $latest->balance_after : 0;
        $this->saveQuietly();
    }

    public function credits(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CitizenCredit::class);
    }

    public function paymentMethods(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CitizenPaymentMethod::class);
    }

    public function transactions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CitizenTransaction::class);
    }

    /**
     * Whether this citizen has completed Stripe identity verification.
     * Standard/community ad types auto-approve once this is true;
     * ballot-issue ads always require admin review regardless.
     */
    public function isIdentityVerified(): bool
    {
        return $this->verified_at !== null;
    }

    /**
     * Whether this citizen is eligible to enable the Neighborhood Token
     * (Sprint 9.5 — MeToken opt-in). Stubbed until neighborhood groups ship.
     */
    public function isEligibleForMeToken(): bool
    {
        return false;
    }

    /**
     * Route model binding key.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
