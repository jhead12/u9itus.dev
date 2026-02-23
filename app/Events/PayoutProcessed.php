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
 * PayoutProcessed
 *
 * Broadcast to the voter's private channel when a batch payout run
 * successfully transfers funds to them (PayPal/CashApp). The frontend
 * Echo listener replaces the "pending" badge with a "paid" confirmation
 * and shows the payout amount + reference ID.
 */
class PayoutProcessed implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Voter $voter,
        public readonly float $amount,
        public readonly string $payoutMethod,
        public readonly string $referenceId,
    ) {}

    public function broadcastOn(): Channel
    {
        return new PrivateChannel('voter.' . $this->voter->user_id);
    }

    public function broadcastWith(): array
    {
        return [
            'amount'        => $this->amount,
            'payout_method' => $this->payoutMethod,
            'reference_id'  => $this->referenceId,
            'processed_at'  => now()->toIso8601String(),
            'message'       => '$' . number_format($this->amount, 2) . ' has been sent to your ' . $this->payoutMethod . ' account.',
        ];
    }

    public function broadcastAs(): string
    {
        return 'payout.processed';
    }
}
