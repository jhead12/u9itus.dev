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
 * CampaignApproved
 *
 * Broadcast to the politician's private channel when an admin approves
 * one of their campaigns. The frontend Echo listener on
 * `private-politician.{userId}` triggers a toast notification and
 * updates the campaign status badge in real time.
 */
class CampaignApproved implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly PoliticalCampaign $campaign,
    ) {}

    /**
     * The channel the event should broadcast on.
     */
    public function broadcastOn(): Channel
    {
        return new PrivateChannel('politician.' . $this->campaign->politician->user_id);
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
