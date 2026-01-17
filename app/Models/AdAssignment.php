<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class AdAssignment extends Model
{
    protected $fillable = [
        'campaign_id',
        'viewer_id',
        'assigned_by',
        'status',
        'assigned_at',
        'started_at',
        'completed_at',
        'expires_at',
        'watch_time',
        'completion_percentage',
        'payment_amount',
        'payment_status',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'expires_at' => 'datetime',
            'paid_at' => 'datetime',
            'completion_percentage' => 'decimal:2',
            'payment_amount' => 'decimal:2',
        ];
    }

    /**
     * Get the campaign for this assignment.
     */
    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }

    /**
     * Get the viewer for this assignment.
     */
    public function viewer()
    {
        return $this->belongsTo(User::class, 'viewer_id');
    }

    /**
     * Get the admin who assigned this ad.
     */
    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    /**
     * Mark the assignment as started.
     */
    public function markStarted()
    {
        $this->update([
            'status' => 'in_progress',
            'started_at' => now(),
        ]);
    }

    /**
     * Mark the assignment as completed.
     */
    public function markCompleted(int $watchTime)
    {
        $campaign = $this->campaign;
        $completionPercentage = ($watchTime / $campaign->media_duration) * 100;
        
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
            'watch_time' => $watchTime,
            'completion_percentage' => $completionPercentage,
            'payment_amount' => $completionPercentage >= $campaign->min_watch_time_percent 
                ? $campaign->payment_per_view 
                : 0,
            'payment_status' => $completionPercentage >= $campaign->min_watch_time_percent 
                ? 'approved' 
                : 'rejected',
        ]);
        
        // Update viewer's current assignment
        $this->viewer->update([
            'current_assignment_id' => null,
            'is_available_for_assignment' => true,
        ]);
        
        // Update campaign views if payment is approved
        if ($this->payment_status === 'approved') {
            $campaign->increment('views_completed');
            
            // Update viewer earnings
            $viewer = $this->viewer->viewer;
            $viewer->increment('pending_earnings', $this->payment_amount);
            $viewer->increment('total_views');
        }
    }

    /**
     * Check if assignment is expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at < now() && $this->status !== 'completed';
    }

    /**
     * Scope a query to only include active assignments.
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', ['assigned', 'in_progress']);
    }

    /**
     * Scope a query to only include completed assignments.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope a query to only include expired assignments.
     */
    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<', now())
                     ->whereIn('status', ['assigned', 'in_progress']);
    }
}
