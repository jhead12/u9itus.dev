<?php

namespace App\Console\Commands;

use App\Mail\LowBalanceAlertMail;
use App\Models\Politician;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Check all politicians' credit balances and send a low-balance
 * warning email to anyone whose balance has fallen below the
 * configured threshold (default 20% of the last purchase amount,
 * or ≤ $12 absolute minimum).
 *
 * Runs daily (configured in routes/console.php).
 */
class SendLowBalanceAlerts extends Command
{
    protected $signature   = 'notifications:low-balance-alerts
                                {--threshold=12.00 : Dollar amount below which to trigger the alert}
                                {--dry-run : Print matches without sending emails}';

    protected $description = 'Send low-balance warning emails to politicians with insufficient credits';

    public function handle(): int
    {
        $threshold = (float) $this->option('threshold');
        $dryRun    = $this->option('dry-run');

        $this->info("Low-balance check (threshold: \${$threshold}) " . ($dryRun ? '[DRY RUN]' : '') . '…');

        $politicians = Politician::with('user')
            ->whereNotNull('credit_balance')
            ->where('credit_balance', '>', 0)
            ->where('credit_balance', '<=', $threshold)
            ->get();

        if ($politicians->isEmpty()) {
            $this->info('No politicians below threshold. Nothing sent.');
            return self::SUCCESS;
        }

        $sent   = 0;
        $failed = 0;

        $revenuePerView = (float) config('u9itus.revenue_per_view', 0.60);

        foreach ($politicians as $politician) {
            $user = $politician->user;

            if (!$user || empty($user->email)) {
                continue;
            }

            $balance       = (float) $politician->credit_balance;
            $remainingViews = $revenuePerView > 0 ? (int) floor($balance / $revenuePerView) : 0;

            if ($dryRun) {
                $this->line("  [DRY] {$user->email} — balance: \${$balance} ≈ {$remainingViews} views");
                $sent++;
                continue;
            }

            try {
                Mail::to($user->email)->queue(new LowBalanceAlertMail(
                    user:           $user,
                    currentBalance: $balance,
                    remainingViews: $remainingViews,
                    campaignTitle:  '', // generic alert; specific campaign alerts fire from the ViewSession lifecycle
                ));

                $sent++;
            } catch (\Exception $e) {
                $failed++;
                Log::error('LowBalanceAlert: failed to queue email', [
                    'politician_id' => $politician->id,
                    'error'         => $e->getMessage(),
                ]);
            }
        }

        $this->info("Done — {$sent} emails queued, {$failed} failed.");

        return self::SUCCESS;
    }
}
