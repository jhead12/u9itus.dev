<?php

namespace App\Http\Controllers\Standalone;

use App\Http\Controllers\Controller;
use App\Models\AdViewToken;
use App\Models\EngagementSurveyResponse;
use App\Models\PoliticalCampaign;
use App\Models\ReferralVisit;
use App\Models\Voter;
use App\Models\ViewSession;
use App\Services\PlatformSettingsService;
use App\Services\PoliticalViewService;
use App\Services\ReverbBroadcastService;
use App\Enums\CampaignStatus;
use App\Enums\ApprovalStatus;
use App\Enums\ViewPaymentStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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

    /**
     * Resolve a campaign media URL into a playback-safe URL.
     *
     * For S3-backed/private media we generate a short-lived signed URL.
     * For YouTube/Vimeo and already-public URLs we return the original value.
     */
    private function resolvePlayableMediaUrl(PoliticalCampaign $campaign): ?string
    {
        $rawMediaUrl = trim((string) ($campaign->media_url ?? ''));
        if ($rawMediaUrl === '') {
            return null;
        }

        $mediaType = strtolower((string) ($campaign->media_type ?? ''));
        if (in_array($mediaType, ['youtube', 'vimeo'], true)) {
            return $rawMediaUrl;
        }

        $isAbsoluteUrl = filter_var($rawMediaUrl, FILTER_VALIDATE_URL) !== false;
        $isS3Like = str_contains($rawMediaUrl, '.amazonaws.com')
            || str_contains($rawMediaUrl, '/s3/')
            || str_contains($rawMediaUrl, 's3.')
            || str_starts_with($rawMediaUrl, 'campaigns/');

        if (! $isS3Like) {
            return $rawMediaUrl;
        }

        // Prefer explicit s3 disk when configured; fallback to default disk.
        $disk = config('filesystems.disks.s3') ? 's3' : (string) config('filesystems.default', 'local');

        try {
            $path = $rawMediaUrl;
            if ($isAbsoluteUrl) {
                $urlParts = parse_url($rawMediaUrl);
                $path = ltrim((string) ($urlParts['path'] ?? ''), '/');
            }

            if ($path === '') {
                return $rawMediaUrl;
            }

            $bucketName = (string) config('filesystems.disks.s3.bucket', '');
            if ($bucketName !== '' && str_starts_with($path, $bucketName . '/')) {
                $path = substr($path, strlen($bucketName) + 1);
            }

            if ($path === '') {
                return $rawMediaUrl;
            }

            return Storage::disk($disk)->temporaryUrl($path, now()->addMinutes(30));
        } catch (\Throwable $e) {
            Log::warning('Unable to generate temporary media URL for voter watch playback', [
                'campaign_id' => $campaign->id,
                'media_type' => $campaign->media_type,
                'error' => $e->getMessage(),
            ]);

            return $rawMediaUrl;
        }
    }

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

    private function makePublicAlias(Voter $voter, int $campaignId): string
    {
        $seed = hash_hmac('sha256', $campaignId . '|' . $voter->id, (string) config('app.key'));
        return 'Voter #' . Str::upper(substr($seed, 0, 6));
    }

    /**
     * Campaign IDs this voter should not see in earnable inventory yet.
     *
     * Includes:
     * - non-repeat campaigns already completed by the voter
     * - repeat campaigns where max per-voter views is reached
     * - repeat campaigns where cooldown has not elapsed since last completion
     *
     * @return array<int, int>
     */
    private function excludedCampaignIdsForVoter(Voter $voter): array
    {
        $noRepeatDoneIds = DB::table('view_sessions')
            ->join('political_campaigns', 'political_campaigns.id', '=', 'view_sessions.political_campaign_id')
            ->where('view_sessions.voter_id', $voter->id)
            ->where('view_sessions.status', 'completed')
            ->where('political_campaigns.allow_repeat_views', false)
            ->distinct()
            ->pluck('view_sessions.political_campaign_id')
            ->all();

        $atCapIds = DB::table('view_sessions')
            ->join('political_campaigns', 'political_campaigns.id', '=', 'view_sessions.political_campaign_id')
            ->where('view_sessions.voter_id', $voter->id)
            ->where('view_sessions.status', 'completed')
            ->where('political_campaigns.allow_repeat_views', true)
            ->selectRaw('view_sessions.political_campaign_id, COUNT(*) as cnt, political_campaigns.max_views_per_voter')
            ->groupBy('view_sessions.political_campaign_id', 'political_campaigns.max_views_per_voter')
            ->havingRaw('cnt >= COALESCE(political_campaigns.max_views_per_voter, 1)')
            ->pluck('political_campaign_id')
            ->all();

        $nowTs = now()->timestamp;
        $cooldownBlockedIds = DB::table('view_sessions')
            ->join('political_campaigns', 'political_campaigns.id', '=', 'view_sessions.political_campaign_id')
            ->where('view_sessions.voter_id', $voter->id)
            ->where('view_sessions.status', 'completed')
            ->where('political_campaigns.allow_repeat_views', true)
            ->selectRaw('view_sessions.political_campaign_id as campaign_id, MAX(view_sessions.completed_at) as last_completed_at, COALESCE(political_campaigns.repeat_view_cooldown_hours, 1) as cooldown_hours')
            ->groupBy('view_sessions.political_campaign_id', 'political_campaigns.repeat_view_cooldown_hours')
            ->get()
            ->filter(function ($row) use ($nowTs) {
                $lastCompletedTs = strtotime((string) ($row->last_completed_at ?? ''));
                if (! $lastCompletedTs) {
                    return false;
                }

                $cooldownHours = max(1, (int) ($row->cooldown_hours ?? 1));
                $nextEligibleTs = $lastCompletedTs + ($cooldownHours * 3600);

                return $nextEligibleTs > $nowTs;
            })
            ->pluck('campaign_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return array_values(array_unique(array_merge($noRepeatDoneIds, $atCapIds, $cooldownBlockedIds)));
    }

    private function questionRateLimitConfig(): array
    {
        return [
            'max_attempts' => max(1, (int) config('u9itus.q_and_a.rate_limit.max_attempts', 5)),
            'decay_seconds' => max(1, (int) config('u9itus.q_and_a.rate_limit.decay_seconds', 600)),
        ];
    }

    /**
     * Resolve the duration used for watch qualification.
     *
     * Priority:
     * 1) persisted campaign media_duration
     * 2) client-reported media duration (from player metadata)
     * 3) platform fallback max_video_duration
     */
    private function effectiveWatchDurationSeconds(PoliticalCampaign $campaign, ?int $clientDuration = null): int
    {
        $campaignDuration = (int) ($campaign->media_duration ?? 0);
        if ($campaignDuration > 0) {
            return $campaignDuration;
        }

        $reportedDuration = (int) ($clientDuration ?? 0);
        if ($reportedDuration > 0) {
            return $reportedDuration;
        }

        $durationFallback = (int) PlatformSettingsService::get(
            'max_video_duration',
            null,
            (int) config('u9itus.max_video_duration', 180)
        );

        return max(1, $durationFallback);
    }

    private function questionContainsBlockedTerms(string $text): bool
    {
        $terms = config('u9itus.q_and_a.moderation.blocked_terms', []);
        if (!is_array($terms) || empty($terms)) {
            return false;
        }

        $normalized = Str::lower(trim($text));
        if ($normalized === '') {
            return true;
        }

        foreach ($terms as $term) {
            $needle = Str::lower(trim((string) $term));
            if ($needle !== '' && str_contains($normalized, $needle)) {
                return true;
            }
        }

        return false;
    }

    // ── Dashboard ────────────────────────────────────────────

    public function dashboard()
    {
        $voter   = $this->resolveVoter();
        $summary = $this->viewService->voterEarningsSummary($voter);

        // Surface watchable campaigns directly on dashboard so voters can start earning immediately.
        $excludedCampaignIds = $this->excludedCampaignIdsForVoter($voter);

        $voterPrefs = $voter->preferred_governance_levels ?? [];

        $availableCampaignsQuery = PoliticalCampaign::needingViews()
            ->with('politician:id,full_name,political_office,governance_level,profile_photo_url,verified_official,slug,page_published')
            ->whereNotIn('id', $excludedCampaignIds);

        if (! empty($voterPrefs)) {
            $availableCampaignsQuery->whereIn('governance_level', $voterPrefs);
        }

        if ($voter->state) {
            $availableCampaignsQuery->where(function ($q) use ($voter) {
                $q->whereNull('target_states')
                  ->orWhereJsonContains('target_states', $voter->state);
            });
        }

        $availableCampaignsCount = (clone $availableCampaignsQuery)->count();
        $availableCampaigns = $availableCampaignsQuery
            ->orderByDesc('revenue_per_view')
            ->orderByDesc('updated_at')
            ->take(6)
            ->get();

        $recentSessions = $voter->viewSessions()
            ->with('campaign.politician')
            ->latest()
            ->take(10)
            ->get();

        // Get active promotions relevant to voters
        $activePromotions = \App\Models\PlatformSetting::active()
            ->whereNotNull('effective_until')
            ->whereIn('category', ['pricing', 'referral'])
            ->orderBy('effective_until')
            ->get();

        return view('standalone.voter.dashboard', [
            'user'            => Auth::user(),
            'voter'           => $voter,
            'summary'         => $summary,
            'availableCampaigns' => $availableCampaigns,
            'availableCampaignsCount' => $availableCampaignsCount,
            'recentSessions'  => $recentSessions,
            'activePromotions' => $activePromotions,
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

        // All campaign IDs the voter has completed at least once (for "Watch Again" label in blade)
        $watchedBeforeIds = $voter->viewSessions()
            ->where('status', 'completed')
            ->pluck('political_campaign_id')
            ->unique()
            ->all();

        // Campaigns this voter cannot currently earn from
        // (already completed non-repeat, at repeat-cap, or still inside cooldown window)
        $excludedCampaignIds = $this->excludedCampaignIdsForVoter($voter);

        // IDs of campaigns with an in-progress (unexpired) token for this voter
        $inProgressTokenCampaignIds = AdViewToken::where('voter_id', $voter->id)
            ->where('is_used', false)
            ->where('is_expired', false)
            ->where('expires_at', '>', now())
            ->pluck('political_campaign_id')
            ->all();

        // Voter's stored governance-level preferences
        $voterPrefs = $voter->preferred_governance_levels ?? [];

        $query = PoliticalCampaign::needingViews()
            ->with(['politician:id,full_name,political_office,governance_level,profile_photo_url,verified_official,slug,page_published', 'topics'])
            // Count recent open issue reports (last 7 days) for visual warning indicator
            ->withCount([
                'voterWatchReports as recent_reports_count' => function ($q) {
                    $q->where('type', 'issue')
                      ->where('status', 'open')
                      ->where('created_at', '>=', now()->subDays(7));
                }
            ])
            ->whereNotIn('id', $excludedCampaignIds);

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

        // Sprint 3: Topic filter from URL (?topic_id=)
        if ($topicId = $request->input('topic_id')) {
            $query->whereHas('topics', function ($q) use ($topicId) {
                $q->where('politician_topics.id', $topicId);
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

        // Sprint 3: Get all active topics for the filter dropdown
        $topics = \App\Models\PoliticianTopic::active()->orderBy('sort_order')->get();

        return view('standalone.voter.ad-room', [
            'voter'                    => $voter,
            'campaigns'                => $campaigns,
            'viewsToday'               => $viewsToday,
            'dailyLimit'               => $dailyLimit,
            'canViewMore'              => $canViewMore,
            'inProgressTokenCampaignIds' => $inProgressTokenCampaignIds,
            'watchedBeforeIds'         => $watchedBeforeIds,
            'excludedCampaignIds'      => $excludedCampaignIds,
            'topics'                   => $topics,
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

        // Guard: check whether this voter is allowed to (re-)watch this campaign.
        // voterCanWatch() respects allow_repeat_views, max_views_per_voter, and cooldown hours.
        if (! $this->viewService->voterCanWatch($campaign, $voter)) {
            $completedCount = $campaign->voterCompletedViewCount($voter->id);
            $message = ($campaign->allow_repeat_views && $completedCount > 0)
                ? 'You have reached the maximum number of views allowed for this campaign.'
                : 'You have already watched this campaign.';
            return back()->withErrors(['claim' => $message]);
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

        $durationFallback = (int) PlatformSettingsService::get('max_video_duration', null, (int) config('u9itus.max_video_duration', 180));
        $duration  = (int) ($campaign->media_duration ?? max(1, $durationFallback));
        $mustWatch = (int) ($campaign->min_watch_time_percent ?? config('u9itus.min_watch_time_percent', 80));
        $payout    = (float) ($campaign->voter_payout_per_view ?? PlatformSettingsService::get('viewer_payout_per_view', null, 0.25));

        $playableMediaUrl = $this->resolvePlayableMediaUrl($campaign);
        if ($playableMediaUrl) {
            $campaign->media_url = $playableMediaUrl;
        }

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
        $request->validate([
            'seconds_watched' => 'required|integer|min:0',
            'media_duration_seconds' => 'nullable|integer|min:1|max:21600',
        ]);

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
        $mediaDuration = $this->effectiveWatchDurationSeconds(
            $campaign,
            $request->integer('media_duration_seconds')
        );
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
        $request->validate([
            'total_seconds_watched' => 'required|integer|min:0',
            'media_duration_seconds' => 'nullable|integer|min:1|max:21600',
        ]);

        $session = ViewSession::where('uuid', $sessionUuid)
            ->with('campaign', 'voter')
            ->firstOrFail();

        $voter = $this->resolveVoter();
        if ($session->voter_id !== $voter->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Keep completion math aligned with the player when the campaign record has no duration.
        if (! $session->campaign->media_duration) {
            $session->campaign->media_duration = $this->effectiveWatchDurationSeconds(
                $session->campaign,
                $request->integer('media_duration_seconds')
            );
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

    /**
     * POST /voter/session/{sessionUuid}/survey
     * Persist a post-view engagement survey response.
     */
    public function submitSurvey(Request $request, string $sessionUuid)
    {
        $validated = $request->validate([
            'response_value' => 'required|string|max:255',
            'response_text' => 'nullable|string|max:2000',
        ]);

        $session = ViewSession::where('uuid', $sessionUuid)
            ->with('campaign', 'voter')
            ->firstOrFail();

        $voter = $this->resolveVoter();
        if ($session->voter_id !== $voter->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if ($session->status !== \App\Enums\ViewSessionStatus::Completed) {
            return response()->json(['error' => 'You can submit a survey only after completing the watch session.'], 422);
        }

        $campaign = $session->campaign;
        $survey = $campaign?->engagement_survey;

        if (! is_array($survey) || empty($survey['options']) || ! is_array($survey['options'])) {
            return response()->json(['error' => 'No engagement survey configured for this campaign.'], 422);
        }

        $validValues = collect($survey['options'])
            ->pluck('value')
            ->filter(fn ($value) => is_string($value) && $value !== '')
            ->values()
            ->all();

        if (! in_array($validated['response_value'], $validValues, true)) {
            return response()->json(['error' => 'Invalid survey response option.'], 422);
        }

        EngagementSurveyResponse::recordResponse(
            $session,
            $voter,
            $campaign,
            $validated['response_value'],
            $validated['response_text'] ?? null
        );

        return response()->json([
            'success' => true,
            'message' => 'Thanks for sharing your response.',
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
     * Send a voter-submitted question to the politician running the campaign.
     * Stores the question and notifies the politician via email.
     */
    public function askQuestion(Request $request, string $token)
    {
        return $this->messagePolitician($request, $token);
    }

    /**
     * Backward-compatible endpoint for voter-to-politician questions.
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

        if ($this->questionContainsBlockedTerms($validated['body'])) {
            return response()->json([
                'success' => false,
                'message' => 'Your question contains blocked language and could not be submitted.',
            ], 422);
        }

        $rateConfig = $this->questionRateLimitConfig();
        $rateKey = 'question-submit:' . $voter->id . ':' . (int) $adToken->political_campaign_id;

        if (RateLimiter::tooManyAttempts($rateKey, $rateConfig['max_attempts'])) {
            return response()->json([
                'success' => false,
                'message' => 'You are submitting too quickly. Please wait before sending another question.',
                'retry_after_seconds' => RateLimiter::availableIn($rateKey),
            ], 429);
        }

        RateLimiter::hit($rateKey, $rateConfig['decay_seconds']);

        \App\Models\VoterWatchReport::create([
            'voter_id'          => $voter->id,
            'campaign_id'       => $adToken->political_campaign_id,
            'view_session_uuid' => $validated['view_session_uuid'] ?? null,
            'type'              => 'message',
            'issue_category'    => null,
            'body'              => $validated['body'],
            'status'            => 'open',
            'public_visibility' => 'pending',
            'is_public_board'   => false,
            'public_alias'      => $this->makePublicAlias($voter, (int) $adToken->political_campaign_id),
        ]);

        // Notify politician (email is on the related User record)
        $politicianEmail = $politician?->user?->email ?? null;
        if ($politicianEmail) {
            try {
                \Illuminate\Support\Facades\Mail::raw(
                    "A voter asked you a question regarding your campaign \"{$campaign->title}\".\n\n"
                    . "Question:\n" . $validated['body'] . "\n\n"
                    . "Sent by voter: {$voter->full_name} ({$voter->email})\n"
                    . "Platform: U9itus",
                    fn ($m) => $m->to($politicianEmail)
                                  ->subject('[U9itus] Voter Question – ' . $campaign->title)
                );
            } catch (\Throwable $e) {
                Log::warning('messagePolitician: mail failed', ['error' => $e->getMessage()]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Your question was received. It will appear publicly after moderation review.',
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
        $minPayout = (float) PlatformSettingsService::get('min_payout_amount', null, 5.00);

        if ((float) $voter->pending_earnings < $minPayout) {
            return back()->withErrors([
                'payout' => "Minimum payout is \${$minPayout}. You have \${$voter->pending_earnings} pending.",
            ]);
        }

        $payoutAmount = (float) $voter->pending_earnings;
        $selectedProcessor = match ((string) ($voter->payment_method ?? '')) {
            'paypal' => 'paypal',
            'cashapp' => 'cashapp',
            default => 'wallet',
        };

        // Ensure payout-eligible completed sessions are marked approved and carry
        // the voter's latest processor preference for downstream payout routing.
        $updated = DB::transaction(function () use ($voter, $selectedProcessor): int {
            return ViewSession::where('voter_id', $voter->id)
                ->where('status', \App\Enums\ViewSessionStatus::Completed)
                ->whereIn('payment_status', [
                    ViewPaymentStatus::Pending,
                    ViewPaymentStatus::Approved,
                ])
                ->where('voter_payout_amount', '>', 0)
                ->update([
                    'payment_status' => ViewPaymentStatus::Approved,
                    'processor_selected' => $selectedProcessor,
                ]);
        });

        Log::info('Payout requested', [
            'voter_id'        => $voter->id,
            'amount'          => $payoutAmount,
            'method'          => $voter->payment_method,
            'sessions_queued' => $updated,
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
            ->forActiveStripeMode()
            ->with('viewSession.campaign')
            ->latest()
            ->take(20)
            ->get();

        // Procurement commission earnings (politician_procurement type)
        $procurementEarnings = $voter->referralEarnings()
            ->procurements()
            ->forActiveStripeMode()
            ->with('politician')
            ->latest()
            ->get();

        $totalReferralEarnings  = (float) $voter->referralEarnings()->voterViews()->forActiveStripeMode()->sum('commission_amount');
        $totalProcurementEarnings = (float) $voter->referralEarnings()->procurements()->forActiveStripeMode()->sum('commission_amount');

        $visitQuery = ReferralVisit::where('referrer_voter_id', $voter->id);
        $totalReferralVisits = (clone $visitQuery)->count();
        $uniqueReferralVisitors = (clone $visitQuery)
            ->whereNotNull('session_id')
            ->distinct('session_id')
            ->count('session_id');
        $referralConversions = (clone $visitQuery)->whereNotNull('converted_at')->count();
        $referralConversionRate = $totalReferralVisits > 0
            ? round(($referralConversions / $totalReferralVisits) * 100, 1)
            : 0.0;

        return view('standalone.voter.referrals', compact(
            'voter', 'referrals', 'referredPoliticians',
            'referralEarnings', 'procurementEarnings',
            'totalReferralEarnings', 'totalProcurementEarnings',
            'totalReferralVisits', 'uniqueReferralVisitors',
            'referralConversions', 'referralConversionRate'
        ));
    }

    public function getReferralLink()
    {
        $voter = $this->resolveVoter();
        $link  = url('/?ref=' . $voter->referral_code . '&target=voter');

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
            'full_name'           => 'required|string|max:255',
            'phone'               => 'nullable|string|max:30',
            'state'               => 'nullable|string|max:2',
            'city'                => 'nullable|string|max:100',
            'zip_code'            => 'nullable|string|max:10',
            'is_registered_voter' => 'nullable|boolean',
        ]);

        // Allow explicit false (unchecked radio) to be stored
        if ($request->has('is_registered_voter')) {
            $validated['is_registered_voter'] = $request->input('is_registered_voter') === '1' ? true
                : ($request->input('is_registered_voter') === '0' ? false : null);
        }

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

    /**
     * View KYC document (self-service - voters can only view their own).
     */
    public function viewKycDocument()
    {
        $user = Auth::user();

        if (!$user->kyc_document_path) {
            abort(404, 'No KYC document found.');
        }

        $path = storage_path('app/public/' . $user->kyc_document_path);

        if (!file_exists($path)) {
            abort(404, 'KYC document file not found on server.');
        }

        $mimeType = mime_content_type($path);
        
        return response()->file($path, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="' . basename($path) . '"',
        ]);
    }

    /**
     * Update voter password
     */
    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        $user = Auth::user();

        // Verify current password
        if (!Hash::check($request->current_password, $user->password)) {
            return back()->withErrors(['current_password' => 'The current password is incorrect.']);
        }

        // Update password
        $user->update([
            'password' => Hash::make($request->new_password),
        ]);

        return back()->with('password_success', 'Password updated successfully.');
    }
}
