<?php

namespace App\Events;

use App\Models\ViewSession;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * ViewSessionCompleted
 *
 * Broadcast to the voter's private channel the moment a view session is
 * marked complete and the $0.25 payout is credited to their wallet.
 * The frontend Echo listener updates the earnings balance in the header
 * and shows a success toast in real time.
 *
 * Also broadcast on the admin notification channel so dashboards can
 * track view throughput without polling.
 */
class ViewSessionCompleted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly ViewSession $session,
    ) {}

    /**
     * Broadcast on the voter's private channel AND the admin monitor channel.
     *
     * @return array<Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('voter.' . $this->session->voter->user_id),
            new PrivateChannel('admin.monitor'),
        ];
    }

    public function broadcastWith(): array
    {
        $voter    = $this->session->voter;
        $campaign = $this->session->campaign;

        return [
            'session_id'        => $this->session->id,
            'session_uuid'      => $this->session->uuid,
            'campaign_title'    => $campaign->title,
            'payout_amount'     => $this->session->payout_amount,
            'new_balance'       => $voter->wallet_balance,
            'completed_at'      => $this->session->completed_at?->toIso8601String() ?? now()->toIso8601String(),
            'message'           => '$' . number_format($this->session->payout_amount, 2) . ' has been added to your wallet.',
        ];
    }

    public function broadcastAs(): string
    {
        return 'session.completed';
    }
}
