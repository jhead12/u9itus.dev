<?php

namespace App\Notifications;

use App\Models\ViewSession;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PayoutProcessedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public float $amount,
        public string $payoutMethod,
        public ?string $transactionId = null
    ) {
    }

    /**
     * Get the notification's delivery channels.
     *
     * Broadcast is intentionally excluded — ReverbBroadcastService fires the
     * WebSocket event directly so we don't dispatch two separate broadcast jobs.
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Payout Processed')
            ->line("Your payout of $" . number_format($this->amount, 2) . " has been processed.")
            ->line("Payment method: {$this->payoutMethod}")
            ->action('View Earnings', route('voter.earnings'));
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'amount' => $this->amount,
            'payout_method' => $this->payoutMethod,
            'transaction_id' => $this->transactionId,
            'message' => "Your payout of $" . number_format($this->amount, 2) . " has been processed.",
            'action_url' => route('voter.earnings'),
            'icon' => 'currency-dollar',
            'type' => 'payout_processed',
        ];
    }

    /**
     * Get the broadcastable representation of the notification.
     */
    public function toBroadcast(object $notifiable): array
    {
        return $this->toArray($notifiable);
    }
}
