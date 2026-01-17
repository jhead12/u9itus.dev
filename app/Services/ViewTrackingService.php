<?php

namespace App\Services;

use App\Models\AdAssignment;
use Carbon\Carbon;

class ViewTrackingService
{
    /**
     * Track when viewing starts
     */
    public function trackViewStart(AdAssignment $assignment)
    {
        $assignment->markStarted();
        return $assignment;
    }

    /**
     * Track viewing progress
     */
    public function trackViewProgress(AdAssignment $assignment, int $seconds)
    {
        $assignment->update([
            'watch_time' => $seconds,
        ]);

        return $assignment;
    }

    /**
     * Validate if view completion meets requirements
     */
    public function validateViewCompletion(AdAssignment $assignment): bool
    {
        $campaign = $assignment->campaign;
        $completionPercentage = ($assignment->watch_time / $campaign->media_duration) * 100;

        return $completionPercentage >= $campaign->min_watch_time_percent;
    }

    /**
     * Complete the view and process payment
     */
    public function completeView(AdAssignment $assignment, int $watchTime)
    {
        $assignment->markCompleted($watchTime);
        
        return $assignment;
    }
}
