<?php

namespace App\Services;

use App\Events\AdTokenDelivered;
use App\Events\CampaignApproved;
use App\Events\CampaignLiveStarted;
use App\Events\CampaignRejected;
use App\Events\CampaignStopped;
use App\Events\FraudFlagRaised;
use App\Events\PayoutProcessed;
use App\Events\ViewSessionCompleted;
use App\Models\AdViewToken;
use App\Models\PoliticalCampaign;
use App\Models\ViewSession;
use App\Models\Voter;
use Illuminate\Support\Facades\Log;

/**
 * ReverbBroadcastService
 *
 * Single entry-point for all server-side WebSocket event dispatches.
 * Wraps each broadcast event in error-handling so a Reverb outage never
 * breaks the calling business logic (billing, fraud, admin actions, etc.).
 *
 * Channel map:
 *   private-politician.{userId}     — campaign lifecycle notifications
 *   private-voter.{userId}          — ad tokens, session payouts, batch payouts
 *   private-admin.monitor           — fraud flags, session throughput
 *   presence-campaign.live.{uuid}   — Phase 12 WebRTC signaling + viewer count
 *
 * Usage (inject or resolve from container):
 *   $broadcast->campaignApproved($campaign);
 *   $broadcast->adTokenDelivered($token);
 *   $broadcast->payoutProcessed($voter, 12.50, 'PayPal', 'REF-123');
 */
class ReverbBroadcastService
{
    // -----------------------------------------------------------------------
    // Politician channels
    // -----------------------------------------------------------------------

    /**
     * Notify a politician that their campaign has been approved.
     */
    public function campaignApproved(PoliticalCampaign $campaign): void
    {
        $this->dispatch(fn () => broadcast(new CampaignApproved($campaign)), 'campaign.approved', $campaign->id);
    }

    /**
     * Notify a politician that their campaign has been rejected with a reason.
     */
    public function campaignRejected(PoliticalCampaign $campaign, string $reason): void
    {
        $this->dispatch(fn () => broadcast(new CampaignRejected($campaign, $reason)), 'campaign.rejected', $campaign->id);
    }

    /**
     * Notify a politician that their campaign has been force-stopped by an admin.
     */
    public function campaignStopped(PoliticalCampaign $campaign, string $reason): void
    {
        $this->dispatch(fn () => broadcast(new CampaignStopped($campaign, $reason)), 'campaign.stopped', $campaign->id);
    }

    // -----------------------------------------------------------------------
    // Voter channels
    // -----------------------------------------------------------------------

    /**
     * Deliver a one-time ad token to the assigned voter in real time.
     */
    public function adTokenDelivered(AdViewToken $token): void
    {
        $this->dispatch(fn () => broadcast(new AdTokenDelivered($token)), 'ad.token.delivered', $token->id);
    }

    /**
     * Notify the voter that their view session has been completed and credited.
     */
    public function viewSessionCompleted(ViewSession $session): void
    {
        $this->dispatch(fn () => broadcast(new ViewSessionCompleted($session)), 'session.completed', $session->id);
    }

    /**
     * Notify the voter that a batch payout has been transferred to them.
     */
    public function payoutProcessed(Voter $voter, float $amount, string $payoutMethod, string $referenceId): void
    {
        $this->dispatch(
            fn () => broadcast(new PayoutProcessed($voter, $amount, $payoutMethod, $referenceId)),
            'payout.processed',
            $voter->id,
        );
    }

    // -----------------------------------------------------------------------
    // Admin monitor channel
    // -----------------------------------------------------------------------

    /**
     * Push a fraud flag to the admin real-time monitor channel.
     */
    public function fraudFlagRaised(Voter $voter, float $fraudScore, string $reason): void
    {
        $this->dispatch(
            fn () => broadcast(new FraudFlagRaised($voter, $fraudScore, $reason)),
            'fraud.flag.raised',
            $voter->id,
        );
    }

    // -----------------------------------------------------------------------
    // Presence channel (Phase 12 WebRTC signaling foundation)
    // -----------------------------------------------------------------------

    /**
     * Signal that a live campaign has started on the presence channel.
     * Phase 12 will attach WebRTC offer/answer events to the same channel.
     */
    public function campaignLiveStarted(PoliticalCampaign $campaign): void
    {
        $this->dispatch(fn () => broadcast(new CampaignLiveStarted($campaign)), 'campaign.live.started', $campaign->id);
    }

    // -----------------------------------------------------------------------
    // Internal helpers
    // -----------------------------------------------------------------------

    /**
     * Dispatch a broadcast event with structured error handling.
     * A Reverb/WebSocket failure is never allowed to propagate up into
     * the calling business-logic layer.
     */
    private function dispatch(callable $broadcastFn, string $eventName, int|string $entityId): void
    {
        try {
            $broadcastFn();

            Log::debug('[Reverb] Event dispatched', [
                'event'     => $eventName,
                'entity_id' => $entityId,
            ]);
        } catch (\Throwable $e) {
            Log::error('[Reverb] Broadcast failed — event dropped', [
                'event'     => $eventName,
                'entity_id' => $entityId,
                'error'     => $e->getMessage(),
            ]);
            // Intentionally swallowed: WebSocket failure is non-fatal
        }
    }
}
