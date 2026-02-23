<?php

namespace App\Events;

use App\Models\PoliticalCampaign;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * CampaignLiveStarted  (Phase 12 WebRTC foundation)
 *
 * Broadcast on the campaign's presence channel when a live feed begins.
 *
 * The presence channel `presence-campaign.live.{uuid}` serves three roles:
 *   1. Notifies subscribed voters that the live feed has started
 *   2. Provides a real-time viewer-count sidebar (via joinedCount)
 *   3. Acts as the WebRTC signaling channel — Phase 12 will attach
 *      ICE candidate and SDP offer/answer events to this same channel
 *
 * By establishing this presence channel in Phase 11 the WebRTC layer
 * in Phase 12 can be bolted on without any channel-architecture changes.
 */
class CampaignLiveStarted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly PoliticalCampaign $campaign,
    ) {}

    public function broadcastOn(): Channel
    {
        // Presence channel — lets the server track who is currently watching
        return new PresenceChannel('campaign.live.' . $this->campaign->uuid);
    }

    public function broadcastWith(): array
    {
        $politician = $this->campaign->politician;

        return [
            'campaign_id'    => $this->campaign->id,
            'campaign_uuid'  => $this->campaign->uuid,
            'title'          => $this->campaign->title,
            'politician'     => [
                'name'  => $politician->user?->name ?? 'Unknown',
                'party' => $politician->party,
                'office'=> $politician->office,
            ],
            'started_at'     => now()->toIso8601String(),
            'message'        => "\"{$this->campaign->title}\" live feed has started.",
        ];
    }

    public function broadcastAs(): string
    {
        return 'campaign.live.started';
    }
}
