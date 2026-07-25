<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CandidateNewsRunLog extends Model
{
    protected $fillable = [
        'command_name',
        'status',
        'exit_code',
        'queued_count',
        'refreshed_count',
        'failed_count',
        'started_at',
        'finished_at',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'exit_code' => 'integer',
            'queued_count' => 'integer',
            'refreshed_count' => 'integer',
            'failed_count' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function markSuccess(int $exitCode, array $counts): void
    {
        $this->fill([
            'status' => 'success',
            'exit_code' => $exitCode,
            'finished_at' => now(),
            'error_message' => null,
            'refreshed_count' => (int) ($counts['refreshed'] ?? 0),
            'failed_count' => (int) ($counts['failed'] ?? 0),
        ]);

        $this->save();
    }

    public function markFailed(int $exitCode, ?string $errorMessage = null): void
    {
        $this->fill([
            'status' => 'failed',
            'exit_code' => $exitCode,
            'finished_at' => now(),
            'error_message' => $errorMessage,
        ]);

        $this->save();
    }
}
