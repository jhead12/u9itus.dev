<?php

namespace App\Console\Commands;

use App\Models\DeletedAccount;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PurgeDeletedAccounts extends Command
{
    protected $signature = 'deleted-accounts:purge
                            {--days= : Override the retention window in days (default: config(u9itus.deleted_account_retention_days))}
                            {--dry-run : Report what would be purged without deleting anything}
                            {--force : Skip the confirmation prompt}';

    protected $description = 'Permanently erase archived deleted_accounts records past the retention window (restore is no longer possible for purged records)';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?? config('u9itus.deleted_account_retention_days', 90));

        if ($days <= 0) {
            $this->error('--days must be a positive integer.');
            return self::FAILURE;
        }

        $cutoff = now()->subDays($days);
        $query = DeletedAccount::where('deleted_at', '<=', $cutoff);
        $count = $query->count();

        if ($count === 0) {
            $this->info("No deleted_accounts records older than {$days} days (cutoff: {$cutoff->toDateString()}).");
            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info("Would purge {$count} deleted_accounts record(s) older than {$days} days (cutoff: {$cutoff->toDateString()}).");
            return self::SUCCESS;
        }

        $this->warn("About to permanently purge {$count} deleted_accounts record(s) older than {$days} days (cutoff: {$cutoff->toDateString()}).");
        $this->line('This erases the archived PII snapshot for good — restore will no longer be possible for these accounts.');

        $isInteractive = $this->input->isInteractive();
        if (! $this->option('force') && $isInteractive && ! $this->confirm('Proceed?', false)) {
            $this->line('Cancelled.');
            return self::SUCCESS;
        }

        $deleted = $query->delete();

        Log::info('deleted-accounts:purge purged archived records', [
            'count'  => $deleted,
            'days'   => $days,
            'cutoff' => $cutoff->toDateTimeString(),
        ]);

        $this->info("Purged {$deleted} deleted_accounts record(s) older than {$days} days.");

        return self::SUCCESS;
    }
}
