<?php

namespace App\Console\Commands;

use App\Models\CampaignTransaction;
use App\Services\CampaignBillingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ResendPendingReceipts extends Command
{
    protected $signature = 'receipts:resend-pending
        {--days=7 : Check for receipts not sent in the last N days}
        {--dry-run : Show what would be sent without actually sending}';

    protected $description = 'Resend Stripe receipt emails for succeeded transactions not yet sent';

    public function __construct(
        protected CampaignBillingService $billingService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $daysBack = (int) $this->option('days');
        $isDryRun = $this->option('dry-run');

        // Find succeeded charges where receipt was never sent or is old
        $cutoffDate = now()->subDays($daysBack);

        $pending = CampaignTransaction::where('transaction_type', 'charge')
            ->where('status', 'succeeded')
            ->where(function ($q) use ($cutoffDate) {
                $q->whereNull('receipt_sent_at')
                  ->orWhere('receipt_sent_at', '<', $cutoffDate);
            })
            ->orderByDesc('created_at')
            ->get();

        if ($pending->isEmpty()) {
            $this->info('No pending receipts to send.');
            return 0;
        }

        $this->info("Found {$pending->count()} pending receipt(s).");

        if ($isDryRun) {
            $this->warn('DRY RUN MODE — No emails will be sent.');
        }

        $sent = 0;
        $failed = 0;

        foreach ($pending as $tx) {
            $politician = $tx->politician;
            if (!$politician || !$politician->user) {
                    $reason = !$politician ? 'politician deleted' : 'user not linked to politician';
                    $this->warn("  ⚠ {$tx->uuid} ({$reason}) — skipping");
                $failed++;
                continue;
            }

            if ($isDryRun) {
                $this->line("  ℹ {$tx->uuid} → {$politician->full_name} ({$tx->amount})");
                $sent++;
                continue;
            }

            $result = $this->billingService->sendCreditsPurchaseReceiptForTransaction($tx);
            if ($result) {
                $this->line("  ✓ {$tx->uuid} → {$politician->user->email}");
                $sent++;
            } else {
                $this->warn("  ✗ {$tx->uuid} → {$politician->full_name} (send failed)");
                $failed++;
            }
        }

        $this->info("\nSummary: {$sent} sent, {$failed} failed.");

        return $failed > 0 ? 1 : 0;
    }
}
