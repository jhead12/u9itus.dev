<?php

namespace App\Services;

use App\Enums\ApprovalStatus;
use App\Models\Cause;
use App\Models\PoliticalCampaign;
use App\Models\Voter;
use App\Services\Marketing\AudienceService;
use Illuminate\Support\Collection;

/**
 * Finds PoliticalCampaigns worth notifying a voter about after they favorite
 * a Cause — campaigns sharing the Cause's Topic, scoped to the voter's own
 * district/state via the existing AudienceService targeting engine (so this
 * doesn't duplicate AudienceService's district-code normalization logic).
 */
class CauseCampaignMatchService
{
    public function __construct(
        private readonly AudienceService $audienceService,
    ) {
    }

    /**
     * @return Collection<int, PoliticalCampaign>
     */
    public function matchesForVoter(Voter $voter, Cause $cause): Collection
    {
        $candidates = PoliticalCampaign::query()
            ->whereHas('topics', fn ($q) => $q->where('topic_id', $cause->topic_id))
            ->where('approval_status', ApprovalStatus::Approved)
            ->get();

        return $candidates->filter(
            fn (PoliticalCampaign $campaign) => $this->audienceService
                ->forCampaign($campaign)
                ->where('voters.id', $voter->id)
                ->exists()
        )->values();
    }
}
