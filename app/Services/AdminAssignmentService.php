<?php

namespace App\Services;

use App\Models\User;
use App\Models\Campaign;
use App\Models\AdAssignment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AdminAssignmentService
{
    /**
     * Get viewers available for assignment
     */
    public function getAvailableViewers()
    {
        return User::viewers()
            ->where('is_available_for_assignment', true)
            ->where('is_verified', true)
            ->whereNull('current_assignment_id')
            ->with('viewer')
            ->get();
    }

    /**
     * Get campaigns that need more viewers
     */
    public function getCampaignsNeedingViewers()
    {
        return Campaign::needingViews()
            ->with('advertiser.user')
            ->get();
    }

    /**
     * Assign an ad to a viewer
     */
    public function assignAdToViewer(Campaign $campaign, User $viewer, User $admin)
    {
        // Validate assignment
        $this->validateAssignment($campaign, $viewer);

        return DB::transaction(function () use ($campaign, $viewer, $admin) {
            $expiresAt = Carbon::now()->addHours(config('dial4dough.assignment_expiry_hours', 24));

            $assignment = AdAssignment::create([
                'campaign_id' => $campaign->id,
                'viewer_id' => $viewer->id,
                'assigned_by' => $admin->id,
                'status' => 'assigned',
                'assigned_at' => now(),
                'expires_at' => $expiresAt,
                'payment_amount' => $campaign->payment_per_view,
            ]);

            // Update viewer
            $viewer->update([
                'current_assignment_id' => $assignment->id,
                'is_available_for_assignment' => false,
                'last_assignment_at' => now(),
            ]);

            return $assignment;
        });
    }

    /**
     * Auto-assign ads to available viewers
     */
    public function autoAssignAds(int $limit = 10)
    {
        $assignments = [];
        $viewers = $this->getAvailableViewers()->take($limit);
        $campaigns = $this->getCampaignsNeedingViewers();

        if ($campaigns->isEmpty() || $viewers->isEmpty()) {
            return $assignments;
        }

        $admin = User::admins()->first();

        foreach ($viewers as $viewer) {
            foreach ($campaigns as $campaign) {
                try {
                    // Check if viewer already watched this campaign
                    $alreadyWatched = AdAssignment::where('campaign_id', $campaign->id)
                        ->where('viewer_id', $viewer->id)
                        ->exists();

                    if (!$alreadyWatched && $campaign->needsMoreViews()) {
                        $assignment = $this->assignAdToViewer($campaign, $viewer, $admin);
                        $assignments[] = $assignment;
                        break; // Move to next viewer
                    }
                } catch (\Exception $e) {
                    // Continue to next campaign if assignment fails
                    continue;
                }
            }
        }

        return $assignments;
    }

    /**
     * Validate if assignment is possible
     */
    public function validateAssignment(Campaign $campaign, User $viewer): void
    {
        // Check if viewer is available
        if (!$viewer->is_available_for_assignment || !$viewer->is_verified) {
            throw new \Exception('Viewer is not available for assignment');
        }

        // Check if viewer already has an assignment
        if ($viewer->current_assignment_id !== null) {
            throw new \Exception('Viewer already has an active assignment');
        }

        // Check if viewer already watched this campaign
        $alreadyWatched = AdAssignment::where('campaign_id', $campaign->id)
            ->where('viewer_id', $viewer->id)
            ->exists();

        if ($alreadyWatched) {
            throw new \Exception('Viewer has already watched this campaign');
        }

        // Check if campaign is active and needs views
        if (!$campaign->needsMoreViews()) {
            throw new \Exception('Campaign does not need more views');
        }
    }
}
