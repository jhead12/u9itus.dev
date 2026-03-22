<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class RefundCompletedNotification extends Notification
{
    public function __construct(
        public readonly float $amount,
        public readonly float $refundedCredits,
        public readonly string $transactionId,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'amount' => $this->amount,
            'refunded_credits' => $this->refundedCredits,
            'transaction_id' => $this->transactionId,
            'message' => 'Refund completed: $' . number_format($this->amount, 2) . ' returned.',
            'type' => 'refund_processed',
            'icon' => 'arrow-uturn-left',
            'action_url' => route('politician.billing.invoices'),
        ];
    }
}
