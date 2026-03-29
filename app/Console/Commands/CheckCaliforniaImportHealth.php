<?php

namespace App\Console\Commands;

use App\Models\ImportRunLog;
use App\Models\User;
use App\Notifications\CaliforniaImportNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class CheckCaliforniaImportHealth extends Command
{
    protected $signature = 'imports:check-california-health
        {--max-age-hours=30 : Maximum allowed age of the latest successful or failed run}';

    protected $description = 'Health check for recurring California profile import runs.';

    public function handle(): int
    {
        $maxAgeHours = max(1, (int) $this->option('max-age-hours'));
        $exitCode = self::SUCCESS;

        $latestRun = ImportRunLog::query()
            ->where('command_name', 'politicians:import-unclaimed-ca')
            ->where('dry_run', false)
            ->latest('started_at')
            ->first();

        if ($latestRun === null) {
            $this->error('No California import run logs found.');
            $exitCode = self::FAILURE;
        } elseif ($latestRun->status !== 'success') {
            $baseMessage = sprintf(
                'Latest California import run failed (status: %s, exit_code: %s, started_at: %s).',
                $latestRun->status,
                (string) ($latestRun->exit_code ?? 'n/a'),
                optional($latestRun->started_at)?->toDateTimeString() ?? 'n/a'
            );

            $this->error(! empty($latestRun->error_message)
                ? $baseMessage.' Error: '.$latestRun->error_message
                : $baseMessage
            );
            $exitCode = self::FAILURE;
        } else {
            $ageHours = (float) $latestRun->started_at->diffInRealHours(now());

            if ($ageHours > $maxAgeHours) {
                $this->error(sprintf(
                    'Latest California import success is stale: %.2f hours old (max allowed: %d hours).',
                    $ageHours,
                    $maxAgeHours
                ));
                // Notify admins of stale import data
                $admins = User::query()
                    ->where('user_type', 'admin')
                    ->get();
                Notification::send($admins, new CaliforniaImportNotification($latestRun, 'stale'));

                $exitCode = self::FAILURE;
            } else {
                $this->info(sprintf(
                    'California import healthy. Last success at %s (%.2f hours ago). created=%d updated=%d skipped=%d campaigns_created=%d',
                    $latestRun->started_at->toDateTimeString(),
                    $ageHours,
                    (int) $latestRun->created_count,
                    (int) $latestRun->updated_count,
                    (int) $latestRun->skipped_count,
                    (int) $latestRun->campaigns_created_count
                ));
            }
        }

        return $exitCode;
    }
}
