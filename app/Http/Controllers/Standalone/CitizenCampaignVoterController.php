<?php

namespace App\Http\Controllers\Standalone;

use App\Enums\CampaignStatus;
use App\Enums\ApprovalStatus;
use App\Http\Controllers\Controller;
use App\Models\CitizenCampaign;
use App\Models\Voter;
use App\Services\CitizenViewService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Voter-facing actions for citizen-paid campaigns (community notices, local
 * business ads, ballot-issue messages, etc.).
 *
 * This controller is intentionally lightweight: the full watch lifecycle is
 * delegated to CitizenViewService so it mirrors the political-campaign flow.
 */
class CitizenCampaignVoterController extends Controller
{
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

        return response()->json([
            'ok'               => true,
            'qualified'        => (float) ($completed->voter_payout_amount ?? 0) > 0,
            'payout_earned'    => (float) $completed->voter_payout_amount,
            'pending_earnings' => (float) ($freshVoter->pending_earnings ?? 0),
            'wallet_balance'   => (float) ($freshVoter->wallet_balance ?? 0),
            'status'           => $completed->status->value,
            'view_session_uuid' => $completed->uuid,
        ]);
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
