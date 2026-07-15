<?php

namespace App\Http\Controllers\Standalone;

use App\Enums\ApprovalStatus;
use App\Enums\CampaignStatus;
use App\Http\Controllers\Controller;
use App\Models\CitizenCampaign;
use Illuminate\Http\Request;

/**
 * Admin citizen-campaign (ballot-issue) moderation lifecycle.
 *
 * Split out of AdminController. Deliberately separate from the political
 * campaign flow so the two moderation queues stay independent for compliance
 * auditing. Handles approve/reject/pause/stop/reactivate of citizen campaigns.
 */
class AdminCitizenCampaignController extends Controller
{
/**
     * Approve a citizen campaign (ballot-issue queue).
     *
     * Deliberately separate from approveCampaign (political) so the two
     * moderation queues remain independent for compliance auditing.
     * Mail/notification/broadcast wiring is deferred to Phase F.
     */
    public function approveCitizenCampaign(\App\Models\CitizenCampaign $campaign)
    {
        $newStatus = ($campaign->scheduled_start_at && $campaign->scheduled_start_at->isFuture())
            ? CampaignStatus::Scheduled->value
            : CampaignStatus::Active->value;

        $campaign->update([
            'approval_status' => ApprovalStatus::Approved->value,
            'status'          => $newStatus,
            'approved_at'     => now(),
            'started_at'      => $newStatus === CampaignStatus::Active->value ? now() : null,
        ]);

        // Note: CampaignAuditLog.campaign_id FK targets political_campaigns only.
        // Citizen campaign audit logging deferred to Phase F (polymorphic audit table).

        return back()->with('success', 'Citizen campaign "' . $campaign->title . '" has been approved.');
    }

/**
     * Reject a citizen campaign (ballot-issue queue).
     */
    public function rejectCitizenCampaign(Request $request, \App\Models\CitizenCampaign $campaign)
    {
        $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $campaign->update([
            'approval_status'  => ApprovalStatus::Rejected->value,
            'status'           => CampaignStatus::Draft->value,
            'rejection_reason' => $request->input('reason', 'Does not meet content guidelines.'),
        ]);

        // Note: CampaignAuditLog.campaign_id FK targets political_campaigns only.
        // Citizen campaign audit logging deferred to Phase F (polymorphic audit table).

        return back()->with('success', 'Citizen campaign "' . $campaign->title . '" has been rejected.');
    }

public function pauseCitizenCampaign(CitizenCampaign $campaign)
    {
        if ($campaign->status === CampaignStatus::Paused->value || $campaign->status?->value === CampaignStatus::Paused->value) {
            return back()->with('error', 'Campaign is already paused.');
        }

        $campaign->update(['status' => CampaignStatus::Paused->value]);

        return back()->with('success', 'Citizen campaign "' . $campaign->title . '" has been paused.');
    }

public function stopCitizenCampaign(Request $request, CitizenCampaign $campaign)
    {
        $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        $campaign->update([
            'status'           => CampaignStatus::Cancelled->value,
            'rejection_reason' => $request->input('reason') ?: 'Stopped by admin.',
            'completed_at'     => now(),
        ]);

        return back()->with('success', 'Citizen campaign "' . $campaign->title . '" has been stopped.');
    }

public function reactivateCitizenCampaign(CitizenCampaign $campaign)
    {
        if ($campaign->approval_status !== ApprovalStatus::Approved->value
            && $campaign->approval_status?->value !== ApprovalStatus::Approved->value) {
            return back()->with('error', 'Only approved campaigns can be reactivated.');
        }

        $newStatus = ($campaign->scheduled_start_at && $campaign->scheduled_start_at->isFuture())
            ? CampaignStatus::Scheduled->value
            : CampaignStatus::Active->value;

        $campaign->update([
            'status'           => $newStatus,
            'rejection_reason' => null,
            'completed_at'     => null,
        ]);

        return back()->with('success', 'Citizen campaign "' . $campaign->title . '" has been reactivated.');
    }
}
