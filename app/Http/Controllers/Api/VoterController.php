<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreVoterRequest;
use App\Http\Resources\VoterResource;
use App\Http\Resources\ViewSessionResource;
use App\Models\Voter;
use App\Models\VoterApiToken;
use App\Models\ViewSession;
use App\Models\PoliticalCampaign;
use App\Http\Middleware\AuthenticateVoterToken;
use App\Http\Middleware\CaptureEarlyBankReferral;
use App\Services\EarlyBankWebhookService;
use App\Services\StripeConnectService;
use App\Services\PoliticalViewService;
use App\Services\FraudPreventionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Log;

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
        protected EarlyBankWebhookService $earlyBankWebhook,
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

        // Capture Early-bank member referral at registration time so we can
        // fire webhooks later without requiring a separate API round-trip.
        $earlybankMemberId = $validated['earlybank_member_id'] ?? null;
        unset($validated['earlybank_member_id']);

        if ($earlybankMemberId) {
            $validated['earlybank_member_id'] = $earlybankMemberId;
            $validated['earlybank_linked_at']  = now();
        }

        $voter = Voter::create($validated);

        // Notify Early-bank that the referral linkage is established.
        // Fire-and-forget — does not block registration response.
        if ($earlybankMemberId) {
            $this->earlyBankWebhook->handleVoterRegistered($voter, (string) $earlybankMemberId);
        }

        // SEC-4: issue the voter's first API token. The plaintext is returned
        // here exactly once — consumers must capture and store it, then send it
        // as Authorization: Bearer on subsequent voter API calls.
        $issued = VoterApiToken::createToken($voter);

        // Clear the Early-bank referral cookie so the same browser cannot
        // accidentally attribute a future voter registration to the same member.
        $response = response()->json([
            'message'       => 'Voter registered successfully',
            'voter'         => new VoterResource($voter),
            'referral_code' => $voter->referral_code,
            'token'         => $issued['token'],
        ], 201);

        if ($earlybankMemberId) {
            $response->withCookie(Cookie::forget(CaptureEarlyBankReferral::COOKIE_NAME));
        }

        return $response;
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
        $clientSeconds = (int) $request->input('seconds_watched', 0);

        // COR-5: never trust the client-reported seconds. Bound to wall-clock
        // elapsed since the session started (SEC-4 guarantees the caller owns
        // this session, but they can still inflate their own claim).
        $seconds = $this->serverAuthoritativeSeconds($session, $clientSeconds);

        $this->viewService->trackProgress($session, $seconds);

        return response()->json(['status' => 'ok']);
    }

    /**
     * Complete the view — voter watched the full message.
     */
    public function completeView(Request $request, ViewSession $session): JsonResponse
    {
        $clientSeconds = (int) $request->input('total_seconds_watched', 0);

        // COR-5: bound the completion claim to real elapsed time so a voter
        // cannot inflate total_seconds_watched to force a payout.
        $seconds = $this->serverAuthoritativeSeconds($session, $clientSeconds);

        $session = $this->viewService->completeView($session, $seconds);
        $session->load('campaign');

        return response()->json([
            'message' => 'View completed',
            'session' => new ViewSessionResource($session),
        ]);
    }

    /**
     * COR-5: derive a server-authoritative watch-time value for a session.
     *
     * A voter cannot have watched more seconds than have actually elapsed on
     * the server clock since the session was started, so clamp the client's
     * claim to that bound. Returns 0 when the session has no started_at (e.g.
     * completed before play was pressed). Materially inflated claims are
     * logged as a fraud signal.
     */
    private function serverAuthoritativeSeconds(ViewSession $session, int $clientClaim): int
    {
        if (!$session->started_at) {
            return 0;
        }

        $elapsed = max(0, (int) $session->started_at->diffInSeconds(now()));

        if ($clientClaim > $elapsed + 2) {
            Log::warning('Voter view-time claim exceeds server elapsed', [
                'view_session_id' => $session->id,
                'voter_id'       => $session->voter_id,
                'client_claim'   => $clientClaim,
                'server_elapsed' => $elapsed,
            ]);
        }

        return min($clientClaim, $elapsed);
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

    /**
     * SEC-4: rotate the caller's voter API token.
     *
     * Requires the current token via the voter-token middleware, which stores
     * the resolved voter + token record on the request. Issues a new token for
     * the same voter and revokes (deletes) the current one. The new plaintext is
     * returned exactly once.
     */
    public function rotateToken(Request $request): JsonResponse
    {
        $voter = $request->attributes->get(AuthenticateVoterToken::VOTER_ATTR);
        $current = $request->attributes->get(AuthenticateVoterToken::TOKEN_ATTR);

        $issued = VoterApiToken::createToken($voter);

        if ($current instanceof VoterApiToken) {
            $current->delete();
        }

        return response()->json([
            'message' => 'Voter API token rotated',
            'token'   => $issued['token'],
        ]);
    }
}
