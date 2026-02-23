<?php

namespace App\Events;

use App\Models\AdViewToken;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * AdTokenDelivered
 *
 * Broadcast to the voter's private channel when a new one-time ad token
 * is issued for them. The frontend Echo listener triggers a push-style
 * banner ("A new ad is ready — earn $0.25") without requiring a page reload
 * or polling. The token itself is included so the watch link can be
 * constructed client-side.
 *
 * Security note: The token is delivered over an already-authenticated
 * private channel — no unauthenticated exposure.
 */
class AdTokenDelivered implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly AdViewToken $token,
    ) {}

    public function broadcastOn(): Channel
    {
        return new PrivateChannel('voter.' . $this->token->voter->user_id);
    }

    public function broadcastWith(): array
    {
        $campaign = $this->token->campaign;

        return [
            'token'            => $this->token->token,
            'watch_url'        => route('voter.watch', ['token' => $this->token->token]),
            'campaign_id'      => $campaign->id,
            'campaign_title'   => $campaign->title,
            'payout_amount'    => config('u9itus.viewer_payout_per_view'),
            'expires_at'       => $this->token->expires_at->toIso8601String(),
            'message'          => 'A new political ad is available — watch it to earn $' . number_format(config('u9itus.viewer_payout_per_view'), 2),
        ];
    }

    public function broadcastAs(): string
    {
        return 'ad.token.delivered';
    }
}
