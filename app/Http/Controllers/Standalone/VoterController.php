<?php

namespace App\Http\Controllers\Standalone;

use App\Http\Controllers\Controller;
use App\Models\AdViewToken;
use App\Models\Voter;
use App\Models\ViewSession;
use App\Services\PoliticalViewService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Standalone Voter Controller
 *
 * Handles voter-specific features in standalone mode:
 * - Ad viewing (token-based, JS heartbeat, completion)
 * - Earnings tracking + payout requests
 * - Referrals
 * - Preferences & profile
 */
class VoterController extends Controller
{
    public function __construct(protected PoliticalViewService $viewService) {}

    // ── Helpers ──────────────────────────────────────────────

    /** Resolve and auto-create voter profile for the authenticated user. */
    private function resolveVoter(): Voter
    {
        $user = Auth::user();

        return $user->voter ?? Voter::firstOrCreate(
            ['user_id' => $user->id],
            [
                'full_name'      => $user->name,
                'email'          => $user->email,
                'phone'          => $user->phone ?? null,
                'wallet_balance' => 0,
                'trust_score'    => 100,
                'is_active'      => true,
                'is_verified'    => false,
            ]
        );
    }

    // ── Dashboard ────────────────────────────────────────────

    public function dashboard()
    {
        $voter   = $this->resolveVoter();
        $summary = $this->viewService->voterEarningsSummary($voter);

        $recentSessions = $voter->viewSessions()
            ->with('campaign.politician')
            ->latest()
            ->take(10)
            ->get();

        return view('standalone.voter.dashboard', [
            'user'           => Auth::user(),
            'voter'          => $voter,
            'summary'        => $summary,
            'recentSessions' => $recentSessions,
        ]);
    }

    // ── Watch (token-based ad delivery) ─────────────────────

    /**
     * GET /voter/watch/{token}
     * Show the ad video player for a valid one-time token.
     */
    public function watch(string $token)
    {
        $adToken = AdViewToken::where('token', $token)
            ->with('campaign.politician', 'voter')
            ->first();

        if (! $adToken) {
            return view('standalone.voter.watch-error', [
                'reason'  => 'invalid',
                'message' => 'This viewing link is invalid.',
            ]);
        }

        $adToken->checkExpiration();

        if (! $adToken->isValid()) {
            $reason = $adToken->is_used ? 'already_used' : 'expired';
            return view('standalone.voter.watch-error', [
                'reason'  => $reason,
                'message' => $adToken->is_used
                    ? 'This link has already been used.'
                    : 'This link has expired.',
            ]);
        }

        $campaign = $adToken->campaign;

        if (! $campaign || $campaign->status !== \App\Enums\CampaignStatus::Active) {
            return view('standalone.voter.watch-error', [
                'reason'  => 'unavailable',
                'message' => 'This ad is no longer available.',
            ]);
        }

        $duration  = (int) ($campaign->media_duration ?? config('u9itus.video_duration_max', 20));
        $mustWatch = (int) ($campaign->min_watch_time_percent ?? config('u9itus.min_watch_percent', 100));
        $payout    = (float) ($campaign->voter_payout_per_view ?? config('u9itus.voter_payout_per_view', 0.25));

        return view('standalone.voter.watch', compact(
            'adToken', 'campaign', 'duration', 'mustWatch', 'payout'
        ));
    }

    /**
     * POST /voter/watch/{token}/start
     * Mark session as started; returns JSON { session_id, status }.
     */
    public function startWatching(Request $request, string $token)
    {
        $adToken = AdViewToken::where('token', $token)
            ->with('campaign', 'voter')
            ->first();

        if (! $adToken || ! $adToken->isValid()) {
            return response()->json(['error' => 'Token is invalid or expired.'], 422);
        }

        $voter    = $adToken->voter ?? $this->resolveVoter();
        $campaign = $adToken->campaign;

        try {
            // Consume the token before creating the session (prevents race-condition double-start)
            $adToken->markAsUsed($request->ip(), $request->userAgent());

            $session = $this->viewService->assignView($campaign, $voter, $request);
            $this->viewService->startView($session);

            return response()->json([
                'session_id' => $session->uuid,
                'status'     => 'started',
            ]);
        } catch (\RuntimeException $e) {
            Log::warning('Watch start blocked', ['token' => $token, 'reason' => $e->getMessage()]);
            return response()->json(['error' => $e->getMessage()], 403);
        }
    }

    /**
     * POST /voter/session/{sessionUuid}/progress
     * JS heartbeat – update seconds watched every ~5 s.
     */
    public function progressHeartbeat(Request $request, string $sessionUuid)
    {
        $request->validate(['seconds_watched' => 'required|integer|min:0']);

        $session = ViewSession::where('uuid', $sessionUuid)->firstOrFail();
        $voter   = $this->resolveVoter();

        if ($session->voter_id !== $voter->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $this->viewService->trackProgress($session, (int) $request->seconds_watched);

        return response()->json(['ok' => true]);
    }

    /**
     * POST /voter/session/{sessionUuid}/complete
     * Mark view session complete and trigger earnings credit if qualified.
     */
    public function markComplete(Request $request, string $sessionUuid)
    {
        $request->validate(['total_seconds_watched' => 'required|integer|min:0']);

        $session = ViewSession::where('uuid', $sessionUuid)
            ->with('campaign', 'voter')
            ->firstOrFail();

        $voter = $this->resolveVoter();
        if ($session->voter_id !== $voter->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $completed = $this->viewService->completeView(
            $session,
            (int) $request->total_seconds_watched
        );

        return response()->json([
            'qualified'     => $completed->payment_status?->value === 'approved',
            'payout_earned' => (float) $completed->voter_payout_amount,
            'status'        => $completed->status->value,
        ]);
    }

    // ── Earnings ─────────────────────────────────────────────

    public function earnings()
    {
        $voter   = $this->resolveVoter();
        $summary = $this->viewService->voterEarningsSummary($voter);

        $sessions = $voter->viewSessions()
            ->with('campaign.politician')
            ->where('status', 'completed')
            ->latest('completed_at')
            ->paginate(15);

        return view('standalone.voter.earnings', compact('voter', 'summary', 'sessions'));
    }

    public function earningsHistory()
    {
        $voter    = $this->resolveVoter();
        $sessions = $voter->viewSessions()
            ->with('campaign.politician')
            ->latest()
            ->paginate(25);

        return view('standalone.voter.earnings-history', compact('voter', 'sessions'));
    }

    public function requestPayout(Request $request)
    {
        $voter     = $this->resolveVoter();
        $minPayout = (float) config('u9itus.batch_payout_min', 10.00);

        if ((float) $voter->pending_earnings < $minPayout) {
            return back()->withErrors([
                'payout' => "Minimum payout is \${$minPayout}. You have \${$voter->pending_earnings} pending.",
            ]);
        }

        // Placeholder — real PayPal/CashApp integration in Phase 7
        Log::info('Payout requested', [
            'voter_id' => $voter->id,
            'amount'   => $voter->pending_earnings,
            'method'   => $voter->payment_method,
        ]);

        return back()->with('success', 'Payout request received! Processing within 1–2 business days.');
    }

    // ── Referrals ────────────────────────────────────────────

    public function referrals()
    {
        $voter = $this->resolveVoter();

        $referrals = $voter->referrals()
            ->with('user:id,name,created_at')
            ->latest()
            ->get();

        $referralEarnings = $voter->referralEarnings()
            ->with('viewSession.campaign')
            ->latest()
            ->take(20)
            ->get();

        $totalReferralEarnings = (float) $voter->referralEarnings()->sum('commission_amount');

        return view('standalone.voter.referrals', compact(
            'voter', 'referrals', 'referralEarnings', 'totalReferralEarnings'
        ));
    }

    public function getReferralLink()
    {
        $voter = $this->resolveVoter();
        $link  = url('/register/voter') . '?ref=' . $voter->referral_code;

        return response()->json(['link' => $link, 'code' => $voter->referral_code]);
    }

    // ── Preferences ──────────────────────────────────────────

    public function preferences()
    {
        $voter = $this->resolveVoter();
        return view('standalone.voter.preferences', compact('voter'));
    }

    public function updatePreferences(Request $request)
    {
        $validated = $request->validate([
            'payment_method'                => 'nullable|in:paypal,cashapp',
            'paypal_email'                  => 'nullable|email|max:255',
            'cashapp_tag'                   => 'nullable|string|max:100',
            'preferred_governance_levels'   => 'nullable|array',
            'preferred_governance_levels.*' => 'string|max:50',
        ]);

        $voter = $this->resolveVoter();
        $voter->update(array_filter($validated, fn ($v) => ! is_null($v)));

        return back()->with('success', 'Preferences updated successfully.');
    }

    // ── Profile ──────────────────────────────────────────────

    public function profile()
    {
        $voter = $this->resolveVoter();
        return view('standalone.voter.profile', [
            'user'  => Auth::user(),
            'voter' => $voter,
        ]);
    }

    public function updateProfile(Request $request)
    {
        $validated = $request->validate([
            'full_name' => 'required|string|max:255',
            'phone'     => 'nullable|string|max:30',
            'state'     => 'nullable|string|max:2',
            'city'      => 'nullable|string|max:100',
            'zip_code'  => 'nullable|string|max:10',
        ]);

        $voter = $this->resolveVoter();
        $voter->update($validated);

        // Keep User name in sync
        Auth::user()->update(['name' => $validated['full_name']]);

        return back()->with('success', 'Profile updated successfully.');
    }
}
