<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\LoyaltyViewer;

class PaymentService
{
    /**
     * Charge advertiser for campaign
     * (Stripe integration placeholder for MVP)
     */
    public function chargeCampaign(Campaign $campaign)
    {
        // TODO: Implement Stripe payment processing
        // For MVP, we'll assume payments are already authorized
        
        $campaign->update([
            'payment_status' => 'captured',
        ]);

        return true;
    }

    /**
     * Calculate Head Enterprises platform fee
     */
    public function calculateHeadEnterprisesFee(Campaign $campaign): float
    {
        return $campaign->total_budget * ($campaign->head_enterprises_fee_percent / 100);
    }

    /**
     * Process payout to viewer
     * (PayPal integration placeholder for MVP)
     */
    public function payoutToViewer(LoyaltyViewer $viewer, float $amount)
    {
        // TODO: Implement PayPal payout API
        // For MVP, we'll mark as paid in the database
        
        if ($amount < config('dial4dough.min_payout_amount', 25.00)) {
            throw new \Exception('Minimum payout amount not met');
        }

        $viewer->update([
            'pending_earnings' => $viewer->pending_earnings - $amount,
            'total_earned' => $viewer->total_earned + $amount,
        ]);

        return true;
    }
}
