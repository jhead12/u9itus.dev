<?php

namespace App\Http\Controllers\Standalone;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Standalone\Concerns\ManagesVoterAuxiliaryActions;
use App\Models\AdViewToken;
use App\Models\Citizen;
use App\Models\EngagementSurveyResponse;
use App\Models\PoliticalCampaign;
use App\Models\Voter;
use App\Models\VoterWatchReport;
use App\Models\ViewSession;
use App\Services\CitizenViewService;
use App\Services\PlatformSettingsService;
use App\Services\PoliticalViewService;
use App\Services\ReverbBroadcastService;
use App\Enums\CampaignStatus;
use App\Enums\ApprovalStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

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
    use ManagesVoterAuxiliaryActions;

    public function __construct(
        protected PoliticalViewService $viewService,
        protected CitizenViewService $citizenViewService,
    ) {
    }

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

        $isAbsoluteUrl = filter_var($rawMediaUrl, FILTER_VALIDATE_URL) !== false;
        $mediaType = strtolower((string) ($campaign->media_type ?? ''));
        $isS3Like = str_contains($rawMediaUrl, '.amazonaws.com')
            || str_contains($rawMediaUrl, '/s3/')
            || str_contains($rawMediaUrl, 's3.')
            || str_starts_with($rawMediaUrl, 'campaigns/');

        if (in_array($mediaType, ['youtube', 'vimeo'], true) || ! $isS3Like) {
            return $rawMediaUrl;
        }

        $resolvedUrl = $rawMediaUrl;

        // Prefer explicit s3 disk when configured; fallback to default disk.
        $disk = config('filesystems.disks.s3') ? 's3' : (string) config('filesystems.default', 'local');

        try {
            $path = $rawMediaUrl;
            if ($isAbsoluteUrl) {
                $urlParts = parse_url($rawMediaUrl);
                $path = ltrim((string) ($urlParts['path'] ?? ''), '/');
            }

            $bucketName = (string) config('filesystems.disks.s3.bucket', '');
            if ($path !== '' && $bucketName !== '' && str_starts_with($path, $bucketName . '/')) {
                $path = substr($path, strlen($bucketName) + 1);
            }

            if ($path !== '') {
                $resolvedUrl = Storage::disk($disk)->temporaryUrl($path, now()->addMinutes(30));
            }
        } catch (\Throwable $e) {
            Log::warning('Unable to generate temporary media URL for voter watch playback', [
                'campaign_id' => $campaign->id,
                'media_type' => $campaign->media_type,
                'error' => $e->getMessage(),
            ]);
        }

        return $resolvedUrl;
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

        // ── Promoted blog posts ───────────────────────────────────────────────
        $promotedPosts = \App\Models\Post::query()
            ->with('author')
            ->published()
            ->promoted()
            ->latest('published_at')
            ->take(3)
            ->get();

        // ── Citizen campaigns (community / local / ballot-issue ads) ─────────
        $citizenCampaigns = $this->citizenViewService->availableCampaigns($voter)
            ->filter(fn ($c) => $this->citizenViewService->voterCanWatch($c, $voter))
            ->values();

        if ($q = $request->input('q')) {
            $citizenCampaigns = $citizenCampaigns->filter(function ($c) use ($q) {
                return str_contains(strtolower($c->title), strtolower($q))
                    || str_contains(strtolower((string) $c->message_summary), strtolower($q));
            })->values();
        }

        $citizenWatchedBeforeIds = \App\Models\CitizenViewSession::where('voter_id', $voter->id)
            ->where('status', 'completed')
            ->pluck('citizen_campaign_id')
            ->unique()
            ->all();

        $citizenExcludedIds = $citizenCampaigns->filter(function ($c) use ($voter) {
            return ! $this->citizenViewService->voterCanWatch($c, $voter);
        })->pluck('id')->all();

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
            'citizenCampaigns'         => $citizenCampaigns,
            'citizenWatchedBeforeIds'  => $citizenWatchedBeforeIds,
            'citizenExcludedIds'       => $citizenExcludedIds,
            'promotedPosts'            => $promotedPosts,
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
        $claimError = null;

        // Guard: campaign must still be active
        if ($campaign->status !== CampaignStatus::Active
            || $campaign->approval_status !== ApprovalStatus::Approved) {
            $claimError = 'This campaign is no longer available.';
        } elseif ($campaign->views_completed >= $campaign->total_views_requested) {
            $claimError = 'This campaign has reached its view target.';
        } elseif (! $voter->canViewToday()) {
            $claimError = 'You have reached your daily viewing limit or your account is restricted.';
        } elseif (! $this->viewService->voterCanWatch($campaign, $voter)) {
            $completedCount = $campaign->voterCompletedViewCount($voter->id);
            $claimError = ($campaign->allow_repeat_views && $completedCount > 0)
                ? 'You have reached the maximum number of views allowed for this campaign.'
                : 'You have already watched this campaign.';
        }

        if ($claimError !== null) {
            return back()->withErrors(['claim' => $claimError]);
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

        $watchError = $this->resolveWatchError($adToken);

        if ($watchError) {
            return view('standalone.voter.watch-error', $watchError);
        }

        $campaign = $adToken->campaign;

        $durationFallback = (int) PlatformSettingsService::get('max_video_duration', null, (int) config('u9itus.max_video_duration', 180));
        $duration  = (int) ($campaign->media_duration ?? max(1, $durationFallback));
        $mustWatch = (int) ($campaign->min_watch_time_percent ?? config('u9itus.min_watch_time_percent', 80));
        $payout    = (float) ($campaign->voter_payout_per_view ?? PlatformSettingsService::get('viewer_payout_per_view', null, 0.25));

        $playableMediaUrl = $this->resolvePlayableMediaUrl($campaign);
        if ($playableMediaUrl) {
            $campaign->media_url = $playableMediaUrl;
        }

        $recentPublicQuestions = $this->recentPublicQuestionsForCampaign($campaign);

        // Find next available campaign for this voter
        $voter = $adToken->voter ?? $this->resolveVoter();
        if ((int) $adToken->voter_id !== (int) $voter->id) {
            abort(403);
        }

        [$nextCampaign, $nextAdToken] = $this->nextCampaignWithTokenForVoter($voter, $campaign);

        return view('standalone.voter.watch', compact(
            'adToken', 'campaign', 'duration', 'mustWatch', 'payout', 'recentPublicQuestions', 'nextAdToken', 'nextCampaign'
        ));
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

        // Auto-complete when the voter has watched enough to qualify,
        // even if the video hasn't fired the 'ended' event yet.
        $campaign      = $session->campaign;
        $mediaDuration = $this->effectiveWatchDurationSeconds(
            $campaign,
            $request->integer('media_duration_seconds')
        );
        $minWatchPct   = (float) ($campaign->min_watch_time_percent ?? config('u9itus.min_watch_time_percent', 80));
        $watchedPct    = $mediaDuration > 0 ? ($watchedSeconds / $mediaDuration) * 100 : 0;

        $responsePayload = [
            'ok' => true,
            'watched_pct' => round($watchedPct, 1),
        ];

        if ($session->status === \App\Enums\ViewSessionStatus::Completed) {
            $freshVoter = $voter->fresh();
            $responsePayload = [
                'ok'             => true,
                'already_completed' => true,
                'qualified'      => (float) ($session->voter_payout_amount ?? 0) > 0,
                'payout_earned'  => (float) $session->voter_payout_amount,
                'pending_earnings' => (float) ($freshVoter->pending_earnings ?? 0),
                'wallet_balance' => (float) ($freshVoter->wallet_balance ?? 0),
            ];
        } elseif ($watchedPct >= $minWatchPct) {
            $completed = $this->viewService->completeView($session, $watchedSeconds);
            $freshVoter = $voter->fresh();

            $responsePayload = [
                'ok'             => true,
                'auto_completed' => true,
                'qualified'      => (float) ($completed->voter_payout_amount ?? 0) > 0,
                'payout_earned'  => (float) $completed->voter_payout_amount,
                'pending_earnings' => (float) ($freshVoter->pending_earnings ?? 0),
                'wallet_balance' => (float) ($freshVoter->wallet_balance ?? 0),
            ];
        }

        return response()->json($responsePayload);
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

        $freshVoter = $voter->fresh();

        return response()->json([
            'qualified'          => (float) ($completed->voter_payout_amount ?? 0) > 0,
            'payout_earned'      => (float) $completed->voter_payout_amount,
            'pending_earnings'   => (float) ($freshVoter->pending_earnings ?? 0),
            'wallet_balance'     => (float) ($freshVoter->wallet_balance ?? 0),
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

        $surveyError = null;

        if ($session->status !== \App\Enums\ViewSessionStatus::Completed) {
            $surveyError = 'You can submit a survey only after completing the watch session.';
        }

        $campaign = $session->campaign;
        $survey = $campaign?->engagement_survey;
        $validValues = collect(is_array($survey['options'] ?? null) ? $survey['options'] : [])
            ->pluck('value')
            ->filter(fn ($value) => is_string($value) && $value !== '')
            ->values()
            ->all();

        if ($surveyError === null && empty($validValues)) {
            $surveyError = 'No engagement survey configured for this campaign.';
        }

        if ($surveyError === null && ! in_array($validated['response_value'], $validValues, true)) {
            $surveyError = 'Invalid survey response option.';
        }

        if ($surveyError !== null) {
            return response()->json(['error' => $surveyError], 422);
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
            'reference_url' => 'nullable|url|max:2048',
            'reference_start_seconds' => 'nullable|integer|min:0|max:86400',
            'reference_end_seconds' => 'nullable|integer|min:0|max:86400|gte:reference_start_seconds',
            'reference_note' => 'nullable|string|max:280',
        ]);

        $adToken   = \App\Models\AdViewToken::where('token', $token)->firstOrFail();
        $voter     = $this->resolveVoter();
        $campaign  = \App\Models\PoliticalCampaign::with('politician.user')->findOrFail($adToken->political_campaign_id);
        $politician = $campaign->politician;

        [$validationError, $referenceSchemaAvailable, $referencePlatform, $referenceUrl] = $this->questionReferenceValidationState($validated);

        if ($validationError !== null) {
            return response()->json([
                'success' => false,
                'message' => $validationError,
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

        $reportPayload = [
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
        ];

        if ($referenceSchemaAvailable) {
            $reportPayload['reference_platform'] = $referencePlatform;
            $reportPayload['reference_url'] = $referenceUrl !== '' ? $referenceUrl : null;
            $reportPayload['reference_start_seconds'] = $validated['reference_start_seconds'] ?? null;
            $reportPayload['reference_end_seconds'] = $validated['reference_end_seconds'] ?? null;
            $reportPayload['reference_note'] = filled($validated['reference_note'] ?? null)
                ? trim((string) $validated['reference_note'])
                : null;
        }

        \App\Models\VoterWatchReport::create($reportPayload);

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

    // ── Citizen profile upgrade ──────────────────────────────────────────────

    /**
     * GET /voter/add-citizen-profile
     * Show the form to add a Citizen profile to the existing voter account.
     */
    public function showAddCitizenProfile()
    {
        $user = Auth::user();

        // Already has a citizen profile — send them straight to the citizen dashboard.
        if ($user->citizen) {
            // Defensive repair: a prior attempt may have created the Citizen row
            // but failed to assign the Spatie role. Assign it now so the redirect
            // does not dump them at a 403.
            if (! $user->hasRole('citizen')) {
                Role::firstOrCreate(
                    ['name' => 'citizen'],
                    ['guard_name' => config('auth.defaults.guard', 'web')]
                );
                $user->assignRole('citizen');
            }

            return redirect()->route('citizen.dashboard')
                ->with('info', 'You already have a Citizen profile on this account.');
        }

        $citizenRate     = (float) PlatformSettingsService::get('citizen_revenue_per_view', null, 0.75);
        $ballotIssueRate = (float) PlatformSettingsService::get('ballot_issue_revenue_per_view', null, 1.00);

        return view('standalone.voter.add-citizen-profile', [
            'user'            => $user,
            'citizenRate'     => $citizenRate,
            'ballotIssueRate' => $ballotIssueRate,
        ]);
    }

    /**
     * POST /voter/add-citizen-profile
     * Create a Citizen row for this voter account, assign the citizen Spatie role.
     * The user keeps their voter role and email — no second account required.
     */
    public function addCitizenProfile(Request $request)
    {
        $user = Auth::user();

        if ($user->citizen) {
            // Defensive repair: a prior attempt may have created the Citizen row
            // but failed to assign the Spatie role (missing role, cache issue,
            // etc.). Fix the role before redirecting so the user can actually
            // enter the citizen portal.
            if (! $user->hasRole('citizen')) {
                Role::firstOrCreate(
                    ['name' => 'citizen'],
                    ['guard_name' => config('auth.defaults.guard', 'web')]
                );
                $user->assignRole('citizen');
            }

            return redirect()->route('citizen.dashboard');
        }

        $validated = $request->validate([
            'full_name'      => ['required', 'string', 'max:255'],
            'business_name'  => ['nullable', 'string', 'max:255'],
            'address_line_1' => ['required', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'city'           => ['required', 'string', 'max:100'],
            'state'          => ['required', 'string', 'size:2'],
            'zip'            => ['required', 'digits:5'],
        ]);

        // Ensure the citizen role exists before we attempt assignment. On some
        // deployments the seeder may not have been run, and without the role
        // the user would be left with a Citizen row but no portal access.
        Role::firstOrCreate(
            ['name' => 'citizen'],
            ['guard_name' => config('auth.defaults.guard', 'web')]
        );

        // Wrap the profile creation, role assignment, and user_type update in a
        // transaction so a partial failure cannot leave the account in a 403
        // state (Citizen row created but role missing).
        DB::transaction(function () use ($user, $validated): void {
            Citizen::create(array_merge($validated, [
                'user_id'   => $user->id,
                'is_active' => true,
            ]));

            $user->assignRole('citizen');

            // Update the canonical account type so that post-login redirects,
            // 2FA completion, and admin dashboards recognize the citizen profile.
            // The voter Spatie role is retained so the user can still switch back.
            if ($user->user_type !== 'citizen') {
                $user->user_type = 'citizen';
                $user->save();
            }
        });

        return redirect()->route('portal-pick')
            ->with('success', 'Citizen profile created! You can now switch between your Voter and Citizen portals.');
    }

}
