<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\UserDeletionService;
use Illuminate\Console\Command;

class DeleteUserAccount extends Command
{
    protected $signature = 'users:delete
                            {user : Id or email of the account to delete}
                            {--admin= : Id or email of the admin performing the deletion (audit trail)}
                            {--reason= : Deletion reason, stored on the archived record}
                            {--force : Skip the confirmation prompt}';

    protected $description = 'Archive and delete a user account (politician/citizen credit refunds + Stripe cleanup queued automatically)';

    public function handle(UserDeletionService $service): int
    {
        $user = $this->resolveUser($this->argument('user'));
        if (! $user) {
            $this->error("No user found matching: {$this->argument('user')}");
            return self::FAILURE;
        }

        if ($user->user_type === 'admin') {
            $this->error('Admin accounts cannot be deleted through this command.');
            return self::FAILURE;
        }

        $adminInput = $this->option('admin') ?? $this->ask('Id or email of the admin performing this deletion');
        $admin = $this->resolveUser($adminInput);
        if (! $admin || $admin->user_type !== 'admin') {
            $this->error("No admin user found matching: {$adminInput}");
            return self::FAILURE;
        }

        if ($admin->id === $user->id) {
            $this->error('The acting admin cannot delete their own account.');
            return self::FAILURE;
        }

        $reason = $this->option('reason');

        $this->warn("About to permanently delete: {$user->email} (user_type: {$user->user_type}, id: {$user->id})");
        $this->line('This will refund any unused politician/citizen credit balance to Stripe,');
        $this->line('detach saved payment methods, and close a voter\'s Connect payout account.');

        if (! $this->option('force') && ! $this->confirm('Proceed?', false)) {
            $this->line('Cancelled.');
            return self::SUCCESS;
        }

        try {
            $record = $service->archiveAndDelete($user, $admin, $reason, null);
        } catch (\Throwable $e) {
            $this->error('Failed to delete account: ' . $e->getMessage());
            return self::FAILURE;
        }

        $this->info("Account for {$record->email} deleted and archived (deleted_accounts id: {$record->id}).");

        match ($record->stripe_cleanup_status) {
            'not_applicable' => $this->line('No Stripe cleanup was needed for this account.'),
            'pending' => $this->line('Stripe cleanup (card detach / customer delete / Connect account close) queued — run `php artisan queue:work` to process it.'),
            default => $this->line("Stripe cleanup status: {$record->stripe_cleanup_status}"),
        };

        return self::SUCCESS;
    }

    private function resolveUser(?string $identifier): ?User
    {
        if ($identifier === null || $identifier === '') {
            return null;
        }

        return ctype_digit($identifier)
            ? User::find((int) $identifier)
            : User::where('email', $identifier)->first();
    }
}
