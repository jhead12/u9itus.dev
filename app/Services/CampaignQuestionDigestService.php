<?php

namespace App\Services;

use App\Mail\CampaignQuestionDigestMail;
use App\Models\PoliticalCampaign;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class CampaignQuestionDigestService
{
    public function queueDigestForCampaign(PoliticalCampaign $campaign): bool
    {
        $campaign->loadMissing('politician.user');

        $politicianUser = $campaign->politician?->user;
        if (! $politicianUser instanceof User || empty($politicianUser->email)) {
            return false;
        }

        $preferences = $politicianUser->notificationPreference()->firstOrCreate([])->refresh();
        if (! $preferences->isEnabled('email', 'campaign_status')) {
            return false;
        }

        $questions = $campaign->voterWatchReports()
            ->messages()
            ->with('voter:id,full_name,email')
            ->oldest('created_at')
            ->get();

        if ($questions->isEmpty()) {
            return false;
        }

        try {
            Mail::to($politicianUser->email)->queue(new CampaignQuestionDigestMail($campaign, $questions));

            return true;
        } catch (\Throwable $e) {
            Log::warning('Failed to queue campaign question digest', [
                'campaign_id' => $campaign->id,
                'user_id' => $politicianUser->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
