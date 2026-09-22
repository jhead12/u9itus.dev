<?php

use App\Models\PoliticalCampaign;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channel Authorisation — U9itus Phase 11
|--------------------------------------------------------------------------
|
| Channel naming conventions used throughout the platform:
|
|   private-politician.{userId}     — Politician notifications (approved, rejected, stopped)
|   private-voter.{userId}          — Voter notifications (new ad token, payout, session complete)
|   private-citizen.{userId}        — Citizen notifications (campaign approved, rejected, stopped)
|   private-admin.monitor           — Admin fraud/analytics stream (admin role only)
|   presence-campaign.live.{uuid}   — Live campaign viewer presence (Phase 12 WebRTC signaling)
|
*/

/*
|--------------------------------------------------------------------------
| Politician Private Channel
| Carries: campaign.approved · campaign.rejected · campaign.stopped
|--------------------------------------------------------------------------
*/
Broadcast::channel('politician.{userId}', function (User $user, int $userId): bool {
    // Only the politician who owns this user ID may subscribe
    return (int) $user->id === $userId && $user->hasRole('politician');
});

/*
|--------------------------------------------------------------------------
| Voter Private Channel
| Carries: ad.token.delivered · session.completed · payout.processed
|--------------------------------------------------------------------------
*/
Broadcast::channel('voter.{userId}', function (User $user, int $userId): bool {
    return (int) $user->id === $userId && $user->hasRole('voter');
});

/*
|--------------------------------------------------------------------------
| Citizen Private Channel
| Carries: campaign.approved · campaign.rejected · campaign.stopped · campaign.reactivated
|--------------------------------------------------------------------------
*/
Broadcast::channel('citizen.{userId}', function (User $user, int $userId): bool {
    return (int) $user->id === $userId && $user->hasRole('citizen');
});

/*
|--------------------------------------------------------------------------
| Admin Monitor Private Channel
| Carries: fraud.flag.raised · session.completed (throughput metrics)
|--------------------------------------------------------------------------
*/
Broadcast::channel('admin.monitor', function (User $user): bool {
    // Fraud/analytics stream: owner always allowed; otherwise requires the
    // fraud.view permission so legacy admins (grandfathered with the full
    // catalog) keep the access they had before staff permissions existed.
    return \App\Support\AdminAccess::allowed($user, 'fraud.view');
});

/*
|--------------------------------------------------------------------------
| Live Campaign Presence Channel  (Phase 12 WebRTC signaling foundation)
| Carries: campaign.live.started + WebRTC offer/answer/ICE (Phase 12)
|
| Returns the user info array so Reverb can expose joinedCount and user
| metadata to all channel members — the client-side Echo presence listener
| can render a live viewer count from this data.
|--------------------------------------------------------------------------
*/
Broadcast::channel('campaign.live.{campaignUuid}', function (User $user, string $campaignUuid): array|bool {
    $campaign = PoliticalCampaign::where('uuid', $campaignUuid)
        ->where('status', 'approved')
        ->first();

    if (! $campaign) {
        return false;
    }

    // Politicians can host; voters can watch; admins can monitor
    if (! $user->hasAnyRole(['politician', 'voter']) && ! \App\Support\AdminAccess::allowed($user, 'campaigns.view')) {
        return false;
    }

    return [
        'id'   => $user->id,
        'name' => $user->name,
        'role' => $user->getRoleNames()->first(),
    ];
});
