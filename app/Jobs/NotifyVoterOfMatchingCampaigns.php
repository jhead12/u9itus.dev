<?php

namespace App\Jobs;

use App\Mail\MatchingCampaignMail;
use App\Models\Cause;
use App\Models\Voter;
use App\Services\CauseCampaignMatchService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * After a voter favorites a Cause, finds PoliticalCampaigns sharing the
 * Cause's Topic and scoped to the voter's district/state (via
 * CauseCampaignMatchService), and emails the voter about each match —
 * gated by their notification preferences, same as CampaignQuestionDigestService.
 */
class NotifyVoterOfMatchingCampaigns implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 120;

    public function __construct(
        public readonly int $voterId,
        public readonly int $causeId,
    ) {
    }

    public function handle(CauseCampaignMatchService $matchService): void
    {
        $voter = Voter::find($this->voterId);
        $cause = Cause::find($this->causeId);

        if (! $voter || ! $cause) {
            Log::warning('NotifyVoterOfMatchingCampaigns: voter or cause not found', [
                'voter_id' => $this->voterId,
                'cause_id' => $this->causeId,
            ]);
            return;
        }

        $user = $voter->user;
        if (! $user || empty($user->email)) {
            return;
        }

        $preferences = $user->notificationPreference()->firstOrCreate([])->refresh();
        if (! $preferences->isEnabled('email', 'campaign_status')) {
            return;
        }

        $matches = $matchService->matchesForVoter($voter, $cause);

        foreach ($matches as $campaign) {
            try {
                Mail::to($user->email)->queue(new MatchingCampaignMail($campaign, $cause));
            } catch (\Throwable $e) {
                Log::warning('NotifyVoterOfMatchingCampaigns: failed to queue mail', [
                    'voter_id'    => $voter->id,
                    'cause_id'    => $cause->id,
                    'campaign_id' => $campaign->id,
                    'error'       => $e->getMessage(),
                ]);
            }
        }
    }
}
