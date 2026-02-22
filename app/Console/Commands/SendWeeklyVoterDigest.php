<?php

namespace App\Console\Commands;

use App\Mail\PayoutProcessedMail;
use App\Models\User;
use App\Models\ViewSession;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Send each voter a weekly earnings digest email showing
 * how many views they completed and what they earned.
 *
 * Runs every Monday at 08:00 (configured in routes/console.php).
 * Only sends if the voter completed at least one view in the past 7 days.
 */
class SendWeeklyVoterDigest extends Command
{
    protected $signature   = 'notifications:voter-digest
                                {--dry-run : Print counts without sending emails}';

    protected $description = 'Send weekly earnings digest emails to voters who completed views in the last 7 days';

    public function handle(): int
    {
        $since    = now()->subDays(7);
        $dryRun   = $this->option('dry-run');

        $this->info('Building weekly voter digest (' . ($dryRun ? 'DRY RUN' : 'LIVE') . ')…');

        // Aggregate completed view sessions per voter in the past week
        $rows = ViewSession::query()
            ->where('status', 'completed')
            ->where('completed_at', '>=', $since)
            ->selectRaw('user_id, COUNT(*) as view_count, SUM(voter_payout_amount) as total_earned')
            ->groupBy('user_id')
            ->having('total_earned', '>', 0)
            ->get()
            ->keyBy('user_id');

        if ($rows->isEmpty()) {
            $this->info('No qualifying voters found. Nothing sent.');
            return self::SUCCESS;
        }

        $sent   = 0;
        $failed = 0;
        $period = $since->format('M j') . ' – ' . now()->format('M j, Y');

        foreach ($rows as $userId => $row) {
            $voter = User::find($userId);

            if (!$voter || empty($voter->email)) {
                continue;
            }

            if ($dryRun) {
                $this->line("  [DRY] {$voter->email} — {$row->view_count} views · \${$row->total_earned}");
                $sent++;
                continue;
            }

            try {
                Mail::to($voter->email)->queue(new PayoutProcessedMail(
                    user:          $voter,
                    amount:        (float) $row->total_earned,
                    viewCount:     (int) $row->view_count,
                    payoutMethod:  'Pending Payout',
                    periodLabel:   $period,
                ));

                $sent++;
            } catch (\Exception $e) {
                $failed++;
                Log::error('WeeklyVoterDigest: failed to queue email', [
                    'user_id' => $userId,
                    'error'   => $e->getMessage(),
                ]);
            }
        }

        $this->info("Done — {$sent} emails queued, {$failed} failed.");

        return self::SUCCESS;
    }
}
