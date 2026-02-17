<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Enums\ViewPaymentStatus;
use App\Models\PoliticalCampaign;
use App\Models\Voter;
use App\Models\ViewSession;
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
    /**
     * Charge a politician for a campaign budget.
     *
     * @return string Stripe PaymentIntent ID (placeholder for MVP)
     */
    public function chargeCampaign(PoliticalCampaign $campaign): string
    {
        // TODO: Implement Stripe PaymentIntent creation
        // $campaign->total_budget is the amount to charge
        // $campaign->politician->stripe_customer_id for the payment source

        $campaign->update([
            'payment_status' => PaymentStatus::Captured,
        ]);

        Log::info("Campaign {$campaign->uuid} charged: \${$campaign->total_budget}");
        return 'pi_placeholder_' . $campaign->uuid;
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
        $minPayout  = config('u9itus.min_payout_amount', 5.00);
        $holdHours  = config('u9itus.fraud.payout_hold_hours', 48);

        $eligibleVoters = Voter::where('pending_earnings', '>=', $minPayout)
            ->where('flagged_for_fraud', false)
            ->where('is_active', true)
            ->get();

        $results = ['processed' => 0, 'total_paid' => 0, 'skipped' => 0];

        foreach ($eligibleVoters as $voter) {
            // Only pay sessions that have passed the hold window
            $approvedEarnings = ViewSession::where('voter_id', $voter->id)
                ->where('payment_status', ViewPaymentStatus::Approved)
                ->where('completed_at', '<=', now()->subHours($holdHours))
                ->sum('voter_payout_amount');

            if ($approvedEarnings < $minPayout) {
                $results['skipped']++;
                continue;
            }

            DB::transaction(function () use ($voter, $approvedEarnings, $holdHours) {
                // Mark sessions as paid
                ViewSession::where('voter_id', $voter->id)
                    ->where('payment_status', ViewPaymentStatus::Approved)
                    ->where('completed_at', '<=', now()->subHours($holdHours))
                    ->update([
                        'payment_status' => ViewPaymentStatus::Paid,
                        'paid_at' => now(),
                    ]);

                // Move from pending to earned
                $voter->decrement('pending_earnings', $approvedEarnings);
                $voter->increment('total_earned', $approvedEarnings);
                $voter->increment('wallet_balance', $approvedEarnings);

                // TODO: Trigger actual PayPal / CashApp / wallet transfer
            });

            $results['processed']++;
            $results['total_paid'] += $approvedEarnings;

            Log::info("Payout processed for voter {$voter->uuid}: \${$approvedEarnings}");
        }

        return $results;
    }

    /**
     * Per-view profit calculation helper.
     *
     * Revenue:   $0.60 (politician pays)
     * Payout:    $0.25 (voter receives)
     * Referral:  $0.025 (10% of payout, if referred)
     * Processing: ~$0.02
     * Ops:       $0.03–$0.12
     * Net margin: 25%–50%
     */
    public function perViewProfit(
        ?float $revenuePerView = null,
        ?float $voterPayout = null,
        bool $hasReferral = false,
        float $processingFee = 0.02,
        float $opsCost = 0.05
    ): array {
        $revenuePerView ??= (float) config('u9itus.revenue_per_view', 0.60);
        $voterPayout    ??= (float) config('u9itus.viewer_payout_per_view', 0.25);
        $referralCommission = $hasReferral
            ? $voterPayout * (config('u9itus.referral_commission_percent', 10) / 100)
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
