<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Enums\ViewPaymentStatus;
use App\Models\PoliticalCampaign;
use App\Models\Voter;
use App\Models\ViewSession;
use App\Services\CampaignBillingService;
use App\Services\CashAppPayoutService;
use App\Services\PayPalPayoutService;
use App\Services\ReverbBroadcastService;
use App\Services\StripePaymentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Payment & payout service for the Political Loyalty Ads platform.
 *
 * Handles:
 *   - Charging politicians for campaigns (Stripe)
 *   - Batch voter payouts to reduce per-transaction fees
 *   - Referral commission payouts
 *   - Platform fee calculations
 */
class PoliticalPaymentService
{
    protected ?CampaignBillingService $billingService;
    protected ?StripePaymentService $stripeService;
    protected ?PayPalPayoutService $paypalService;
    protected ?CashAppPayoutService $cashAppService;
    protected ReverbBroadcastService $broadcastService;

    public function __construct(
        ?CampaignBillingService $billingService = null,
        ?StripePaymentService $stripeService = null,
        ?PayPalPayoutService $paypalService = null,
        ?CashAppPayoutService $cashAppService = null,
        ?ReverbBroadcastService $broadcastService = null,
    ) {
        $this->billingService   = $billingService;
        $this->stripeService    = $stripeService;
        $this->paypalService    = $paypalService;
        $this->cashAppService   = $cashAppService;
        $this->broadcastService = $broadcastService ?? app(ReverbBroadcastService::class);
    }

    /**
     * Charge a politician for a campaign budget.
     *
     * @return string Stripe PaymentIntent ID (placeholder for MVP)
     */
    public function chargeCampaign(PoliticalCampaign $campaign): string
    {
        $amount = (float) $campaign->total_budget;

        // Attempt to create Stripe PaymentIntent if Stripe SDK available
        try {
            if ($this->stripeService) {
                $pi = $this->stripeService->createPaymentIntent($amount, 'usd', [
                    'campaign_id' => $campaign->id,
                    'campaign_uuid' => $campaign->uuid,
                ]);

                $piId = $pi->id ?? null;
                $clientSecret = $pi->client_secret ?? null;

                // Record the transaction (pending capture)
                if ($this->billingService) {
                    $this->billingService->recordTransaction([
                        'campaign_id' => $campaign->id,
                        'politician_id' => $campaign->politician_id ?? null,
                        'transaction_type' => 'charge',
                        'amount' => $amount,
                        'currency' => 'USD',
                        'stripe_payment_intent_id' => $piId,
                        'status' => 'pending',
                        'description' => 'Campaign pre-authorization',
                        'metadata' => [
                            'client_secret' => $clientSecret,
                            'payment_mode'  => $this->stripeService->configuredMode(),
                        ],
                    ]);
                }

                // Mark campaign as authorized
                $campaign->update([
                    'payment_status' => PaymentStatus::Authorized,
                    // Persist PI on the campaign so voter inventory can verify funding state.
                    'stripe_payment_intent_id' => $piId,
                ]);
                Log::info("Campaign {$campaign->uuid} authorized for: \${$amount}", ['payment_intent' => $piId]);
                return $piId;
            }
        } catch (\Exception $e) {
            Log::error('Stripe PaymentIntent creation failed: ' . $e->getMessage());
            // fall through to fallback behavior
        }

        // Fallback: mark as captured (legacy behavior) and log
        $fallbackPaymentIntentId = 'pi_fallback_' . $campaign->uuid;
        $campaign->update([
            'payment_status' => PaymentStatus::Captured,
            'stripe_payment_intent_id' => $fallbackPaymentIntentId,
        ]);

        Log::info('Campaign ' . $campaign->uuid . ' charged (fallback): $' . $amount);
        return $fallbackPaymentIntentId;
    }

    /**
     * Calculate the Head Enterprises platform fee.
     */
    public function calculatePlatformFee(PoliticalCampaign $campaign): float
    {
        return $campaign->total_budget * ($campaign->head_enterprises_fee_percent / 100);
    }

    /**
     * Process batch payouts for voters who have met the minimum threshold.
     *
     * Batching payouts weekly or at threshold reduces per-transaction fees.
     */
    public function processBatchPayouts(): array
    {
        $minPayout  = (float) PlatformSettingsService::get('min_payout_amount', null, 5.00);
        $holdHours  = (int) PlatformSettingsService::get('fraud_payout_hold_hours', null, 48);
        $holdCutoff = now()->subHours($holdHours);

        $eligibleVoterIds = ViewSession::query()
            ->where('status', 'completed')
            ->where('payment_status', ViewPaymentStatus::Approved)
            ->where('completed_at', '<=', $holdCutoff)
            ->groupBy('voter_id')
            ->pluck('voter_id');

        if ($eligibleVoterIds->isEmpty()) {
            return ['processed' => 0, 'total_paid' => 0, 'skipped' => 0];
        }

        $eligibleVoters = Voter::whereIn('id', $eligibleVoterIds)
            ->where('flagged_for_fraud', false)
            ->where('is_active', true)
            ->get();

        $results = ['processed' => 0, 'total_paid' => 0, 'skipped' => 0];

        foreach ($eligibleVoters as $voter) {
            // Only pay sessions that have passed the hold window
            $approvedSessionsQuery = ViewSession::where('voter_id', $voter->id)
                ->where('status', 'completed')
                ->where('payment_status', ViewPaymentStatus::Approved)
                ->where('completed_at', '<=', $holdCutoff);

            $approvedEarnings = (float) $approvedSessionsQuery->sum('voter_payout_amount');

            if ($approvedEarnings < $minPayout) {
                $results['skipped']++;
                continue;
            }

            $selectedProcessor = (string) ($approvedSessionsQuery->whereNotNull('processor_selected')->value('processor_selected')
                ?? $voter->payment_method
                ?? 'wallet');

            $batchId = 'u9itus_' . $voter->uuid . '_' . now()->format('Ymd_His');
            $processorExecuted = 'wallet';
            $processorReference = $batchId;
            $processorFee = 0.00;

            $canUsePayPal = $selectedProcessor === 'paypal'
                && ! empty($voter->paypal_email)
                && $this->paypalService;

            $canUseCashApp = $selectedProcessor === 'cashapp'
                && ! empty($voter->cashapp_tag)
                && $this->cashAppService
                && $this->cashAppService->isConfigured();

            if ($canUsePayPal) {
                try {
                    $paypalResult = $this->paypalService->sendBatchPayout($batchId, [[
                        'email'          => $voter->paypal_email,
                        'amount'         => $approvedEarnings,
                        'note'           => 'U9itus viewer earnings',
                        'sender_item_id' => $batchId,
                    ]]);

                    $processorExecuted = 'paypal';
                    $processorReference = (string) ($paypalResult['batch_header']['payout_batch_id'] ?? $batchId);
                } catch (\Exception $e) {
                    Log::error("PayPal payout failed for voter {$voter->uuid}: " . $e->getMessage());
                    $results['skipped']++;
                    continue;
                }
            } elseif ($canUseCashApp) {
                try {
                    $cashAppResult = $this->cashAppService->sendPayout(
                        (string) $voter->cashapp_tag,
                        $approvedEarnings,
                        $batchId,
                        'U9itus viewer earnings'
                    );

                    $processorExecuted = 'cashapp';
                    $processorReference = (string) ($cashAppResult['reference'] ?? $batchId);
                    $processorFee = (float) ($cashAppResult['fee'] ?? 0.00);
                } catch (\Exception $e) {
                    Log::error("Cash App payout failed for voter {$voter->uuid}: " . $e->getMessage());
                    $results['skipped']++;
                    continue;
                }
            } elseif ($selectedProcessor === 'paypal' || $selectedProcessor === 'cashapp') {
                Log::warning("Skipping payout for voter {$voter->uuid}: selected processor unavailable", [
                    'selected_processor' => $selectedProcessor,
                    'has_paypal_email' => ! empty($voter->paypal_email),
                    'has_cashapp_tag' => ! empty($voter->cashapp_tag),
                    'paypal_service_available' => (bool) $this->paypalService,
                    'cashapp_service_available' => (bool) $this->cashAppService,
                    'cashapp_configured' => $this->cashAppService?->isConfigured() ?? false,
                ]);
                $results['skipped']++;
                continue;
            }

            DB::transaction(function () use ($voter, $approvedEarnings, $holdCutoff, $processorExecuted, $processorReference, $processorFee) {
                // Mark sessions as paid
                ViewSession::where('voter_id', $voter->id)
                    ->where('status', 'completed')
                    ->where('payment_status', ViewPaymentStatus::Approved)
                    ->where('completed_at', '<=', $holdCutoff)
                    ->update([
                        'payment_status' => ViewPaymentStatus::Paid,
                        'paid_at'        => now(),
                        'processor_executed' => $processorExecuted,
                        'processor_reference' => $processorReference,
                        'processor_fee' => $processorFee,
                    ]);

                // Move from pending to earned using session-derived approved earnings.
                $voter->decrement('pending_earnings', $approvedEarnings);
                $voter->increment('total_earned', $approvedEarnings);

                if ($processorExecuted === 'wallet') {
                    // No external transfer — credit the on-platform wallet instead.
                    $voter->increment('wallet_balance', $approvedEarnings);
                }
            });

            $results['processed']++;
            $results['total_paid'] += $approvedEarnings;

            // Notify the voter via WebSocket (Phase 11)
            $displayMethod = match ($processorExecuted) {
                'paypal' => 'PayPal',
                'cashapp' => 'CashApp',
                default => 'Wallet',
            };

            $this->broadcastService->payoutProcessed(
                $voter,
                $approvedEarnings,
                $displayMethod,
                $processorReference,
            );

            Log::info("Payout processed for voter {$voter->uuid}: \${$approvedEarnings}", [
                'method' => $processorExecuted,
                'selected_processor' => $selectedProcessor,
                'reference' => $processorReference,
            ]);
        }

        return $results;
    }

    /**
     * Per-view profit calculation helper.
     *
     * Revenue:   $0.60 (politician pays)
     * Payout:    $0.50 (voter receives)
     * Referral:  $0.050 (10% of payout, if referred)
     * Processing: ~$0.02
     * Ops:       $0.03–$0.08
     * Net margin: 20%–35%
     */
    public function perViewProfit(
        ?float $revenuePerView = null,
        ?float $voterPayout = null,
        bool $hasReferral = false,
        float $processingFee = 0.02,
        float $opsCost = 0.05
    ): array {
        $revenuePerView ??= (float) PlatformSettingsService::get('revenue_per_view', null, 0.60);
        $voterPayout    ??= (float) PlatformSettingsService::get('viewer_payout_per_view', null, 0.25);
        $referralCommission = $hasReferral
            ? $voterPayout * (PlatformSettingsService::get('referral_commission_percent', null, 10) / 100)
            : 0;

        $totalCost = $voterPayout + $referralCommission + $processingFee + $opsCost;
        $profit    = $revenuePerView - $totalCost;
        $margin    = $revenuePerView > 0 ? ($profit / $revenuePerView) * 100 : 0;

        return [
            'revenue'               => $revenuePerView,
            'voter_payout'          => $voterPayout,
            'referral_commission'   => $referralCommission,
            'processing_fee'        => $processingFee,
            'ops_cost'              => $opsCost,
            'total_cost'            => $totalCost,
            'profit'                => round($profit, 4),
            'margin_percent'        => round($margin, 2),
        ];
    }
}
