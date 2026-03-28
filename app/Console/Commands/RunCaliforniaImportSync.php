<?php

namespace App\Console\Commands;

use App\Mail\CaliforniaImportSyncFailedMail;
use App\Models\ImportRunLog;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class RunCaliforniaImportSync extends Command
{
    protected $signature = 'imports:sync-california
        {--source-url=https://unitedstates.github.io/congress-legislators/legislators-current.json : JSON feed URL}
        {--with-campaigns : Create preview campaigns during sync}
        {--dry-run : Parse and report only}';

    protected $description = 'Run California unclaimed politician import with run logging and failure alerts.';

    public function handle(): int
    {
        $sourceUrl = (string) $this->option('source-url');
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
            '--source-url' => $sourceUrl,
            '--with-campaigns' => $withCampaigns,
            '--dry-run' => $dryRun,
        ];

        try {
            $exitCode = Artisan::call('politicians:import-unclaimed-ca', $arguments);
            $output = trim(Artisan::output());

            if ($exitCode === self::SUCCESS) {
                $runLog->markSuccess($exitCode, $output, $this->extractCounts($output));

                $this->info('California sync completed successfully.');

                return self::SUCCESS;
            }

            $errorMessage = $this->extractErrorMessage($output);
            $runLog->markFailed($exitCode, $output, $errorMessage);
            $this->sendFailureAlert($runLog);

            $this->error('California sync failed. Alert email queued for admins.');

            return self::FAILURE;
        } catch (\Throwable $exception) {
            $output = trim(Artisan::output());
            $errorMessage = $exception->getMessage();

            $runLog->markFailed(-1, $output, $errorMessage);
            $this->sendFailureAlert($runLog);

            Log::error('California sync wrapper crashed', [
                'exception' => $errorMessage,
                'trace' => $exception->getTraceAsString(),
            ]);

            $this->error('California sync crashed. Alert email queued for admins.');

            return self::FAILURE;
        }
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
