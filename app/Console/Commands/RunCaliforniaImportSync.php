<?php

namespace App\Console\Commands;

use App\Mail\CaliforniaImportSyncFailedMail;
use App\Models\ImportRunLog;
use App\Models\User;
use App\Notifications\CaliforniaImportNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

class RunCaliforniaImportSync extends Command
{
    protected $signature = 'imports:sync-california
        {--source-url=https://unitedstates.github.io/congress-legislators/legislators-current.json : JSON feed URL}
        {--state= : Two-letter state override (defaults to daily rotation)}
        {--rotation-date= : Date used for state rotation (YYYY-MM-DD, defaults to today in PT)}
        {--with-campaigns : Create preview campaigns during sync}
        {--dry-run : Parse and report only}';

    protected $description = 'Run daily rotating-state unclaimed politician import with run logging and failure alerts.';

    /**
     * @var array<int, string>
     */
    protected array $rotationStates = [
        'AL', 'AK', 'AZ', 'AR', 'CA', 'CO', 'CT', 'DE', 'FL', 'GA',
        'HI', 'ID', 'IL', 'IN', 'IA', 'KS', 'KY', 'LA', 'ME', 'MD',
        'MA', 'MI', 'MN', 'MS', 'MO', 'MT', 'NE', 'NV', 'NH', 'NJ',
        'NM', 'NY', 'NC', 'ND', 'OH', 'OK', 'OR', 'PA', 'RI', 'SC',
        'SD', 'TN', 'TX', 'UT', 'VT', 'VA', 'WA', 'WV', 'WI', 'WY',
    ];

    public function handle(): int
    {
        $sourceUrl = (string) $this->option('source-url');
        try {
            $targetState = $this->resolveTargetState(
                $this->option('state'),
                $this->option('rotation-date'),
            );
        } catch (\InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
        $withCampaigns = (bool) $this->option('with-campaigns');
        $dryRun = (bool) $this->option('dry-run');

        $runLog = ImportRunLog::create([
            'command_name' => 'politicians:import-unclaimed-ca',
            'source_url' => $sourceUrl,
            'with_campaigns' => $withCampaigns,
            'dry_run' => $dryRun,
            'status' => 'running',
            'started_at' => now(),
        ]);

        $arguments = [
            '--fetcher' => 'current',
            '--current-url' => $sourceUrl,
            '--state' => [$targetState],
            '--with-campaigns' => $withCampaigns,
            '--dry-run' => $dryRun,
        ];

        try {
            $exitCode = Artisan::call('politicians:import-unclaimed-us', $arguments);
            $output = trim(Artisan::output());
            $stateOutput = "[state={$targetState}]\n" . $output;

            if ($exitCode === self::SUCCESS) {
                $runLog->markSuccess($exitCode, $stateOutput, $this->extractCounts($output));
                $this->notifyAdmins($runLog, 'success');

                $this->info("State sync completed successfully for {$targetState}.");

                return self::SUCCESS;
            }

            $errorMessage = $this->extractErrorMessage($output);
            $runLog->markFailed($exitCode, $stateOutput, $errorMessage);
            $this->sendFailureAlert($runLog);
            $this->notifyAdmins($runLog, 'failure');

            $this->error("State sync failed for {$targetState}. Alert email queued for admins.");

            return self::FAILURE;
        } catch (\Throwable $exception) {
            $output = trim(Artisan::output());
            $stateOutput = "[state={$targetState}]\n" . $output;
            $errorMessage = $exception->getMessage();

            $runLog->markFailed(-1, $stateOutput, $errorMessage);
            $this->sendFailureAlert($runLog);
            $this->notifyAdmins($runLog, 'failure');

            Log::error('California sync wrapper crashed', [
                'exception' => $errorMessage,
                'trace' => $exception->getTraceAsString(),
            ]);

            $this->error("State sync crashed for {$targetState}. Alert email queued for admins.");

            return self::FAILURE;
        }
    }

    protected function resolveTargetState(mixed $stateOption, mixed $rotationDateOption): string
    {
        $manualState = strtoupper(trim((string) $stateOption));
        if ($manualState !== '') {
            if (! in_array($manualState, $this->rotationStates, true)) {
                throw new \InvalidArgumentException('Invalid --state option. Expected one of the 50 U.S. state codes.');
            }

            return $manualState;
        }

        $rotationDate = trim((string) $rotationDateOption);
        $date = $rotationDate !== ''
            ? Carbon::parse($rotationDate, 'America/Los_Angeles')
            : now('America/Los_Angeles');

        $index = ($date->dayOfYear - 1) % count($this->rotationStates);

        return $this->rotationStates[$index];
    }

    /**
     * @return array{created:int,updated:int,skipped:int,campaigns_created:int}
     */
    protected function extractCounts(string $output): array
    {
        $counts = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'campaigns_created' => 0,
        ];

        if (preg_match('/:\s*(\d+)\s+created,\s*(\d+)\s+updated,\s*(\d+)\s+skipped,\s*(\d+)\s+campaigns created\./i', $output, $matches) !== 1) {
            return $counts;
        }

        $counts['created'] = (int) $matches[1];
        $counts['updated'] = (int) $matches[2];
        $counts['skipped'] = (int) $matches[3];
        $counts['campaigns_created'] = (int) $matches[4];

        return $counts;
    }

    protected function extractErrorMessage(string $output): string
    {
        if ($output === '') {
            return 'Unknown import failure';
        }

        $lines = preg_split('/\R/', $output) ?: [];

        for ($index = count($lines) - 1; $index >= 0; $index--) {
            $line = trim((string) $lines[$index]);
            if ($line !== '') {
                return $line;
            }
        }

        return 'Unknown import failure';
    }

    protected function sendFailureAlert(ImportRunLog $runLog): void
    {
        $recipients = $this->adminRecipients();

        foreach ($recipients as $email) {
            Mail::to($email)->queue(new CaliforniaImportSyncFailedMail($runLog));
        }
    }

    protected function notifyAdmins(ImportRunLog $runLog, string $eventType): void
    {
        $admins = User::query()
            ->where('user_type', 'admin')
            ->get();

        Notification::send($admins, new CaliforniaImportNotification($runLog, $eventType));
    }

    /**
     * @return array<int,string>
     */
    protected function adminRecipients(): array
    {
        $admins = User::query()
            ->where('user_type', 'admin')
            ->whereNotNull('email')
            ->pluck('email')
            ->filter(fn ($email) => is_string($email) && $email !== '')
            ->unique()
            ->values()
            ->all();

        if ($admins !== []) {
            return $admins;
        }

        $fallback = (string) config('mail.from.address', '');

        return $fallback !== '' ? [$fallback] : [];
    }
}
