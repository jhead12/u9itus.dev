<?php

namespace App\Models;

use App\Jobs\MatchPoliticianToElectionData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;
use App\Models\ReferralEarning;
use App\Models\PoliticianPage;
use App\Models\PoliticianInitiative;

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
        // Phase 13
        'slug',
        'page_settings',
        'page_published',
        // Profile verification/transparency IDs
        'verification_status',
        'verification_email',
        'verified_at',
        'verification_token',
        'show_ballotpedia_data',
        'show_opensecrets_data',
        'show_votesmart_data',
        'show_fec_data',
        'ballotpedia_id',
        'opensecrets_id',
        'votesmart_id',
        'fec_candidate_id',
    ];

    protected function casts(): array
    {
        return [
            'credit_balance'   => 'decimal:2',
            'pending_earnings' => 'decimal:4',
            'total_spent'      => 'decimal:2',
            'verified_official' => 'boolean',
            'is_active'         => 'boolean',
            // Phase 13
            'page_settings'     => 'array',
            'page_published'    => 'boolean',
            // Phase 16
            'verified_at' => 'datetime',
            'show_ballotpedia_data' => 'boolean',
            'show_opensecrets_data' => 'boolean',
            'show_votesmart_data' => 'boolean',
            'show_fec_data' => 'boolean',
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
            if (empty($politician->slug)) {
                $politician->slug = static::generateSlug($politician);
            }
        });

        static::updating(function (Politician $politician): void {
            // Regenerate slug if profile fields that feed it have changed
            if ($politician->isDirty(['full_name', 'political_office', 'city']) && ! $politician->isDirty('slug')) {
                $politician->slug = static::generateSlug($politician);
            }
        });

        static::created(function (Politician $politician): void {
            MatchPoliticianToElectionData::dispatch($politician->id);
        });

        static::updated(function (Politician $politician): void {
            if ($politician->wasChanged([
                'full_name',
                'political_office',
                'governance_level',
                'state',
                'city',
                'district',
                'party_affiliation',
            ])) {
                MatchPoliticianToElectionData::dispatch($politician->id);
            }
        });
    }

    /**
     * Generate a unique slug: {5-char-uuid-prefix}-{seo-readable-name}
     * e.g. a3f9b-mayor-john-smith-chicago
     */
    public static function generateSlug(Politician $politician): string
    {
        $uuid   = $politician->uuid ?: (string) Str::uuid();
        $prefix = substr($uuid, 0, 5);
        $office = $politician->political_office ?? 'official';
        $city   = $politician->city ?? '';
        $base   = Str::slug("{$office} {$politician->full_name} {$city}");
        $cand   = "{$prefix}-{$base}";

        // Guarantee uniqueness
        $counter = 0;
        $exists = fn(string $s) => static::where('slug', $s)
            ->when($politician->id, fn($q) => $q->where('id', '!=', $politician->id))
            ->exists();

        while ($exists($cand)) {
            $counter++;
            $cand = "{$prefix}-{$base}-{$counter}";
        }
        return $cand;
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

    // ── Phase 13: Public Profile Page ─────────────────────────────────────

    /**
     * Public profile page theme configuration (one-to-one).
     */
    public function page(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(PoliticianPage::class);
    }

    /**
     * Policy / platform initiatives shown on the public page.
     */
    public function initiatives(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PoliticianInitiative::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Return the page config, creating one with defaults if it doesn't exist yet.
     */
    public function getOrCreatePage(): PoliticianPage
    {
        return $this->page ?? PoliticianPage::create(PoliticianPage::defaults($this->id));
    }

    // ── Campaign & credit relationships ────────────────────────────────────

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
