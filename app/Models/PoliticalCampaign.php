<?php

namespace App\Models;

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

    protected function casts(): array
    {
        return [
            'target_states' => 'array',
            'target_cities' => 'array',
            'target_districts' => 'array',
            'target_governance_levels' => 'array',
            'revenue_per_view' => 'decimal:2',
            'voter_payout_per_view' => 'decimal:2',
            'total_budget' => 'decimal:2',
            'amount_spent' => 'decimal:2',
            'head_enterprises_fee_percent' => 'decimal:2',
            'live_scheduled_at' => 'datetime',
            'live_ended_at' => 'datetime',
            'approved_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($campaign) {
            if (empty($campaign->uuid)) {
                $campaign->uuid = (string) Str::uuid();
            }
            if (is_null($campaign->revenue_per_view)) {
                $campaign->revenue_per_view = config('dial4dough.revenue_per_view', 0.60);
            }
            if (is_null($campaign->voter_payout_per_view)) {
                $campaign->voter_payout_per_view = config('dial4dough.viewer_payout_per_view', 0.25);
            }
        });
    }

    public function politician()
    {
        return $this->belongsTo(Politician::class);
    }

    public function viewSessions()
    {
        return $this->hasMany(ViewSession::class);
    }

    /**
     * Check if campaign still needs more views.
     */
    public function needsMoreViews(): bool
    {
        return $this->views_completed < $this->total_views_requested
            && $this->status === 'active';
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
        return $this->campaign_type === 'live_feed';
    }

    // ── Scopes ──────────────────────────────────────────────
    public function scopeActive($query)
    {
        return $query->where('status', 'active')
                     ->where('approval_status', 'approved');
    }

    public function scopeNeedingViews($query)
    {
        return $query->active()
                     ->whereColumn('views_completed', '<', 'total_views_requested');
    }

    public function scopeLive($query)
    {
        return $query->where('campaign_type', 'live_feed')
                     ->where('status', 'active');
    }
}
