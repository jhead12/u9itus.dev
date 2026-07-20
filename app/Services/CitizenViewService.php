<?php

namespace App\Services;

use App\Enums\CampaignStatus;
use App\Enums\ViewPaymentStatus;
use App\Enums\ViewSessionStatus;
use App\Models\CitizenCampaign;
use App\Models\CitizenViewSession;
use App\Models\Voter;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Orchestrates the full lifecycle of a citizen-campaign view session:
 *   assign → start → track progress → complete → campaign spend + payout.
 *
 * Citizen campaigns are funded up-front (full budget reserved at admin
 * approval), so completing a view records the per-view charge against the
 * campaign's reserved budget rather than hitting the wallet again.
 */
class CitizenViewService
{
    public function __construct(
        protected FraudPreventionService $fraudService,
        protected CitizenBillingService $billingService,
    ) {
    }

    /**
     * Create a new assigned citizen view session for a voter.
     *
     * @throws \RuntimeException If fraud score is too high or campaign unavailable.
     */
    public function assignView(CitizenCampaign $campaign, Voter $voter, Request $request): CitizenViewSession
    {
        if (! $this->campaignAcceptsViews($campaign)) {
            throw new \RuntimeException('Campaign is not currently available for viewing.');
        }

        $fraudCheck = $this->fraudService->evaluate($voter, $request);

        if (! $fraudCheck['allowed']) {
            throw new \RuntimeException('View not allowed — fraud score too high');
        }

        return DB::transaction(function () use ($campaign, $voter, $request, $fraudCheck): CitizenViewSession {
            return CitizenViewSession::create([
                'citizen_campaign_id' => $campaign->id,
                'voter_id'            => $voter->id,
                'status'              => ViewSessionStatus::Assigned,
                'expires_at'          => Carbon::now()->addHours((int) config('u9itus.assignment_expiry_hours', 24)),
                'ip_address'          => $request->ip(),
                'device_fingerprint'  => $request->header('X-Device-Fingerprint') ?? $request->input('device_fingerprint'),
                'user_agent'          => $request->userAgent(),
                'fraud_score'         => $fraudCheck['score'],
                'fraud_flags'         => $fraudCheck['flags'],
            ]);
        });
    }

    /**
     * Mark the session as started (voter pressed play).
     */
    public function startView(CitizenViewSession $session): CitizenViewSession
    {
        $session->markStarted();
        return $session->fresh();
    }

    /**
     * Progress heartbeat — update watch time periodically.
     */
    public function trackProgress(CitizenViewSession $session, int $secondsWatched): CitizenViewSession
    {
        $session->update([
            'watch_time_seconds' => $secondsWatched,
        ]);
        return $session->fresh();
    }

    /**
     * Complete the view, calculate payouts, record campaign spend, and credit
     * the voter if qualified.
     */
    public function completeView(CitizenViewSession $session, int $totalWatchTimeSeconds, ?int $clientMediaDuration = null): CitizenViewSession
    {
        if ($session->status === ViewSessionStatus::Completed) {
            return $session;
        }

        $completed = DB::transaction(function () use ($session, $totalWatchTimeSeconds, $clientMediaDuration): CitizenViewSession {
            $campaign = $session->campaign;

            $mediaDuration = $this->effectiveMediaDuration($campaign, $clientMediaDuration);
            $completionPct = $this->calculateCompletionPercentage($totalWatchTimeSeconds, $mediaDuration);
            $qualifies = $completionPct >= $campaign->min_watch_time_percent;

            $voterPayoutCents     = $qualifies ? \App\Support\Money::toCents($campaign->voter_payout_per_view) : 0;
            $platformRevenueCents = $qualifies ? \App\Support\Money::toCents($campaign->revenue_per_view) - $voterPayoutCents : 0;

            $voterPayout     = (float) \App\Support\Money::fromCents($voterPayoutCents);
            $platformRevenue = (float) \App\Support\Money::fromCents($platformRevenueCents);

            $session->update([
                'status'                => ViewSessionStatus::Completed,
                'completed_at'          => now(),
                'watch_time_seconds'    => $totalWatchTimeSeconds,
                'completion_percentage' => $completionPct,
                'voter_payout_amount'   => $voterPayout,
                'platform_revenue'      => $platformRevenue,
                'referral_commission'   => 0,
                'payment_status'        => $qualifies
                    ? ViewPaymentStatus::Pending
                    : ViewPaymentStatus::Rejected,
            ]);

            if ($qualifies) {
                $this->creditVoter($session->voter, $voterPayout);
                $this->recordCampaignSpend($campaign, $session);
            }

            return $session->fresh();
        });

        return $completed;
    }

    /**
     * Get active citizen campaigns that still need views and are within their
     * scheduled window (if any).
     *
     * @return \Illuminate\Database\Eloquent\Collection<CitizenCampaign>
     */
    public function availableCampaigns(Voter $voter): \Illuminate\Database\Eloquent\Collection
    {
        $voterZip = $voter->zip_code;

        return CitizenCampaign::query()
            ->where('status', CampaignStatus::Active)
            ->where('approval_status', \App\Enums\ApprovalStatus::Approved)
            ->where(function ($q): void {
                $q->whereNull('scheduled_start_at')
                  ->orWhere('scheduled_start_at', '<=', now());
            })
            ->where(function ($q): void {
                $q->whereNull('scheduled_end_at')
                  ->orWhere('scheduled_end_at', '>', now());
            })
            ->whereColumn('views_completed', '<', 'total_views_requested')
            ->whereColumn('amount_spent', '<', 'total_budget')
            // Geo-targeting: campaigns with no target_zip are shown to all voters.
            // Campaigns with a target_zip are only shown to voters whose zip_code
            // matches OR whose zip is null (zip unknown → include for reach).
            ->where(function ($q) use ($voterZip): void {
                $q->whereNull('target_zip');
                if ($voterZip) {
                    $q->orWhere('target_zip', $voterZip);
                }
            })
            ->orderByDesc('revenue_per_view')
            ->get();
    }

    /**
     * Check whether a voter is allowed to watch (or re-watch) a citizen campaign.
     */
    public function voterCanWatch(CitizenCampaign $campaign, Voter $voter): bool
    {
        if (! $this->campaignAcceptsViews($campaign)) {
            return false;
        }

        if (! $campaign->allow_repeat_views) {
            return $this->voterCompletedViewCount($campaign->id, $voter->id) === 0;
        }

        $completedCount = $this->voterCompletedViewCount($campaign->id, $voter->id);

        if ($completedCount >= $campaign->max_views_per_voter) {
            return false;
        }

        if ($completedCount === 0) {
            return true;
        }

        $lastCompleted = $this->voterLastCompletedAt($campaign->id, $voter->id);
        if ($lastCompleted === null) {
            return true;
        }

        return $lastCompleted->addHours(max(0, (int) $campaign->repeat_view_cooldown_hours))->lte(now());
    }

    /**
     * Number of completed citizen-campaign views for this voter.
     */
    public function voterCompletedViewCount(int $campaignId, int $voterId): int
    {
        return CitizenViewSession::where('citizen_campaign_id', $campaignId)
            ->where('voter_id', $voterId)
            ->where('status', ViewSessionStatus::Completed)
            ->count();
    }

    /**
     * Timestamp of the voter's most recent completed view, if any.
     */
    public function voterLastCompletedAt(int $campaignId, int $voterId): ?Carbon
    {
        $session = CitizenViewSession::where('citizen_campaign_id', $campaignId)
            ->where('voter_id', $voterId)
            ->where('status', ViewSessionStatus::Completed)
            ->latest('completed_at')
            ->first();

        return $session?->completed_at;
    }

    // ── Private helpers ────────────────────────────────────────────────

    private function campaignAcceptsViews(CitizenCampaign $campaign): bool
    {
        if ($campaign->status !== CampaignStatus::Active) {
            return false;
        }

        if ($campaign->approval_status !== \App\Enums\ApprovalStatus::Approved) {
            return false;
        }

        if (! $campaign->isWithinScheduleWindow()) {
            return false;
        }

        if ($campaign->views_completed >= $campaign->total_views_requested) {
            return false;
        }

        return (float) $campaign->remainingBudget() > 0;
    }

    private function effectiveMediaDuration(CitizenCampaign $campaign, ?int $clientDuration): int
    {
        $campaignDuration = (int) ($campaign->media_duration ?? 0);
        if ($campaignDuration > 0) {
            return $campaignDuration;
        }

        $reportedDuration = (int) ($clientDuration ?? 0);
        if ($reportedDuration > 0) {
            return $reportedDuration;
        }

        $fallback = (int) PlatformSettingsService::get(
            'max_video_duration',
            null,
            (int) config('u9itus.max_video_duration', 180)
        );

        return max(1, $fallback);
    }

    private function calculateCompletionPercentage(int $watchTimeSeconds, int $mediaDuration): float
    {
        if ($mediaDuration <= 0) {
            return 100.0;
        }

        return min(100.0, ($watchTimeSeconds / $mediaDuration) * 100);
    }

    private function creditVoter(Voter $voter, float $amount): void
    {
        $voter->increment('pending_earnings', $amount);
        $voter->increment('total_views');
    }

    private function recordCampaignSpend(CitizenCampaign $campaign, CitizenViewSession $session): void
    {
        $cost = (float) $campaign->revenue_per_view;
        if ($cost <= 0) {
            return;
        }

        $this->billingService->debitForView($campaign, $cost, [
            'description' => "View charge — citizen campaign #{$campaign->id}",
            'metadata'    => [
                'view_session_uuid' => $session->uuid,
                'campaign_uuid'     => $campaign->uuid,
            ],
        ]);
    }
}
