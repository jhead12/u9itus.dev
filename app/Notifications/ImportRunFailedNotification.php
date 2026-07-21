<?php

namespace App\Notifications;

use App\Models\ImportRunLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Generic "a scheduled import/sync run failed" alert for admins.
 *
 * Reusable across pipelines (Census demographics sync, politician imports,
 * etc.) so each new scheduled job gets an in-bell + email alert without
 * duplicating a pipeline-specific notification class. The pipeline is
 * identified by $label (e.g. "Census Demographics Sync") and the run details
 * come from the attached ImportRunLog.
 */
class ImportRunFailedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly ImportRunLog $runLog,
        public readonly string $label = 'Scheduled Sync',
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->error()
            ->subject("[U9itus] {$this->label} Failed")
            ->line("The {$this->label} run failed and may need attention.")
            ->line("Command: {$this->runLog->command_name}")
            ->line('Error: ' . ($this->runLog->error_message ?: 'See run log for details.'))
            ->line("Started: {$this->runLog->started_at?->toDateTimeString()}");
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'label'         => $this->label,
            'command_name'  => $this->runLog->command_name,
            'error_message' => $this->runLog->error_message,
            'started_at'    => $this->runLog->started_at?->toDateTimeString(),
            'run_log_id'    => $this->runLog->id,
        ];
    }
}