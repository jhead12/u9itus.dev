<?php

namespace App\Services;

use App\Enums\ViewPaymentStatus;
use App\Enums\ViewSessionStatus;
use App\Models\PoliticalCampaign;
use App\Services\CampaignBillingService;
use App\Services\ReverbBroadcastService;
use App\Models\ReferralEarning;
use App\Models\Voter;
use App\Models\ViewSession;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Orchestrates the full lifecycle of a political view session:
 *   assign → start → track progress → complete → payout.
 *
 * All multi-table mutations are wrapped in DB transactions.
 */
class PoliticalViewService
{
    public function __construct(
        protected FraudPreventionService $fraudService,
        protected CampaignBillingService $billingService,
        protected ?ReverbBroadcastService $broadcastService = null,
    ) {
        $this->broadcastService ??= app(ReverbBroadcastService::class);
    }

    /**
     * Assign a campaign to a voter (create a view session).
     *
     * @throws \RuntimeException If fraud score is too high.
     */
    public function assignView(PoliticalCampaign $campaign, Voter $voter, Request $request): ViewSession
    {
        $fraudCheck = $this->fraudService->evaluate($voter, $request);

        if (!$fraudCheck['allowed']) {
            throw new \RuntimeException('View not allowed — fraud score too high');
        }

        return DB::transaction(function () use ($campaign, $voter, $request, $fraudCheck): ViewSession {
            return ViewSession::create([
                'political_campaign_id' => $campaign->id,
                'voter_id'              => $voter->id,
                'status'                => ViewSessionStatus::Assigned,
                'expires_at'            => Carbon::now()->addHours((int) config('u9itus.assignment_expiry_hours', 24)),
                'ip_address'            => $request->ip(),
                'device_fingerprint'    => $request->header('X-Device-Fingerprint') ?? $request->input('device_fingerprint'),
                'user_agent'            => $request->userAgent(),
                'fraud_score'           => $fraudCheck['score'],
                'fraud_flags'           => $fraudCheck['flags'],
            ]);
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
     *
     * All financial mutations (session update, voter credits, referral
     * earnings, campaign spend) are wrapped in a single DB transaction.
     */
    public function completeView(ViewSession $session, int $totalWatchTimeSeconds): ViewSession
    {
        // Idempotency guard — never double-credit a session that is already completed.
        if ($session->status === ViewSessionStatus::Completed) {
            return $session;
        }

        $completed = DB::transaction(function () use ($session, $totalWatchTimeSeconds): ViewSession {
            $campaign = $session->campaign;

            $completionPct = $this->calculateCompletionPercentage(
                $totalWatchTimeSeconds,
                $campaign->media_duration,
            );
            $qualifies = $completionPct >= $campaign->min_watch_time_percent;

            // Calculate payout amounts using integer-cent arithmetic to avoid drift.
            $voterPayoutCents     = $qualifies ? \App\Support\Money::toCents($campaign->voter_payout_per_view) : 0;
            $platformRevenueCents = $qualifies ? \App\Support\Money::toCents($campaign->revenue_per_view) - $voterPayoutCents : 0;
            $referralCommissionCents = 0;

            // Referral commission: 10% of voter payout if the voter was referred
            if ($qualifies && $session->voter->referred_by_voter_id) {
                $referralCommissionCents = \App\Support\Money::percentOf($voterPayoutCents, config('u9itus.referral_commission_percent', 10));
                $platformRevenueCents -= $referralCommissionCents;
            }

            $voterPayout        = (float) \App\Support\Money::fromCents($voterPayoutCents);
            $platformRevenue    = (float) \App\Support\Money::fromCents($platformRevenueCents);
            $referralCommission = (float) \App\Support\Money::fromCents($referralCommissionCents);

            // Update the session
            $session->update([
                'status'                => ViewSessionStatus::Completed,
                'completed_at'          => now(),
                'watch_time_seconds'    => $totalWatchTimeSeconds,
                'completion_percentage' => $completionPct,
                'voter_payout_amount'   => $voterPayout,
                'platform_revenue'      => $platformRevenue,
                'referral_commission'   => $referralCommission,
                'payment_status'        => $qualifies
                    ? ViewPaymentStatus::Pending
                    : ViewPaymentStatus::Rejected,
            ]);

            if ($qualifies) {
                $this->creditVoter($session->voter, $voterPayout);
                $this->creditReferrer($session, $referralCommission);
                $this->updateCampaignSpend($campaign);

                // Internal accounting log — platform margin per completed view.
                Log::info('Platform margin recorded', [
                    'view_session_id'     => $session->id,
                    'campaign_id'         => $campaign->id,
                    'politician_id'       => $campaign->politician_id,
                    'voter_id'            => $session->voter_id,
                    'revenue_per_view'    => (float) $campaign->revenue_per_view,  // $0.60 charged to politician
                    'voter_payout'        => $voterPayout,                          // $0.25 credited to voter
                    'referral_commission' => $referralCommission,                   // $0.050 if referred, else 0
                    'platform_margin'     => $platformRevenue,                      // $0.35 (or $0.325 w/ referral)
                ]);
            }

            return $session->fresh();
        });

        // Broadcast outside the transaction so the event only fires on commit (Phase 11)
        // Notifies the voter's private channel + admin monitor channel.
        $this->broadcastService->viewSessionCompleted($completed);

        return $completed;
    }

    /**
     * Get available campaigns for a voter based on location & preferences.
     *
     * Phase 14: respects campaign-level repeat-view settings.
     *   - allow_repeat_views = false  → exclude campaigns the voter already completed (original behaviour)
     *   - allow_repeat_views = true   → include BUT enforce cooldown + per-voter view cap via PHP filter
     *
     * @return \Illuminate\Database\Eloquent\Collection<PoliticalCampaign>
     */
    public function availableCampaigns(Voter $voter): \Illuminate\Database\Eloquent\Collection
    {
        $candidates = PoliticalCampaign::needingViews()
            ->whereHas('politician', function ($q): void {
                $q->where('is_active', true)
                  ->whereNotNull('user_id')
                  ->whereHas('user');
            })
            ->with('politician:id,full_name,political_office')
            ->where(function ($q) use ($voter): void {
                if ($voter->state) {
                    $q->whereJsonContains('target_states', $voter->state)
                      ->orWhereNull('target_states');
                }
            })
            // Hard-exclude non-repeatable campaigns the voter already completed.
            // Repeatable campaigns (allow_repeat_views = true) pass through here
            // and are subject to the PHP filter below.
            ->where(function ($q) use ($voter): void {
                $q->where('allow_repeat_views', true)
                  ->orWhereDoesntHave('viewSessions', function ($q2) use ($voter): void {
                      $q2->where('voter_id', $voter->id)
                         ->where('status', ViewSessionStatus::Completed);
                  });
            })
            ->orderByDesc('revenue_per_view')
            ->get();

        // PHP-level filter for repeat-view rules (cap + cooldown)
        return $candidates->filter(function (PoliticalCampaign $campaign) use ($voter): bool {
            if (!$campaign->allow_repeat_views) {
                return true; // already handled by the SQL exclusion above
            }

            $completedCount = $campaign->voterCompletedViewCount($voter->id);

            // Under the per-voter cap?
            if ($completedCount >= $campaign->max_views_per_voter) {
                return false;
            }

            // First-time viewer — always eligible
            if ($completedCount === 0) {
                return true;
            }

            // Cooldown check — last completed view must be old enough
            $lastCompleted = $campaign->voterLastCompletedAt($voter->id);
            if ($lastCompleted === null) {
                return true;
            }

            $cooldownHours = max(1, (int) $campaign->repeat_view_cooldown_hours);
            return $lastCompleted->addHours($cooldownHours)->isPast();
        })->values();
    }

    /**
     * Check whether a specific voter is currently allowed to watch (or re-watch)
     * a campaign.  Used by VoterController token-watch flow.
     */
    public function voterCanWatch(PoliticalCampaign $campaign, Voter $voter): bool
    {
        if (!$campaign->allow_repeat_views) {
            return $campaign->voterCompletedViewCount($voter->id) === 0;
        }

        $completedCount = $campaign->voterCompletedViewCount($voter->id);

        if ($completedCount >= $campaign->max_views_per_voter) {
            return false;
        }

        if ($completedCount === 0) {
            return true;
        }

        $lastCompleted = $campaign->voterLastCompletedAt($voter->id);
        if ($lastCompleted === null) {
            return true;
        }

        return $lastCompleted->addHours(max(1, (int) $campaign->repeat_view_cooldown_hours))->isPast();
    }

    /**
     * Get voter's earnings summary.
     *
     * @return array<string, mixed>
     */
    public function voterEarningsSummary(Voter $voter): array
    {
        // Sum of sessions already queued for payout (Approved, not yet Paid).
        $approvedEarnings = (float) $voter->viewSessions()
            ->where('payment_status', ViewPaymentStatus::Approved)
            ->sum('voter_payout_amount');

        return [
            'total_earned'               => $voter->total_earned,
            'pending_earnings'           => $voter->pending_earnings,
            'approved_earnings'          => $approvedEarnings,
            'wallet_balance'             => $voter->wallet_balance,
            'total_views'                => $voter->total_views,
            // Voter-view commissions: 10% of each referred voter's payout
            'referral_earnings'          => (float) $voter->referralEarnings()->voterViews()->forActiveStripeMode()->sum('commission_amount'),
            // Politician-procurement commissions: 10% of referred politician's first purchase
            'procurement_earnings'       => (float) $voter->referralEarnings()->procurements()->forActiveStripeMode()->sum('commission_amount'),
            'total_referral_earnings'    => (float) $voter->referralEarnings()->forActiveStripeMode()->sum('commission_amount'),
            'referrals_count'            => $voter->referrals()->count(),
            'referrals_politician_count' => \App\Models\Politician::where('referred_by_voter_id', $voter->id)->count(),
            'views_today'                => $voter->viewSessions()->whereDate('created_at', today())->count(),
        ];
    }

    // ── Private Helpers ─────────────────────────────────────

    private function calculateCompletionPercentage(int $watchTimeSeconds, ?int $mediaDuration): float
    {
        $effectiveDuration = (int) ($mediaDuration ?? 0);
        if ($effectiveDuration <= 0) {
            $effectiveDuration = (int) PlatformSettingsService::get(
                'max_video_duration',
                null,
                (int) config('u9itus.max_video_duration', 180)
            );
        }

        if ($effectiveDuration <= 0) {
            return 100.0;
        }

        return min(100.0, ($watchTimeSeconds / $effectiveDuration) * 100);
    }

    private function creditVoter(Voter $voter, float $amount): void
    {
        $voter->increment('pending_earnings', $amount);
        $voter->increment('total_views');
    }

    private function creditReferrer(ViewSession $session, float $commission): void
    {
        if ($commission <= 0) {
            return;
        }

        $voter = $session->voter;

        // Voter referred by another voter
        if ($voter->referred_by_voter_id && $voter->referrer) {
            ReferralEarning::create([
                'referrer_voter_id' => $voter->referred_by_voter_id,
                'referred_voter_id' => $voter->id,
                'view_session_id'   => $session->id,
                'commission_amount' => $commission,
                'payment_mode'      => ReferralEarning::normalizePaymentMode(app(StripePaymentService::class)->configuredMode()),
                'referral_type'     => ReferralEarning::TYPE_VOTER_VIEW,
            ]);
            $voter->referrer->increment('pending_earnings', $commission);
        }

        // Voter referred by a politician
        if ($voter->referred_by_politician_id && $voter->politicianReferrer) {
            ReferralEarning::create([
                'referrer_politician_id' => $voter->referred_by_politician_id,
                'referred_voter_id'      => $voter->id,
                'view_session_id'        => $session->id,
                'commission_amount'      => $commission,
                'payment_mode'           => ReferralEarning::normalizePaymentMode(app(StripePaymentService::class)->configuredMode()),
                'referral_type'          => ReferralEarning::TYPE_VOTER_VIEW,
            ]);
            $voter->politicianReferrer->increment('pending_earnings', $commission);
        }
    }

    private function updateCampaignSpend(PoliticalCampaign $campaign): void
    {
        $cost = (float) $campaign->revenue_per_view;
        $campaign->increment('views_completed');
        $campaign->increment('amount_spent', $cost);

        // Deduct from politician's credit ledger — precise per-view spend tracking.
        if ($cost > 0) {
            $politician = $campaign->politician;
            if ($politician) {
                $this->billingService->addCredits($politician, -$cost, [
                    'transaction_type' => 'usage',
                    'campaign_id'      => $campaign->id,
                    'description'      => "View charge — campaign #{$campaign->id}",
                ]);
            }
        }
    }
}
