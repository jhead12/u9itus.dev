<?php

namespace App\Notifications;

use App\Models\PoliticalCampaign;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CampaignStatusChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public PoliticalCampaign $campaign,
        public string $status,
        public ?string $reason = null
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
        $message = (new MailMessage)
            ->subject("Campaign Status: {$this->getStatusLabel()}")
            ->line("Your campaign \"{$this->campaign->title}\" has been {$this->getStatusLabel()}.");

        if ($this->reason) {
            $message->line("Reason: {$this->reason}");
        }

        return $message->action('View Campaign', route('politician.campaigns.show', $this->campaign->id));
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'campaign_id' => $this->campaign->id,
            'campaign_title' => $this->campaign->title,
            'status' => $this->status,
            'reason' => $this->reason,
            'message' => "Your campaign \"{$this->campaign->title}\" has been {$this->getStatusLabel()}.",
            'action_url' => route('politician.campaigns.show', $this->campaign->id),
            'icon' => $this->getIcon(),
            'type' => 'campaign_' . $this->status,
        ];
    }

    /**
     * Get the broadcastable representation of the notification.
     */
    public function toBroadcast(object $notifiable): array
    {
        return $this->toArray($notifiable);
    }

    /**
     * Get the status label.
     */
    private function getStatusLabel(): string
    {
        return match ($this->status) {
            'approved' => 'approved',
            'rejected' => 'rejected',
            'stopped' => 'stopped',
            'reactivated' => 'reactivated',
            'paused' => 'paused',
            default => 'updated',
        };
    }

    /**
     * Get the icon for this notification type.
     */
    private function getIcon(): string
    {
        return match ($this->status) {
            'approved' => 'check-circle',
            'rejected' => 'x-circle',
            'stopped' => 'pause-circle',
            'reactivated' => 'play-circle',
            'paused' => 'pause-circle',
            default => 'bell',
        };
    }
}
