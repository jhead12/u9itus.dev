<?php

namespace App\Events;

use App\Contracts\BroadcastableCampaign;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * CampaignRejected
 *
 * Broadcast to a campaign owner's private channel when an admin rejects
 * one of their campaigns. Works for any campaign type implementing
 * BroadcastableCampaign (political, citizen, ...). Includes the rejection
 * reason so the frontend can display it in a notification without a page
 * refresh.
 */
class CampaignRejected implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly BroadcastableCampaign $campaign,
        public readonly string $reason,
    ) {}

    public function broadcastOn(): Channel
    {
        return new PrivateChannel($this->campaign->broadcastChannelName());
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
