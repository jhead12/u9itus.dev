<?php

namespace App\Http\Controllers\Api;

use App\Enums\ApprovalStatus;
use App\Enums\CampaignStatus;
use App\Enums\ViewPaymentStatus;
use App\Enums\ViewSessionStatus;
use App\Http\Controllers\Concerns\PaymentModeFilterable;
use App\Http\Controllers\Controller;
use App\Http\Resources\CampaignResource;
use App\Http\Resources\VoterResource;
use App\Models\CampaignTransaction;
use App\Models\PoliticalCampaign;
use App\Models\Politician;
use App\Models\ReferralEarning;
use App\Models\Voter;
use App\Models\ViewSession;
use App\Services\CampaignModerationService;
use App\Services\PoliticalPaymentService;
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
    use PaymentModeFilterable;

    public function __construct(
        protected PoliticalPaymentService $paymentService,
        protected CampaignModerationService $moderationService,
    ) {}

    /**
     * Platform-wide analytics.
     */
    public function analytics(): JsonResponse
    {
        $activePaymentMode = $this->activePaymentMode();

        // Credit-purchase transactions carry payment_mode in metadata but have campaign_id = null.
        // Derive campaign IDs via the politician IDs who purchased in the active mode.
        $politicianIds = $this->applyPaymentModeFilter(
            CampaignTransaction::query()->select('politician_id')->whereNotNull('politician_id')->distinct(),
            $activePaymentMode
        );
        $campaignIds = PoliticalCampaign::query()->select('id')->whereIn('politician_id', $politicianIds)->distinct();

        $completedViewQuery = ViewSession::where('status', ViewSessionStatus::Completed)
            ->whereIn('political_campaign_id', $campaignIds);

        $paidViewQuery = ViewSession::where('payment_status', ViewPaymentStatus::Paid)
            ->whereIn('political_campaign_id', $campaignIds);

        $totalRevenue   = (clone $completedViewQuery)->sum('platform_revenue');
        $totalPayouts   = (clone $paidViewQuery)->sum('voter_payout_amount');
        $totalReferrals = ReferralEarning::forPaymentMode($activePaymentMode)->sum('commission_amount');

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
        $result = $this->moderationService->approve($campaign, auth()->id());

        return response()->json([
            'message'  => 'Campaign ' . $result['label'],
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

        $this->moderationService->reject($campaign, $rejectionReason, auth()->id());

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
     * List fraud-flagged voters (paginated).
     */
    public function flaggedVoters(Request $request): JsonResponse
    {
        $voters = Voter::where('flagged_for_fraud', true)
            ->withCount('viewSessions')
            ->paginate(50);

        return response()->json([
            'flagged_voters' => VoterResource::collection($voters),
            'meta' => [
                'current_page' => $voters->currentPage(),
                'last_page'    => $voters->lastPage(),
                'total'        => $voters->total(),
            ],
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

    // ─────────────────────────────────────────────────────────────────────────
    // Registration Security — IP Blocking & Rate Limiting
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * List all registration attempts (paginated) with filtering.
     */
    public function registrationAttempts(Request $request): JsonResponse
    {
        $attempts = DB::table('registration_attempts')
            ->when($request->input('outcome'), fn ($q) => $q->where('outcome', $request->input('outcome')))
            ->when($request->input('reason'), fn ($q) => $q->where('reason', $request->input('reason')))
            ->orderByDesc('created_at')
            ->paginate(50);

        return response()->json($attempts);
    }

    /**
     * Get registration attempts for a specific IP.
     */
    public function registrationAttemptsByIp(Request $request, string $ip): JsonResponse
    {
        $attempts = DB::table('registration_attempts')
            ->where('ip_address', $ip)
            ->orderByDesc('created_at')
            ->paginate(50);

        $blockedStatus = DB::table('ip_registration_blocks')
            ->where('ip_address', $ip)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            })
            ->first();

        return response()->json([
            'ip'           => $ip,
            'attempts'     => $attempts,
            'blocked'      => $blockedStatus ? true : false,
            'block_reason' => $blockedStatus?->reason,
            'expires_at'   => $blockedStatus?->expires_at,
        ]);
    }

    /**
     * Get all active (non-expired) IP blocks.
     */
    public function activeIpBlocks(Request $request): JsonResponse
    {
        $blocks = DB::table('ip_registration_blocks')
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            })
            ->orderByDesc('created_at')
            ->paginate(50);

        return response()->json($blocks);
    }

    /**
     * Block an IP address (optionally with expiry).
     */
    public function blockIp(Request $request): JsonResponse
    {
        $request->validate([
            'ip_address' => ['required', 'ip'],
            'reason'     => ['required', 'string', 'max:255'],
            'expires_at' => ['nullable', 'date_format:Y-m-d H:i:s'],
            'notes'      => ['nullable', 'string', 'max:1000'],
        ]);

        app(\App\Services\RegistrationSecurityService::class)->blockIp(
            $request->ip_address,
            $request->reason,
            $request->expires_at,
            auth()->id()
        );

        return response()->json([
            'message'    => 'IP blocked successfully',
            'ip_address' => $request->ip_address,
            'expires_at' => $request->expires_at,
        ], 201);
    }

    /**
     * Unblock an IP address.
     */
    public function unblockIp(Request $request, string $ip): JsonResponse
    {
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return response()->json([
                'message' => 'Invalid IP address.',
            ], 422);
        }

        app(\App\Services\RegistrationSecurityService::class)->unblockIp($ip);

        return response()->json([
            'message'    => 'IP unblocked successfully',
            'ip_address' => $ip,
        ]);
    }
}
