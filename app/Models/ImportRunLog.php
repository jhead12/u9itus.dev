<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportRunLog extends Model
{
    protected $fillable = [
        'command_name',
        'source_url',
        'with_campaigns',
        'dry_run',
        'status',
        'exit_code',
        'created_count',
        'updated_count',
        'skipped_count',
        'campaigns_created_count',
        'started_at',
        'finished_at',
        'output',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'with_campaigns' => 'boolean',
            'dry_run' => 'boolean',
            'exit_code' => 'integer',
            'created_count' => 'integer',
            'updated_count' => 'integer',
            'skipped_count' => 'integer',
            'campaigns_created_count' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function markSuccess(int $exitCode, string $output, array $counts): void
    {
        $this->fill([
            'status' => 'success',
            'exit_code' => $exitCode,
            'finished_at' => now(),
            'output' => $output,
            'error_message' => null,
            'created_count' => (int) ($counts['created'] ?? 0),
            'updated_count' => (int) ($counts['updated'] ?? 0),
            'skipped_count' => (int) ($counts['skipped'] ?? 0),
            'campaigns_created_count' => (int) ($counts['campaigns_created'] ?? 0),
        ]);

        $this->save();
    }

    public function markFailed(int $exitCode, string $output, ?string $errorMessage = null): void
    {
        $this->fill([
            'status' => 'failed',
            'exit_code' => $exitCode,
            'finished_at' => now(),
            'output' => $output,
            'error_message' => $errorMessage,
        ]);

        $this->save();
    }
}
