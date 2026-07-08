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
                            {--dry-run : Show what would be deleted without deleting}
                            {--force : Skip confirmation prompt}';

    protected $description = 'Delete user accounts (and their voter/politician profiles) that have never logged in and are older than the grace period. Safe exclusions: admins, accounts with earnings, accounts with campaigns.';

    public function handle(): int
    {
        $days   = (int) $this->option('days');
        $dryRun = (bool) $this->option('dry-run');
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
            // Exclude voters who have ever earned or hold a balance.
            ->whereDoesntHave('voter', fn ($q) => $q
                ->where('total_earned', '>', 0)
                ->orWhere('wallet_balance', '>', 0)
                ->orWhere('total_views', '>', 0)
            )
            // Exclude politicians who have any campaigns.
            ->whereDoesntHave('politician', fn ($q) => $q
                ->whereHas('campaigns')
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
}
