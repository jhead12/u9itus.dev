<?php

namespace App\Http\Controllers\Standalone;

use App\Enums\CampaignStatus;
use App\Enums\ApprovalStatus;
use App\Http\Controllers\Concerns\ResolvesPlayableCampaignMedia;
use App\Http\Controllers\Controller;
use App\Models\CitizenCampaign;
use App\Models\CitizenCampaignMessage;
use App\Models\Voter;
use App\Services\CitizenViewService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Voter-facing actions for citizen-paid campaigns (community notices, local
 * business ads, ballot-issue messages, etc.).
 *
 * This controller is intentionally lightweight: the full watch lifecycle is
 * delegated to CitizenViewService so it mirrors the political-campaign flow.
 */
class CitizenCampaignVoterController extends Controller
{
    use ResolvesPlayableCampaignMedia;

    public function __construct(
        protected CitizenViewService $viewService,
    ) {
    }

    /**
     * GET /voter/citizen-campaigns/{campaign}/watch
     *
     * Show the watch page for a citizen campaign. Eligibility is checked
     * up-front; the page itself drives completion via the /complete endpoint.
     */
    public function watch(CitizenCampaign $campaign)
    {
        $voter = $this->resolveVoter();

        if ($campaign->status !== CampaignStatus::Active
            || $campaign->approval_status !== ApprovalStatus::Approved) {
            return back()->withErrors(['watch' => 'This campaign is not currently available.']);
        }

        if (! $voter->canViewToday() || ! $this->viewService->voterCanWatch($campaign, $voter)) {
            return back()->withErrors([
                'watch' => 'You are not currently eligible to watch this campaign.',
            ]);
        }

        $duration  = (int) ($campaign->media_duration ?? 0);
        $mustWatch = (int) ($campaign->min_watch_time_percent ?? config('u9itus.min_watch_time_percent', 80));
        $payout    = (float) ($campaign->voter_payout_per_view ?? 0.50);

        if ($resolvedMediaUrl = $this->resolvePlayableCampaignMediaUrl($campaign)) {
            $campaign->media_url = $resolvedMediaUrl;
        }

        return view('standalone.voter.watch-citizen', compact(
            'campaign', 'voter', 'duration', 'mustWatch', 'payout'
        ));
    }

    /**
     * POST /voter/citizen-campaigns/{campaign}/complete
     *
     * Mark a view of a citizen campaign as complete and credit the voter.
     * Creates a CitizenViewSession in the background for audit/payout tracking.
     */
    public function complete(Request $request, CitizenCampaign $campaign)
    {
        $validated = $request->validate([
            'total_seconds_watched'  => ['required', 'integer', 'min:0'],
            'media_duration_seconds' => ['nullable', 'integer', 'min:1', 'max:21600'],
        ]);

        $voter = $this->resolveVoter();

        if ($campaign->status !== CampaignStatus::Active
            || $campaign->approval_status !== ApprovalStatus::Approved) {
            return response()->json(['error' => 'This campaign is not currently available.'], 403);
        }

        if (! $voter->canViewToday()) {
            return response()->json([
                'error' => 'You have reached your daily viewing limit or your account is restricted.',
            ], 429);
        }

        if (! $this->viewService->voterCanWatch($campaign, $voter)) {
            return response()->json([
                'error' => 'You are not currently eligible to earn from this campaign.',
            ], 403);
        }

        try {
            $session = $this->viewService->assignView($campaign, $voter, $request);
            $session = $this->viewService->startView($session);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 403);
        }

        $completed = $this->viewService->completeView(
            $session,
            (int) $validated['total_seconds_watched'],
            isset($validated['media_duration_seconds']) ? (int) $validated['media_duration_seconds'] : null
        );

        $freshVoter = $voter->fresh();

        // A repeat completed view is recorded but pays nothing (free re-watch).
        // voterCompletedViewCount now includes the session just completed, so a
        // count > 1 means a prior completion existed for this voter/campaign.
        $isRepeat = $this->viewService->voterCompletedViewCount($campaign->id, $voter->id) > 1;

        return response()->json([
            'ok'               => true,
            'qualified'        => (float) ($completed->voter_payout_amount ?? 0) > 0,
            'is_repeat'        => $isRepeat,
            'payout_earned'    => (float) $completed->voter_payout_amount,
            'pending_earnings' => (float) ($freshVoter->pending_earnings ?? 0),
            'wallet_balance'   => (float) ($freshVoter->wallet_balance ?? 0),
            'status'           => $completed->status->value,
            'view_session_uuid' => $completed->uuid,
        ]);
    }

    /**
     * POST /voter/citizen-campaigns/{campaign}/report-issue
     *
     * Store a voter-reported issue for a citizen campaign and notify platform
     * support. Mirrors the political-campaign VoterController::reportIssue flow
     * but against the citizen message store.
     */
    public function reportIssue(Request $request, CitizenCampaign $campaign)
    {
        $validated = $request->validate([
            'issue_category' => ['required', 'in:video_not_playing,incorrect_info,offensive_content,other'],
            'body'           => ['nullable', 'string', 'max:1000'],
        ]);

        $voter = $this->resolveVoter();

        if (! $this->campaignIsAvailable($campaign)) {
            return response()->json(['success' => false, 'message' => 'This campaign is not currently available.'], 403);
        }

        CitizenCampaignMessage::create([
            'voter_id'          => $voter->id,
            'citizen_campaign_id' => $campaign->id,
            'type'              => 'issue',
            'issue_category'    => $validated['issue_category'],
            'body'              => $validated['body'] ?? '',
            'status'            => 'open',
        ]);

        try {
            Mail::raw(
                "Issue reported by voter #{$voter->id} ({$voter->email}) on citizen campaign #{$campaign->id}.\n"
                . "Category: {$validated['issue_category']}\n"
                . 'Message: ' . ($validated['body'] ?? '(none)'),
                fn ($m) => $m->to(config('mail.from.address', 'admin@u9itus.com'))
                              ->subject('[U9itus] Citizen Campaign Issue Report – #' . $campaign->id)
            );
        } catch (\Throwable $e) {
            Log::warning('citizen reportIssue: mail failed', ['error' => $e->getMessage()]);
        }

        return response()->json(['success' => true, 'message' => 'Your report has been submitted. Thank you!']);
    }

    /**
     * POST /voter/citizen-campaigns/{campaign}/ask-question
     *
     * Store a voter-to-sponsor question and email it to the citizen who owns
     * the campaign. No public Q&A board (v1) — the message is delivered
     * privately to the sponsor.
     */
    public function askQuestion(Request $request, CitizenCampaign $campaign)
    {
        $validated = $request->validate([
            'body'                    => ['required', 'string', 'max:1000'],
            'reference_url'           => ['nullable', 'url', 'max:2048'],
            'reference_start_seconds' => ['nullable', 'integer', 'min:0', 'max:86400'],
            'reference_end_seconds'   => ['nullable', 'integer', 'min:0', 'max:86400', 'gte:reference_start_seconds'],
            'reference_note'          => ['nullable', 'string', 'max:280'],
        ]);

        $voter = $this->resolveVoter();

        if (! $this->campaignIsAvailable($campaign)) {
            return response()->json(['success' => false, 'message' => 'This campaign is not currently available.'], 403);
        }

        // Lightweight rate limit: 3 questions per 10 minutes per voter/campaign.
        $rateKey = 'citizen-question-submit:' . $voter->id . ':' . $campaign->id;
        if (RateLimiter::tooManyAttempts($rateKey, 3)) {
            return response()->json([
                'success' => false,
                'message' => 'You are submitting too quickly. Please wait before sending another question.',
                'retry_after_seconds' => RateLimiter::availableIn($rateKey),
            ], 429);
        }
        RateLimiter::hit($rateKey, 600);

        CitizenCampaignMessage::create([
            'voter_id'               => $voter->id,
            'citizen_campaign_id'    => $campaign->id,
            'type'                   => 'message',
            'issue_category'         => null,
            'body'                   => $validated['body'],
            'reference_url'          => $validated['reference_url'] ?? null,
            'reference_start_seconds' => $validated['reference_start_seconds'] ?? null,
            'reference_end_seconds'   => $validated['reference_end_seconds'] ?? null,
            'reference_note'         => filled($validated['reference_note'] ?? null) ? trim((string) $validated['reference_note']) : null,
            'status'                 => 'open',
        ]);

        $sponsor = $campaign->citizen;
        $recipient = $sponsor?->user?->email ?: $sponsor?->receipt_email;

        if ($recipient) {
            try {
                Mail::raw(
                    "A voter asked a question about your campaign \"{$campaign->title}\".\n\n"
                    . "From voter #{$voter->id}:\n"
                    . $validated['body']
                    . (! empty($validated['reference_url'])
                        ? "\n\nReference: " . $validated['reference_url']
                        : ''),
                    fn ($m) => $m->to($recipient)
                                  ->subject('[U9itus] New question about your campaign: ' . $campaign->title)
                );
            } catch (\Throwable $e) {
                Log::warning('citizen askQuestion: mail failed', ['error' => $e->getMessage()]);
            }
        }

        return response()->json(['success' => true, 'message' => 'Your question has been sent to the campaign sponsor.']);
    }

    /**
     * A citizen campaign is voter-facing only when active and approved.
     */
    private function campaignIsAvailable(CitizenCampaign $campaign): bool
    {
        return $campaign->status === CampaignStatus::Active
            && $campaign->approval_status === ApprovalStatus::Approved;
    }

    /**
     * Resolve the voter profile for the authenticated user.
     */
    private function resolveVoter(): Voter
    {
        $user = Auth::user();

        if ($voter = $user->voter) {
            return $voter;
        }

        $voter = Voter::where('email', $user->email)
            ->whereNull('user_id')
            ->first();

        if ($voter) {
            $voter->update(['user_id' => $user->id]);
            return $voter->fresh();
        }

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
}
