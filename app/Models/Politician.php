<?php

namespace App\Models;

use App\Jobs\MatchPoliticianToElectionData;
use App\Support\PoliticianDataRules;
use App\Traits\HasProfileBadges;
use App\Traits\HasProfileEnrichments;
use App\Traits\HasViralMoments;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * A politician or local governance official who creates campaigns
 * (video messages / live feeds) to reach voters.
 */
class Politician extends Model
{
    use HasFactory, HasProfileBadges, HasProfileEnrichments, HasViralMoments;

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
        'wikipedia_url',
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
        'earlybank_payouts_enabled',
        'earlybank_stripe_connect_account_id',
        'earlybank_stripe_connect_onboarding_complete',
        'earlybank_subscription_status',
        // Early-bank member who referred this politician (set at registration time)
        'earlybank_member_id',
        'earlybank_linked_at',
        // Viral-moment featured clip (denormalized for map-pin reads)
        'featured_moment',
        'featured_moment_score',
        'featured_moment_published_at',
        'featured_moment_updated_at',
    ];

    protected function casts(): array
    {
        return [
            'credit_balance' => 'decimal:2',
            'pending_earnings' => 'decimal:4',
            'total_spent' => 'decimal:2',
            'verified_official' => 'boolean',
            'profile_photo_validation_confidence' => 'decimal:3',
            'profile_photo_last_validated_at' => 'datetime',
            'is_active' => 'boolean',
            'is_running_candidate' => 'boolean',
            'term_ends_on' => 'date',
            'status_updated_at' => 'datetime',
            // Phase 13
            'page_settings' => 'array',
            'page_published' => 'boolean',
            // Phase 16
            'verified_at' => 'datetime',
            'show_ballotpedia_data' => 'boolean',
            'show_opensecrets_data' => 'boolean',
            'show_votesmart_data' => 'boolean',
            'show_fec_data' => 'boolean',
            // video_links is intentionally NOT cast to 'array' here.
            // Some imported records contain non-JSON values that cause JsonException
            // on access. Use the getVideoLinksAttribute accessor instead.
            'claim_requested_at' => 'datetime',
            'earlybank_own_linked_at' => 'datetime',
            'earlybank_payouts_enabled' => 'boolean',
            'earlybank_stripe_connect_onboarding_complete' => 'boolean',
            'earlybank_subscription_status' => 'string',
            'earlybank_member_id' => 'string',
            'earlybank_linked_at' => 'datetime',
            // Viral-moment featured clip (denormalized for map-pin reads)
            'featured_moment' => 'array',
            'featured_moment_score' => 'decimal:4',
            'featured_moment_published_at' => 'datetime',
            'featured_moment_updated_at' => 'datetime',
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
                    PoliticianDataRules::normalizeParty($politician->party_affiliation);
            }

            // Uppercase state codes.
            if ($politician->isDirty('state') && $politician->state !== null) {
                $politician->state = strtoupper(trim($politician->state));
            }

            // Reject artifact names outright — these must never persist.
            if ($politician->isDirty('full_name')) {
                $violation = PoliticianDataRules::nameViolation($politician->full_name);
                if ($violation !== null) {
                    Log::warning('Politician save blocked by data rules', [
                        'id' => $politician->id,
                        'full_name' => $politician->full_name,
                        'violation' => $violation,
                    ]);
                    throw new \InvalidArgumentException(
                        "Politician full_name rejected ({$violation}): \"{$politician->full_name}\""
                    );
                }
            }

            // Resolve full state names (e.g. 'CALIFORNIA' → 'CA') before
            // clearing; only null it out when truly unmappable.
            if (PoliticianDataRules::stateViolation($politician->state) !== null) {
                $politician->state = PoliticianDataRules::resolveStateAbbreviation($politician->state);
            }
        });

        static::creating(function (Politician $politician): void {
            if (empty($politician->uuid)) {
                $politician->uuid = (string) Str::uuid();
            }
            if (empty($politician->referral_code)) {
                // Generate a unique 8-char alphanumeric referral code (e.g. POL3X9KW)
                do {
                    $code = 'P'.strtoupper(Str::random(7));
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

        // The public map's per-state payload (MapStateCandidatesController)
        // is cached for an hour under map_state_candidates_{state}. Without
        // busting it here, any create/update/delete — including the daily
        // reconciliation commands that promote or correct candidate rows —
        // can leave a state's map silently stale for up to an hour.
        static::saved(function (Politician $politician): void {
            self::forgetMapCacheFor($politician->state);
            if ($politician->wasChanged('state')) {
                self::forgetMapCacheFor($politician->getOriginal('state'));
            }
        });

        static::deleted(function (Politician $politician): void {
            self::forgetMapCacheFor($politician->state);
        });
    }

    private static function forgetMapCacheFor(?string $state): void
    {
        $state = strtoupper(trim((string) $state));
        if ($state === '') {
            return;
        }
        Cache::forget("map_state_candidates_{$state}");
    }

    /**
     * Generate a unique slug: {5-char-uuid-prefix}-{seo-readable-name}
     * e.g. a3f9b-mayor-john-smith-chicago
     */
    public static function generateSlug(Politician $politician): string
    {
        $uuid = $politician->uuid ?: (string) Str::uuid();
        $prefix = substr($uuid, 0, 5);
        $office = $politician->political_office ?? 'official';
        $city = $politician->city ?? '';
        $base = Str::slug("{$office} {$politician->full_name} {$city}");
        $cand = "{$prefix}-{$base}";

        // Guarantee uniqueness
        $counter = 0;
        $exists = fn (string $s) => static::where('slug', $s)
            ->when($politician->id, fn ($q) => $q->where('id', '!=', $politician->id))
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The voter who referred this politician to the platform.
     */
    public function voterReferrer(): BelongsTo
    {
        return $this->belongsTo(Voter::class, 'referred_by_voter_id');
    }

    /**
     * Alias kept for backwards compatibility with CampaignBillingService.
     */
    public function referrer(): BelongsTo
    {
        return $this->voterReferrer();
    }

    /**
     * The politician who referred this politician to the platform.
     */
    public function politicianReferrer(): BelongsTo
    {
        return $this->belongsTo(Politician::class, 'referred_by_politician_id');
    }

    public function photoQuarantines(): HasMany
    {
        return $this->hasMany(PoliticianPhotoQuarantine::class);
    }

    /**
     * Procurement referral earning record for this politician (one-time).
     */
    public function procurementReferralEarning(): HasOne
    {
        return $this->hasOne(ReferralEarning::class, 'politician_id')
            ->where('referral_type', ReferralEarning::TYPE_POLITICIAN_PROCUREMENT);
    }

    // ── Politician-as-referrer relationships ──────────────────────────────

    /**
     * Voters that this politician recruited via their referral link.
     */
    public function referredVoters(): HasMany
    {
        return $this->hasMany(Voter::class, 'referred_by_politician_id');
    }

    /**
     * Politicians that this politician recruited via their referral link.
     */
    public function referredPoliticians(): HasMany
    {
        return $this->hasMany(Politician::class, 'referred_by_politician_id');
    }

    /**
     * Commission earnings generated from this politician's referral activity.
     */
    public function referralEarnings(): HasMany
    {
        return $this->hasMany(ReferralEarning::class, 'referrer_politician_id');
    }

    /**
     * Inbound Early-bank earnings reported for this politician.
     */
    public function earlybankEarnings(): HasMany
    {
        return $this->hasMany(EarlyBankEarning::class, 'politician_id');
    }

    // ── Civic education: office profile (voter-facing info popup) ─────────

    /**
     * Structured civic data about this politician's office —
     * salary, responsibilities, community impact, etc.
     * Exposed publicly so voters understand what the role does.
     */
    public function officeProfile(): HasOne
    {
        return $this->hasOne(PoliticianOfficeProfile::class);
    }

    // ── Phase 13: Public Profile Page ─────────────────────────────────────

    /**
     * Public profile page theme configuration (one-to-one).
     */
    public function page(): HasOne
    {
        return $this->hasOne(PoliticianPage::class);
    }

    /**
     * Policy / platform initiatives shown on the public page.
     */
    public function initiatives(): HasMany
    {
        return $this->hasMany(PoliticianInitiative::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Real public endorsements detected from news coverage (see
     * App\Services\EndorsementClassifier) — distinct from the
     * donor-inferred PAC affiliation badges.
     */
    public function endorsements(): HasMany
    {
        return $this->hasMany(PoliticianEndorsement::class);
    }

    /**
     * Public "Favorite Songs" picks rendered on the politician profile
     * and (V2) inside the map's candidate panel. Only active rows are
     * returned by default — soft-takedowns stay in the DB but hidden.
     */
    public function songPicks(): HasMany
    {
        return $this->hasMany(PoliticianSongPick::class)
            ->where('is_active', true)
            ->orderBy('display_order')
            ->orderBy('id');
    }

    /**
     * Cached donor/sponsor snapshot populated by the enrichment pipeline.
     */
    public function donorSnapshot(): HasOne
    {
        return $this->hasOne(PoliticianDonorSnapshot::class);
    }

    /**
     * Safe accessor for video_links — returns [] instead of throwing on bad JSON.
     */
    public function getVideoLinksAttribute(): array
    {
        $raw = $this->getRawOriginal('video_links');
        if ($raw === null || $raw === '' || $raw === 'null') {
            return [];
        }
        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

            return is_array($decoded) ? $decoded : [];
        } catch (\JsonException) {
            return [];
        }
    }

    /**
     * Setter for video_links — since the column isn't cast to 'array' (see
     * getVideoLinksAttribute), Eloquent won't auto-encode arrays on save.
     */
    public function setVideoLinksAttribute(mixed $value): void
    {
        $this->attributes['video_links'] = $value === null ? null : json_encode($value);
    }

    /**
     * Return the page config, creating one with defaults if it doesn't exist yet.
     */
    public function getOrCreatePage(): PoliticianPage
    {
        return $this->page ?? PoliticianPage::create(PoliticianPage::defaults($this->id));
    }

    // ── Campaign & credit relationships ────────────────────────────────────

    public function campaigns(): HasMany
    {
        return $this->hasMany(PoliticalCampaign::class);
    }

    /**
     * Blog posts authored by this politician.
     */
    public function posts(): MorphMany
    {
        return $this->morphMany(Post::class, 'author');
    }

    public function events(): MorphMany
    {
        return $this->morphMany(CivicEvent::class, 'host');
    }

    public function credits(): HasMany
    {
        return $this->hasMany(PoliticianCredit::class);
    }

    /**
     * View sessions across all of this politician's campaigns.
     */
    public function viewSessions(): HasManyThrough
    {
        return $this->hasManyThrough(ViewSession::class, PoliticalCampaign::class);
    }

    /**
     * Per-topic evidence rollup (news/viral-moment/Vote Smart signals) that
     * drives inferred issue badges. Built by PoliticianTopicSignalService.
     */
    public function topicSignals(): HasMany
    {
        return $this->hasMany(PoliticianTopicSignal::class);
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
