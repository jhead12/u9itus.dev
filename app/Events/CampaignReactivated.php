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
 * CampaignReactivated
 *
 * Broadcast to the politician's private channel when an admin reactivates
 * a previously stopped/paused campaign. Provides immediate UI feedback
 * without requiring a page refresh.
 */
class CampaignReactivated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly PoliticalCampaign $campaign,
    ) {}

    public function broadcastOn(): Channel
    {
        return new PrivateChannel('politician.' . $this->campaign->politician->user_id);
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
