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
 * CampaignReactivated
 *
 * Broadcast to a campaign owner's private channel when an admin
 * reactivates a previously stopped/paused campaign. Works for any
 * campaign type implementing BroadcastableCampaign (political, citizen,
 * ...). Provides immediate UI feedback without requiring a page refresh.
 */
class CampaignReactivated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly BroadcastableCampaign $campaign,
    ) {}

    public function broadcastOn(): Channel
    {
        return new PrivateChannel($this->campaign->broadcastChannelName());
    }

    public function broadcastWith(): array
    {
        return [
            'campaign_id'    => $this->campaign->id,
            'campaign_uuid'  => $this->campaign->uuid,
            'title'          => $this->campaign->title,
            'status'         => 'active',
            'reactivated_at' => now()->toIso8601String(),
            'message'        => "Your campaign \"{$this->campaign->title}\" has been reactivated and is now live again.",
        ];
    }

    public function broadcastAs(): string
    {
        return 'campaign.reactivated';
    }
}
