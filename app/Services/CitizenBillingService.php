<?php

namespace App\Services;

use App\Mail\CreditsPurchasedMail;
use App\Mail\CreditsRefundedMail;
use App\Models\Citizen;
use Illuminate\Support\Str;
use App\Models\CitizenCampaign;
use App\Models\CitizenCredit;
use App\Models\CitizenTransaction;
use App\Models\EmailTemplate;
use App\Notifications\RefundCompletedNotification;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Citizen billing mirror of CampaignBillingService.
 *
 * Citizens pre-pay into a credit wallet; credits are debited as their campaigns
 * receive verified views. Refunds are supported against unused credits.
 */
class CitizenBillingService
{
    protected StripePaymentService $stripe;

    public function __construct(StripePaymentService $stripe)
    {
        $this->stripe = $stripe;
    }

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
     * Record a transaction in citizen_transactions table.
     */
    public function recordTransaction(array $data): CitizenTransaction
    {
        $tx = CitizenTransaction::create([
            'citizen_campaign_id'      => $data['citizen_campaign_id'] ?? null,
            'citizen_id'               => $data['citizen_id'] ?? null,
            'transaction_type'         => $data['transaction_type'] ?? 'charge',
            'amount'                   => $data['amount'] ?? 0,
            'currency'                 => $data['currency'] ?? 'USD',
            'stripe_payment_intent_id' => $data['stripe_payment_intent_id'] ?? null,
            'stripe_charge_id'         => $data['stripe_charge_id'] ?? null,
            'stripe_refund_id'         => $data['stripe_refund_id'] ?? null,
            'status'                   => $data['status'] ?? 'pending',
            'description'              => $data['description'] ?? null,
            'metadata'                 => $data['metadata'] ?? null,
        ]);

        Log::info('Recorded citizen transaction', ['id' => $tx->id, 'uuid' => $tx->uuid]);

        return $tx;
    }

    /**
     * Add credits to a citizen wallet.
     */
    public function addCredits(Citizen $citizen, float $amount, array $opts = []): CitizenCredit
    {
        return DB::transaction(function () use ($citizen, $amount, $opts): CitizenCredit {
            return $this->addCreditsInTransaction($citizen, $amount, $opts);
        });
    }

    /** Internal: must always be called inside an active DB transaction. */
    private function addCreditsInTransaction(Citizen $citizen, float $amount, array $opts): CitizenCredit
    {
        $citizen = Citizen::lockForUpdate()->findOrFail($citizen->id);

        $relatedTransactionId = $opts['related_transaction_id'] ?? null;
        $transactionType = $opts['transaction_type'] ?? 'purchase';

        if ($relatedTransactionId !== null && in_array($transactionType, ['purchase', 'refund'], true)) {
            $existing = CitizenCredit::where('citizen_id', $citizen->id)
                ->where('transaction_type', $transactionType)
                ->where('related_transaction_id', $relatedTransactionId)
                ->first();

            if ($existing) {
                return $existing;
            }
        }

        $current = CitizenCredit::where('citizen_id', $citizen->id)->orderBy('created_at', 'desc')->value('balance_after') ?: 0.00;
        $newBalance = $current + $amount;

        $credit = CitizenCredit::create([
            'citizen_id'             => $citizen->id,
            'transaction_type'       => $transactionType,
            'amount'                 => $amount,
            'balance_after'          => $newBalance,
            'citizen_campaign_id'    => $opts['citizen_campaign_id'] ?? null,
            'related_transaction_id' => $relatedTransactionId,
            'description'            => $opts['description'] ?? 'Purchased credits',
            'metadata'               => $opts['metadata'] ?? null,
        ]);

        $citizen->syncCreditBalance();

        Log::info('Added credits for citizen', [
            'citizen_id' => $citizen->id,
            'amount'     => $amount,
            'balance'    => $newBalance,
        ]);

        return $credit;
    }

    /**
     * Record a verified view against a citizen campaign's reserved budget.
     *
     * The full campaign budget is reserved from the citizen wallet at admin
     * approval time, so this method does not re-debit the wallet. It simply
     * increments the campaign's spent amount and records an auditable charge
     * transaction for the view.
     *
     * Returns the recorded transaction, or null when the reserved budget is
     * exhausted.
     */
    public function debitForView(CitizenCampaign $campaign, float $amount, array $opts = []): ?CitizenTransaction
    {
        if ($amount <= 0) {
            return null;
        }

        return DB::transaction(function () use ($campaign, $amount, $opts): ?CitizenTransaction {
            $campaign = CitizenCampaign::lockForUpdate()->findOrFail($campaign->id);

            $remaining = (float) $campaign->remainingBudget();

            if ($remaining < $amount) {
                Log::warning('Insufficient reserved budget for citizen view', [
                    'citizen_id'  => $campaign->citizen_id,
                    'campaign_id' => $campaign->id,
                    'amount'      => $amount,
                    'remaining'   => $remaining,
                ]);
                return null;
            }

            $campaign->increment('views_completed');
            $campaign->increment('amount_spent', $amount);

            $tx = $this->recordTransaction([
                'citizen_id'          => $campaign->citizen_id,
                'citizen_campaign_id' => $campaign->id,
                'transaction_type'    => 'view_charge',
                'amount'              => $amount,
                'currency'            => $opts['currency'] ?? 'USD',
                'status'              => 'succeeded',
                'description'         => $opts['description'] ?? "View charge for citizen campaign #{$campaign->id}",
                'metadata'            => array_merge($opts['metadata'] ?? [], [
                    'campaign_uuid' => $campaign->uuid,
                    'payment_mode'  => $this->configuredPaymentModeFromConfig(),
                ]),
            ]);

            return $tx;
        });
    }

    /**
     * Reserve campaign budget from a citizen's wallet at approval time.
     * Returns the ledger credit row for the debit, or null if unable.
     */
    public function reserveCampaignBudget(Citizen $citizen, CitizenCampaign $campaign): ?CitizenCredit
    {
        $budget = (float) $campaign->total_budget;
        if ($budget <= 0) {
            return null;
        }

        return DB::transaction(function () use ($citizen, $campaign, $budget): ?CitizenCredit {
            $citizen = Citizen::lockForUpdate()->findOrFail($citizen->id);

            $latest = CitizenCredit::where('citizen_id', $citizen->id)
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->first();

            $currentBalance = $latest ? (float) $latest->balance_after : 0.0;

            if ($currentBalance < $budget) {
                throw new \LogicException(
                    'Insufficient credits to fund this campaign. Required: $' . number_format($budget, 2) .
                    ', available: $' . number_format($currentBalance, 2) . '.'
                );
            }

            $credit = CitizenCredit::create([
                'citizen_id'          => $citizen->id,
                'citizen_campaign_id' => $campaign->id,
                'transaction_type'    => 'debit_campaign_budget',
                'amount'              => -$budget,
                'balance_after'       => $currentBalance - $budget,
                'description'         => "Reserved budget for citizen campaign #{$campaign->id}",
                'metadata'            => [
                    'campaign_uuid' => $campaign->uuid,
                    'budget'        => $budget,
                ],
            ]);

            $citizen->syncCreditBalance();

            return $credit;
        });
    }

    /**
     * Create a purchase PaymentIntent for citizen credit purchase and record a pending transaction.
     */
    public function createPurchaseIntent(Citizen $citizen, float $amount, array $opts = []): array
    {
        $feePercent = (float) config('u9itus.stripe_fee_percent', 2.5);

        $amountCents  = Money::toCents($amount);
        $grossCents   = Money::grossUp($amountCents, $feePercent);
        $feeCents     = $grossCents - $amountCents;
        $grossAmount  = (float) Money::fromCents($grossCents);
        $stripeFee    = (float) Money::fromCents($feeCents);
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
            $customerId = $this->stripe->ensureCustomer($citizen);

            $idempotencyKey = 'citizen-credits-' . $citizen->id . '-' . ($opts['idempotency_key'] ?? (string) Str::uuid());

            $pi = $this->stripe->createPaymentIntent(
                $grossAmount,
                'usd',
                [
                    'citizen_id'   => $citizen->id,
                    'citizen_uuid' => $citizen->uuid ?? null,
                ],
                $customerId,
                $opts['payment_method_id'] ?? null,
                $idempotencyKey,
            );

            $piId         = $pi->id ?? null;
            $clientSecret = $pi->client_secret ?? null;
            $paymentMode  = $this->stripe->modeFromStripeObject($pi, $configuredMode);
            $isLiveMode   = $paymentMode === 'live';

            $this->recordTransaction([
                'citizen_id'               => $citizen->id,
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
        } catch (\Exception $e) {
            Log::error('createPurchaseIntent failed for citizen: ' . $e->getMessage());
        }

        return $result;
    }

    /**
     * Get refundability summary for an already-succeeded purchase transaction.
     */
    public function getUnusedRefundSummary(CitizenTransaction $purchaseTx): array
    {
        if ($purchaseTx->transaction_type !== 'charge' || $purchaseTx->status !== 'succeeded') {
            throw new \InvalidArgumentException('Only succeeded charge transactions are refundable.');
        }

        if (! $purchaseTx->citizen_id) {
            throw new \InvalidArgumentException('Purchase transaction has no associated citizen.');
        }

        $creditsPurchasedRaw = $purchaseTx->metadata['credits_amount'] ?? $purchaseTx->amount;
        $creditsPurchasedCents = Money::toCents(is_string($creditsPurchasedRaw)
            ? $creditsPurchasedRaw
            : number_format((float) $creditsPurchasedRaw, 2, '.', ''));

        if ($creditsPurchasedCents <= 0) {
            throw new \InvalidArgumentException('Purchase transaction has invalid credits amount.');
        }

        $currentBalanceRaw = CitizenCredit::where('citizen_id', $purchaseTx->citizen_id)
            ->orderByDesc('created_at')
            ->value('balance_after') ?? '0.00';
        $currentBalanceCents = Money::toCents((string) $currentBalanceRaw);

        $refundRows = CitizenTransaction::query()
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
            'credits_purchased'        => (float) Money::fromCents($creditsPurchasedCents),
            'current_balance'          => (float) Money::fromCents($currentBalanceCents),
            'already_refunded_credits' => (float) Money::fromCents($alreadyRefundedCreditsCents),
            'already_refunded_gross'   => (float) Money::fromCents($alreadyRefundedGrossCents),
            'remaining_by_purchase'    => (float) Money::fromCents($remainingByPurchaseCents),
            'refundable_credits_now'   => (float) Money::fromCents($refundableCreditsNowCents),
        ];
    }

    /**
     * Process admin refund for unused credits tied to a citizen purchase.
     */
    public function refundUnusedCredits(
        CitizenTransaction $purchaseTx,
        int $adminId,
        ?float $requestedCredits = null,
        ?string $reason = null
    ): CitizenTransaction {
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
        $grossToRefund   = (float) Money::fromCents($grossToRefundCents);

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
        ): CitizenTransaction {
            $refundTx = $this->recordTransaction([
                'citizen_id'                 => $purchaseTx->citizen_id,
                'transaction_type'           => 'refund',
                'amount'                     => $grossToRefund,
                'currency'                   => $purchaseTx->currency ?: 'USD',
                'stripe_refund_id'           => $stripeRefund->id ?? null,
                'status'                     => $refundStatus === 'succeeded' ? 'succeeded' : 'pending',
                'description'                => 'Admin refund for unused credits',
                'metadata'                   => [
                    'original_transaction_id'      => $purchaseTx->id,
                    'original_payment_intent_id' => $purchaseTx->stripe_payment_intent_id,
                    'refunded_credits_amount'      => $creditsToRefund,
                    'refunded_gross_amount'        => $grossToRefund,
                    'reason'                       => $reason,
                    'admin_id'                     => $adminId,
                    'payment_mode'                 => $paymentMode,
                    'stripe_livemode'              => $paymentMode === 'live',
                ],
            ]);

            $citizen = Citizen::findOrFail((int) $purchaseTx->citizen_id);
            $this->addCredits($citizen, -$creditsToRefund, [
                'transaction_type'       => 'refund',
                'related_transaction_id'   => $refundTx->id,
                'description'            => 'Admin refund of unused credits',
                'metadata'                 => [
                    'original_transaction_id'      => $purchaseTx->id,
                    'stripe_refund_id'           => $stripeRefund->id ?? null,
                    'refunded_credits_amount'    => $creditsToRefund,
                    'reason'                     => $reason,
                    'admin_id'                   => $adminId,
                    'payment_mode'               => $paymentMode,
                    'stripe_livemode'            => $paymentMode === 'live',
                ],
            ]);

            return $refundTx;
        });

        if ($refundTx->status === 'succeeded') {
            $this->sendCreditsRefundReceiptForTransaction($refundTx);

            $citizen = Citizen::with('user')->find($refundTx->citizen_id);
            if ($citizen?->user) {
                $citizen->user->notify(new RefundCompletedNotification(
                    amount: (float) $refundTx->amount,
                    refundedCredits: (float) ($refundTx->metadata['refunded_credits_amount'] ?? 0),
                    transactionId: (string) ($refundTx->uuid ?? $refundTx->id),
                ));
            }
        }

        return $refundTx;
    }

    /**
     * Finalize a PaymentIntent: mark transaction succeeded/failed and credit citizen.
     */
    public function finalizePaymentIntent(string $paymentIntentId, $event = null): ?CitizenTransaction
    {
        $tx = CitizenTransaction::where('stripe_payment_intent_id', $paymentIntentId)->first();
        if (! $tx) {
            Log::warning('No citizen transaction found for PaymentIntent: ' . $paymentIntentId);
            return null;
        }

        if (in_array($tx->status, ['succeeded', 'failed', 'refunded'])) {
            Log::info('Citizen transaction already finalized', ['tx' => $tx->id, 'status' => $tx->status]);
            return $tx;
        }

        $status = 'succeeded';
        $chargeId = null;
        $amount = (float) $tx->amount;
        $resolvedPaymentMode = $tx->metadata['payment_mode'] ?? $this->configuredPaymentModeFromConfig();
        $resolvedLiveMode = isset($tx->metadata['stripe_livemode'])
            ? (bool) $tx->metadata['stripe_livemode']
            : $resolvedPaymentMode === 'live';

        if ($event) {
            try {
                if (is_object($event)) {
                    $obj = $event->data->object ?? null;
                    if ($obj) {
                        $amount = isset($obj->amount) ? ((float) $obj->amount) / 100.0 : $amount;
                        $resolvedPaymentMode = $this->paymentModeFromStripePayload($obj, $resolvedPaymentMode);
                        $resolvedLiveMode = $resolvedPaymentMode === 'live';
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
                        if (! empty($obj['charges']['data'][0]['id'])) {
                            $chargeId = $obj['charges']['data'][0]['id'];
                        }
                    }
                    if (! empty($event['type']) && $event['type'] === 'payment_intent.payment_failed') {
                        $status = 'failed';
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Error parsing Stripe event in finalizePaymentIntent: ' . $e->getMessage());
            }
        }

        $alreadyFinalized = false;

        DB::transaction(function () use ($tx, $status, $chargeId, $amount, $resolvedPaymentMode, $resolvedLiveMode, &$alreadyFinalized) {
            // Re-fetch with an exclusive row lock so concurrent webhook / redirect-confirm
            // calls on the same PaymentIntent cannot both pass the pending-status guard.
            $fresh = CitizenTransaction::lockForUpdate()->find($tx->id);
            if (in_array($fresh->status, ['succeeded', 'failed', 'refunded'])) {
                $alreadyFinalized = true;
                $tx->status = $fresh->status;
                return;
            }

            $fresh->status = $status;
            if ($chargeId) {
                $fresh->stripe_charge_id = $chargeId;
            }
            $fresh->metadata = array_merge($fresh->metadata ?? [], [
                'payment_mode'    => $resolvedPaymentMode,
                'stripe_livemode' => $resolvedLiveMode,
            ]);
            $fresh->save();

            // Sync mutations back to $tx so post-transaction code sees the right state.
            $tx->status   = $status;
            $tx->metadata = $fresh->metadata;

            if ($status === 'succeeded') {
                $citizen = $tx->citizen_id ? Citizen::find($tx->citizen_id) : null;

                if ($citizen) {
                    $creditsAmount = isset($tx->metadata['credits_amount'])
                        ? (float) $tx->metadata['credits_amount']
                        : $amount;

                    $feeNote = isset($tx->metadata['stripe_fee'])
                        ? ' (net of $' . number_format((float) $tx->metadata['stripe_fee'], 2) . ' Stripe processing fee)'
                        : '';

                    $this->addCredits($citizen, $creditsAmount, [
                        'transaction_type'       => 'purchase',
                        'related_transaction_id' => $tx->id,
                        'description'            => 'Credits added from Stripe payment' . $feeNote,
                        'metadata'               => [
                            'payment_mode'    => $resolvedPaymentMode,
                            'stripe_livemode' => $resolvedLiveMode,
                        ],
                    ]);
                } else {
                    Log::warning('Citizen not found when finalizing PaymentIntent', ['tx' => $tx->id]);
                }
            }
        });

        if ($alreadyFinalized) {
            Log::info('Citizen transaction already finalized (concurrent request)', ['tx' => $tx->id, 'status' => $tx->status]);
            return $tx;
        }

        Log::info('Finalized citizen PaymentIntent', [
            'payment_intent' => $paymentIntentId,
            'tx_id'          => $tx->id,
            'status'         => $status,
        ]);

        if ($status === 'succeeded') {
            $this->sendCreditsPurchaseReceiptForTransaction($tx);
        }

        return $tx;
    }

    /**
     * Send credit purchase receipt for a succeeded charge transaction.
     */
    public function sendCreditsPurchaseReceiptForTransaction(CitizenTransaction $tx): bool
    {
        if ($tx->transaction_type !== 'charge' || $tx->status !== 'succeeded') {
            return false;
        }

        if (! $tx->citizen_id) {
            return false;
        }

        $citizen = Citizen::with('user')->find($tx->citizen_id);
        if (! $citizen || ! $citizen->user || ! $citizen->user->email) {
            return false;
        }

        $creditsAmount = isset($tx->metadata['credits_amount'])
            ? (float) $tx->metadata['credits_amount']
            : (float) $tx->amount;

        $newBalance = (float) (CitizenCredit::query()
            ->where('citizen_id', $citizen->id)
            ->where('transaction_type', 'purchase')
            ->where('related_transaction_id', $tx->id)
            ->value('balance_after')
            ?? CitizenCredit::query()
                ->where('citizen_id', $citizen->id)
                ->orderByDesc('created_at')
                ->value('balance_after')
            ?? 0.0);

        $receiptEmail = $citizen->receipt_email ?? $citizen->user->email;

        try {
            Mail::to($receiptEmail)->send(new CreditsPurchasedMail(
                user: $citizen->user,
                amount: (float) $tx->amount,
                credits: $creditsAmount,
                newBalance: $newBalance,
                transactionId: (string) ($tx->uuid ?? $tx->id),
            ));

            Log::info('Sent citizen credits purchase receipt email', [
                'transaction_id' => $tx->id,
                'citizen_id'   => $citizen->id,
                'email'        => $receiptEmail,
            ]);

            $tx->update(['receipt_sent_at' => now()]);

            return true;
        } catch (\Throwable $e) {
            Log::warning('Failed to send citizen credits purchase receipt email', [
                'transaction_id' => $tx->id,
                'citizen_id'   => $citizen->id,
                'error'        => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Send refund receipt for a succeeded refund transaction.
     */
    public function sendCreditsRefundReceiptForTransaction(CitizenTransaction $tx): bool
    {
        $result = false;
        $skipAsSuccess = false;

        $isRefundSucceeded = $tx->transaction_type === 'refund' && $tx->status === 'succeeded';
        $canProcess = $isRefundSucceeded && (bool) $tx->citizen_id;

        if ($canProcess && $tx->receipt_sent_at) {
            $skipAsSuccess = true;
        }

        $template = EmailTemplate::forKey('credits_refunded');
        if ($canProcess && ! $skipAsSuccess && $template && ! $template->is_active) {
            Log::info('Skipped citizen refund receipt because template is disabled', ['transaction_id' => $tx->id]);
            $skipAsSuccess = true;
        }

        $citizen = $canProcess && ! $skipAsSuccess
            ? Citizen::with('user')->find($tx->citizen_id)
            : null;

        if ($citizen && $citizen->user && $citizen->user->email) {
            $newBalance = (float) (CitizenCredit::query()
                ->where('citizen_id', $citizen->id)
                ->orderByDesc('created_at')
                ->value('balance_after')
                ?? 0.0);

            $receiptEmail = $citizen->receipt_email ?? $citizen->user->email;
            $refundedCredits = (float) ($tx->metadata['refunded_credits_amount'] ?? 0);
            $reason = isset($tx->metadata['reason']) ? (string) $tx->metadata['reason'] : null;

            try {
                Mail::to($receiptEmail)->send(new CreditsRefundedMail(
                    user: $citizen->user,
                    amount: (float) $tx->amount,
                    refundedCredits: $refundedCredits,
                    newBalance: $newBalance,
                    transactionId: (string) ($tx->uuid ?? $tx->id),
                    reason: $reason,
                ));

                $tx->update(['receipt_sent_at' => now()]);

                Log::info('Sent citizen credits refund receipt email', [
                    'transaction_id' => $tx->id,
                    'citizen_id'   => $citizen->id,
                    'email'        => $receiptEmail,
                ]);

                $result = true;
            } catch (\Throwable $e) {
                Log::warning('Failed to send citizen credits refund receipt email', [
                    'transaction_id' => $tx->id,
                    'citizen_id'   => $citizen->id,
                    'error'        => $e->getMessage(),
                ]);
            }
        }

        if ($skipAsSuccess) {
            return true;
        }

        return $result;
    }
}
