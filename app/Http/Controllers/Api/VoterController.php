<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreVoterRequest;
use App\Http\Resources\VoterResource;
use App\Http\Resources\ViewSessionResource;
use App\Models\Voter;
use App\Models\ViewSession;
use App\Models\PoliticalCampaign;
use App\Services\StripeConnectService;
use App\Services\PoliticalViewService;
use App\Services\FraudPreventionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * REST API for voters — registration, watching videos, earnings, referrals.
 *
 * These endpoints power the voter widget and voter dashboard.
 * Voter routes use UUID-based binding and rate limiting (see routes/api.php).
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
    public function store(StoreVoterRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // Handle referral code → referrer lookup
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
            'voter'         => new VoterResource($voter),
            'referral_code' => $voter->referral_code,
        ], 201);
    }

    /**
     * Get voter profile + earnings summary.
     */
    public function show(Voter $voter): JsonResponse
    {
        return response()->json([
            'voter'    => new VoterResource($voter),
            'earnings' => $this->viewService->voterEarningsSummary($voter),
        ]);
    }

    /**
     * Get available campaigns for this voter (location-matched, not yet watched).
     */
    public function availableCampaigns(Voter $voter): JsonResponse
    {
        $campaigns = $this->viewService->availableCampaigns($voter);

        return response()->json([
            'campaigns' => $campaigns->map(fn ($c) => [
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
            $session->load('campaign');

            return response()->json([
                'message'    => 'View session started',
                'session'    => new ViewSessionResource($session),
                'media_url'  => $campaign->media_url ?? $campaign->live_feed_url,
                'must_watch' => $campaign->min_watch_time_percent,
            ], 201);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 403);
        }
    }

    /**
     * Heartbeat — track viewing progress (called every ~5 seconds by widget).
     */
    public function trackProgress(Request $request, ViewSession $session): JsonResponse
    {
        $seconds = (int) $request->input('seconds_watched', 0);

        $this->viewService->trackProgress($session, $seconds);

        return response()->json(['status' => 'ok']);
    }

    /**
     * Complete the view — voter watched the full message.
     */
    public function completeView(Request $request, ViewSession $session): JsonResponse
    {
        $totalSeconds = (int) $request->input('total_seconds_watched', 0);

        $session = $this->viewService->completeView($session, $totalSeconds);
        $session->load('campaign');

        return response()->json([
            'message' => 'View completed',
            'session' => new ViewSessionResource($session),
        ]);
    }

    /**
     * Get voter's view history (paginated).
     */
    public function viewHistory(Voter $voter): \Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        $sessions = $voter->viewSessions()
            ->with('campaign')
            ->latest()
            ->paginate(20);

        return ViewSessionResource::collection($sessions);
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
            'referral_code'     => $voter->referral_code,
            'referrals_count'   => $voter->referrals()->count(),
            'referral_earnings' => $voter->referralEarnings()->forActiveStripeMode()->sum('commission_amount'),
            'referred_voters'   => $voter->referrals()->select('uuid', 'full_name', 'created_at')->get(),
        ]);
    }

    /**
     * Generate a Stripe Connect onboarding link for a voter.
     */
    public function connectOnboard(Request $request, Voter $voter, StripeConnectService $stripeConnect): JsonResponse
    {
        $validated = $request->validate([
            'return_url' => 'nullable|url|max:2048',
            'refresh_url' => 'nullable|url|max:2048',
        ]);

        $link = $stripeConnect->createOnboardingLink(
            $voter,
            $validated['return_url'] ?? null,
            $validated['refresh_url'] ?? null,
        );

        $voter->update(['payment_method' => 'stripe']);

        return response()->json([
            'onboarding_url' => $link['url'],
            'expires_at' => $link['expires_at'],
            'account_id' => $link['account_id'],
        ]);
    }

    /**
     * Check Stripe Connect account readiness for payouts.
     */
    public function connectStatus(Voter $voter, StripeConnectService $stripeConnect): JsonResponse
    {
        return response()->json($stripeConnect->getAccountStatus($voter));
    }
}
