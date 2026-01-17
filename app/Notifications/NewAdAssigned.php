<?php

namespace App\Notifications;

use App\Models\AdAssignment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewAdAssigned extends Notification
{
    use Queueable;

    protected AdAssignment $assignment;

    /**
     * Create a new notification instance.
     */
    public function __construct(AdAssignment $assignment)
    {
        $this->assignment = $assignment;
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
        $campaign = $this->assignment->campaign;
        $expiresIn = $this->assignment->expires_at->diffInHours(now());
        
        return (new MailMessage)
            ->subject('New Ad Assignment - Watch & Earn!')
            ->greeting('Hello ' . $notifiable->first_name . '!')
            ->line('You have been assigned a new ad to watch!')
            ->line('**Campaign:** ' . $campaign->title)
            ->line('**Payment:** $' . number_format($this->assignment->payment_amount, 2))
            ->line('**Duration:** ' . $campaign->media_duration . ' seconds')
            ->line('**Deadline:** ' . $this->assignment->expires_at->format('M d, Y h:i A'))
            ->line('You have ' . $expiresIn . ' hours to complete this assignment.')
            ->action('Watch Ad Now', route('viewer.watch', $this->assignment))
            ->line('Thank you for being part of Dial4Dough!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'assignment_id' => $this->assignment->id,
            'campaign_title' => $this->assignment->campaign->title,
            'payment_amount' => $this->assignment->payment_amount,
            'expires_at' => $this->assignment->expires_at,
        ];
    }
}

