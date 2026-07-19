<?php

namespace App\Services;

use App\Mail\CreditsRefundedMail;
use App\Mail\CreditsPurchasedMail;
use App\Models\EmailTemplate;
use App\Models\CampaignTransaction;
use App\Models\Politician;
use App\Models\PoliticianCredit;
use App\Models\ReferralEarning;
use App\Notifications\RefundCompletedNotification;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class CampaignBillingService
{
    protected StripePaymentService $stripe;

    public function __construct(StripePaymentService $stripe)
    {
        $this->stripe = $stripe;
    }

    /**
     * Fallback detector that does not depend on Stripe service mocks.
     */
    private function configuredPaymentModeFromConfig(): string
    {
        $key = (string) config('services.stripe.secret', '');

        if (str_starts_with($key, 'sk_live_')) {
            return 'live';
        }

        if (str_starts_with($key, 'sk_test_')) {
            return 'test';
        }

        return 'unknown';
    }

    /**
     * Resolve payment mode from Stripe payload/object when livemode is present.
     */
    private function paymentModeFromStripePayload($payload, string $fallback): string
    {
        if (is_object($payload) && isset($payload->livemode)) {
            return $payload->livemode ? 'live' : 'test';
        }

        if (is_array($payload) && array_key_exists('livemode', $payload)) {
            return $payload['livemode'] ? 'live' : 'test';
        }

        return $fallback;
    }

    /**
     * Record a transaction in campaign_transactions table.
     */
    public function recordTransaction(array $data): CampaignTransaction
    {
        $tx = CampaignTransaction::create([
            'campaign_id' => $data['campaign_id'] ?? null,
            'politician_id' => $data['politician_id'] ?? null,
            'transaction_type' => $data['transaction_type'] ?? 'charge',
            'amount' => $data['amount'] ?? 0,
            'currency' => $data['currency'] ?? 'USD',
            'stripe_payment_intent_id' => $data['stripe_payment_intent_id'] ?? null,
            'stripe_charge_id' => $data['stripe_charge_id'] ?? null,
            'stripe_refund_id' => $data['stripe_refund_id'] ?? null,
            'status' => $data['status'] ?? 'pending',
            'description' => $data['description'] ?? null,
            'metadata' => $data['metadata'] ?? null,
        ]);

        Log::info('Recorded campaign transaction', ['id' => $tx->id, 'uuid' => $tx->uuid]);

        return $tx;
    }

    /**
     * Purchase credits for a politician (records credit transaction and campaign transaction).
     * This function does not charge Stripe directly; it expects the payment intent/charge to be completed separately.
     */
    public function addCredits(Politician $politician, float $amount, array $opts = []): PoliticianCredit
    {
        return DB::transaction(function () use ($politician, $amount, $opts): PoliticianCredit {
            return $this->addCreditsInTransaction($politician, $amount, $opts);
        });
    }

    /** Internal: must always be called inside an active DB transaction. */
    private function addCreditsInTransaction(Politician $politician, float $amount, array $opts): PoliticianCredit
    {
        // Lock the politician row so that concurrent calls (e.g. two views
        // completing at the same instant) cannot both read the same
        // balance_after and produce an incorrect running total.
        $politician = Politician::lockForUpdate()->findOrFail($politician->id);

        $relatedTransactionId = $opts['related_transaction_id'] ?? null;
        $transactionType = $opts['transaction_type'] ?? 'purchase';

        if ($relatedTransactionId !== null && in_array($transactionType, ['purchase', 'refund'], true)) {
            $existing = PoliticianCredit::where('politician_id', $politician->id)
                ->where('transaction_type', $transactionType)
                ->where('related_transaction_id', $relatedTransactionId)
                ->first();

            if ($existing) {
                return $existing;
            }
        }

        // Calculate new balance
        $current = PoliticianCredit::where('politician_id', $politician->id)->orderBy('created_at', 'desc')->value('balance_after') ?: 0.00;
        $newBalance = $current + $amount;

        $credit = PoliticianCredit::create([
            'politician_id' => $politician->id,
            'transaction_type' => $transactionType,
            'amount' => $amount,
            'balance_after' => $newBalance,
            'campaign_id' => $opts['campaign_id'] ?? null,
            'related_transaction_id' => $relatedTransactionId,
            'description' => $opts['description'] ?? 'Purchased credits',
            'metadata' => $opts['metadata'] ?? null,
        ]);

        // Keep politicians.credit_balance in sync with the ledger.
        $politician->syncCreditBalance();

        // Fire one-time procurement commission on the politician's first purchase.
        if ($transactionType === 'purchase') {
            $this->triggerProcurementCommission($politician, $amount);
        }

        Log::info('Added credits for politician', ['politician_id' => $politician->id, 'amount' => $amount, 'balance' => $newBalance]);

        return $credit;
    }

    /**
     * Award a one-time procurement commission to whoever referred this politician
     * (either a voter or another politician), triggered on the politician's first
     * credit purchase.
     *
     * Commission = procurement_commission_percent (10%) of the purchase amount.
     * Fires at most once per politician (guarded by existing outbound log row).
     *
     * The actual payout is handled by Early-bank.com; U9itus only notifies EB so
     * the referrer's EB wallet can be credited. The inbound payout.commission
     * event will later reconcile the actual settled amount in earlybank_earnings.
     */
    private function triggerProcurementCommission(Politician $politician, float $purchaseAmount): void
    {
        try {
            app(EarlyBankWebhookService::class)->notifyPoliticianPurchased($politician, $purchaseAmount);
        } catch (\Throwable $e) {
            // Fire-and-forget: EB notification failures must not break credit purchase.
            Log::warning('CampaignBillingService: politician.purchased notification failed', [
                'politician_id' => $politician->id,
                'amount'        => $purchaseAmount,
                'error'         => $e->getMessage(),
            ]);
        }
    }

    /**
     * Create a purchase PaymentIntent for politician credit purchase and record a pending transaction.
     *
     * Stripe charges 2.5% per transaction. To ensure the platform always receives
     * the full credit value the politician requested, we gross-up the charge:
     *   gross_charge = requested_credits / (1 - stripe_fee_percent / 100)
     *
     * The gross_charge is what Stripe processes. The original `$amount` (credits_amount)
     * is stored in the transaction metadata and applied to the politician's balance
     * when the payment is finalised.
     *
     * Returns array with keys:
     *   payment_intent_id, client_secret, gross_amount, credits_amount, stripe_fee, stripe_fee_percent
     */
    public function createPurchaseIntent(Politician $politician, float $amount, array $opts = []): array
    {
        $feePercent   = (float) config('u9itus.stripe_fee_percent', 2.5);

        // Perform gross-up in integer cents to avoid floating-point drift.
        $amountCents  = \App\Support\Money::toCents($amount);
        $grossCents   = \App\Support\Money::grossUp($amountCents, $feePercent);
        $feeCents     = $grossCents - $amountCents;
        $grossAmount  = (float) \App\Support\Money::fromCents($grossCents);
        $stripeFee    = (float) \App\Support\Money::fromCents($feeCents);
        $configuredMode = $this->stripe->configuredMode();

        $result = [
            'payment_intent_id'  => null,
            'client_secret'      => null,
            'gross_amount'       => $grossAmount,
            'credits_amount'     => $amount,
            'stripe_fee'         => $stripeFee,
            'stripe_fee_percent' => $feePercent,
        ];

        try {
            if ($this->stripe) {
                // Auto-create the Stripe Customer if one doesn't exist yet.
                $customerId = $this->stripe->ensureCustomer($politician);

                // Charge the gross amount so the platform receives the full credits_amount
                // after Stripe deducts its 2.5% processing fee.
                $pi = $this->stripe->createPaymentIntent(
                    $grossAmount,
                    'usd',
                    [
                        'politician_id'   => $politician->id,
                        'politician_uuid' => $politician->uuid ?? null,
                    ],
                    $customerId,
                    $opts['payment_method_id'] ?? null,
                );

                $piId         = $pi->id ?? null;
                $clientSecret = $pi->client_secret ?? null;
                $paymentMode  = $this->stripe->modeFromStripeObject($pi, $configuredMode);
                $isLiveMode   = $paymentMode === 'live';

                // Record pending transaction — store credits_amount and fee so
                // finalizePaymentIntent() knows exactly how much to credit.
                $this->recordTransaction([
                    'campaign_id'              => null,
                    'politician_id'            => $politician->id,
                    'transaction_type'         => 'charge',
                    'amount'                   => $grossAmount,
                    'currency'                 => 'USD',
                    'stripe_payment_intent_id' => $piId,
                    'status'                   => 'pending',
                    'description'              => 'Credit purchase (incl. 2.5% Stripe processing fee)',
                    'metadata'                 => [
                        'client_secret'      => $clientSecret,
                        'credits_amount'     => $amount,
                        'stripe_fee'         => $stripeFee,
                        'stripe_fee_percent' => $feePercent,
                        'payment_mode'       => $paymentMode,
                        'stripe_livemode'    => $isLiveMode,
                    ],
                ]);

                $result['payment_intent_id'] = $piId;
                $result['client_secret']     = $clientSecret;
                $result['payment_mode']      = $paymentMode;
            }
        } catch (\Exception $e) {
            Log::error('createPurchaseIntent failed: ' . $e->getMessage());
        }

        return $result;
    }

    /**
     * Get refundability summary for an already-succeeded purchase transaction.
     *
     * Refund cap is constrained by BOTH:
     *  1) remaining credits from this original purchase (purchased - already refunded)
     *  2) politician's currently available on-platform balance (unused credits)
     */
    public function getUnusedRefundSummary(CampaignTransaction $purchaseTx): array
    {
        if ($purchaseTx->transaction_type !== 'charge' || $purchaseTx->status !== 'succeeded') {
            throw new \InvalidArgumentException('Only succeeded charge transactions are refundable.');
        }

        if (!$purchaseTx->politician_id) {
            throw new \InvalidArgumentException('Purchase transaction has no associated politician.');
        }

        $creditsPurchasedRaw = $purchaseTx->metadata['credits_amount'] ?? $purchaseTx->amount;
        $creditsPurchasedCents = Money::toCents(is_string($creditsPurchasedRaw)
            ? $creditsPurchasedRaw
            : number_format((float) $creditsPurchasedRaw, 2, '.', ''));

        if ($creditsPurchasedCents <= 0) {
            throw new \InvalidArgumentException('Purchase transaction has invalid credits amount.');
        }

        $currentBalanceRaw = PoliticianCredit::where('politician_id', $purchaseTx->politician_id)
            ->orderByDesc('created_at')
            ->value('balance_after') ?? '0.00';
        $currentBalanceCents = Money::toCents((string) $currentBalanceRaw);

        $refundRows = CampaignTransaction::query()
            ->where('transaction_type', 'refund')
            ->whereIn('status', ['pending', 'succeeded'])
            ->where('metadata->original_transaction_id', (int) $purchaseTx->id)
            ->get(['amount', 'metadata']);

        $alreadyRefundedCreditsCents = 0;
        $alreadyRefundedGrossCents = 0;
        foreach ($refundRows as $row) {
            $alreadyRefundedCreditsRaw = $row->metadata['refunded_credits_amount'] ?? null;
            if ($alreadyRefundedCreditsRaw !== null) {
                $normalizedRefundedCredits = is_string($alreadyRefundedCreditsRaw)
                    ? $alreadyRefundedCreditsRaw
                    : number_format((float) $alreadyRefundedCreditsRaw, 2, '.', '');
                $alreadyRefundedCreditsCents += Money::toCents($normalizedRefundedCredits);
            }
            $alreadyRefundedGrossCents += Money::toCents((string) $row->amount);
        }

        $remainingByPurchaseCents = max(0, $creditsPurchasedCents - $alreadyRefundedCreditsCents);
        $refundableCreditsNowCents = max(0, min($remainingByPurchaseCents, max(0, $currentBalanceCents)));

        return [
            'credits_purchased' => (float) Money::fromCents($creditsPurchasedCents),
            'current_balance' => (float) Money::fromCents($currentBalanceCents),
            'already_refunded_credits' => (float) Money::fromCents($alreadyRefundedCreditsCents),
            'already_refunded_gross' => (float) Money::fromCents($alreadyRefundedGrossCents),
            'remaining_by_purchase' => (float) Money::fromCents($remainingByPurchaseCents),
            'refundable_credits_now' => (float) Money::fromCents($refundableCreditsNowCents),
        ];
    }

    /**
     * Process admin refund for unused credits tied to a politician purchase.
     */
    public function refundUnusedCredits(
        CampaignTransaction $purchaseTx,
        int $adminId,
        ?float $requestedCredits = null,
        ?string $reason = null
    ): CampaignTransaction {
        if (empty($purchaseTx->stripe_payment_intent_id)) {
            throw new \InvalidArgumentException('Purchase transaction is missing Stripe payment intent id.');
        }

        $summary = $this->getUnusedRefundSummary($purchaseTx);
        $maxRefundableCreditsCents = Money::toCents(number_format((float) $summary['refundable_credits_now'], 2, '.', ''));

        if ($maxRefundableCreditsCents <= 0) {
            throw new \LogicException('No unused credits are available to refund.');
        }

        $creditsToRefundCents = $requestedCredits === null
            ? $maxRefundableCreditsCents
            : Money::toCents(number_format((float) $requestedCredits, 2, '.', ''));

        if ($creditsToRefundCents <= 0) {
            throw new \InvalidArgumentException('Refund credits must be greater than zero.');
        }

        if ($creditsToRefundCents > $maxRefundableCreditsCents) {
            throw new \InvalidArgumentException('Requested refund exceeds unused refundable credits.');
        }

        $purchaseGrossCents = Money::toCents((string) $purchaseTx->amount);
        $creditsPurchasedCents = Money::toCents(number_format((float) $summary['credits_purchased'], 2, '.', ''));
        $alreadyRefundedGrossCents = Money::toCents(number_format((float) $summary['already_refunded_gross'], 2, '.', ''));
        $remainingGrossCents = max(0, $purchaseGrossCents - $alreadyRefundedGrossCents);

        $grossToRefundCents = $creditsPurchasedCents > 0
            ? (int) bcadd(
                bcdiv(
                    bcmul((string) $creditsToRefundCents, (string) $purchaseGrossCents, 10),
                    (string) $creditsPurchasedCents,
                    10
                ),
                '0.5',
                0
            )
            : 0;
        $grossToRefundCents = min($grossToRefundCents, $remainingGrossCents);

        if ($grossToRefundCents <= 0) {
            throw new \LogicException('Unable to compute refundable gross amount.');
        }

        $creditsToRefund = (float) Money::fromCents($creditsToRefundCents);
        $grossToRefund = (float) Money::fromCents($grossToRefundCents);

        $paymentMode = $purchaseTx->metadata['payment_mode'] ?? $this->stripe->configuredMode();
        $stripeRefund = $this->stripe->createRefundForPaymentIntent(
            $purchaseTx->stripe_payment_intent_id,
            $grossToRefund,
            [
                'source' => 'admin_unused_credits_refund',
                'original_transaction_id' => (string) $purchaseTx->id,
                'admin_id' => (string) $adminId,
            ]
        );

        $refundStatus = (string) ($stripeRefund->status ?? 'pending');
        if (in_array($refundStatus, ['failed', 'canceled'], true)) {
            throw new \LogicException('Stripe refund failed: ' . $refundStatus);
        }

        $refundTx = DB::transaction(function () use (
            $purchaseTx,
            $adminId,
            $reason,
            $creditsToRefund,
            $grossToRefund,
            $paymentMode,
            $stripeRefund,
            $refundStatus
        ): CampaignTransaction {
            $refundTx = $this->recordTransaction([
                'campaign_id' => null,
                'politician_id' => $purchaseTx->politician_id,
                'transaction_type' => 'refund',
                'amount' => $grossToRefund,
                'currency' => $purchaseTx->currency ?: 'USD',
                // Refund rows should not reuse the purchase PI because campaign_transactions
                // enforces PI uniqueness for charge idempotency.
                'stripe_payment_intent_id' => null,
                'stripe_refund_id' => $stripeRefund->id ?? null,
                'status' => $refundStatus === 'succeeded' ? 'succeeded' : 'pending',
                'description' => 'Admin refund for unused credits',
                'metadata' => [
                    'original_transaction_id' => $purchaseTx->id,
                    'original_payment_intent_id' => $purchaseTx->stripe_payment_intent_id,
                    'refunded_credits_amount' => $creditsToRefund,
                    'refunded_gross_amount' => $grossToRefund,
                    'reason' => $reason,
                    'admin_id' => $adminId,
                    'payment_mode' => $paymentMode,
                    'stripe_livemode' => $paymentMode === 'live',
                ],
            ]);

            // Deduct refunded credits from on-platform wallet balance.
            $politician = Politician::findOrFail((int) $purchaseTx->politician_id);
            $this->addCredits($politician, -$creditsToRefund, [
                'transaction_type' => 'refund',
                'related_transaction_id' => $refundTx->id,
                'description' => 'Admin refund of unused credits',
                'metadata' => [
                    'original_transaction_id' => $purchaseTx->id,
                    'stripe_refund_id' => $stripeRefund->id ?? null,
                    'refunded_credits_amount' => $creditsToRefund,
                    'reason' => $reason,
                    'admin_id' => $adminId,
                    'payment_mode' => $paymentMode,
                    'stripe_livemode' => $paymentMode === 'live',
                ],
            ]);

            return $refundTx;
        });

        // Send refund receipt and in-app notification only when Stripe confirms success.
        if ($refundTx->status === 'succeeded') {
            $this->sendCreditsRefundReceiptForTransaction($refundTx);

            $politician = Politician::with('user')->find($refundTx->politician_id);
            if ($politician?->user) {
                $politician->user->notify(new RefundCompletedNotification(
                    amount: (float) $refundTx->amount,
                    refundedCredits: (float) ($refundTx->metadata['refunded_credits_amount'] ?? 0),
                    transactionId: (string) ($refundTx->uuid ?? $refundTx->id),
                ));
            }
        }

        return $refundTx;
    }

    /**
     * Finalize a PaymentIntent: mark transaction succeeded/failed and credit politician.
     * Accepts either a Stripe Event object/array or null.
     */
    public function finalizePaymentIntent(string $paymentIntentId, $event = null): ?CampaignTransaction
    {
        $tx = CampaignTransaction::where('stripe_payment_intent_id', $paymentIntentId)->first();
        if (! $tx) {
            Log::warning('No campaign transaction found for PaymentIntent: ' . $paymentIntentId);
            return null;
        }

        // If already finalized, return early
        if (in_array($tx->status, ['succeeded', 'failed', 'refunded'])) {
            Log::info('Transaction already finalized', ['tx' => $tx->id, 'status' => $tx->status]);
            return $tx;
        }

        $status = 'succeeded';
        $chargeId = null;
        $amount = (float) $tx->amount;
        $resolvedPaymentMode = $tx->metadata['payment_mode'] ?? $this->configuredPaymentModeFromConfig();
        $resolvedLiveMode = isset($tx->metadata['stripe_livemode'])
            ? (bool) $tx->metadata['stripe_livemode']
            : $resolvedPaymentMode === 'live';

        // Try to extract details from Stripe event if available
        if ($event) {
            try {
                if (is_object($event)) {
                    $obj = $event->data->object ?? null;
                    if ($obj) {
                        $amount = isset($obj->amount) ? ((float) $obj->amount) / 100.0 : $amount;
                        $resolvedPaymentMode = $this->paymentModeFromStripePayload($obj, $resolvedPaymentMode);
                        $resolvedLiveMode = $resolvedPaymentMode === 'live';
                        // charges is a nested object
                        if (isset($obj->charges) && isset($obj->charges->data[0]->id)) {
                            $chargeId = $obj->charges->data[0]->id;
                        }
                    }
                    if (isset($event->type) && $event->type === 'payment_intent.payment_failed') {
                        $status = 'failed';
                    }
                } elseif (is_array($event)) {
                    $obj = $event['data']['object'] ?? null;
                    if ($obj) {
                        $amount = isset($obj['amount']) ? ((float) $obj['amount']) / 100.0 : $amount;
                        $resolvedPaymentMode = $this->paymentModeFromStripePayload($obj, $resolvedPaymentMode);
                        $resolvedLiveMode = $resolvedPaymentMode === 'live';
                        if (!empty($obj['charges']['data'][0]['id'])) {
                            $chargeId = $obj['charges']['data'][0]['id'];
                        }
                    }
                    if (!empty($event['type']) && $event['type'] === 'payment_intent.payment_failed') {
                        $status = 'failed';
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Error parsing Stripe event in finalizePaymentIntent: ' . $e->getMessage());
            }
        }

        // Wrap the status update + credit addition atomically so that
        // a failed addCredits() cannot leave the transaction as 'succeeded'
        // with no credits applied (which would prevent any retry via the
        // idempotency guard above).
        DB::transaction(function () use ($tx, $status, $chargeId, $amount, $resolvedPaymentMode, $resolvedLiveMode) {
            $tx->status = $status;
            if ($chargeId) {
                $tx->stripe_charge_id = $chargeId;
            }
            $tx->metadata = array_merge($tx->metadata ?? [], [
                'payment_mode'    => $resolvedPaymentMode,
                'stripe_livemode' => $resolvedLiveMode,
            ]);
            $tx->save();

            // If succeeded, credit the politician's balance.
            // Use the stored credits_amount (net of Stripe fee) if available;
            // otherwise fall back to the gross amount recorded on the transaction.
            if ($status === 'succeeded') {
                $politician = null;
                if ($tx->politician_id) {
                    $politician = \App\Models\Politician::find($tx->politician_id);
                }

                if ($politician) {
                    $creditsAmount = isset($tx->metadata['credits_amount'])
                        ? (float) $tx->metadata['credits_amount']
                        : $amount;

                    $feeNote = isset($tx->metadata['stripe_fee'])
                        ? ' (net of $' . number_format((float) $tx->metadata['stripe_fee'], 2) . ' Stripe processing fee)'
                        : '';

                    $this->addCredits($politician, $creditsAmount, [
                        'transaction_type'       => 'purchase',
                        'related_transaction_id' => $tx->id,
                        'description'            => 'Credits added from Stripe payment' . $feeNote,
                        'metadata'               => [
                            'payment_mode'    => $resolvedPaymentMode,
                            'stripe_livemode' => $resolvedLiveMode,
                        ],
                    ]);
                } else {
                    Log::warning('Politician not found when finalizing PaymentIntent', ['tx' => $tx->id]);
                }
            }
        });

        Log::info('Finalized PaymentIntent', ['payment_intent' => $paymentIntentId, 'tx_id' => $tx->id, 'status' => $status]);

        // Send purchase receipt email (best-effort) after transaction commit.
        if ($status === 'succeeded') {
            $this->sendCreditsPurchaseReceiptForTransaction($tx);
        }

        return $tx;
    }

    /**
     * Send credit purchase receipt for a succeeded charge transaction.
     * Returns true when delivery was attempted successfully.
     */
    public function sendCreditsPurchaseReceiptForTransaction(CampaignTransaction $tx): bool
    {
        if ($tx->transaction_type !== 'charge' || $tx->status !== 'succeeded') {
            return false;
        }

        if (! $tx->politician_id) {
            return false;
        }

        $politician = Politician::with('user')->find($tx->politician_id);
        if (! $politician || ! $politician->user || ! $politician->user->email) {
            return false;
        }

        $creditsAmount = isset($tx->metadata['credits_amount'])
            ? (float) $tx->metadata['credits_amount']
            : (float) $tx->amount;

        // Balance right after this purchase credit entry.
        $newBalance = (float) (PoliticianCredit::query()
            ->where('politician_id', $politician->id)
            ->where('transaction_type', 'purchase')
            ->where('related_transaction_id', $tx->id)
            ->value('balance_after')
            ?? PoliticianCredit::query()
                ->where('politician_id', $politician->id)
                ->orderByDesc('created_at')
                ->value('balance_after')
            ?? 0.0);

        // Use receipt_email override if set; otherwise use account email
        $receiptEmail = $politician->receipt_email ?? $politician->user->email;

        try {
            Mail::to($receiptEmail)->send(new CreditsPurchasedMail(
                user: $politician->user,
                amount: (float) $tx->amount,
                credits: $creditsAmount,
                newBalance: $newBalance,
                transactionId: (string) ($tx->uuid ?? $tx->id),
            ));

            Log::info('Sent credits purchase receipt email', [
                'transaction_id' => $tx->id,
                'politician_id' => $politician->id,
                'email' => $receiptEmail,
            ]);

                // Mark receipt as sent for tracking
                $tx->update(['receipt_sent_at' => now()]);

            return true;
        } catch (\Throwable $e) {
            Log::warning('Failed to send credits purchase receipt email', [
                'transaction_id' => $tx->id,
                'politician_id' => $politician->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Send refund receipt for a succeeded refund transaction.
     */
    public function sendCreditsRefundReceiptForTransaction(CampaignTransaction $tx): bool
    {
        $result = false;
        $skipAsSuccess = false;

        $isRefundSucceeded = $tx->transaction_type === 'refund' && $tx->status === 'succeeded';
        $canProcess = $isRefundSucceeded && (bool) $tx->politician_id;

        if ($canProcess && $tx->receipt_sent_at) {
            $skipAsSuccess = true;
        }

        $template = EmailTemplate::forKey('credits_refunded');
        if ($canProcess && ! $skipAsSuccess && $template && ! $template->is_active) {
            Log::info('Skipped refund receipt because template is disabled', ['transaction_id' => $tx->id]);
            $skipAsSuccess = true;
        }

        $politician = $canProcess && ! $skipAsSuccess
            ? Politician::with('user')->find($tx->politician_id)
            : null;

        if ($politician && $politician->user && $politician->user->email) {
            $newBalance = (float) (PoliticianCredit::query()
                ->where('politician_id', $politician->id)
                ->orderByDesc('created_at')
                ->value('balance_after')
                ?? 0.0);

            $receiptEmail = $politician->receipt_email ?? $politician->user->email;
            $refundedCredits = (float) ($tx->metadata['refunded_credits_amount'] ?? 0);
            $reason = isset($tx->metadata['reason']) ? (string) $tx->metadata['reason'] : null;

            try {
                Mail::to($receiptEmail)->send(new CreditsRefundedMail(
                    user: $politician->user,
                    amount: (float) $tx->amount,
                    refundedCredits: $refundedCredits,
                    newBalance: $newBalance,
                    transactionId: (string) ($tx->uuid ?? $tx->id),
                    reason: $reason,
                ));

                $tx->update(['receipt_sent_at' => now()]);

                Log::info('Sent credits refund receipt email', [
                    'transaction_id' => $tx->id,
                    'politician_id' => $politician->id,
                    'email' => $receiptEmail,
                ]);

                $result = true;
            } catch (\Throwable $e) {
                Log::warning('Failed to send credits refund receipt email', [
                    'transaction_id' => $tx->id,
                    'politician_id' => $politician->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($skipAsSuccess) {
            return true;
        }

        return $result;
    }
}
