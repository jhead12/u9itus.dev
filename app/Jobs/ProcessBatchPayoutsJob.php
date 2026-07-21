<?php

namespace App\Jobs;

use App\Models\PayoutRun;
use App\Services\PoliticalPaymentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * ARCH-1: runs a batch payout run off the request cycle so live Stripe/PayPal/
 * CashApp calls don't block the web worker (which would time out mid-batch and
 * leave a half-dispatched run with zeroed counters).
 *
 * Safety:
 *  - tries = 1: never auto-retry. A dead worker is not re-run by the queue,
 *    eliminating double-payout races. Re-triggering is an explicit admin action.
 *  - PayoutAttempt.idempotency_key: even if an operator manually re-dispatches,
 *    voters with an existing submitted/paid attempt are skipped — no double pay.
 *  - The run's status machine (pending → running → completed|failed) makes
 *    partial runs visible and recoverable, and counters increment per voter.
 */
class ProcessBatchPayoutsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** A full batch can take a while — give the worker room. */
    public int $timeout = 600;

    /** Never retry a money job. Idempotency guards a manual re-dispatch instead. */
    public int $tries = 1;

    public function __construct(public PayoutRun $run)
    {
    }

    public function handle(PoliticalPaymentService $service): void
    {
        Log::info('ProcessBatchPayoutsJob started', ['payout_run_id' => $this->run->id]);

        $this->run->update(['status' => 'running', 'started_at' => now(), 'failed_at' => null]);

        try {
            $service->processBatchPayoutsForRun($this->run);
            $this->run->update(['status' => 'completed', 'completed_at' => now()]);
            Log::info('ProcessBatchPayoutsJob completed', [
                'payout_run_id' => $this->run->id,
                'processed'     => (int) $this->run->fresh()->processed_count,
                'skipped'       => (int) $this->run->fresh()->skipped_count,
                'total_paid'    => (float) $this->run->fresh()->total_paid,
            ]);
        } catch (\Throwable $e) {
            $this->run->update(['status' => 'failed', 'failed_at' => now()]);
            Log::error('ProcessBatchPayoutsJob failed', [
                'payout_run_id' => $this->run->id,
                'error'         => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Handle job failure (worker death / uncaught exception after retries exhausted).
     */
    public function failed(\Throwable $exception): void
    {
        // Mark the run failed if it wasn't already (covers worker-kill scenarios
        // where handle() never reached the catch block).
        $this->run->update(['status' => 'failed', 'failed_at' => now()]);

        logger()->error('ProcessBatchPayoutsJob failed', [
            'payout_run_id' => $this->run->id,
            'error' => $exception->getMessage(),
        ]);
    }
}