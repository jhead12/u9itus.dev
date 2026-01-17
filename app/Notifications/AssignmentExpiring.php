<?php

namespace App\Notifications;

use App\Models\AdAssignment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AssignmentExpiring extends Notification
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
        $hoursLeft = $this->assignment->expires_at->diffInHours(now());
        
        return (new MailMessage)
            ->subject('Reminder: Your Ad Assignment Expires Soon!')
            ->greeting('Hello ' . $notifiable->first_name . ',')
            ->line('Your ad assignment is expiring soon!')
            ->line('**Campaign:** ' . $campaign->title)
            ->line('**Payment:** $' . number_format($this->assignment->payment_amount, 2))
            ->line('**Expires in:** ' . $hoursLeft . ' hours')
            ->action('Watch Ad Now', route('viewer.watch', $this->assignment))
            ->line('Don\'t miss out on your earnings!');
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
            'expires_at' => $this->assignment->expires_at,
        ];
    }
}

