<?php

namespace App\Models;

use App\Enums\ApprovalStatus;
use App\Enums\CampaignStatus;
use App\Enums\CampaignType;
use App\Enums\PaymentStatus;
use App\Services\PlatformSettingsService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

/**
 * A political campaign — a video message or live feed that a politician
 * pays to distribute to voters.
 *
 * Revenue model per view:
 *   Politician pays          $0.60
 *   Voter receives           $0.50
 *   Referral commission      $0.050  (10% of voter payout, if referred)
 *   Platform keeps           $0.45  (before ops / payment fees)
 */
class PoliticalCampaign extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'politician_id',
        'title',
        'message_summary',
        'video_blurb',
        'campaign_type',           // video | live_feed | q_and_a
        'governance_level',
        'media_url',
        'media_type',              // youtube | vimeo | direct_file | s3_cloudfront
        'media_duration',
        'thumbnail_url',
        'live_feed_url',
        'live_scheduled_at',
        'live_ended_at',
        'revenue_per_view',
        'voter_payout_per_view',
        'total_budget',
        'amount_spent',
        'head_enterprises_fee_percent',
        'total_views_requested',
        'views_completed',
        'target_states',
        'target_cities',
        'target_districts',
        'target_governance_levels',
        'min_watch_time_percent',
        'status',                  // draft | pending_approval | active | paused | completed | cancelled
        'approval_status',         // pending | approved | rejected
        'rejection_reason',
        'payment_status',          // pending | authorized | captured | refunded
        'stripe_payment_intent_id',
        'approved_at',
        'started_at',
        'completed_at',
        // Phase 14 — Scheduling
        'scheduled_start_at',
        'scheduled_end_at',
        // Phase 14 — Repeat Viewing
        'allow_repeat_views',
        'repeat_view_cooldown_hours',
        'max_views_per_voter',
        // Phase 3 (Sprint 3) — Q&A and Topics
        'intro_text',              // Q&A intro/key message
        'qa_items',                // Q&A pairs JSON
        'engagement_survey',       // Post-view survey payload JSON
    ];

    protected $table = 'political_campaigns';

    protected $hidden = [
        'stripe_payment_intent_id',
        'head_enterprises_fee_percent',
    ];

    protected function casts(): array
    {
        return [
            'campaign_type'              => CampaignType::class,
            'status'                     => CampaignStatus::class,
            'approval_status'            => ApprovalStatus::class,
            'payment_status'             => PaymentStatus::class,
            'target_states'              => 'array',
            'target_cities'              => 'array',
            'target_districts'           => 'array',
            'target_governance_levels'   => 'array',
            'revenue_per_view'           => 'decimal:2',
            'voter_payout_per_view'      => 'decimal:2',
            'total_budget'               => 'decimal:2',
            'amount_spent'               => 'decimal:2',
            'head_enterprises_fee_percent' => 'decimal:2',
            'live_scheduled_at'          => 'datetime',
            'live_ended_at'              => 'datetime',
            'approved_at'                => 'datetime',
            'started_at'                 => 'datetime',
            'completed_at'               => 'datetime',
            // Phase 14 — Scheduling
            'scheduled_start_at'         => 'datetime',
            'scheduled_end_at'           => 'datetime',
            // Phase 14 — Repeat Viewing
            'allow_repeat_views'         => 'boolean',
            'repeat_view_cooldown_hours' => 'integer',
            'max_views_per_voter'        => 'integer',
            // Phase 3 (Sprint 3) — Q&A Topics and Survey
            'qa_items'                   => 'json',     // [{question, answer}, ...]
            'engagement_survey'          => 'json',     // {question, options: [{text, value}], ...}
        ];
    }

    public function setVideoBlurbAttribute($value): void
    {
        $this->attributes['video_blurb'] = $this->sanitizeVideoBlurb($value);
    }

    private function sanitizeVideoBlurb($value): ?string
    {
        $html = trim((string) ($value ?? ''));
        if ($html === '') {
            return null;
        }

        // Remove unsafe embedded content before allowing limited formatting tags.
        $html = preg_replace('/<(script|style|iframe|object|embed)[^>]*>.*?<\/\1>/is', '', $html) ?? '';
        $html = strip_tags($html, '<p><br><strong><b><em><i><u><ul><ol><li><a><blockquote><img>');

        // Remove all attributes from non-anchor tags.
        $html = preg_replace('/<(\/?)\s*(p|br|strong|b|em|i|u|ul|ol|li|blockquote)\b[^>]*>/i', '<$1$2>', $html) ?? '';

        // Allow only safe http/https href values on anchor tags.
        $html = preg_replace_callback('/<a\b[^>]*>/i', function (array $match): string {
            $tag = $match[0];
            if (!preg_match('/href\s*=\s*("([^"]*)"|\'([^\']*)\'|([^\s>]+))/i', $tag, $hrefMatch)) {
                return '<a>';
            }

            $href = trim((string) ($hrefMatch[2] ?? $hrefMatch[3] ?? $hrefMatch[4] ?? ''));
            if (!preg_match('/^https?:\/\//i', $href)) {
                return '<a>';
            }

            return '<a href="' . e($href) . '" target="_blank" rel="noopener noreferrer nofollow">';
        }, $html) ?? '';

        // Allow only safe http/https src values on images.
        $html = preg_replace_callback('/<img\b[^>]*>/i', function (array $match): string {
            $tag = $match[0];

            if (!preg_match('/src\s*=\s*("([^"]*)"|\'([^\']*)\'|([^\s>]+))/i', $tag, $srcMatch)) {
                return '';
            }

            $src = trim((string) ($srcMatch[2] ?? $srcMatch[3] ?? $srcMatch[4] ?? ''));
            if (!preg_match('/^https?:\/\//i', $src)) {
                return '';
            }

            $alt = '';
            if (preg_match('/alt\s*=\s*("([^"]*)"|\'([^\']*)\'|([^\s>]+))/i', $tag, $altMatch)) {
                $alt = trim((string) ($altMatch[2] ?? $altMatch[3] ?? $altMatch[4] ?? ''));
            }

            if ($alt === '') {
                $alt = 'Campaign image';
            }

            return '<img src="' . e($src) . '" alt="' . e($alt) . '" loading="lazy" decoding="async" referrerpolicy="no-referrer">';
        }, $html) ?? '';

        // Remove inline event handlers if any slipped through.
        $html = preg_replace('/\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html) ?? '';
        $html = trim($html);

        return $html !== '' ? $html : null;
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (PoliticalCampaign $campaign): void {
            if (empty($campaign->uuid)) {
                $campaign->uuid = (string) Str::uuid();
            }
            if (is_null($campaign->revenue_per_view)) {
                $campaign->revenue_per_view = (float) PlatformSettingsService::get('revenue_per_view', null, 0.60);
            }
            if (empty($campaign->voter_payout_per_view)) {
                $campaign->voter_payout_per_view = (float) PlatformSettingsService::get('viewer_payout_per_view', null, 0.25);
            }
        });
    }

    public function politician(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Politician::class);
    }

    public function topics(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(
            PoliticianTopic::class,
            'campaign_topic',
            'campaign_id',
            'topic_id'
        )->withTimestamps();
    }

    public function viewSessions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ViewSession::class);
    }

    public function adViewTokens(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AdViewToken::class, 'political_campaign_id');
    }

    public function voterWatchReports(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\VoterWatchReport::class, 'campaign_id');
    }

    /**
     * Alias: video_storage_path → media_url
     * The media_url column stores the video file path for 'video' type campaigns
     * and acts as the canonical "video storage path" in the platform schema.
     */
    public function getVideoStoragePathAttribute(): ?string
    {
        return $this->media_url;
    }

    public function setVideoStoragePathAttribute(string $value): void
    {
        $this->attributes['media_url'] = $value;
    }

    /**
     * Check if campaign still needs more views.
     */
    public function needsMoreViews(): bool
    {
        return $this->views_completed < $this->total_views_requested
            && $this->status === CampaignStatus::Active;
    }

    /**
     * How many times a specific voter has completed a view of this campaign.
     */
    public function voterCompletedViewCount(int $voterId): int
    {
        return $this->viewSessions()
            ->where('voter_id', $voterId)
            ->where('status', \App\Enums\ViewSessionStatus::Completed)
            ->count();
    }

    /**
     * Timestamp of the voter's most recent completed view of this campaign.
     */
    public function voterLastCompletedAt(int $voterId): ?\Carbon\Carbon
    {
        return $this->viewSessions()
            ->where('voter_id', $voterId)
            ->where('status', \App\Enums\ViewSessionStatus::Completed)
            ->latest('completed_at')
            ->value('completed_at');
    }

    /**
     * Remaining views needed.
     */
    public function remainingViews(): int
    {
        return max(0, $this->total_views_requested - $this->views_completed);
    }

    /**
     * Remaining budget.
     */
    public function remainingBudget(): float
    {
        return max(0, $this->total_budget - $this->amount_spent);
    }

    /**
     * Is the campaign within its scheduled delivery window right now?
     * (True even for 'active' campaigns that have no schedule set.)
     */
    public function isWithinScheduleWindow(): bool
    {
        $now = \Carbon\Carbon::now();
        if ($this->scheduled_start_at && $this->scheduled_start_at->gt($now)) {
            return false;
        }
        if ($this->scheduled_end_at && $this->scheduled_end_at->lte($now)) {
            return false;
        }
        return true;
    }

    /**
     * Is this a live feed campaign?
     */
    public function isLiveFeed(): bool
    {
        return $this->campaign_type === CampaignType::LiveFeed;
    }

    // ── Scopes ──────────────────────────────────────────────
    public function scopeActive($query): void
    {
        $query->where('status', CampaignStatus::Active)
              ->where('approval_status', ApprovalStatus::Approved);
    }

    public function scopeNeedingViews($query): void
    {
        $query->active()
              // Live-mode voter inventory must be backed by Stripe payment.
              ->where('payment_status', PaymentStatus::Captured)
              ->whereNotNull('stripe_payment_intent_id')
              ->where('stripe_payment_intent_id', '!=', '')
              // Exclude seed/demo politician accounts from voter-facing inventory.
              ->whereHas('politician', function ($q): void {
                  $q->where('is_active', true)
                    ->whereNotNull('user_id')
                    ->whereHas('user', function ($u): void {
                        $u->where('email', 'not like', '%@seed.u9itus.test');
                    });
              })
              ->whereColumn('views_completed', '<', 'total_views_requested')
              // Respect scheduled delivery window
              ->where(function ($q): void {
                  $q->whereNull('scheduled_start_at')
                    ->orWhere('scheduled_start_at', '<=', now());
              })
              ->where(function ($q): void {
                  $q->whereNull('scheduled_end_at')
                    ->orWhere('scheduled_end_at', '>', now());
              });
    }

    /** Include 'scheduled' alongside active/paused for admin campaign list queries. */
    public function scopeManageable($query): void
    {
        $query->whereIn('status', [
            CampaignStatus::Active->value,
            CampaignStatus::Paused->value,
            CampaignStatus::Scheduled->value,
        ]);
    }

    public function scopeLive($query): void
    {
        $query->where('campaign_type', CampaignType::LiveFeed)
              ->where('status', CampaignStatus::Active);
    }
}
