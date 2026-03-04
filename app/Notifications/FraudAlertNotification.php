<?php

namespace App\Notifications;

use App\Models\Voter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FraudAlertNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public Voter $voter,
        public string $reason,
        public int $fraudScore
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
            ->subject('Fraud Alert: Voter Flagged')
            ->line("Voter {$this->voter->user->email} has been flagged for potential fraud.")
            ->line("Reason: {$this->reason}")
            ->line("Fraud Score: {$this->fraudScore}")
            ->action('Review Voter', route('admin.fraud'));
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return [
'voter_id' => $this->voter->id,
            'voter_email' => $this->voter->user->email,
            'reason' => $this->reason,
            'fraud_score' => $this->fraudScore,
            'message' => "Voter {$this->voter->user->email} flagged for fraud: {$this->reason}",
            'action_url' => route('admin.fraud'),
            'icon' => 'shield-exclamation',
            'type' => 'fraud_alert',
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
