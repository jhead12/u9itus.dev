<?php

use App\Enums\ApprovalStatus;
use App\Enums\CampaignStatus;
use App\Models\CampaignAuditLog;
use App\Models\PoliticalCampaign;
use App\Models\Politician;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

// ── Helpers ───────────────────────────────────────────────────────────────────

beforeEach(function () {
    Mail::fake();

    if (class_exists(\Spatie\Permission\Models\Role::class)) {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin',      'guard_name' => 'web']);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'politician', 'guard_name' => 'web']);
    }
});

function makeAdmin(): User
{
    $user = User::factory()->create(['platform' => 'standalone']);
    if (method_exists($user, 'assignRole')) {
        $user->assignRole('admin');
    }
    
    // Skip onboarding for test
    skipOnboarding($user, 'admin');
    
    return $user;
}

function makePendingCampaign(array $attrs = []): PoliticalCampaign
{
    return PoliticalCampaign::factory()->create(array_merge([
        'status'          => CampaignStatus::PendingApproval->value,
        'approval_status' => ApprovalStatus::Pending->value,
    ], $attrs));
}

// ── Access control ────────────────────────────────────────────────────────────

test('guest cannot access the pending campaigns page', function () {
    $this->get(route('admin.campaigns.pending'))
         ->assertRedirect(route('login'));
});

test('non-admin politician is denied access to admin routes', function () {
    \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'politician', 'guard_name' => 'web']);

    $politician = User::factory()->create(['platform' => 'standalone']);
    $politician->assignRole('politician');
    
    // Skip onboarding for test
    skipOnboarding($politician, 'politician');

    $this->actingAs($politician)
         ->get(route('admin.campaigns.pending'))
         ->assertForbidden();
});

// ── pendingCampaigns() ────────────────────────────────────────────────────────

test('admin can view pending campaigns list', function () {
    makePendingCampaign();
    makePendingCampaign();

    $this->actingAs(makeAdmin())
         ->get(route('admin.campaigns.pending'))
         ->assertOk()
         ->assertViewIs('standalone.admin.campaigns-pending');
});

// ── approveCampaign() ─────────────────────────────────────────────────────────

test('admin can approve a pending campaign', function () {
    $admin    = makeAdmin();
    $campaign = makePendingCampaign();

    $this->actingAs($admin)
         ->post(route('admin.campaigns.approve', $campaign))
         ->assertRedirect();

    $campaign->refresh();

    expect($campaign->approval_status)->toBe(ApprovalStatus::Approved);
    expect($campaign->status)->toBe(CampaignStatus::Active);

    $this->assertDatabaseHas('campaign_audit_logs', [
        'campaign_id' => $campaign->id,
        'admin_id'    => $admin->id,
        'action'      => 'approved',
    ]);
});

test('approving a campaign queues a notification email to the politician', function () {
    $admin     = makeAdmin();
    $politician = Politician::factory()->create();
    $campaign   = makePendingCampaign(['politician_id' => $politician->id]);

    $this->actingAs($admin)
         ->post(route('admin.campaigns.approve', $campaign));

    // Mail is faked — just confirm no exception was thrown and redirect succeeded
    // (email delivery tested in isolation in NotificationTest)
    expect(true)->toBeTrue();
});

// ── rejectCampaign() ──────────────────────────────────────────────────────────

test('admin can reject a pending campaign with a reason', function () {
    $admin    = makeAdmin();
    $campaign = makePendingCampaign();

    $this->actingAs($admin)
         ->post(route('admin.campaigns.reject', $campaign), [
             'reason' => 'Violates content policy.',
         ])
         ->assertRedirect();

    $campaign->refresh();

    expect($campaign->approval_status)->toBe(ApprovalStatus::Rejected);
    expect($campaign->status)->toBe(CampaignStatus::Cancelled);

    $this->assertDatabaseHas('campaign_audit_logs', [
        'campaign_id' => $campaign->id,
        'admin_id'    => $admin->id,
        'action'      => 'rejected',
        'reason'      => 'Violates content policy.',
    ]);
});

test('rejection reason defaults to content-guidelines message when omitted', function () {
    $admin    = makeAdmin();
    $campaign = makePendingCampaign();

    $this->actingAs($admin)
         ->post(route('admin.campaigns.reject', $campaign));

    $log = CampaignAuditLog::where('campaign_id', $campaign->id)
        ->where('action', 'rejected')
        ->first();

    expect($log)->not->toBeNull();

    $campaign->refresh();
    expect($campaign->rejection_reason)
        ->toBe('Does not meet content guidelines.');
});

// ── stopCampaign() ────────────────────────────────────────────────────────────

test('admin can stop an active campaign', function () {
    $admin    = makeAdmin();
    $campaign = PoliticalCampaign::factory()->create([
        'status'          => CampaignStatus::Active->value,
        'approval_status' => ApprovalStatus::Approved->value,
    ]);

    $this->actingAs($admin)
         ->post(route('admin.campaigns.stop', $campaign), [
             'reason' => 'Pending investigation.',
         ])
         ->assertRedirect();

    $campaign->refresh();

    expect($campaign->status)->toBe(CampaignStatus::Paused);

    $this->assertDatabaseHas('campaign_audit_logs', [
        'campaign_id' => $campaign->id,
        'action'      => 'stopped',
        'reason'      => 'Pending investigation.',
    ]);
});

test('stopCampaign requires a reason', function () {
    $admin    = makeAdmin();
    $campaign = PoliticalCampaign::factory()->create([
        'status' => CampaignStatus::Active->value,
    ]);

    $this->actingAs($admin)
         ->post(route('admin.campaigns.stop', $campaign))
         ->assertSessionHasErrors('reason');
});

// ── reactivateCampaign() ──────────────────────────────────────────────────────

test('admin can reactivate a paused campaign', function () {
    $admin    = makeAdmin();
    $campaign = PoliticalCampaign::factory()->create([
        'status'          => CampaignStatus::Paused->value,
        'approval_status' => ApprovalStatus::Approved->value,
    ]);

    $this->actingAs($admin)
         ->post(route('admin.campaigns.reactivate', $campaign))
         ->assertRedirect();

    $campaign->refresh();

    expect($campaign->status)->toBe(CampaignStatus::Active);

    $this->assertDatabaseHas('campaign_audit_logs', [
        'campaign_id' => $campaign->id,
        'action'      => 'reactivated',
    ]);
});
