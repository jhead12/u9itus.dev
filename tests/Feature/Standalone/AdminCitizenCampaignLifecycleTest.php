<?php

use App\Enums\ApprovalStatus;
use App\Enums\CampaignStatus;
use App\Models\Citizen;
use App\Models\CitizenCampaign;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ── Helpers ───────────────────────────────────────────────────────────────

beforeEach(function () {
    if (class_exists(\Spatie\Permission\Models\Role::class)) {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    }
});

function makeAdminForCitizenLifecycle(): User
{
    $admin = User::factory()->create(['platform' => 'standalone', 'user_type' => 'admin']);
    if (method_exists($admin, 'assignRole')) {
        $admin->assignRole('admin');
    }
    skipOnboarding($admin, 'admin');
    return $admin;
}

function makeApprovedActiveCitizenCampaign(array $overrides = []): CitizenCampaign
{
    $citizenUser = User::factory()->create(['platform' => 'standalone', 'user_type' => 'citizen']);
    $citizen = Citizen::factory()->create(['user_id' => $citizenUser->id]);

    return CitizenCampaign::factory()->create(array_merge([
        'citizen_id'      => $citizen->id,
        'status'          => CampaignStatus::Active->value,
        'approval_status' => ApprovalStatus::Approved->value,
        'amount_spent'    => 25.50,
        'views_completed' => 30,
        'total_budget'    => 100.00,
        'total_views_requested' => 100,
    ], $overrides));
}

// ── Pending queue ─────────────────────────────────────────────────────────

test('pending queue shows all citizen ad types, not just ballot issue', function () {
    $admin = makeAdminForCitizenLifecycle();

    $citizenUser = User::factory()->create(['platform' => 'standalone', 'user_type' => 'citizen']);
    $citizen = Citizen::factory()->create(['user_id' => $citizenUser->id]);

    CitizenCampaign::factory()->create([
        'citizen_id'      => $citizen->id,
        'citizen_ad_type' => 'local_business',
        'approval_status' => ApprovalStatus::Pending->value,
        'status'          => 'pending_approval',
        'title'           => 'Local Coffee Shop Ad',
    ]);

    CitizenCampaign::factory()->create([
        'citizen_id'      => $citizen->id,
        'citizen_ad_type' => 'ballot_issue',
        'approval_status' => ApprovalStatus::Pending->value,
        'status'          => 'pending_approval',
        'title'           => 'Prop 22 Campaign',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.campaigns.pending'))
        ->assertOk()
        ->assertSee('Citizen Campaigns')
        ->assertDontSee('Citizen Campaigns — Ballot Issue')
        ->assertSee('Local Coffee Shop Ad')
        ->assertSee('Prop 22 Campaign')
        ->assertSee('Local Business')
        ->assertSee('Ballot Issue');
});

test('pending queue does not show PAC warning for non-ballot citizen campaigns', function () {
    $admin = makeAdminForCitizenLifecycle();

    $citizenUser = User::factory()->create(['platform' => 'standalone', 'user_type' => 'citizen']);
    $citizen = Citizen::factory()->create(['user_id' => $citizenUser->id]);

    CitizenCampaign::factory()->create([
        'citizen_id'      => $citizen->id,
        'citizen_ad_type' => 'community_notice',
        'title'           => 'Community Notice Campaign',
        'approval_status' => ApprovalStatus::Pending->value,
        'status'          => 'pending_approval',
        'pac_registration_id' => null,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.campaigns.pending'))
        ->assertOk()
        ->assertSee('Community Notice Campaign')
        ->assertDontSee('No PAC registration ID on file');
});

// ── Running campaigns view ────────────────────────────────────────────────

test('running campaigns page lists active citizen campaigns', function () {
    $admin = makeAdminForCitizenLifecycle();
    $cc = makeApprovedActiveCitizenCampaign(['title' => 'My Active Citizen Campaign']);

    $this->actingAs($admin)
        ->get(route('admin.campaigns.running'))
        ->assertOk()
        ->assertSee('Citizen Campaigns')
        ->assertSee('My Active Citizen Campaign')
        ->assertSee('$25.50'); // amount_spent
});

test('running campaigns page shows citizen summary stats', function () {
    $admin = makeAdminForCitizenLifecycle();
    makeApprovedActiveCitizenCampaign();
    makeApprovedActiveCitizenCampaign(['status' => CampaignStatus::Paused->value]);

    $this->actingAs($admin)
        ->get(route('admin.campaigns.running'))
        ->assertOk()
        ->assertSee('Running Citizen Campaigns');
});

// ── Pause ─────────────────────────────────────────────────────────────────

test('admin can pause an active citizen campaign', function () {
    $admin = makeAdminForCitizenLifecycle();
    $cc = makeApprovedActiveCitizenCampaign();

    $this->actingAs($admin)
        ->post(route('admin.citizen-campaigns.pause', $cc))
        ->assertRedirect();

    expect($cc->fresh()->status)->toBe(CampaignStatus::Paused);
});

test('pausing an already paused campaign returns an error flash', function () {
    $admin = makeAdminForCitizenLifecycle();
    $cc = makeApprovedActiveCitizenCampaign(['status' => CampaignStatus::Paused->value]);

    $this->actingAs($admin)
        ->post(route('admin.citizen-campaigns.pause', $cc))
        ->assertRedirect()
        ->assertSessionHas('error');
});

// ── Stop ─────────────────────────────────────────────────────────────────

test('admin can stop an active citizen campaign', function () {
    $admin = makeAdminForCitizenLifecycle();
    $cc = makeApprovedActiveCitizenCampaign();

    $this->actingAs($admin)
        ->post(route('admin.citizen-campaigns.stop', $cc), ['reason' => 'Policy violation'])
        ->assertRedirect();

    $fresh = $cc->fresh();
    expect($fresh->status)->toBe(CampaignStatus::Cancelled);
    expect($fresh->rejection_reason)->toBe('Policy violation');
});

test('stop without reason falls back to default message', function () {
    $admin = makeAdminForCitizenLifecycle();
    $cc = makeApprovedActiveCitizenCampaign();

    $this->actingAs($admin)
        ->post(route('admin.citizen-campaigns.stop', $cc))
        ->assertRedirect();

    expect($cc->fresh()->rejection_reason)->toBe('Stopped by admin.');
});

// ── Reactivate ────────────────────────────────────────────────────────────

test('admin can reactivate a paused citizen campaign', function () {
    $admin = makeAdminForCitizenLifecycle();
    $cc = makeApprovedActiveCitizenCampaign(['status' => CampaignStatus::Paused->value]);

    $this->actingAs($admin)
        ->post(route('admin.citizen-campaigns.reactivate', $cc))
        ->assertRedirect();

    expect($cc->fresh()->status)->toBe(CampaignStatus::Active);
});

test('reactivate sets status to scheduled when scheduled_start_at is in the future', function () {
    $admin = makeAdminForCitizenLifecycle();
    $cc = makeApprovedActiveCitizenCampaign([
        'status'              => CampaignStatus::Paused->value,
        'scheduled_start_at'  => now()->addDays(3),
    ]);

    $this->actingAs($admin)
        ->post(route('admin.citizen-campaigns.reactivate', $cc))
        ->assertRedirect();

    expect($cc->fresh()->status)->toBe(CampaignStatus::Scheduled);
});

test('reactivate is blocked for unapproved campaigns', function () {
    $admin = makeAdminForCitizenLifecycle();
    $citizenUser = User::factory()->create(['platform' => 'standalone', 'user_type' => 'citizen']);
    $citizen = Citizen::factory()->create(['user_id' => $citizenUser->id]);

    $cc = CitizenCampaign::factory()->create([
        'citizen_id'      => $citizen->id,
        'status'          => CampaignStatus::Paused->value,
        'approval_status' => ApprovalStatus::Pending->value,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.citizen-campaigns.reactivate', $cc))
        ->assertRedirect()
        ->assertSessionHas('error');
});

// ── Political campaigns unaffected ────────────────────────────────────────

test('political running campaigns are still listed after citizen section added', function () {
    $admin = makeAdminForCitizenLifecycle();

    $this->actingAs($admin)
        ->get(route('admin.campaigns.running'))
        ->assertOk()
        ->assertSee('Running Campaigns');
});
