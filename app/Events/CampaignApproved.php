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
 * CampaignApproved
 *
 * Broadcast to a campaign owner's private channel when an admin approves
 * one of their campaigns. Works for any campaign type implementing
 * BroadcastableCampaign (political, citizen, ...) — the owner's channel
 * name is resolved polymorphically via $campaign->broadcastChannelName().
 * The frontend Echo listener on that channel triggers a toast notification
 * and updates the campaign status badge in real time.
 */
class CampaignApproved implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly BroadcastableCampaign $campaign,
    ) {}

    /**
     * The channel the event should broadcast on.
     */
    public function broadcastOn(): Channel
    {
        return new PrivateChannel($this->campaign->broadcastChannelName());
    }

    /**
     * The data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'campaign_id'    => $this->campaign->id,
            'campaign_uuid'  => $this->campaign->uuid,
            'title'          => $this->campaign->title,
            'status'         => 'approved',
            'approved_at'    => now()->toIso8601String(),
            'message'        => "Your campaign \"{$this->campaign->title}\" has been approved and is now live.",
        ];
    }

    public function broadcastAs(): string
    {
        return 'campaign.approved';
    }
}
