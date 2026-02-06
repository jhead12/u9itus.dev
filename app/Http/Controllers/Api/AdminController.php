<?php

namespace App\Http\Controllers\Api;

use App\Enums\ApprovalStatus;
use App\Enums\CampaignStatus;
use App\Enums\ViewPaymentStatus;
use App\Enums\ViewSessionStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\CampaignResource;
use App\Http\Resources\VoterResource;
use App\Models\PoliticalCampaign;
use App\Models\Politician;
use App\Models\Voter;
use App\Models\ViewSession;
use App\Services\PoliticalPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Admin API — campaign approval, fraud management, payouts, analytics.
 *
 * Protected by wix.verify middleware (see routes/api.php).
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
        $totalRevenue   = ViewSession::where('status', ViewSessionStatus::Completed)->sum('platform_revenue');
        $totalPayouts   = ViewSession::where('payment_status', ViewPaymentStatus::Paid)->sum('voter_payout_amount');
        $totalReferrals = \App\Models\ReferralEarning::sum('commission_amount');

        return response()->json([
            'overview' => [
                'total_politicians'          => Politician::count(),
                'total_voters'               => Voter::count(),
                'active_campaigns'           => PoliticalCampaign::where('status', CampaignStatus::Active)->count(),
                'completed_views'            => ViewSession::where('status', ViewSessionStatus::Completed)->count(),
                'total_platform_revenue'     => $totalRevenue,
                'total_voter_payouts'        => $totalPayouts,
                'total_referral_commissions' => $totalReferrals,
            ],
            'per_view_economics' => $this->paymentService->perViewProfit(),
            'fraud_stats' => [
                'flagged_voters' => Voter::where('flagged_for_fraud', true)->count(),
                'held_payouts'   => ViewSession::where('payment_status', ViewPaymentStatus::Held)->sum('voter_payout_amount'),
            ],
        ]);
    }

    /**
     * List campaigns pending approval.
     */
    public function pendingCampaigns(): JsonResponse
    {
        $campaigns = PoliticalCampaign::where('approval_status', ApprovalStatus::Pending)
            ->with('politician')
            ->latest()
            ->get();

        return response()->json([
            'campaigns' => CampaignResource::collection($campaigns),
        ]);
    }

    /**
     * Approve a campaign and activate it.
     */
    public function approveCampaign(PoliticalCampaign $campaign): JsonResponse
    {
        $campaign->update([
            'approval_status' => ApprovalStatus::Approved,
            'status'          => CampaignStatus::Active,
            'approved_at'     => now(),
            'started_at'      => now(),
        ]);

        // Charge the politician's campaign budget
        $this->paymentService->chargeCampaign($campaign);

        return response()->json([
            'message'  => 'Campaign approved and activated',
            'campaign' => new CampaignResource($campaign->fresh()),
        ]);
    }

    /**
     * Reject a campaign.
     */
    public function rejectCampaign(Request $request, PoliticalCampaign $campaign): JsonResponse
    {
        $campaign->update([
            'approval_status' => ApprovalStatus::Rejected,
            'status'          => CampaignStatus::Cancelled,
        ]);

        return response()->json(['message' => 'Campaign rejected']);
    }

    /**
     * Process batch payouts for eligible voters.
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
     * List fraud-flagged voters.
     */
    public function flaggedVoters(): JsonResponse
    {
        $voters = Voter::where('flagged_for_fraud', true)
            ->withCount('viewSessions')
            ->get();

        return response()->json([
            'flagged_voters' => VoterResource::collection($voters),
        ]);
    }

    /**
     * Clear a voter's fraud flag and release held payouts (transactional).
     */
    public function clearFraudFlag(Voter $voter): JsonResponse
    {
        DB::transaction(function () use ($voter) {
            $voter->update([
                'flagged_for_fraud' => false,
                'trust_score'       => min(100, $voter->trust_score + 25),
            ]);

            // Release held payouts
            ViewSession::where('voter_id', $voter->id)
                ->where('payment_status', ViewPaymentStatus::Held)
                ->update(['payment_status' => ViewPaymentStatus::Approved]);
        });

        return response()->json([
            'message' => 'Fraud flag cleared',
            'voter'   => new VoterResource($voter->fresh()),
        ]);
    }
}
