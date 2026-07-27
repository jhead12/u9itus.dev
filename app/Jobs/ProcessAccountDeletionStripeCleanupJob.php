<?php

namespace App\Jobs;

use App\Models\DeletedAccount;
use App\Services\StripeConnectService;
use App\Services\StripePaymentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Off-request Stripe cleanup for a deleted account: detaches saved payment
 * methods, deletes the Stripe Customer, and closes a voter's Connect payout
 * account. Refunds already happened synchronously in UserDeletionService
 * (they need the live politician/citizen row); this job only touches Stripe
 * objects whose ids were snapshotted before those rows were deleted.
 *
 * Safety:
 *  - tries = 1: never auto-retry. Each step is applied at most once per
 *    dispatch; a re-run is an explicit admin action (re-dispatch the job).
 *  - Per-item try/catch: one failed Stripe call doesn't block the rest of
 *    the cleanup. Failures are collected and surfaced via stripe_cleanup_error
 *    so an admin can finish cleanup manually.
 */
class ProcessAccountDeletionStripeCleanupJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 300;

    public int $tries = 1;

    public function __construct(public DeletedAccount $deletedAccount)
    {
    }

    public function handle(StripePaymentService $stripePayments, StripeConnectService $stripeConnect): void
    {
        if ($this->deletedAccount->stripe_cleanup_status === 'completed') {
            return;
        }

        Log::info('ProcessAccountDeletionStripeCleanupJob started', ['deleted_account_id' => $this->deletedAccount->id]);

        $this->deletedAccount->update([
            'stripe_cleanup_status'     => 'processing',
            'stripe_cleanup_started_at' => now(),
        ]);

        $plan = $this->deletedAccount->stripe_cleanup_plan ?? [];
        $errors = [];

        $customerIds = [];
        foreach (($plan['payment_methods'] ?? []) as $paymentMethod) {
            $pmId = $paymentMethod['stripe_payment_method_id'] ?? null;
            $customerId = $paymentMethod['stripe_customer_id'] ?? null;

            if ($customerId) {
                $customerIds[$customerId] = true;
            }

            if (! $pmId) {
                continue;
            }

            try {
                $stripePayments->detachPaymentMethod($pmId);
            } catch (\Throwable $e) {
                $errors[] = "Detach payment method {$pmId}: {$e->getMessage()}";
                Log::error('ProcessAccountDeletionStripeCleanupJob: detach failed', [
                    'deleted_account_id' => $this->deletedAccount->id,
                    'payment_method_id'  => $pmId,
                    'error'              => $e->getMessage(),
                ]);
            }
        }

        foreach (array_keys($customerIds) as $customerId) {
            try {
                $stripePayments->deleteCustomer($customerId);
            } catch (\Throwable $e) {
                $errors[] = "Delete customer {$customerId}: {$e->getMessage()}";
                Log::error('ProcessAccountDeletionStripeCleanupJob: delete customer failed', [
                    'deleted_account_id' => $this->deletedAccount->id,
                    'customer_id'        => $customerId,
                    'error'              => $e->getMessage(),
                ]);
            }
        }

        foreach (($plan['connect_account_ids'] ?? []) as $connectAccountId) {
            try {
                $stripeConnect->closeAccount($connectAccountId);
            } catch (\Throwable $e) {
                $errors[] = "Close Connect account {$connectAccountId}: {$e->getMessage()}";
                Log::error('ProcessAccountDeletionStripeCleanupJob: close Connect account failed', [
                    'deleted_account_id' => $this->deletedAccount->id,
                    'connect_account_id' => $connectAccountId,
                    'error'              => $e->getMessage(),
                ]);
            }
        }

        $this->deletedAccount->update([
            'stripe_cleanup_status'       => empty($errors) ? 'completed' : 'partially_completed',
            'stripe_cleanup_completed_at' => now(),
            'stripe_cleanup_error'        => empty($errors) ? null : implode('; ', $errors),
        ]);

        Log::info('ProcessAccountDeletionStripeCleanupJob finished', [
            'deleted_account_id' => $this->deletedAccount->id,
            'status'             => $this->deletedAccount->stripe_cleanup_status,
            'error_count'        => count($errors),
        ]);
    }

    /**
     * Handle job failure (worker death / uncaught exception).
     */
    public function failed(\Throwable $exception): void
    {
        $this->deletedAccount->update([
            'stripe_cleanup_status' => 'failed',
            'stripe_cleanup_error'  => $exception->getMessage(),
        ]);

        Log::error('ProcessAccountDeletionStripeCleanupJob failed', [
            'deleted_account_id' => $this->deletedAccount->id,
            'error'              => $exception->getMessage(),
        ]);
    }
}
