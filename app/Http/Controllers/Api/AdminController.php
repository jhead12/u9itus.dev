<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PoliticalCampaign;
use App\Models\Politician;
use App\Models\Voter;
use App\Models\ViewSession;
use App\Services\PoliticalPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin API — campaign approval, fraud management, payouts, analytics.
 */
class AdminController extends Controller
{
    public function __construct(
        protected PoliticalPaymentService $paymentService,
    ) {}

    /**
     * Platform-wide analytics.
     */
    public function analytics(): JsonResponse
    {
        $totalRevenue  = ViewSession::completed()->sum('platform_revenue');
        $totalPayouts  = ViewSession::where('payment_status', 'paid')->sum('voter_payout_amount');
        $totalReferrals = \App\Models\ReferralEarning::sum('commission_amount');

        $profitPerView = $this->paymentService->perViewProfit();

        return response()->json([
            'overview' => [
                'total_politicians'    => Politician::count(),
                'total_voters'         => Voter::count(),
                'active_campaigns'     => PoliticalCampaign::where('status', 'active')->count(),
                'completed_views'      => ViewSession::completed()->count(),
                'total_platform_revenue' => $totalRevenue,
                'total_voter_payouts'  => $totalPayouts,
                'total_referral_commissions' => $totalReferrals,
            ],
            'per_view_economics' => $profitPerView,
            'fraud_stats' => [
                'flagged_voters' => Voter::where('flagged_for_fraud', true)->count(),
                'held_payouts'   => ViewSession::where('payment_status', 'held')->sum('voter_payout_amount'),
            ],
        ]);
    }

    /**
     * List campaigns pending approval.
     */
    public function pendingCampaigns(): JsonResponse
    {
        $campaigns = PoliticalCampaign::where('approval_status', 'pending')
            ->with('politician')
            ->latest()
            ->get();

        return response()->json(['campaigns' => $campaigns]);
    }

    /**
     * Approve a campaign.
     */
    public function approveCampaign(PoliticalCampaign $campaign): JsonResponse
    {
        $campaign->update([
            'approval_status' => 'approved',
            'status'          => 'active',
            'approved_at'     => now(),
            'started_at'      => now(),
        ]);

        // Charge the politician
        $this->paymentService->chargeCampaign($campaign);

        return response()->json([
            'message'  => 'Campaign approved and activated',
            'campaign' => $campaign->fresh(),
        ]);
    }

    /**
     * Reject a campaign.
     */
    public function rejectCampaign(Request $request, PoliticalCampaign $campaign): JsonResponse
    {
        $campaign->update([
            'approval_status' => 'rejected',
            'status'          => 'cancelled',
        ]);

        return response()->json(['message' => 'Campaign rejected']);
    }

    /**
     * Process batch payouts.
     */
    public function processBatchPayouts(): JsonResponse
    {
        $results = $this->paymentService->processBatchPayouts();

        return response()->json([
            'message' => 'Batch payout processing complete',
            'results' => $results,
        ]);
    }

    /**
     * List flagged voters.
     */
    public function flaggedVoters(): JsonResponse
    {
        $voters = Voter::where('flagged_for_fraud', true)
            ->withCount('viewSessions')
            ->get();

        return response()->json(['flagged_voters' => $voters]);
    }

    /**
     * Clear a voter's fraud flag.
     */
    public function clearFraudFlag(Voter $voter): JsonResponse
    {
        $voter->update([
            'flagged_for_fraud' => false,
            'trust_score' => min(100, $voter->trust_score + 25),
        ]);

        // Release held payouts
        ViewSession::where('voter_id', $voter->id)
            ->where('payment_status', 'held')
            ->update(['payment_status' => 'approved']);

        return response()->json(['message' => 'Fraud flag cleared', 'voter' => $voter->fresh()]);
    }
}
