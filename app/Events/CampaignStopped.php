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
 * CampaignStopped
 *
 * Broadcast to the politician's private channel when an admin force-pauses
 * a live campaign (e.g. broken video, policy violation). Carries the
 * admin-supplied reason so it surfaces immediately in the politician's UI.
 */
class CampaignStopped implements ShouldBroadcastNow
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
