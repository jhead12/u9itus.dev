<?php

namespace App\Events;

use App\Models\PoliticalCampaign;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * CampaignRejected
 *
 * Broadcast to the politician's private channel when an admin rejects
 * one of their campaigns. Includes the rejection reason so the frontend
 * can display it in a notification without a page refresh.
 */
class CampaignRejected implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly PoliticalCampaign $campaign,
        public readonly string $reason,
    ) {}

    public function broadcastOn(): Channel
    {
        return new PrivateChannel('politician.' . $this->campaign->politician->user_id);
    }

    public function broadcastWith(): array
    {
        return [
            'campaign_id'   => $this->campaign->id,
            'campaign_uuid' => $this->campaign->uuid,
            'title'         => $this->campaign->title,
            'status'        => 'rejected',
            'reason'        => $this->reason,
            'rejected_at'   => now()->toIso8601String(),
            'message'       => "Your campaign \"{$this->campaign->title}\" was rejected: {$this->reason}",
        ];
    }

    public function broadcastAs(): string
    {
        return 'campaign.rejected';
    }
}
