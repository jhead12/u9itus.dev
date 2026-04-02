<?php

namespace App\Services;

use App\Mail\CampaignApprovedMail;
use App\Mail\CampaignReactivatedMail;
use App\Mail\CampaignRejectedMail;
use App\Models\PoliticalCampaign;
use App\Models\User;
use App\Notifications\CampaignStatusChangedNotification;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class CampaignStatusNotifier
{
    public function __construct(
        private readonly ReverbBroadcastService $broadcastService,
    ) {
    }

    public function notifyStatusChanged(PoliticalCampaign $campaign, string $status, ?string $reason = null): void
    {
        $politicianUser = $campaign->politician?->user;

        if (! $politicianUser instanceof User) {
            return;
        }

        $preferences = $politicianUser->notificationPreference()->firstOrCreate([])->refresh();

        if ($preferences->isEnabled('inapp', 'campaign_status')) {
            try {
                $politicianUser->notify(new CampaignStatusChangedNotification($campaign, $status, $reason));
            } catch (\Throwable $e) {
                Log::warning('Failed to send campaign in-app notification', [
                    'campaign_id' => $campaign->id,
                    'user_id' => $politicianUser->id,
                    'status' => $status,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($preferences->isEnabled('email', 'campaign_status')) {
            $mail = $this->resolveMailForStatus($campaign, $status, $reason);

            if ($mail !== null && ! empty($politicianUser->email)) {
                try {
                    Mail::to($politicianUser->email)->queue($mail);
                } catch (\Throwable $e) {
                    Log::warning('Failed to send campaign status email', [
                        'campaign_id' => $campaign->id,
                        'user_id' => $politicianUser->id,
                        'status' => $status,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        $this->broadcastStatus($campaign, $status, $reason);
    }

    private function resolveMailForStatus(PoliticalCampaign $campaign, string $status, ?string $reason): ?Mailable
    {
        return match ($status) {
            'approved' => new CampaignApprovedMail($campaign),
            'rejected' => new CampaignRejectedMail(
                $campaign,
                $reason ?: 'Does not meet content guidelines.'
            ),
            'reactivated' => new CampaignReactivatedMail($campaign),
            default => null,
        };
    }

    private function broadcastStatus(PoliticalCampaign $campaign, string $status, ?string $reason): void
    {
        match ($status) {
            'approved' => $this->broadcastService->campaignApproved($campaign),
            'rejected' => $this->broadcastService->campaignRejected(
                $campaign,
                $reason ?: 'Does not meet content guidelines.'
            ),
            'stopped' => $this->broadcastService->campaignStopped(
                $campaign,
                $reason ?: 'Stopped by administrator.'
            ),
            'reactivated' => $this->broadcastService->campaignReactivated($campaign),
            default => null,
        };
    }
}
