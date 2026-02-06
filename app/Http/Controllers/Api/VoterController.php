<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Voter;
use App\Models\ViewSession;
use App\Models\PoliticalCampaign;
use App\Services\PoliticalViewService;
use App\Services\FraudPreventionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * REST API for voters — registration, watching videos, earnings, referrals.
 * These endpoints power the Wix widget and voter dashboard.
 */
class VoterController extends Controller
{
    public function __construct(
        protected PoliticalViewService $viewService,
        protected FraudPreventionService $fraudService,
    ) {}

    /**
     * Register a new voter.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = Validator::make($request->all(), [
            'full_name'     => 'required|string|max:255',
            'email'         => 'required|email|unique:voters,email',
            'phone'         => 'nullable|string|max:20',
            'state'         => 'nullable|string|max:2',
            'city'          => 'nullable|string|max:255',
            'zip_code'      => 'nullable|string|max:10',
            'referral_code' => 'nullable|string|max:16',
            'payment_method'=> 'nullable|in:wallet,paypal,cashapp',
            'paypal_email'  => 'nullable|email',
            'cashapp_tag'   => 'nullable|string|max:50',
            'wix_member_id' => 'nullable|string',
            'wix_site_id'   => 'nullable|integer',
            'preferred_governance_levels' => 'nullable|array',
        ])->validate();

        // Handle referral
        if (!empty($validated['referral_code'])) {
            $referrer = Voter::where('referral_code', $validated['referral_code'])->first();
            if ($referrer) {
                $validated['referred_by_voter_id'] = $referrer->id;
            }
            unset($validated['referral_code']);
        }

        $voter = Voter::create($validated);

        return response()->json([
            'message'       => 'Voter registered successfully',
            'voter'         => $voter,
            'referral_code' => $voter->referral_code,
        ], 201);
    }

    /**
     * Get voter profile + earnings summary.
     */
    public function show(Voter $voter): JsonResponse
    {
        return response()->json([
            'voter'    => $voter,
            'earnings' => $this->viewService->voterEarningsSummary($voter),
        ]);
    }

    /**
     * Get available campaigns for this voter.
     */
    public function availableCampaigns(Voter $voter): JsonResponse
    {
        $campaigns = $this->viewService->availableCampaigns($voter);

        return response()->json([
            'campaigns' => $campaigns->map(fn($c) => [
                'id'                => $c->id,
                'uuid'              => $c->uuid,
                'title'             => $c->title,
                'message_summary'   => $c->message_summary,
                'campaign_type'     => $c->campaign_type,
                'governance_level'  => $c->governance_level,
                'politician'        => $c->politician->full_name ?? 'Unknown',
                'political_office'  => $c->politician->political_office ?? null,
                'payout'            => $c->voter_payout_per_view,
                'media_duration'    => $c->media_duration,
                'thumbnail_url'     => $c->thumbnail_url,
                'is_live'           => $c->isLiveFeed(),
                'live_scheduled_at' => $c->live_scheduled_at,
            ]),
        ]);
    }

    /**
     * Start watching a campaign video / join a live feed.
     */
    public function startView(Request $request, Voter $voter, PoliticalCampaign $campaign): JsonResponse
    {
        if (!$voter->canViewToday()) {
            return response()->json(['error' => 'Daily view limit reached or account restricted'], 429);
        }

        if (!$campaign->needsMoreViews()) {
            return response()->json(['error' => 'Campaign has reached its view goal'], 410);
        }

        try {
            $session = $this->viewService->assignView($campaign, $voter, $request);
            $session = $this->viewService->startView($session);

            return response()->json([
                'message'    => 'View session started',
                'session_id' => $session->uuid,
                'media_url'  => $campaign->media_url ?? $campaign->live_feed_url,
                'payout'     => $campaign->voter_payout_per_view,
                'duration'   => $campaign->media_duration,
                'must_watch' => $campaign->min_watch_time_percent,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 403);
        }
    }

    /**
     * Heartbeat — track viewing progress.
     */
    public function trackProgress(Request $request, ViewSession $session): JsonResponse
    {
        $seconds = $request->input('seconds_watched', 0);

        $this->viewService->trackProgress($session, $seconds);

        return response()->json(['status' => 'ok']);
    }

    /**
     * Complete the view — voter watched the full message.
     */
    public function completeView(Request $request, ViewSession $session): JsonResponse
    {
        $totalSeconds = $request->input('total_seconds_watched', 0);

        $session = $this->viewService->completeView($session, $totalSeconds);

        return response()->json([
            'message'        => 'View completed',
            'status'         => $session->status,
            'payout_earned'  => $session->voter_payout_amount,
            'payment_status' => $session->payment_status,
        ]);
    }

    /**
     * Get voter's view history.
     */
    public function viewHistory(Voter $voter): JsonResponse
    {
        $sessions = $voter->viewSessions()
            ->with('campaign.politician')
            ->latest()
            ->paginate(20);

        return response()->json($sessions);
    }

    /**
     * Get voter's earnings breakdown.
     */
    public function earnings(Voter $voter): JsonResponse
    {
        return response()->json($this->viewService->voterEarningsSummary($voter));
    }

    /**
     * Get voter's referral info.
     */
    public function referrals(Voter $voter): JsonResponse
    {
        return response()->json([
            'referral_code'    => $voter->referral_code,
            'referrals_count'  => $voter->referrals()->count(),
            'referral_earnings'=> $voter->referralEarnings()->sum('commission_amount'),
            'referred_voters'  => $voter->referrals()->select('id', 'full_name', 'created_at')->get(),
        ]);
    }
}
