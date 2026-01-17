<?php

namespace App\Policies;

use App\Models\Campaign;
use App\Models\User;

class CampaignPolicy
{
    public function view(User $user, Campaign $campaign)
    {
        return $user->advertiser && $user->advertiser->id === $campaign->advertiser_id;
    }

    public function update(User $user, Campaign $campaign)
    {
        return $user->advertiser && $user->advertiser->id === $campaign->advertiser_id;
    }

    public function delete(User $user, Campaign $campaign)
    {
        return $user->advertiser && $user->advertiser->id === $campaign->advertiser_id;
    }
}
