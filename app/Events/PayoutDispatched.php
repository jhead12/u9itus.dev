<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * PayoutDispatched
 *
 * Broadcast to the admin monitor channel for every payout outcome (success
 * or skip) during a batch run. Allows the admin payout screen to show a
 * live transaction feed without page refresh.
 */
class PayoutDispatched implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $voterId,
        public readonly string $voterName,
        public readonly float $amount,
        public readonly string $processor,
        public readonly string $referenceId,
        public readonly int $runId,
        public readonly string $outcome,        // 'paid' | 'skipped'
        public readonly ?string $reasonBucket,  // null for paid; e.g. 'below_min' for skipped
    ) {}

    public function broadcastOn(): Channel
    {
        return new PrivateChannel('admin.monitor');
    }

    public function broadcastWith(): array
    {
        return [
            'voter_id'      => $this->voterId,
            'voter_name'    => $this->voterName,
            'amount'        => $this->amount,
            'processor'     => $this->processor,
            'reference_id'  => $this->referenceId,
            'run_id'        => $this->runId,
            'outcome'       => $this->outcome,
            'reason_bucket' => $this->reasonBucket,
            'dispatched_at' => now()->toIso8601String(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'payout.dispatched';
    }
}
