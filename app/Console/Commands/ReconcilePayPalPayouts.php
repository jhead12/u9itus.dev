<?php

namespace App\Console\Commands;

use App\Enums\ViewPaymentStatus;
use App\Models\ViewSession;
use App\Services\PayPalPayoutReconciliationService;
use Illuminate\Console\Command;

class ReconcilePayPalPayouts extends Command
{
    protected $signature = 'payouts:reconcile-paypal';

    protected $description = 'Reconcile unresolved PayPal payouts against PayPal batch/item status.';

    public function __construct(private readonly PayPalPayoutReconciliationService $reconciliationService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $references = ViewSession::query()
            ->where('processor_executed', 'paypal')
            ->where('payment_status', ViewPaymentStatus::Pending->value)
            ->whereNotNull('processor_reference')
            ->distinct()
            ->pluck('processor_reference')
            ->filter()
            ->values();

        if ($references->isEmpty()) {
            $this->info('No unresolved PayPal payouts to reconcile.');
            return self::SUCCESS;
        }

        $totals = ['updated' => 0, 'paid' => 0, 'rejected' => 0, 'pending' => 0];
        foreach ($references as $reference) {
            $result = $this->reconciliationService->reconcileBatchByReference((string) $reference);
            $totals['updated'] += (int) ($result['updated'] ?? 0);
            $totals['paid'] += (int) ($result['paid'] ?? 0);
            $totals['rejected'] += (int) ($result['rejected'] ?? 0);
            $totals['pending'] += (int) ($result['pending'] ?? 0);
        }

        $this->info(sprintf(
            'PayPal reconciliation complete. Updated: %d, Paid: %d, Rejected: %d, Still pending: %d',
            $totals['updated'],
            $totals['paid'],
            $totals['rejected'],
            $totals['pending'],
        ));

        return self::SUCCESS;
    }
}
