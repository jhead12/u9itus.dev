<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentReceived extends Notification
{
    use Queueable;

    protected float $amount;
    protected string $campaignTitle;

    /**
     * Create a new notification instance.
     */
    public function __construct(float $amount, string $campaignTitle)
    {
        $this->amount = $amount;
        $this->campaignTitle = $campaignTitle;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Payment Received - Dial4Dough')
            ->greeting('Great news, ' . $notifiable->first_name . '!')
            ->line('You have received a payment!')
            ->line('**Amount:** $' . number_format($this->amount, 2))
            ->line('**Campaign:** ' . $this->campaignTitle)
            ->action('View Dashboard', route('viewer.dashboard'))
            ->line('Keep watching more ads to earn more!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'amount' => $this->amount,
            'campaign_title' => $this->campaignTitle,
        ];
    }
}

