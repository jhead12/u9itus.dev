<?php

namespace App\Services;

use App\Enums\ApprovalStatus;
use App\Enums\CampaignStatus;
use App\Models\CampaignAuditLog;
use App\Models\PoliticalCampaign;

/**
 * Single source of truth for campaign moderation actions (approve / reject).
 *
 * Both the API and standalone admin controllers delegate here so that
 * status-transition logic, budget charging, audit logging, and notifications
 * stay in sync across both surfaces (PATT-004 fix).
 */
class CampaignModerationService
{
    public function __construct(
        protected PoliticalPaymentService $paymentService,
        protected CampaignStatusNotifier $statusNotifier,
    ) {}

    /**
     * Approve a campaign: transition status, charge budget, write audit log, notify.
     *
     * Respects `scheduled_start_at`: if set and in the future the campaign is
     * placed in `Scheduled` status and activated by the `campaigns:apply-schedule`
     * Artisan command at the right time.  Otherwise it transitions directly to
     * `Active` and `started_at` is stamped immediately.
     *
     * @param  PoliticalCampaign  $campaign
     * @param  int|null           $adminId  Authenticated admin user ID; omit only
     *                                      when no audit trail is required.
     * @return array{new_status: CampaignStatus, label: string}
     */
    public function approve(PoliticalCampaign $campaign, ?int $adminId = null): array
    {
        $scheduledStart = $campaign->scheduled_start_at;
        $newStatus = ($scheduledStart && $scheduledStart->isFuture())
            ? CampaignStatus::Scheduled
            : CampaignStatus::Active;

        $now = now();
        $campaign->update([
            'approval_status' => ApprovalStatus::Approved,
            'status'          => $newStatus,
            'approved_at'     => $now,
            'started_at'      => $newStatus === CampaignStatus::Active ? $now : null,
        ]);

        $this->paymentService->chargeCampaign($campaign);

        if ($adminId !== null) {
            CampaignAuditLog::create([
                'campaign_id' => $campaign->id,
                'admin_id'    => $adminId,
                'action'      => 'approved',
            ]);
        }

        $this->statusNotifier->notifyStatusChanged($campaign, 'approved');

        $label = $newStatus === CampaignStatus::Scheduled
            ? 'approved and scheduled (activates ' . $scheduledStart->format('M j, Y H:i') . ')'
            : 'approved and set to active';

        return ['new_status' => $newStatus, 'label' => $label];
    }

    /**
     * Reject a campaign: return it to Draft, write audit log, notify.
     *
     * Rejected campaigns return to `Draft` so politicians can revise or delete.
     *
     * @param  PoliticalCampaign  $campaign
     * @param  string             $reason
     * @param  int|null           $adminId  Authenticated admin user ID; omit only
     *                                      when no audit trail is required.
     */
    public function reject(PoliticalCampaign $campaign, string $reason, ?int $adminId = null): void
    {
        $campaign->update([
            'approval_status'  => ApprovalStatus::Rejected,
            'status'           => CampaignStatus::Draft,
            'rejection_reason' => $reason,
        ]);

        if ($adminId !== null) {
            CampaignAuditLog::create([
                'campaign_id' => $campaign->id,
                'admin_id'    => $adminId,
                'action'      => 'rejected',
                'reason'      => $reason,
            ]);
        }

        $this->statusNotifier->notifyStatusChanged($campaign, 'rejected', $reason);
    }
}
