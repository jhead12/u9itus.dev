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
 * CampaignStopped
 *
 * Broadcast to a campaign owner's private channel when an admin
 * pauses/force-stops a running campaign (e.g. broken video, policy
 * violation). Works for any campaign type implementing
 * BroadcastableCampaign (political, citizen, ...). Carries the
 * admin-supplied reason so it surfaces immediately in the owner's UI.
 */
class CampaignStopped implements ShouldBroadcastNow
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
            'status'        => 'stopped',
            'reason'        => $this->reason,
            'stopped_at'    => now()->toIso8601String(),
            'message'       => "Your campaign \"{$this->campaign->title}\" has been paused by an admin: {$this->reason}",
        ];
    }

    public function broadcastAs(): string
    {
        return 'campaign.stopped';
    }
}
