<?php

namespace App\Models;

use App\Enums\ApprovalStatus;
use App\Enums\CampaignStatus;
use App\Enums\CampaignType;
use App\Enums\CitizenAdType;
use App\Enums\PaymentStatus;
use App\Services\PlatformSettingsService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

/**
 * A citizen campaign — a video message or live feed that a citizen pays to
 * distribute to voters within a local zip/radius, without the FEC/election
 * compliance burden that applies to PoliticalCampaign.
 *
 * Kept as a separate table from political_campaigns (per doc/Creative.md
 * Sprint 7.5) for cleaner audit trails and distinct moderation queues.
 *
 * Revenue model per view (Standard tier):
 *   Citizen pays              $0.75
 *   Voter receives            $0.50
 *   Referral commission       $0.05  (10% of voter payout, if referred)
 *   Platform keeps            ~$0.20 (before ops / payment fees)
 *
 * Ballot-issue campaigns use the 'ballot_issue' pricing tier ($1.00/view),
 * always route through the admin approval queue, and require
 * pac_registration_id regardless of the citizen's verification status.
 */
class CitizenCampaign extends Model
{
    use HasFactory;

    protected $table = 'citizen_campaigns';

    protected $fillable = [
        'uuid',
        'citizen_id',
        'title',
        'message_summary',
        'video_blurb',
        'campaign_type',           // video | live_feed | q_and_a
        'citizen_ad_type',         // local_business | community_notice | ballot_issue | general_announcement
        'media_url',
        'media_type',              // youtube | vimeo | direct_file | s3_cloudfront | hls_stream
        'media_duration',
        'thumbnail_url',
        'subtitle_url',
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
        'target_zip',
        'target_zip_radius',
        'daily_view_cap',
        'pac_registration_id',
        'min_watch_time_percent',
        'status',                  // draft | pending_approval | scheduled | active | paused | completed | cancelled
        'approval_status',         // pending | approved | rejected
        'rejection_reason',
        'payment_status',          // pending | authorized | captured | refunded
        'stripe_payment_intent_id',
        'approved_at',
        'started_at',
        'completed_at',
        'scheduled_start_at',
        'scheduled_end_at',
        'allow_repeat_views',
        'repeat_view_cooldown_hours',
        'max_views_per_voter',
    ];

    protected $hidden = [
        'stripe_payment_intent_id',
        'head_enterprises_fee_percent',
    ];

    protected function casts(): array
    {
        return [
            'campaign_type'                => CampaignType::class,
            'citizen_ad_type'               => CitizenAdType::class,
            'status'                        => CampaignStatus::class,
            'approval_status'               => ApprovalStatus::class,
            'payment_status'                => PaymentStatus::class,
            'revenue_per_view'              => 'decimal:2',
            'voter_payout_per_view'         => 'decimal:2',
            'total_budget'                  => 'decimal:2',
            'amount_spent'                  => 'decimal:2',
            'head_enterprises_fee_percent'  => 'decimal:2',
            'live_scheduled_at'             => 'datetime',
            'live_ended_at'                 => 'datetime',
            'approved_at'                   => 'datetime',
            'started_at'                    => 'datetime',
            'completed_at'                  => 'datetime',
            'scheduled_start_at'            => 'datetime',
            'scheduled_end_at'              => 'datetime',
            'allow_repeat_views'            => 'boolean',
            'repeat_view_cooldown_hours'    => 'integer',
            'max_views_per_voter'           => 'integer',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (CitizenCampaign $campaign): void {
            if (empty($campaign->uuid)) {
                $campaign->uuid = (string) Str::uuid();
            }

            $tier = $campaign->citizen_ad_type === CitizenAdType::BallotIssue
                ? 'ballot_issue'
                : 'citizen';

            // Uses namespaced keys ('citizen_revenue_per_view', etc.) rather
            // than PlatformSettingsService::get('revenue_per_view', $tier)
            // because platform_settings.key is uniquely indexed on its own
            // (not composite with user_tier), so a shared key can't hold
            // both a 'citizen' and 'ballot_issue' row. See
            // CitizenTierPricingSeeder for the seeded values.
            if (is_null($campaign->revenue_per_view)) {
                $campaign->revenue_per_view = (float) PlatformSettingsService::get(
                    $tier . '_revenue_per_view',
                    null,
                    $tier === 'ballot_issue' ? 1.00 : 0.75
                );
            }
            if (empty($campaign->voter_payout_per_view)) {
                $campaign->voter_payout_per_view = (float) PlatformSettingsService::get(
                    $tier . '_voter_payout_per_view',
                    null,
                    0.50
                );
            }

            // Ballot-issue campaigns are uncapped and always admin-reviewed.
            if ($campaign->citizen_ad_type === CitizenAdType::BallotIssue) {
                $campaign->daily_view_cap = null;
                $campaign->approval_status = ApprovalStatus::Pending;
            }
        });
    }

    public function citizen(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Citizen::class);
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

    /**
     * Is this a ballot-issue campaign (special pricing/approval routing)?
     */
    public function isBallotIssue(): bool
    {
        return $this->citizen_ad_type === CitizenAdType::BallotIssue;
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

    public function needsMoreViews(): bool
    {
        return $this->views_completed < $this->total_views_requested
            && $this->status === CampaignStatus::Active;
    }

    // ── Scopes ──────────────────────────────────────────────
    public function scopeActive($query): void
    {
        $query->where('status', CampaignStatus::Active)
              ->where('approval_status', ApprovalStatus::Approved);
    }
}
