<?php

namespace App\Notifications;

use App\Models\ImportRunLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CaliforniaImportNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     *
     * @param ImportRunLog $importLog The import run log entry
     * @param string $eventType 'success', 'failure', or 'stale'
     */
    public function __construct(
        public ImportRunLog $importLog,
        public string $eventType = 'success'
    ) {
    }

    /**
     * Get the notification's delivery channels.
     *
     * Database: stores in notifications table (appears in bell)
     * Broadcast: sends WebSocket event in real-time
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * This is optional — emails will use the registered mail channel
     * if the admin has notifications:email preference enabled.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $subject = match ($this->eventType) {
            'success' => 'California Import Completed Successfully',
            'failure' => 'California Import Failed',
            'stale' => 'California Import Health Check: Stale Data',
            default => 'California Import Status',
        };

        $message = (new MailMessage)
            ->subject($subject);

        if ($this->eventType === 'success') {
            $message
                ->line('The daily California politician import completed successfully.')
                ->line("Created: {$this->importLog->created_count} | Updated: {$this->importLog->updated_count} | Skipped: {$this->importLog->skipped_count}")
                ->line("Campaigns created: {$this->importLog->campaigns_created_count}");
        } elseif ($this->eventType === 'failure') {
            $message
                ->line('The daily California politician import failed.')
                ->line("Exit code: {$this->importLog->exit_code}");

            if ($this->importLog->error_message) {
                $message->line("Error: {$this->importLog->error_message}");
            }
        } else { // stale
            $message
                ->line('The California import health check detected stale data.')
                ->line('No successful import has run in the past 30 hours.')
                ->line('Manual investigation or intervention may be required.');
        }

        return $message->action('View Import Dashboard', route('admin.imports'));
    }

    /**
     * Get the array representation of the notification.
     *
     * Stored in the notifications table for display in the bell UI.
     */
    public function toArray(object $notifiable): array
    {
        $icon = match ($this->eventType) {
            'success' => '✓',
            'failure' => '✗',
            'stale' => '⚠',
            default => 'ℹ',
        };

        $title = match ($this->eventType) {
            'success' => 'California Import Successful',
            'failure' => 'California Import Failed',
            'stale' => 'California Import Stale',
            default => 'California Import Update',
        };

        $message = match ($this->eventType) {
            'success' => "Import completed: {$this->importLog->created_count} created, {$this->importLog->updated_count} updated",
            'failure' => "Import failed with exit code {$this->importLog->exit_code}",
            'stale' => 'No import run in the past 30 hours',
            default => 'See import dashboard for details',
        };

        return [
            'icon' => $icon,
            'title' => $title,
            'message' => $message,
            'event_type' => $this->eventType,
            'import_log_id' => $this->importLog->id,
            'status' => $this->importLog->status,
            'created_count' => $this->importLog->created_count,
            'updated_count' => $this->importLog->updated_count,
            'skipped_count' => $this->importLog->skipped_count,
            'campaigns_created_count' => $this->importLog->campaigns_created_count,
            'exit_code' => $this->importLog->exit_code,
            'error_message' => $this->importLog->error_message,
            'started_at' => $this->importLog->started_at?->toIso8601String(),
            'finished_at' => $this->importLog->finished_at?->toIso8601String(),
            'action_url' => route('admin.imports'),
        ];
    }

    /**
     * Get the broadcastable representation of the notification.
     */
    public function toBroadcast(object $notifiable): array
    {
        return [
            'type' => 'california_import_notification',
            'event_type' => $this->eventType,
            'title' => match ($this->eventType) {
                'success' => 'California Import Successful',
                'failure' => 'California Import Failed',
                'stale' => 'California Import Stale',
                default => 'California Import Update',
            },
            'message' => match ($this->eventType) {
                'success' => "Import completed: {$this->importLog->created_count} created, {$this->importLog->updated_count} updated",
                'failure' => "Import failed: {$this->importLog->error_message}",
                'stale' => 'No import run in the past 30 hours',
                default => 'See import dashboard for details',
            },
        ];
    }
}
