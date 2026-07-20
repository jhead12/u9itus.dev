<?php

namespace App\Notifications;

use App\Models\CandidateNewsRunLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class CandidateNewsRefreshNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param CandidateNewsRunLog $runLog The run log entry (may be null-ish/empty for a missing-run alert)
     * @param string $eventType 'failure' or 'stale'
     */
    public function __construct(
        public ?CandidateNewsRunLog $runLog,
        public string $eventType = 'stale'
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toArray(object $notifiable): array
    {
        $title = match ($this->eventType) {
            'failure' => 'Candidate News Refresh Failed',
            'missing' => 'Candidate News Refresh Never Run',
            default => 'Candidate News Refresh Stale',
        };

        $message = match ($this->eventType) {
            'failure' => "Latest run failed: {$this->runLog?->error_message}",
            'missing' => 'No candidate news refresh run has ever been recorded.',
            default => 'No successful candidate news refresh run recently — the map overview panel may be serving stale data.',
        };

        return [
            'icon' => $this->eventType === 'failure' ? '✗' : '⚠',
            'title' => $title,
            'message' => $message,
            'event_type' => $this->eventType,
            'run_log_id' => $this->runLog?->id,
            'status' => $this->runLog?->status,
            'refreshed_count' => $this->runLog?->refreshed_count,
            'failed_count' => $this->runLog?->failed_count,
            'started_at' => $this->runLog?->started_at?->toIso8601String(),
        ];
    }

    public function toBroadcast(object $notifiable): array
    {
        return [
            'type' => 'candidate_news_refresh_notification',
            'event_type' => $this->eventType,
            'title' => match ($this->eventType) {
                'failure' => 'Candidate News Refresh Failed',
                'missing' => 'Candidate News Refresh Never Run',
                default => 'Candidate News Refresh Stale',
            },
        ];
    }
}
