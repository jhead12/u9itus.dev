<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Ops alert for politicians:check-cleanup-health — the daily data-quality pipeline
 * (politicians:cleanup-workflow) went silent, a step's finding rate looks anomalous
 * against its trailing 14-day baseline, or the pipeline's own claimed queued_count
 * disagrees with what's actually sitting in the review queues.
 *
 * Delivered via mail too (not just database/broadcast) since a silent pipeline is
 * exactly the scenario where nobody is watching the in-app bell icon.
 */
class PoliticiansCleanupHealthNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  string  $eventType  missing_or_stale | finding_rate_zero | finding_rate_spike | self_consistency_mismatch
     * @param  array<string, mixed>  $details
     */
    public function __construct(
        public string $eventType,
        public string $summary,
        public array $details = [],
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $subject = match ($this->eventType) {
            'missing_or_stale' => 'Politicians Cleanup: pipeline went silent',
            'finding_rate_zero' => 'Politicians Cleanup: finding rate dropped to zero',
            'finding_rate_spike' => 'Politicians Cleanup: finding rate spiked',
            'self_consistency_mismatch' => 'Politicians Cleanup: queue count mismatch',
            default => 'Politicians Cleanup: health check alert',
        };

        $message = (new MailMessage)->subject($subject)->line($this->summary);

        foreach ($this->details as $key => $value) {
            $message->line(ucfirst(str_replace('_', ' ', (string) $key)).': '.$value);
        }

        return $message->action('View Data Quality Queue', route('admin.data-quality.index'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'icon' => '⚠',
            'title' => 'Politicians cleanup health alert',
            'message' => $this->summary,
            'event_type' => $this->eventType,
            'details' => $this->details,
            'action_url' => route('admin.data-quality.index'),
        ];
    }

    public function toBroadcast(object $notifiable): array
    {
        return [
            'type' => 'politicians_cleanup_health_notification',
            'event_type' => $this->eventType,
            'title' => 'Politicians cleanup health alert',
            'message' => $this->summary,
        ];
    }
}
