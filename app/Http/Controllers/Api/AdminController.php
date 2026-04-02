<?php

namespace App\Http\Controllers\Api;

use App\Enums\ApprovalStatus;
use App\Enums\CampaignStatus;
use App\Enums\ViewPaymentStatus;
use App\Enums\ViewSessionStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\CampaignResource;
use App\Http\Resources\VoterResource;
use App\Models\CampaignTransaction;
use App\Models\PoliticalCampaign;
use App\Models\Politician;
use App\Models\Voter;
use App\Models\ViewSession;
use App\Services\CampaignStatusNotifier;
use App\Services\PoliticalPaymentService;
use App\Services\StripePaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Admin API — campaign approval, fraud management, payouts, analytics.
 *
 * Protected by auth:sanctum middleware (see routes/api.php).
 */
class AdminController extends Controller
{
    public function __construct(
        protected PoliticalPaymentService $paymentService,
        protected CampaignStatusNotifier $campaignStatusNotifier,
    ) {}

    /**
     * Defaults to 'test' when the key is unrecognised so the mode filter
     * is always applied and live data is never mixed with test data.
     */
    private function activePaymentMode(): string
    {
        $mode = app(StripePaymentService::class)->configuredMode();
        return $mode === 'live' ? 'live' : 'test';
    }

    private function applyPaymentModeFilter($query, string $mode)
    {
        return $query->where('metadata->payment_mode', $mode);
    }

    /**
     * Platform-wide analytics.
     */
    public function analytics(): JsonResponse
    {
        $activePaymentMode = $this->activePaymentMode();
        $campaignIds = $this->applyPaymentModeFilter(
            CampaignTransaction::query()->select('campaign_id')->whereNotNull('campaign_id')->distinct(),
            $activePaymentMode
        );

        $completedViewQuery = ViewSession::where('status', ViewSessionStatus::Completed)
            ->whereIn('political_campaign_id', $campaignIds);

        $paidViewQuery = ViewSession::where('payment_status', ViewPaymentStatus::Paid)
            ->whereIn('political_campaign_id', $campaignIds);

        $totalRevenue   = (clone $completedViewQuery)->sum('platform_revenue');
        $totalPayouts   = (clone $paidViewQuery)->sum('voter_payout_amount');
        $totalReferrals = \App\Models\ReferralEarning::sum('commission_amount');

        return response()->json([
            'overview' => [
                'total_politicians'          => Politician::count(),
                'total_voters'               => Voter::count(),
                'active_campaigns'           => PoliticalCampaign::where('status', CampaignStatus::Active)
                    ->whereIn('id', $campaignIds)
                    ->count(),
                'completed_views'            => (clone $completedViewQuery)->count(),
                'total_platform_revenue'     => $totalRevenue,
                'total_voter_payouts'        => $totalPayouts,
                'total_referral_commissions' => $totalReferrals,
                'payment_mode'               => $activePaymentMode,
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

        $this->campaignStatusNotifier->notifyStatusChanged($campaign, 'approved');

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
        $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $rejectionReason = $request->input('reason', 'Does not meet content guidelines.');

        $campaign->update([
            'approval_status' => ApprovalStatus::Rejected,
            // Return rejected campaigns to draft so politicians can revise or delete.
            'status'          => CampaignStatus::Draft,
            'rejection_reason' => $rejectionReason,
        ]);

        $this->campaignStatusNotifier->notifyStatusChanged($campaign, 'rejected', $rejectionReason);

        return response()->json(['message' => 'Campaign rejected']);
    }

    /**
     * Force-stop (pause) an active campaign.
     */
    public function stopCampaign(Request $request, PoliticalCampaign $campaign): JsonResponse
    {
        $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        if ($campaign->status !== CampaignStatus::Active) {
            return response()->json([
                'message' => 'Only active campaigns can be stopped',
            ], 422);
        }

        $campaign->update(['status' => CampaignStatus::Paused]);

        return response()->json([
            'message'  => 'Campaign stopped',
            'campaign' => new CampaignResource($campaign->fresh()),
        ]);
    }

    /**
     * Reactivate a stopped/paused campaign.
     */
    public function reactivateCampaign(Request $request, PoliticalCampaign $campaign): JsonResponse
    {
        $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        if ($campaign->status !== CampaignStatus::Paused) {
            return response()->json([
                'message' => 'Only paused campaigns can be reactivated',
            ], 422);
        }

        $campaign->update(['status' => CampaignStatus::Active]);

        return response()->json([
            'message'  => 'Campaign reactivated',
            'campaign' => new CampaignResource($campaign->fresh()),
        ]);
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
