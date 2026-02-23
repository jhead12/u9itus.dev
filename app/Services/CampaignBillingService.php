<?php

namespace App\Services;

use App\Models\CampaignTransaction;
use App\Models\Politician;
use App\Models\PoliticianCredit;
use App\Models\ReferralEarning;
use Illuminate\Support\Facades\Log;

class CampaignBillingService
{
    protected StripePaymentService $stripe;

    public function __construct(StripePaymentService $stripe)
    {
        $this->stripe = $stripe;
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
        // Calculate new balance
        $current = PoliticianCredit::where('politician_id', $politician->id)->orderBy('created_at', 'desc')->value('balance_after') ?: 0.00;
        $newBalance = $current + $amount;

        $credit = PoliticianCredit::create([
            'politician_id' => $politician->id,
            'transaction_type' => $opts['transaction_type'] ?? 'purchase',
            'amount' => $amount,
            'balance_after' => $newBalance,
            'campaign_id' => $opts['campaign_id'] ?? null,
            'related_transaction_id' => $opts['related_transaction_id'] ?? null,
            'description' => $opts['description'] ?? 'Purchased credits',
            'metadata' => $opts['metadata'] ?? null,
        ]);

        // Keep politicians.credit_balance in sync with the ledger.
        $politician->syncCreditBalance();

        // Fire one-time procurement commission on the politician's first purchase.
        if (($opts['transaction_type'] ?? null) === 'purchase') {
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
     * Fires at most once per politician (guarded by existing ReferralEarning row).
     */
    private function triggerProcurementCommission(Politician $politician, float $purchaseAmount): void
    {
        // One-time only — skip if a procurement commission already exists
        $alreadyPaid = ReferralEarning::where('politician_id', $politician->id)
            ->where('referral_type', ReferralEarning::TYPE_POLITICIAN_PROCUREMENT)
            ->exists();

        if ($alreadyPaid) {
            return;
        }

        $pct        = (float) config('u9itus.procurement_commission_percent', 10);
        $commission = round($purchaseAmount * ($pct / 100), 2);

        if ($commission <= 0) {
            return;
        }

        // Referred by a voter
        if ($politician->referred_by_voter_id) {
            ReferralEarning::create([
                'referrer_voter_id' => $politician->referred_by_voter_id,
                'referred_voter_id' => null,
                'view_session_id'   => null,
                'commission_amount' => $commission,
                'referral_type'     => ReferralEarning::TYPE_POLITICIAN_PROCUREMENT,
                'politician_id'     => $politician->id,
            ]);
            $politician->referrer?->increment('pending_earnings', $commission);

            Log::info('Procurement commission awarded to voter', [
                'politician_id'     => $politician->id,
                'referrer_voter_id' => $politician->referred_by_voter_id,
                'commission'        => $commission,
            ]);
        }

        // Referred by a politician
        if ($politician->referred_by_politician_id) {
            ReferralEarning::create([
                'referrer_politician_id' => $politician->referred_by_politician_id,
                'referred_voter_id'      => null,
                'view_session_id'        => null,
                'commission_amount'      => $commission,
                'referral_type'          => ReferralEarning::TYPE_POLITICIAN_PROCUREMENT,
                'politician_id'          => $politician->id,
            ]);
            $politician->politicianReferrer?->increment('pending_earnings', $commission);

            Log::info('Procurement commission awarded to politician', [
                'politician_id'          => $politician->id,
                'referrer_politician_id' => $politician->referred_by_politician_id,
                'commission'             => $commission,
            ]);
        }
    }

    /**
     * Create a purchase PaymentIntent for politician credit purchase and record a pending transaction.
     * Returns array with keys: payment_intent_id and client_secret (when Stripe available).
     */
    public function createPurchaseIntent(Politician $politician, float $amount, array $opts = []): array
    {
        $result = ['payment_intent_id' => null, 'client_secret' => null];

        try {
            if ($this->stripe) {
                // Auto-create the Stripe Customer if one doesn't exist yet.
                $customerId = $this->stripe->ensureCustomer($politician);

                $pi = $this->stripe->createPaymentIntent(
                    $amount,
                    'usd',
                    [
                        'politician_id'   => $politician->id,
                        'politician_uuid' => $politician->uuid ?? null,
                    ],
                    $customerId,
                    $opts['payment_method_id'] ?? null,
                );

                $piId = $pi->id ?? null;
                $clientSecret = $pi->client_secret ?? null;

                // Record campaign transaction placeholder
                if ($this) {
                    $this->recordTransaction([
                        'campaign_id' => null,
                        'politician_id' => $politician->id,
                        'transaction_type' => 'charge',
                        'amount' => $amount,
                        'currency' => 'USD',
                        'stripe_payment_intent_id' => $piId,
                        'status' => 'pending',
                        'description' => 'Credit purchase',
                        'metadata' => ['client_secret' => $clientSecret],
                    ]);
                }

                $result['payment_intent_id'] = $piId;
                $result['client_secret'] = $clientSecret;
            }
        } catch (\Exception $e) {
            Log::error('createPurchaseIntent failed: ' . $e->getMessage());
        }

        return $result;
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

        // Try to extract details from Stripe event if available
        if ($event) {
            try {
                if (is_object($event)) {
                    $obj = $event->data->object ?? null;
                    if ($obj) {
                        $amount = isset($obj->amount) ? ((float) $obj->amount) / 100.0 : $amount;
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

        $tx->status = $status;
        if ($chargeId) {
            $tx->stripe_charge_id = $chargeId;
        }
        $tx->save();

        // If succeeded, credit the politician's balance
        if ($status === 'succeeded') {
            try {
                $politician = null;
                if ($tx->politician_id) {
                    $politician = \App\Models\Politician::find($tx->politician_id);
                }

                if ($politician) {
                    $this->addCredits($politician, $amount, [
                        'transaction_type' => 'purchase',
                        'related_transaction_id' => $tx->id,
                        'description' => 'Credits added from Stripe payment',
                    ]);
                } else {
                    Log::warning('Politician not found when finalizing PaymentIntent', ['tx' => $tx->id]);
                }
            } catch (\Exception $e) {
                Log::error('Failed to add credits after payment intent succeeded: ' . $e->getMessage());
            }
        }

        Log::info('Finalized PaymentIntent', ['payment_intent' => $paymentIntentId, 'tx_id' => $tx->id, 'status' => $status]);

        return $tx;
    }
}
