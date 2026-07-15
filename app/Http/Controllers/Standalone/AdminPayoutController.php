<?php

namespace App\Http\Controllers\Standalone;

use App\Http\Controllers\Concerns\PaymentModeFilterable;
use App\Http\Controllers\Controller;
use App\Enums\ViewPaymentStatus;
use App\Models\AdminSecurityAuditLog;
use App\Models\CampaignTransaction;
use App\Models\PayoutRun;
use App\Models\PayoutRunSkippedItem;
use App\Models\ViewSession;
use App\Services\CampaignBillingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Admin money-out: voter payouts and politician credit refunds.
 *
 * Split out of AdminController. Covers the payouts dashboard, pending and
 * skipped-payout diagnostics, batch payout processing, one-off below-minimum
 * force-pays (audit-logged), and billing refunds of unused politician credits.
 * billingRefunds scopes charge transactions to the active Stripe payment mode
 * via the PaymentModeFilterable trait.
 */
class AdminPayoutController extends Controller
{
    use PaymentModeFilterable;

    /**
     * Show payouts management.
     */
    public function payouts()
    {
        $unpaidStatuses = [ViewPaymentStatus::Pending->value, ViewPaymentStatus::Approved->value];

        $stats = [
            'pending_amount' => ViewSession::where('status', 'completed')
                ->whereIn('payment_status', $unpaidStatuses)->sum('voter_payout_amount') ?? 0,
            'paid_amount'    => ViewSession::where('payment_status', ViewPaymentStatus::Paid->value)->sum('voter_payout_amount') ?? 0,
            'pending_count'  => ViewSession::where('status', 'completed')
                ->whereIn('payment_status', $unpaidStatuses)->count(),
        ];

        $paypalConfigured = filled((string) config('services.paypal.client_id'))
            && filled((string) config('services.paypal.client_secret'));
        $paypalSandbox = (bool) config('services.paypal.sandbox', true);
        $cashAppConfigured = filled((string) config('services.cashapp.api_key'))
            && filled((string) config('services.cashapp.merchant_id'))
            && filled((string) config('services.cashapp.base_url'));
        $cashAppBaseUrl = (string) config('services.cashapp.base_url', '');

        $latestRun = PayoutRun::query()->latest()->first();
        $skipBuckets = [
            'below_min' => 0,
            'missing_paypal_email' => 0,
            'processor_unavailable' => 0,
        ];

        if ($latestRun) {
            $bucketRows = PayoutRunSkippedItem::query()
                ->where('payout_run_id', $latestRun->id)
                ->whereIn('reason_bucket', array_keys($skipBuckets))
                ->selectRaw('reason_bucket, COUNT(*) as count')
                ->groupBy('reason_bucket')
                ->get();

            foreach ($bucketRows as $row) {
                $skipBuckets[(string) $row->reason_bucket] = (int) $row->count;
            }
        }

        return view('standalone.admin.payouts', compact(
            'stats',
            'paypalConfigured',
            'paypalSandbox',
            'cashAppConfigured',
            'cashAppBaseUrl',
            'latestRun',
            'skipBuckets'
        ));
    }

    /**
     * Show persisted skipped payouts diagnostics by reason bucket.
     */
    public function skippedPayouts(Request $request)
    {
        $runId = $request->integer('run_id');
        $reason = (string) $request->query('reason', '');

        $selectedRun = $runId
            ? PayoutRun::query()->find($runId)
            : PayoutRun::query()->latest()->first();

        $query = PayoutRunSkippedItem::query()
            ->with(['voter.user', 'viewSession'])
            ->latest();

        if ($selectedRun) {
            $query->where('payout_run_id', $selectedRun->id);
        }

        if ($reason !== '') {
            $query->where('reason_bucket', $reason);
        }

        $items = $query->paginate(30)->withQueryString();

        $bucketSummary = ['below_min' => 0, 'missing_paypal_email' => 0, 'processor_unavailable' => 0];
        if ($selectedRun) {
            $rows = PayoutRunSkippedItem::query()
                ->where('payout_run_id', $selectedRun->id)
                ->whereIn('reason_bucket', array_keys($bucketSummary))
                ->selectRaw('reason_bucket, COUNT(*) as count')
                ->groupBy('reason_bucket')
                ->get();

            foreach ($rows as $row) {
                $bucketSummary[(string) $row->reason_bucket] = (int) $row->count;
            }
        }

        $recentRuns = PayoutRun::query()->latest()->limit(20)->get();

        return view('standalone.admin.payouts-skipped', compact(
            'items',
            'selectedRun',
            'recentRuns',
            'bucketSummary',
            'reason'
        ));
    }

    /**
     * Show pending payouts.
     */
    public function pendingPayouts(Request $request)
    {
        $search = trim((string) $request->query('search', ''));

        $sessionsQuery = ViewSession::with(['voter.user', 'campaign'])
            ->where('status', 'completed')
            ->whereIn('payment_status', [ViewPaymentStatus::Pending->value, ViewPaymentStatus::Approved->value]);

        if ($search !== '') {
            $sessionsQuery->where(function ($query) use ($search) {
                $query->whereHas('campaign', function ($campaignQuery) use ($search) {
                    $campaignQuery->where('title', 'like', "%{$search}%");
                })->orWhereHas('voter', function ($voterQuery) use ($search) {
                    $voterQuery->where('email', 'like', "%{$search}%")
                        ->orWhere('full_name', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($userQuery) use ($search) {
                            $userQuery->where('email', 'like', "%{$search}%")
                                ->orWhere('name', 'like', "%{$search}%");
                        });
                })->orWhere('processor_reference', 'like', "%{$search}%")
                  ->orWhere('processor_selected', 'like', "%{$search}%");
            });
        }

        $sessions = $sessionsQuery
            ->latest()
            ->paginate(30)
            ->withQueryString();

        return view('standalone.admin.payouts-pending', compact('sessions', 'search'));
    }

    /**
     * Process batch payouts — moves approved earnings to voters via PayPal
     * (or credits the on-platform wallet for voters without a PayPal email).
     */
    public function processBatchPayouts(Request $request)
    {
        /** @var \App\Services\PoliticalPaymentService $paymentService */
        $paymentService = app(\App\Services\PoliticalPaymentService::class);

        try {
            $results = $paymentService->processBatchPayouts(
                triggeredByAdminId: (int) $request->user()->id,
                triggerSource: 'admin',
            );
        } catch (\Exception $e) {
            Log::error('Batch payout run failed: ' . $e->getMessage());
            return back()->withErrors(['error' => 'Payout run failed: ' . $e->getMessage()]);
        }

        return back()->with('success', sprintf(
            'Batch payouts complete — %d paid ($%.2f total), %d skipped.',
            $results['processed'],
            $results['total_paid'],
            $results['skipped'],
        ));
    }

    /**
     * Execute a one-off below-minimum payout from skipped diagnostics.
     */
    public function forcePayBelowMinimum(Request $request, PayoutRunSkippedItem $skippedItem)
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        /** @var \App\Models\User $admin */
        $admin = $request->user();

        if (! $admin->hasRole('admin')) {
            abort(403);
        }

        /** @var \App\Services\PoliticalPaymentService $paymentService */
        $paymentService = app(\App\Services\PoliticalPaymentService::class);

        try {
            $result = $paymentService->forcePayBelowMinimum(
                skippedItem: $skippedItem,
                adminId: (int) $admin->id,
                reason: (string) $validated['reason'],
            );

            AdminSecurityAuditLog::record(
                $admin,
                'admin.payout.force_below_minimum.success',
                [
                    'skipped_item_id' => $skippedItem->id,
                    'voter_id' => $skippedItem->voter_id,
                    'processor' => $result['processor'] ?? null,
                    'reference' => $result['reference'] ?? null,
                    'amount' => $result['amount'] ?? null,
                ],
                $request
            );
        } catch (\Throwable $e) {
            AdminSecurityAuditLog::record(
                $admin,
                'admin.payout.force_below_minimum.failed',
                [
                    'skipped_item_id' => $skippedItem->id,
                    'voter_id' => $skippedItem->voter_id,
                    'error' => $e->getMessage(),
                ],
                $request
            );

            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return back()->with('success', 'Exceptional payout request submitted successfully.');
    }

    /**
     * Show billing refunds management page.
     */
    public function billingRefunds(Request $request)
    {
        $activePaymentMode = $this->activePaymentMode();
        
        // Query all succeeded charge transactions
        $query = CampaignTransaction::where('transaction_type', 'charge')
            ->where('status', 'succeeded')
            ->with('politician.user')
            ->latest();

        // Apply payment mode filter
        $query = $this->applyPaymentModeFilter($query, $activePaymentMode);

        // Allow search by politician email or name
        if ($search = $request->get('search')) {
            $query->whereHas('politician', function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                  ->orWhereHas('user', fn($q2) => $q2->where('email', 'like', "%{$search}%"));
            });
        }

        $transactions = $query->paginate(20);

        return view('standalone.admin.billing-refunds', compact('transactions', 'activePaymentMode'));
    }

    /**
     * Refund only UNUSED credits for a succeeded politician purchase transaction.
     */
    public function refundUnusedCredits(Request $request, CampaignTransaction $transaction, CampaignBillingService $billingService)
    {
        $request->validate([
            'credits_amount' => ['nullable', 'numeric', 'gt:0'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $admin = $request->user();
        $requestedCredits = $request->filled('credits_amount')
            ? (float) $request->input('credits_amount')
            : null;
        $reason = $request->input('reason');

        try {
            $summary = $billingService->getUnusedRefundSummary($transaction);
            $refundTx = $billingService->refundUnusedCredits(
                $transaction,
                (int) $admin->id,
                $requestedCredits,
                $reason
            );

            AdminSecurityAuditLog::record(
                $admin,
                'admin.refund.unused_credits.success',
                [
                    'purchase_transaction_id' => $transaction->id,
                    'refund_transaction_id' => $refundTx->id,
                    'requested_credits' => $requestedCredits,
                    'max_refundable_before' => $summary['refundable_credits_now'] ?? null,
                ],
                $request
            );

            $refundedCredits = (float) ($refundTx->metadata['refunded_credits_amount'] ?? 0);
            return back()->with('success', sprintf(
                'Refund created successfully. Refunded %.2f unused credits.',
                $refundedCredits
            ));
        } catch (\Throwable $e) {
            AdminSecurityAuditLog::record(
                $admin,
                'admin.refund.unused_credits.failed',
                [
                    'purchase_transaction_id' => $transaction->id,
                    'requested_credits' => $requestedCredits,
                    'error' => $e->getMessage(),
                ],
                $request
            );

            return back()->withErrors(['refund' => $e->getMessage()]);
        }
    }
}
