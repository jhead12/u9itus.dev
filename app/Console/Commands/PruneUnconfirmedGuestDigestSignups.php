<?php

namespace App\Console\Commands;

use App\Models\Voter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Deletes pending (user_id-null) guest digest-opt-in Voter rows created by
 * GuestDigestOptInController that were never confirmed within 14 days of
 * their confirmation email being sent. Mirrors PruneExpiredGuestVoters'
 * 14-day grace pattern. Cascades to their voter_favorite_boundaries rows via
 * the existing FK.
 */
class PruneUnconfirmedGuestDigestSignups extends Command
{
    protected $signature = 'guests:prune-unconfirmed-digest-signups {--dry-run : List rows that would be deleted without deleting them}';

    protected $description = 'Delete unconfirmed guest saved-places digest opt-ins older than 14 days.';

    public function handle(): int
    {
        $cutoff = now()->subDays(14);

        $pending = Voter::whereNull('user_id')
            ->where('digest_opt_in_pending', true)
            ->whereNull('digest_confirmed_at')
            ->where('digest_confirmation_sent_at', '<', $cutoff)
            ->get();

        if ($pending->isEmpty()) {
            $this->info('No unconfirmed guest digest signups to prune.');
            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info("Would delete {$pending->count()} unconfirmed guest digest signup(s):");
            foreach ($pending as $voter) {
                $this->line("  #{$voter->id} ({$voter->email}) — sent {$voter->digest_confirmation_sent_at->diffForHumans()}");
            }
            return self::SUCCESS;
        }

        $count = $pending->count();

        foreach ($pending as $voter) {
            $voter->delete();
        }

        Log::info('Pruned unconfirmed guest digest signups', ['count' => $count]);
        $this->info("Deleted {$count} unconfirmed guest digest signup(s).");

        return self::SUCCESS;
    }
}
