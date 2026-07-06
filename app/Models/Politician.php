<?php

namespace App\Models;

use App\Jobs\MatchPoliticianToElectionData;
use App\Traits\HasProfileBadges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;
use App\Models\ReferralEarning;
use App\Models\PoliticianPage;
use App\Models\PoliticianInitiative;
use App\Models\PoliticianOfficeProfile;

/**
 * A politician or local governance official who creates campaigns
 * (video messages / live feeds) to reach voters.
 */
class Politician extends Model
{
    use HasFactory, HasProfileBadges;

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
        'profile_photo_status',
        'profile_photo_validation_confidence',
        'profile_photo_last_validated_at',
        'verified_official',
        'kyc_status',
        'stripe_customer_id',
        'receipt_email',
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
        'video_links',
        'is_running_candidate',
        'term_status',
        'term_ends_on',
        'status_updated_at',
        // Profile claim flow
        'claim_email',
        'claim_token',
        'claim_requested_at',
        // Sprint 7 — Web3 read-only enrichment (MeToken subgraph)
        'wallet_address',
        'metoken_address',
        // Early-bank own membership (set via member-enrolled webhook)
        'earlybank_own_member_uuid',
        'earlybank_own_linked_at',
    ];

    protected function casts(): array
    {
        return [
            'credit_balance'   => 'decimal:2',
            'pending_earnings' => 'decimal:4',
            'total_spent'      => 'decimal:2',
            'verified_official' => 'boolean',
            'profile_photo_validation_confidence' => 'decimal:3',
            'profile_photo_last_validated_at' => 'datetime',
            'is_active'              => 'boolean',
            'is_running_candidate'   => 'boolean',
            'term_ends_on'            => 'date',
            'status_updated_at'       => 'datetime',
            // Phase 13
            'page_settings'     => 'array',
            'page_published'    => 'boolean',
            // Phase 16
            'verified_at' => 'datetime',
            'show_ballotpedia_data' => 'boolean',
            'show_opensecrets_data' => 'boolean',
            'show_votesmart_data' => 'boolean',
            'show_fec_data' => 'boolean',
            'video_links'          => 'array',
            'claim_requested_at'   => 'datetime',
            'earlybank_own_linked_at' => 'datetime',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        // ── Data-integrity gate ────────────────────────────────────────────
        // Every save (create or update, from any import/scraper/AI path) is
        // validated against the central rules. Fixable issues are normalized
        // in place; unfixable garbage (artifact names) aborts the save.
        static::saving(function (Politician $politician): void {
            // Normalize party to canonical form; unmappable strings become null.
            if ($politician->isDirty('party_affiliation')) {
                $politician->party_affiliation =
                    \App\Support\PoliticianDataRules::normalizeParty($politician->party_affiliation);
            }

            // Uppercase state codes.
            if ($politician->isDirty('state') && $politician->state !== null) {
                $politician->state = strtoupper(trim($politician->state));
            }

            // Reject artifact names outright — these must never persist.
            if ($politician->isDirty('full_name')) {
                $violation = \App\Support\PoliticianDataRules::nameViolation($politician->full_name);
                if ($violation !== null) {
                    \Illuminate\Support\Facades\Log::warning('Politician save blocked by data rules', [
                        'id' => $politician->id,
                        'full_name' => $politician->full_name,
                        'violation' => $violation,
                    ]);
                    throw new \InvalidArgumentException(
                        "Politician full_name rejected ({$violation}): \"{$politician->full_name}\""
                    );
                }
            }

            // Invalid state codes are cleared (kept nullable) rather than fatal.
            if (\App\Support\PoliticianDataRules::stateViolation($politician->state) !== null) {
                \Illuminate\Support\Facades\Log::warning('Politician state cleared by data rules', [
                    'id' => $politician->id,
                    'state' => $politician->state,
                ]);
                $politician->state = null;
            }
        });

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

    public function photoQuarantines(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PoliticianPhotoQuarantine::class);
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

    // ── Civic education: office profile (voter-facing info popup) ─────────

    /**
     * Structured civic data about this politician's office —
     * salary, responsibilities, community impact, etc.
     * Exposed publicly so voters understand what the role does.
     */
    public function officeProfile(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(PoliticianOfficeProfile::class);
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
     * Public "Favorite Songs" picks rendered on the politician profile
     * and (V2) inside the map's candidate panel. Only active rows are
     * returned by default — soft-takedowns stay in the DB but hidden.
     */
    public function songPicks(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PoliticianSongPick::class)
            ->where('is_active', true)
            ->orderBy('display_order')
            ->orderBy('id');
    }

    /**
     * Cached donor/sponsor snapshot populated by the enrichment pipeline.
     */
    public function donorSnapshot(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(PoliticianDonorSnapshot::class);
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
     * Voters who have favorited this politician.
     */
    public function favoritedByVoters(): BelongsToMany
    {
        return $this->belongsToMany(
            Voter::class,
            'voter_favorite_politicians',
            'politician_id',
            'voter_id'
        )->withPivot('favorited_at');
    }

    /**
     * Whether this politician is eligible to enable the Sovereign (MeToken) tier.
     * Restricted to gubernatorial candidates only.
     */
    public function isEligibleForMeToken(): bool
    {
        return $this->governance_level === 'state'
            && strcasecmp($this->political_office ?? '', 'Governor') === 0;
    }

    /**
     * Route model binding key.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
