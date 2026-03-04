<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LowBalanceNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public float $currentBalance,
        public float $threshold = 50.00
    ) {
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Low Balance Alert')
            ->line("Your campaign credit balance is running low: $" . number_format($this->currentBalance, 2))
            ->line("Add more credits to keep your campaigns running.")
            ->action('Add Credits', route('politician.billing'));
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'current_balance' => $this->currentBalance,
            'threshold' => $this->threshold,
            'message' => "Your campaign credit balance is running low: $" . number_format($this->currentBalance, 2),
            'action_url' => route('politician.billing'),
            'icon' => 'exclamation-triangle',
            'type' => 'low_balance',
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
