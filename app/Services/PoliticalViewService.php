<?php

namespace App\Services;

use App\Models\PoliticalCampaign;
use App\Models\Voter;
use App\Models\ViewSession;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Orchestrates the full lifecycle of a political view session:
 *   assign → start → track progress → complete → payout.
 */
class PoliticalViewService
{
    public function __construct(
        protected FraudPreventionService $fraudService,
    ) {}

    /**
     * Assign a campaign to a voter (create a view session).
     */
    public function assignView(PoliticalCampaign $campaign, Voter $voter, Request $request): ViewSession
    {
        $fraudCheck = $this->fraudService->evaluate($voter, $request);

        if (!$fraudCheck['allowed']) {
            throw new \RuntimeException('View not allowed — fraud score too high');
        }

        return DB::transaction(function () use ($campaign, $voter, $request, $fraudCheck) {
            $session = ViewSession::create([
                'political_campaign_id' => $campaign->id,
                'voter_id'              => $voter->id,
                'status'                => 'assigned',
                'expires_at'            => Carbon::now()->addHours(config('dial4dough.assignment_expiry_hours', 24)),
                'ip_address'            => $request->ip(),
                'device_fingerprint'    => $request->header('X-Device-Fingerprint') ?? $request->input('device_fingerprint'),
                'user_agent'            => $request->userAgent(),
                'fraud_score'           => $fraudCheck['score'],
                'fraud_flags'           => $fraudCheck['flags'],
            ]);

            return $session;
        });
    }

    /**
     * Mark the session as started (voter pressed play).
     */
    public function startView(ViewSession $session): ViewSession
    {
        $session->markStarted();
        return $session->fresh();
    }

    /**
     * Progress heartbeat — update watch time periodically.
     */
    public function trackProgress(ViewSession $session, int $secondsWatched): ViewSession
    {
        $session->update([
            'watch_time_seconds' => $secondsWatched,
        ]);
        return $session->fresh();
    }

    /**
     * Complete the view, calculate payouts, and credit accounts.
     */
    public function completeView(ViewSession $session, int $totalWatchTimeSeconds): ViewSession
    {
        $session->markCompleted($totalWatchTimeSeconds);
        return $session->fresh();
    }

    /**
     * Get available campaigns for a voter based on location & preferences.
     */
    public function availableCampaigns(Voter $voter)
    {
        return PoliticalCampaign::needingViews()
            ->where(function ($q) use ($voter) {
                // Match by state
                if ($voter->state) {
                    $q->whereJsonContains('target_states', $voter->state)
                      ->orWhereNull('target_states');
                }
            })
            ->whereDoesntHave('viewSessions', function ($q) use ($voter) {
                // Exclude campaigns voter already completed
                $q->where('voter_id', $voter->id)
                  ->where('status', 'completed');
            })
            ->orderByDesc('revenue_per_view')
            ->get();
    }

    /**
     * Get voter's earnings summary.
     */
    public function voterEarningsSummary(Voter $voter): array
    {
        return [
            'total_earned'       => $voter->total_earned,
            'pending_earnings'   => $voter->pending_earnings,
            'wallet_balance'     => $voter->wallet_balance,
            'total_views'        => $voter->total_views,
            'referral_earnings'  => $voter->referralEarnings()->sum('commission_amount'),
            'referrals_count'    => $voter->referrals()->count(),
            'views_today'        => $voter->viewSessions()->whereDate('created_at', today())->count(),
        ];
    }
}
