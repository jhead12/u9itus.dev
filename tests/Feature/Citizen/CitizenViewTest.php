<?php

use App\Enums\ApprovalStatus;
use App\Enums\CampaignStatus;
use App\Enums\CitizenAdType;
use App\Models\Citizen;
use App\Models\CitizenCampaign;
use App\Models\CitizenCredit;
use App\Models\User;
use App\Models\Voter;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    if (class_exists(\Spatie\Permission\Models\Role::class)) {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'voter', 'guard_name' => 'web']);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'citizen', 'guard_name' => 'web']);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    }
});

function makeVoterForCitizenView(): User
{
    $user = User::factory()->create(['platform' => 'standalone']);
    $user->assignRole('voter');
    Voter::factory()->create([
        'user_id'     => $user->id,
        'email'       => $user->email,
        'is_verified' => true,
        'is_active'   => true,
    ]);
    skipOnboarding($user, 'voter');
    return $user->load('voter');
}

function makeAdminForCitizenView(): User
{
    $user = User::factory()->create(['platform' => 'standalone']);
    $user->assignRole('admin');
    skipOnboarding($user, 'admin');
    return $user;
}

function makeApprovedCitizenCampaign(float $budget = 30.00, int $viewsRequested = 10): CitizenCampaign
{
    $citizen = Citizen::factory()->create();

    CitizenCredit::factory()->create([
        'citizen_id'       => $citizen->id,
        'transaction_type' => 'purchase',
        'amount'           => $budget,
        'balance_after'    => $budget,
        'description'      => 'Opening balance',
    ]);
    $citizen->syncCreditBalance();

    $campaign = CitizenCampaign::factory()->create([
        'citizen_id'          => $citizen->id,
        'citizen_ad_type'     => CitizenAdType::LocalBusiness->value,
        'status'              => CampaignStatus::PendingApproval->value,
        'approval_status'     => ApprovalStatus::Pending->value,
        'total_budget'        => $budget,
        'total_views_requested' => $viewsRequested,
        'media_duration'      => 60,
        'min_watch_time_percent' => 80,
        'allow_repeat_views'  => true,
        'max_views_per_voter' => $viewsRequested,
        'repeat_view_cooldown_hours' => 0,
        'media_url'           => 'https://www.youtube.com/watch?v=abc',
    ]);

    $admin = makeAdminForCitizenView();

    test()->actingAs($admin)
        ->post(route('admin.citizen-campaigns.approve', $campaign))
        ->assertSessionHas('success');

    $campaign->refresh();
    return $campaign;
}

// ── Citizen campaign view completion ──────────────────────────────────────────

test('voter can complete a citizen campaign view and earn payout', function () {
    $user    = makeVoterForCitizenView();
    $voter   = $user->voter;
    $campaign = makeApprovedCitizenCampaign(30.00, 10);

    $response = $this->actingAs($user)
        ->postJson(route('voter.citizen-campaigns.complete', $campaign), [
            'total_seconds_watched'  => 60,
            'media_duration_seconds' => 60,
        ]);

    $response->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('qualified', true)
        ->assertJsonPath('payout_earned', 0.50)
        ->assertJsonPath('status', 'completed');

    $campaign->refresh();
    expect($campaign->views_completed)->toBe(1);
    expect((float) $campaign->amount_spent)->toBe(0.75);

    $voter->refresh();
    expect((float) $voter->pending_earnings)->toBe(0.50);
    expect($voter->total_views)->toBe(1);

    $session = \App\Models\CitizenViewSession::first();
    expect($session)->not->toBeNull();
    expect($session->status->value)->toBe('completed');
    expect((float) $session->voter_payout_amount)->toBe(0.50);

    $tx = \App\Models\CitizenTransaction::where('transaction_type', 'view_charge')->first();
    expect($tx)->not->toBeNull();
    expect((float) $tx->amount)->toBe(0.75);
});

test('voter cannot complete a citizen campaign view when reserved budget is exhausted', function () {
    $user    = makeVoterForCitizenView();
    $campaign = makeApprovedCitizenCampaign(3.00, 10); // 4 views possible at $0.75 each

    // Exhaust reserved budget
    for ($i = 0; $i < 4; $i++) {
        $this->actingAs($user)
            ->postJson(route('voter.citizen-campaigns.complete', $campaign), [
                'total_seconds_watched'  => 60,
                'media_duration_seconds' => 60,
            ])
            ->assertOk();
    }

    $campaign->refresh();
    expect($campaign->views_completed)->toBe(4);
    expect((float) $campaign->amount_spent)->toBe(3.00);

    // Fifth view should be rejected because the campaign no longer accepts views.
    $this->actingAs($user)
        ->postJson(route('voter.citizen-campaigns.complete', $campaign), [
            'total_seconds_watched'  => 60,
            'media_duration_seconds' => 60,
        ])
        ->assertStatus(403)
        ->assertJsonPath('error', 'You are not currently eligible to earn from this campaign.');
});

test('non-voter cannot complete a citizen campaign view', function () {
    $campaign = makeApprovedCitizenCampaign();

    $citizenUser = User::factory()->create(['platform' => 'standalone']);
    $citizenUser->assignRole('citizen');
    Citizen::factory()->create(['user_id' => $citizenUser->id]);
    skipOnboarding($citizenUser, 'citizen');

    $this->actingAs($citizenUser)
        ->postJson(route('voter.citizen-campaigns.complete', $campaign), [
            'total_seconds_watched' => 60,
        ])
        ->assertForbidden();
});

// ── Ad room integration ─────────────────────────────────────────────────────

test('voter ad room shows available citizen campaigns', function () {
    $user     = makeVoterForCitizenView();
    $campaign = makeApprovedCitizenCampaign();

    $this->actingAs($user)
        ->get(route('voter.ad-room'))
        ->assertOk()
        ->assertSee('Community')
        ->assertSee($campaign->title)
        ->assertSee('Watch')
        ->assertSee('Earn');
});

test('voter can view citizen campaign watch page', function () {
    $user     = makeVoterForCitizenView();
    $campaign = makeApprovedCitizenCampaign();

    $this->actingAs($user)
        ->get(route('voter.citizen-campaigns.watch', $campaign))
        ->assertOk()
        ->assertSee($campaign->title)
        ->assertSee('Click to Play');
});

test('ineligible voter is redirected away from citizen campaign watch page', function () {
    $user     = makeVoterForCitizenView();
    $campaign = makeApprovedCitizenCampaign(3.00, 1);

    // Exhaust the only available view
    $this->actingAs($user)
        ->postJson(route('voter.citizen-campaigns.complete', $campaign), [
            'total_seconds_watched'  => 60,
            'media_duration_seconds' => 60,
        ])
        ->assertOk();

    $campaign->refresh();
    expect($campaign->views_completed)->toBe(1);

    $this->actingAs($user)
        ->get(route('voter.citizen-campaigns.watch', $campaign))
        ->assertRedirect()
        ->assertSessionHasErrors(['watch']);
});
