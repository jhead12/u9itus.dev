<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * State-level status for the on-demand ballot-measure backfill. See
 * App\Jobs\BackfillStateElectionData and WebMcpController::backfillStatusFor().
 */
class ElectionDataBackfill extends Model
{
    public const STATUS_QUEUED = 'queued';

    public const STATUS_RUNNING = 'running';

    public const STATUS_READY = 'ready';

    public const STATUS_UNAVAILABLE = 'unavailable';

    public const STATUS_FAILED = 'failed';

    /** Re-attempt an `unavailable` state only after this long — feeds may fill in. */
    public const RETRY_UNAVAILABLE_AFTER_HOURS = 24;

    protected $fillable = [
        'state',
        'status',
        'measures_found',
        'elections_found',
        'attempts',
        'last_attempted_at',
        'last_error',
        'watch_emails',
    ];

    protected function casts(): array
    {
        return [
            'last_attempted_at' => 'datetime',
            'watch_emails' => 'array',
        ];
    }

    /** Add an email to the watch list (deduped, case-insensitive). */
    public function addWatcher(string $email): void
    {
        $email = strtolower(trim($email));
        $emails = collect($this->watch_emails ?? []);

        if ($emails->contains(fn ($w) => strtolower($w['email'] ?? '') === $email)) {
            return;
        }

        $this->watch_emails = $emails
            ->push(['email' => $email, 'requested_at' => now()->toIso8601String()])
            ->all();
    }

    /** @return list<string> */
    public function watcherEmails(): array
    {
        return collect($this->watch_emails ?? [])
            ->pluck('email')
            ->filter()
            ->values()
            ->all();
    }

    public function isReattemptable(): bool
    {
        if ($this->status === self::STATUS_RUNNING) {
            return false;
        }

        if ($this->status === self::STATUS_UNAVAILABLE) {
            return $this->last_attempted_at === null
                || $this->last_attempted_at->lt(now()->subHours(self::RETRY_UNAVAILABLE_AFTER_HOURS));
        }

        return $this->status !== self::STATUS_READY;
    }
}
