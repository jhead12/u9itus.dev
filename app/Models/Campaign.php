<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Campaign extends Model
{
    protected $fillable = [
        'uuid',
        'advertiser_id',
        'title',
        'description',
        'campaign_type',
        'media_file_url',
        'media_duration',
        'thumbnail_url',
        'total_budget',
        'payment_per_view',
        'head_enterprises_fee_percent',
        'total_views_requested',
        'views_completed',
        'target_states',
        'target_cities',
        'max_views_per_viewer',
        'min_watch_time_percent',
        'status',
        'approval_status',
        'payment_status',
        'stripe_payment_intent_id',
    ];

    protected function casts(): array
    {
        return [
            'target_states' => 'array',
            'target_cities' => 'array',
            'total_budget' => 'decimal:2',
            'payment_per_view' => 'decimal:2',
            'head_enterprises_fee_percent' => 'decimal:2',
        ];
    }

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($campaign) {
            if (empty($campaign->uuid)) {
                $campaign->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * Get the advertiser that owns the campaign.
     */
    public function advertiser()
    {
        return $this->belongsTo(Advertiser::class);
    }

    /**
     * Get all ad assignments for this campaign.
     */
    public function assignments()
    {
        return $this->hasMany(AdAssignment::class);
    }

    /**
     * Check if campaign needs more views.
     */
    public function needsMoreViews(): bool
    {
        return $this->views_completed < $this->total_views_requested &&
               $this->status === 'active';
    }

    /**
     * Get remaining views needed.
     */
    public function remainingViews(): int
    {
        return max(0, $this->total_views_requested - $this->views_completed);
    }

    /**
     * Scope a query to only include active campaigns.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active')
                     ->where('approval_status', 'approved');
    }

    /**
     * Scope a query to only include campaigns needing views.
     */
    public function scopeNeedingViews($query)
    {
        return $query->active()
                     ->whereColumn('views_completed', '<', 'total_views_requested');
    }
}
