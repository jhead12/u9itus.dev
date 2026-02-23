<?php

namespace App\Events;

use App\Models\Voter;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * FraudFlagRaised
 *
 * Broadcast to the admin private monitor channel when the fraud prevention
 * system flags a voter. The admin dashboard updates the fraud queue counter
 * in real time and can display a sticky alert without polling.
 *
 * NOT broadcast to the voter to avoid tipping off bad actors.
 */
class FraudFlagRaised implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Voter $voter,
        public readonly float $fraudScore,
        public readonly string $reason,
    ) {}

    public function broadcastOn(): Channel
    {
        return new PrivateChannel('admin.monitor');
    }

    public function broadcastWith(): array
    {
        return [
            'voter_id'    => $this->voter->id,
            'voter_uuid'  => $this->voter->uuid,
            'voter_name'  => $this->voter->user?->name ?? 'Unknown',
            'fraud_score' => $this->fraudScore,
            'reason'      => $this->reason,
            'flagged_at'  => now()->toIso8601String(),
            'message'     => "Voter flagged for fraud (score: {$this->fraudScore}): {$this->reason}",
        ];
    }

    public function broadcastAs(): string
    {
        return 'fraud.flag.raised';
    }
}
