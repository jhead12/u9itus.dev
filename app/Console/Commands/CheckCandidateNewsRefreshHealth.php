<?php

namespace App\Console\Commands;

use App\Models\CandidateNewsRunLog;
use App\Models\User;
use App\Notifications\CandidateNewsRefreshNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class CheckCandidateNewsRefreshHealth extends Command
{
    protected $signature = 'news:check-refresh-health
        {--max-age-hours=8 : Maximum allowed age of the latest successful run}';

    protected $description = 'Health check for the recurring candidate news refresh job (candidates:refresh-news).';

    public function handle(): int
    {
        $maxAgeHours = max(1, (int) $this->option('max-age-hours'));
        $exitCode = self::SUCCESS;

        $latestRun = CandidateNewsRunLog::query()
            ->where('command_name', 'candidates:refresh-news')
            ->latest('started_at')
            ->first();

        if ($latestRun === null) {
            $this->error('No candidate news refresh run logs found.');
            $this->notifyAdmins(null, 'missing');
            $exitCode = self::FAILURE;
        } elseif ($latestRun->status !== 'success') {
            $this->error(sprintf(
                'Latest candidate news refresh run failed (status: %s, started_at: %s).',
                $latestRun->status,
                optional($latestRun->started_at)?->toDateTimeString() ?? 'n/a'
            ));
            $this->notifyAdmins($latestRun, 'failure');
            $exitCode = self::FAILURE;
        } else {
            $ageHours = (float) $latestRun->started_at->diffInRealHours(now());

            if ($ageHours > $maxAgeHours) {
                $this->error(sprintf(
                    'Latest candidate news refresh success is stale: %.2f hours old (max allowed: %d hours).',
                    $ageHours,
                    $maxAgeHours
                ));
                $this->notifyAdmins($latestRun, 'stale');
                $exitCode = self::FAILURE;
            } else {
                $this->info(sprintf(
                    'Candidate news refresh healthy. Last success at %s (%.2f hours ago). refreshed=%d failed=%d',
                    $latestRun->started_at->toDateTimeString(),
                    $ageHours,
                    (int) $latestRun->refreshed_count,
                    (int) $latestRun->failed_count
                ));
            }
        }

        return $exitCode;
    }

    private function notifyAdmins(?CandidateNewsRunLog $runLog, string $eventType): void
    {
        $admins = User::query()->where('user_type', 'admin')->get();
        Notification::send($admins, new CandidateNewsRefreshNotification($runLog, $eventType));
    }
}
