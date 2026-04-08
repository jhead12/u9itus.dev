<?php

use App\Enums\ApprovalStatus;
use App\Enums\CampaignStatus;
use App\Enums\PaymentStatus;
use App\Enums\ViewSessionStatus;
use App\Models\PoliticalCampaign;
use App\Models\User;
use App\Models\ViewSession;
use App\Models\Voter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

const REPEAT_COOLDOWN_TITLE = 'Repeat Campaign Cooldown Test';

function voterForAdRoom(): array
{
    Role::firstOrCreate(['name' => 'voter', 'guard_name' => 'web']);

    $user = User::factory()->create(['user_type' => 'voter']);
    $user->assignRole('voter');
    skipOnboarding($user, 'voter');

    $voter = Voter::factory()->create([
        'user_id' => $user->id,
        'is_verified' => true,
        'is_active' => true,
        'flagged_for_fraud' => false,
    ]);

    return [$user, $voter];
}

function cooldownEligibleCampaign(array $overrides = []): PoliticalCampaign
{
    $politicianUser = User::factory()->create([
        'user_type' => 'politician',
        'email' => 'candidate+' . uniqid() . '@example.com',
    ]);

    return PoliticalCampaign::factory()->create(array_merge([
        'politician_id' => \App\Models\Politician::factory()->create([
            'user_id' => $politicianUser->id,
            'is_active' => true,
        ])->id,
        'status' => CampaignStatus::Active->value,
        'approval_status' => ApprovalStatus::Approved->value,
        'payment_status' => PaymentStatus::Captured->value,
        'stripe_payment_intent_id' => 'pi_' . uniqid(),
        'allow_repeat_views' => true,
        'repeat_view_cooldown_hours' => 24,
        'max_views_per_voter' => 3,
        'title' => REPEAT_COOLDOWN_TITLE,
        'total_views_requested' => 100,
        'views_completed' => 0,
    ], $overrides));
}

test('ad room hides repeat campaign while cooldown window is still active', function () {
    [$user, $voter] = voterForAdRoom();
    $campaign = cooldownEligibleCampaign();

    ViewSession::factory()->create([
        'voter_id' => $voter->id,
        'political_campaign_id' => $campaign->id,
        'status' => ViewSessionStatus::Completed->value,
        'completed_at' => now()->subHours(2),
    ]);

    $response = $this->actingAs($user)->get(route('voter.ad-room'));

    $response->assertOk();
    $response->assertDontSee(REPEAT_COOLDOWN_TITLE);
});

test('ad room shows repeat campaign again after cooldown window elapses', function () {
    [$user, $voter] = voterForAdRoom();
    $campaign = cooldownEligibleCampaign();

    ViewSession::factory()->create([
        'voter_id' => $voter->id,
        'political_campaign_id' => $campaign->id,
        'status' => ViewSessionStatus::Completed->value,
        'completed_at' => now()->subHours(26),
    ]);

    $response = $this->actingAs($user)->get(route('voter.ad-room'));

    $response->assertOk();
    $response->assertSee(REPEAT_COOLDOWN_TITLE);
});
