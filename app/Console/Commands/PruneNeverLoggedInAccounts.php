<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PruneNeverLoggedInAccounts extends Command
{
    protected $signature = 'users:prune-never-logged-in
                            {--days=30 : Only prune accounts older than this many days}
                            {--example-only : Only delete accounts with Faker seed emails (@example.com/net/org) — bypasses login/age checks}
                            {--include-seed-admins : When used with --example-only, also removes example-email accounts that hold the admin role (seed admins). Real admin@u9itus.com is always safe.}
                            {--dry-run : Show what would be deleted without deleting}
                            {--force : Skip confirmation prompt}';

    protected $description = 'Delete user accounts (and their voter/politician profiles) that have never logged in and are older than the grace period. Use --example-only to remove all Faker seed accounts. Safe exclusions: admins, accounts with earnings, accounts with campaigns.';

    /** Faker seed email domains — never belong to real users. */
    private const SEED_DOMAINS = ['example.com', 'example.net', 'example.org'];

    public function handle(): int
    {
        $dryRun           = (bool) $this->option('dry-run');
        $exampleOnly      = (bool) $this->option('example-only');
        $includeSeedAdmins = (bool) $this->option('include-seed-admins');

        if ($exampleOnly) {
            return $this->pruneExampleAccounts($dryRun, $includeSeedAdmins);
        }

        $days   = (int) $this->option('days');
        $cutoff = now()->subDays($days);

        $this->info("Finding accounts created before {$cutoff->toDateTimeString()} that have never logged in…");

        // "Never logged in" = remember_token is null AND no sessions row.
        // We also honour email_verified_at as evidence of at least one
        // interaction (email click) — verified accounts are NOT pruned.
        $query = User::query()
            ->whereNull('remember_token')
            ->whereNull('email_verified_at')
            ->where('created_at', '<', $cutoff)
            // Exclude admins — always safe-guard.
            ->whereDoesntHave('roles', fn ($q) => $q->where('name', 'admin'))
            ->where(fn ($q) => $q->whereNull('user_type')->orWhereNotIn('user_type', ['admin']))
            // Exclude users who have an active session.
            ->whereNotIn('id', DB::table('sessions')->whereNotNull('user_id')->pluck('user_id'))
            // Exclude voters who have ever earned, hold any balance, or have
            // earnings still inside the fraud-hold window.
            ->whereDoesntHave('voter', fn ($q) => $q
                ->where('total_earned', '>', 0)
                ->orWhere('wallet_balance', '>', 0)
                ->orWhere('pending_earnings', '>', 0)
                ->orWhere('total_views', '>', 0)
            )
            // Exclude politicians who have any campaigns OR any credit history
            // (purchased credits without ever creating a campaign).
            ->whereDoesntHave('politician', fn ($q) => $q
                ->whereHas('campaigns')
                ->orWhere('credit_balance', '>', 0)
                ->orWhereHas('credits')
            );

        $count = $query->count();

        if ($count === 0) {
            $this->info('No accounts matched the prune criteria. Nothing to delete.');
            return self::SUCCESS;
        }

        $this->warn("{$count} account(s) matched (created before {$cutoff->toDateString()}, never logged in, no earnings, no campaigns, not admin).");

        if ($dryRun) {
            $this->table(
                ['ID', 'Email', 'Role', 'Created At'],
                $query->with('roles')->get()->map(fn ($u) => [
                    $u->id,
                    $u->email,
                    $u->roles->pluck('name')->join(', ') ?: $u->user_type ?: '—',
                    $u->created_at->toDateTimeString(),
                ])->toArray()
            );
            $this->info('--dry-run: no changes made.');
            return self::SUCCESS;
        }

        if (
            ! $this->option('force') &&
            ! $this->confirm("Permanently delete {$count} account(s) and their associated voter/politician profiles?")
        ) {
            $this->info('Aborted.');
            return self::SUCCESS;
        }

        $deleted  = 0;
        $failures = 0;

        // Chunk to avoid memory pressure on large seed datasets.
        $query->chunkById(200, function ($users) use (&$deleted, &$failures) {
            foreach ($users as $user) {
                try {
                    DB::transaction(function () use ($user) {
                        // Related voter/politician profiles are deleted first
                        // to avoid FK constraint violations on tables that do
                        // not cascade-on-delete from users.
                        $user->voter?->delete();
                        $user->politician?->delete();
                        $user->delete();
                    });
                    $deleted++;
                } catch (\Throwable $e) {
                    $failures++;
                    Log::warning('users:prune-never-logged-in: failed to delete user', [
                        'user_id' => $user->id,
                        'email'   => $user->email,
                        'error'   => $e->getMessage(),
                    ]);
                    $this->warn("  Skipped user #{$user->id} ({$user->email}): {$e->getMessage()}");
                }
            }
        });

        $this->info("Deleted {$deleted} account(s)." . ($failures > 0 ? " {$failures} failed — check logs." : ''));

        Log::info('users:prune-never-logged-in completed', [
            'deleted'    => $deleted,
            'failures'   => $failures,
            'older_than' => $this->option('days') . ' days',
        ]);

        return $failures > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Delete all accounts whose email domain is a known Faker seed domain
     * (@example.com / @example.net / @example.org), regardless of login state.
     * Admins and accounts with any financial activity are always excluded.
     */
    private function pruneExampleAccounts(bool $dryRun, bool $includeSeedAdmins = false): int
    {
        $domainPatterns = array_map(fn ($d) => '%@' . $d, self::SEED_DOMAINS);

        $query = User::query()
            ->where(function ($q) use ($domainPatterns) {
                foreach ($domainPatterns as $pattern) {
                    $q->orWhere('email', 'like', $pattern);
                }
            });

        if (! $includeSeedAdmins) {
            // Default: protect all admin-role accounts.
            $query
                ->whereDoesntHave('roles', fn ($q) => $q->where('name', 'admin'))
                ->where(fn ($q) => $q->whereNull('user_type')->orWhereNotIn('user_type', ['admin']));
        } else {
            // --include-seed-admins: remove example-email admins too, but
            // never touch accounts whose email doesn't match a seed domain
            // (the real admin@u9itus.com is safe because it doesn't match).
            $this->warn('--include-seed-admins active: seed admin accounts will also be deleted.');
        }

        // Always exclude accounts with any financial activity.
        $query
            ->whereDoesntHave('voter', fn ($q) => $q
                ->where('total_earned', '>', 0)
                ->orWhere('wallet_balance', '>', 0)
                ->orWhere('pending_earnings', '>', 0)
                ->orWhere('total_views', '>', 0)
            )
            ->whereDoesntHave('politician', fn ($q) => $q
                ->whereHas('campaigns')
                ->orWhere('credit_balance', '>', 0)
                ->orWhereHas('credits')
            );

        $count = $query->count();

        $domains = implode(', ', self::SEED_DOMAINS);
        $this->info("Finding seed accounts with emails matching: {$domains}");

        if ($count === 0) {
            $this->info('No seed accounts found. Nothing to delete.');
            return self::SUCCESS;
        }

        $this->warn("{$count} seed account(s) found.");

        if ($dryRun) {
            $this->table(
                ['ID', 'Email', 'Role', 'Created At'],
                $query->with('roles')->get()->map(fn ($u) => [
                    $u->id,
                    $u->email,
                    $u->roles->pluck('name')->join(', ') ?: $u->user_type ?: '—',
                    $u->created_at->toDateTimeString(),
                ])->toArray()
            );
            $this->info('--dry-run: no changes made.');
            return self::SUCCESS;
        }

        if (
            ! $this->option('force') &&
            ! $this->confirm("Permanently delete {$count} seed account(s) and their voter/politician profiles?")
        ) {
            $this->info('Aborted.');
            return self::SUCCESS;
        }

        $deleted  = 0;
        $failures = 0;

        $query->chunkById(200, function ($users) use (&$deleted, &$failures) {
            foreach ($users as $user) {
                try {
                    DB::transaction(function () use ($user) {
                        $user->voter?->delete();
                        $user->politician?->delete();
                        $user->delete();
                    });
                    $deleted++;
                } catch (\Throwable $e) {
                    $failures++;
                    Log::warning('users:prune-never-logged-in --example-only: failed to delete user', [
                        'user_id' => $user->id,
                        'email'   => $user->email,
                        'error'   => $e->getMessage(),
                    ]);
                    $this->warn("  Skipped user #{$user->id} ({$user->email}): {$e->getMessage()}");
                }
            }
        });

        $this->info("Deleted {$deleted} seed account(s)." . ($failures > 0 ? " {$failures} failed — check logs." : ''));

        Log::info('users:prune-never-logged-in --example-only completed', [
            'deleted'  => $deleted,
            'failures' => $failures,
        ]);

        return $failures > 0 ? self::FAILURE : self::SUCCESS;
    }
}
