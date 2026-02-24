<?php

namespace App\Http\Controllers\Standalone;

use App\Http\Controllers\Controller;
use App\Models\AdViewToken;
use App\Models\PoliticalCampaign;
use App\Models\Voter;
use App\Models\ViewSession;
use App\Services\PoliticalViewService;
use App\Services\ReverbBroadcastService;
use App\Enums\CampaignStatus;
use App\Enums\ApprovalStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

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

        // Primary lookup: via the user_id foreign key
        if ($voter = $user->voter) {
            return $voter;
        }

        // Fallback: find an orphaned voter row matched by email (NULL user_id
        // caused by a registration race condition) and stitch the FK.
        $voter = Voter::where('email', $user->email)
            ->whereNull('user_id')
            ->first();

        if ($voter) {
            $voter->update(['user_id' => $user->id]);
            return $voter->fresh();
        }

        // Last resort: create a new voter profile linked to this user.
        return Voter::firstOrCreate(
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

    // ── Ad Viewing Room ──────────────────────────────────────

    /**
     * GET /voter/ad-room
     * List all active campaigns available for this voter to watch.
     *
     * Filtering priority:
     *  1. Campaigns the voter has NOT already completed (no duplicate payout).
     *  2. Campaigns with remaining views (views_completed < total_views_requested).
     *  3. Active + admin-approved only.
     *  4. Soft-match voter's preferred_governance_levels & state if set.
     */
    public function adRoom(Request $request)
    {
        $voter = $this->resolveVoter();

        // IDs of campaigns the voter already completed (no re-watching for pay)
        $completedCampaignIds = $voter->viewSessions()
            ->where('status', 'completed')
            ->pluck('political_campaign_id')
            ->all();

        // IDs of campaigns with an in-progress (unexpired) token for this voter
        $inProgressTokenCampaignIds = AdViewToken::where('voter_id', $voter->id)
            ->where('is_used', false)
            ->where('is_expired', false)
            ->where('expires_at', '>', now())
            ->pluck('political_campaign_id')
            ->all();

        // Voter's stored governance-level preferences
        $voterPrefs = $voter->preferred_governance_levels ?? [];

        $query = PoliticalCampaign::with('politician:id,full_name,political_office,governance_level,profile_photo_url,verified_official,slug,page_published')
            ->where('status', CampaignStatus::Active)
            ->where('approval_status', ApprovalStatus::Approved)
            ->whereColumn('views_completed', '<', 'total_views_requested')
            ->whereNotIn('id', $completedCampaignIds);

        // Text search (title / message summary)
        if ($q = $request->input('q')) {
            $query->where(function ($s) use ($q) {
                $s->where('title', 'like', "%{$q}%")
                  ->orWhere('message_summary', 'like', "%{$q}%");
            });
        }

        // Explicit governance-level filter from the URL (?level=)
        if ($levelFilter = $request->input('level')) {
            $query->where('governance_level', $levelFilter);
        } elseif (! empty($voterPrefs)) {
            // Voter preference soft-filter only when no explicit level chosen
            $query->whereIn('governance_level', $voterPrefs);
        }

        // State filter: campaigns targeting voter's state (or no state restriction)
        if ($voter->state) {
            $query->where(function ($q) use ($voter) {
                $q->whereNull('target_states')
                  ->orWhereJsonContains('target_states', $voter->state);
            });
        }

        $campaigns = $query
            ->orderByDesc('revenue_per_view')
            ->orderByDesc('updated_at')
            ->paginate(12)
            ->withQueryString();

        // Today's view count for this voter (fraud / daily limit display)
        $viewsToday = $voter->viewSessions()
            ->whereDate('created_at', today())
            ->count();

        $dailyLimit  = (int) config('u9itus.fraud.max_views_per_voter_per_day', 50);
        $canViewMore = $viewsToday < $dailyLimit && ! $voter->flagged_for_fraud && $voter->is_active;

        return view('standalone.voter.ad-room', [
            'voter'                    => $voter,
            'campaigns'                => $campaigns,
            'viewsToday'               => $viewsToday,
            'dailyLimit'               => $dailyLimit,
            'canViewMore'              => $canViewMore,
            'inProgressTokenCampaignIds' => $inProgressTokenCampaignIds,
            'completedCampaignIds'     => $completedCampaignIds,
        ]);
    }

    /**
     * POST /voter/campaigns/{campaign}/claim
     * Mint a one-time AdViewToken for the voter and redirect to the watch page.
     * This is the "Watch Now" action from the Ad Viewing Room.
     */
    public function claimCampaign(Request $request, PoliticalCampaign $campaign)
    {
        $voter = $this->resolveVoter();

        // Guard: campaign must still be active
        if ($campaign->status !== CampaignStatus::Active
            || $campaign->approval_status !== ApprovalStatus::Approved) {
            return back()->withErrors(['claim' => 'This campaign is no longer available.']);
        }

        // Guard: no more views needed
        if ($campaign->views_completed >= $campaign->total_views_requested) {
            return back()->withErrors(['claim' => 'This campaign has reached its view target.']);
        }

        // Guard: voter eligibility
        if (! $voter->canViewToday()) {
            return back()->withErrors([
                'claim' => 'You have reached your daily viewing limit or your account is restricted.',
            ]);
        }

        // Guard: voter already completed this campaign
        $alreadyCompleted = $voter->viewSessions()
            ->where('political_campaign_id', $campaign->id)
            ->where('status', 'completed')
            ->exists();

        if ($alreadyCompleted) {
            return back()->withErrors(['claim' => 'You have already watched this campaign.']);
        }

        // Re-use an existing unexpired token for this campaign if one exists
        $existing = AdViewToken::where('voter_id', $voter->id)
            ->where('political_campaign_id', $campaign->id)
            ->where('is_used', false)
            ->where('is_expired', false)
            ->where('expires_at', '>', now())
            ->first();

        if ($existing) {
            return redirect()->route('voter.watch', $existing->token);
        }

        // Mint a new token (no email/SMS — direct from Ad Room)
        $token = AdViewToken::create([
            'political_campaign_id' => $campaign->id,
            'voter_id'              => $voter->id,
            'notification_method'   => 'direct',
            'sent_to'               => $voter->email ?? 'direct',
            'sent_at'               => now(),
        ]);

        Log::info('AdViewToken minted from Ad Room', [
            'voter_id'    => $voter->id,
            'campaign_id' => $campaign->id,
            'token'       => substr($token->token, 0, 8) . '…',
        ]);

        // Notify the voter's WebSocket channel (Phase 11 — useful when voter
        // has the dashboard open on a second tab or device)
        app(ReverbBroadcastService::class)->adTokenDelivered($token);

        return redirect()->route('voter.watch', $token->token);
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

        $duration  = (int) ($campaign->media_duration ?? config('u9itus.max_video_duration', 20));
        $mustWatch = (int) ($campaign->min_watch_time_percent ?? config('u9itus.min_watch_time_percent', 80));
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

        $session = ViewSession::where('uuid', $sessionUuid)
            ->with('campaign')
            ->firstOrFail();
        $voter   = $this->resolveVoter();

        if ($session->voter_id !== $voter->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $watchedSeconds = (int) $request->seconds_watched;
        $this->viewService->trackProgress($session, $watchedSeconds);

        // Already completed via a previous heartbeat — just report status
        if ($session->status === \App\Enums\ViewSessionStatus::Completed) {
            return response()->json([
                'ok'             => true,
                'already_completed' => true,
                'qualified'      => $session->payment_status?->value === 'approved',
                'payout_earned'  => (float) $session->voter_payout_amount,
            ]);
        }

        // Auto-complete when the voter has watched enough to qualify,
        // even if the video hasn't fired the 'ended' event yet.
        $campaign      = $session->campaign;
        $mediaDuration = (int) ($campaign->media_duration ?? config('u9itus.max_video_duration', 20));
        $minWatchPct   = (float) ($campaign->min_watch_time_percent ?? config('u9itus.min_watch_time_percent', 80));
        $watchedPct    = $mediaDuration > 0 ? ($watchedSeconds / $mediaDuration) * 100 : 0;

        if ($watchedPct >= $minWatchPct) {
            $completed = $this->viewService->completeView($session, $watchedSeconds);
            return response()->json([
                'ok'             => true,
                'auto_completed' => true,
                'qualified'      => $completed->payment_status?->value === 'approved',
                'payout_earned'  => (float) $completed->voter_payout_amount,
            ]);
        }

        return response()->json(['ok' => true, 'watched_pct' => round($watchedPct, 1)]);
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
            'qualified'          => $completed->payment_status?->value === 'approved',
            'payout_earned'      => (float) $completed->voter_payout_amount,
            'status'             => $completed->status->value,
            'already_completed'  => $session->status === \App\Enums\ViewSessionStatus::Completed
                                    && $completed->status === \App\Enums\ViewSessionStatus::Completed
                                    && ! $session->wasChanged(),
        ]);
    }

    // ── In-Watch Interactions ────────────────────────────────

    /**
     * Report a technical or content issue with the currently-playing ad.
     * Stores the report and notifies admin via email.
     */
    public function reportIssue(Request $request, string $token)
    {
        $validated = $request->validate([
            'issue_category'    => 'required|in:video_not_playing,incorrect_info,offensive_content,other',
            'body'              => 'nullable|string|max:1000',
            'view_session_uuid' => 'nullable|string|max:36',
        ]);

        $adToken = \App\Models\AdViewToken::where('token', $token)->firstOrFail();
        $voter   = $this->resolveVoter();
        $campaignId = $adToken->political_campaign_id;

        \App\Models\VoterWatchReport::create([
            'voter_id'          => $voter->id,
            'campaign_id'       => $campaignId,
            'view_session_uuid' => $validated['view_session_uuid'] ?? null,
            'type'              => 'issue',
            'issue_category'    => $validated['issue_category'],
            'body'              => $validated['body'] ?? '',
            'status'            => 'open',
        ]);

        // Notify admin
        try {
            \Illuminate\Support\Facades\Mail::raw(
                "Issue reported by voter #{$voter->id} ({$voter->email}) on campaign #{$campaignId}.\n"
                . "Category: {$validated['issue_category']}\n"
                . "Message: " . ($validated['body'] ?? '(none)'),
                fn ($m) => $m->to(config('mail.from.address', 'admin@u9itus.com'))
                              ->subject('[U9itus] Ad Issue Report – Campaign #' . $campaignId)
            );
        } catch (\Throwable $e) {
            Log::warning('reportIssue: mail failed', ['error' => $e->getMessage()]);
        }

        return response()->json(['success' => true, 'message' => 'Your report has been submitted. Thank you!']);
    }

    /**
     * Send a direct message from the voter to the politician running the campaign.
     * Stores the message and notifies the politician via email.
     */
    public function messagePolitician(Request $request, string $token)
    {
        $validated = $request->validate([
            'body'              => 'required|string|max:1000',
            'view_session_uuid' => 'nullable|string|max:36',
        ]);

        $adToken   = \App\Models\AdViewToken::where('token', $token)->firstOrFail();
        $voter     = $this->resolveVoter();
        $campaign  = \App\Models\PoliticalCampaign::with('politician.user')->findOrFail($adToken->political_campaign_id);
        $politician = $campaign->politician;

        \App\Models\VoterWatchReport::create([
            'voter_id'          => $voter->id,
            'campaign_id'       => $adToken->political_campaign_id,
            'view_session_uuid' => $validated['view_session_uuid'] ?? null,
            'type'              => 'message',
            'issue_category'    => null,
            'body'              => $validated['body'],
            'status'            => 'open',
        ]);

        // Notify politician (email is on the related User record)
        $politicianEmail = $politician?->user?->email ?? null;
        if ($politicianEmail) {
            try {
                \Illuminate\Support\Facades\Mail::raw(
                    "A voter has sent you a message regarding your campaign \"{$campaign->title}\".\n\n"
                    . "Message:\n" . $validated['body'] . "\n\n"
                    . "Sent by voter: {$voter->full_name} ({$voter->email})\n"
                    . "Platform: U9itus",
                    fn ($m) => $m->to($politicianEmail)
                                  ->subject('[U9itus] Voter Message – ' . $campaign->title)
                );
            } catch (\Throwable $e) {
                Log::warning('messagePolitician: mail failed', ['error' => $e->getMessage()]);
            }
        }

        return response()->json(['success' => true, 'message' => 'Your message has been sent to the campaign team!']);
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

        // Voters referred by this voter
        $referrals = $voter->referrals()
            ->with('user:id,name,created_at')
            ->latest()
            ->get();

        // Politicians referred by this voter
        $referredPoliticians = \App\Models\Politician::where('referred_by_voter_id', $voter->id)
            ->with('user:id,name,created_at')
            ->latest()
            ->get();

        // Per-view referral earnings (voter_view type)
        $referralEarnings = $voter->referralEarnings()
            ->voterViews()
            ->with('viewSession.campaign')
            ->latest()
            ->take(20)
            ->get();

        // Procurement commission earnings (politician_procurement type)
        $procurementEarnings = $voter->referralEarnings()
            ->procurements()
            ->with('politician')
            ->latest()
            ->get();

        $totalReferralEarnings  = (float) $voter->referralEarnings()->voterViews()->sum('commission_amount');
        $totalProcurementEarnings = (float) $voter->referralEarnings()->procurements()->sum('commission_amount');

        return view('standalone.voter.referrals', compact(
            'voter', 'referrals', 'referredPoliticians',
            'referralEarnings', 'procurementEarnings',
            'totalReferralEarnings', 'totalProcurementEarnings'
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

    /**
     * Upload a government-issued ID document for KYC (Know Your Customer) verification.
     *
     * Accepts jpg/jpeg/png/pdf up to 5 MB. Stores on the `public` disk under
     * `kyc/{user_id}/document.{ext}` and resets kyc_status to 'pending' so
     * the admin is prompted to review the new document.
     */
    public function uploadKycDocument(Request $request)
    {
        $request->validate([
            'kyc_document' => [
                'required',
                'file',
                'mimes:jpg,jpeg,png,pdf',
                'max:5120', // 5 MB
            ],
        ]);

        $user = Auth::user();
        $file = $request->file('kyc_document');

        // Delete old document if one exists
        if ($user->kyc_document_path) {
            Storage::disk('public')->delete($user->kyc_document_path);
        }

        $ext  = $file->getClientOriginalExtension();
        $path = $file->storeAs("kyc/{$user->id}", "document.{$ext}", 'public');

        // Save path and reset KYC status to pending so admin reviews the new doc
        $user->update([
            'kyc_document_path'    => $path,
            'kyc_status'           => 'pending',
            'kyc_reviewed_at'      => null,
            'kyc_reviewer_id'      => null,
            'kyc_rejection_reason' => null,
        ]);

        return back()->with('kyc_success', 'Document uploaded successfully. Your identity is now pending review.');
    }
}
