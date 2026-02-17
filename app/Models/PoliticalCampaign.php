<?php

namespace App\Models;

use App\Enums\ApprovalStatus;
use App\Enums\CampaignStatus;
use App\Enums\CampaignType;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

/**
 * A political campaign — a video message or live feed that a politician
 * pays to distribute to voters.
 *
 * Revenue model per view:
 *   Politician pays          $0.60
 *   Voter receives           $0.25
 *   Referral commission      $0.025  (10% of voter payout, if referred)
 *   Platform keeps           $0.325  (before ops / payment fees)
 */
class PoliticalCampaign extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'politician_id',
        'title',
        'message_summary',
        'campaign_type',           // video | live_feed
        'governance_level',
        'media_url',
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
        'payment_status',          // pending | authorized | captured | refunded
        'stripe_payment_intent_id',
        'approved_at',
        'started_at',
        'completed_at',
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
        ];
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (PoliticalCampaign $campaign): void {
            if (empty($campaign->uuid)) {
                $campaign->uuid = (string) Str::uuid();
            }
            if (is_null($campaign->revenue_per_view)) {
                $campaign->revenue_per_view = config('u9itus.revenue_per_view', 0.60);
            }
            if (empty($campaign->voter_payout_per_view)) {
                $campaign->voter_payout_per_view = config('u9itus.viewer_payout_per_view', 0.25);
            }
        });
    }

    public function politician(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Politician::class);
    }

    public function viewSessions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ViewSession::class);
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
              ->whereColumn('views_completed', '<', 'total_views_requested');
    }

    public function scopeLive($query): void
    {
        $query->where('campaign_type', CampaignType::LiveFeed)
              ->where('status', CampaignStatus::Active);
    }
}
