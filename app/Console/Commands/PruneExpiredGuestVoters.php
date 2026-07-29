<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Deletes guest-trial voter accounts (see ProvisionGuestVoterSession) whose
 * trial ended at least 14 days ago. The grace buffer past each guest's own
 * guest_expires_at gives a guest who converts right at the boundary room
 * to finish before cleanup deletes the row out from under them.
 *
 * `voters.user_id` is nullOnDelete (not cascade) — deleting the User alone
 * would orphan the Voter row instead of removing it. So the Voter is
 * deleted explicitly first, which does cascade to its favorites/notes
 * (existing FK cascadeOnDelete constraints keyed on voter_id).
 */
class PruneExpiredGuestVoters extends Command
{
    protected $signature = 'guests:prune-expired {--dry-run : List guests that would be deleted without deleting them}';

    protected $description = 'Delete guest-trial voter accounts whose trial expired more than 14 days ago.';

    public function handle(): int
    {
        $cutoff = now()->subDays(14);

        $guests = User::where('is_guest', true)
            ->whereNotNull('guest_expires_at')
            ->where('guest_expires_at', '<', $cutoff)
            ->get();

        if ($guests->isEmpty()) {
            $this->info('No expired guest accounts to prune.');
            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info("Would delete {$guests->count()} expired guest account(s):");
            foreach ($guests as $guest) {
                $this->line("  #{$guest->id} — expired {$guest->guest_expires_at->diffForHumans()}");
            }
            return self::SUCCESS;
        }

        $count = $guests->count();

        foreach ($guests as $guest) {
            $guest->voter?->delete();
            $guest->delete();
        }

        Log::info('Pruned expired guest voter accounts', ['count' => $count]);
        $this->info("Deleted {$count} expired guest account(s).");

        return self::SUCCESS;
    }
}
