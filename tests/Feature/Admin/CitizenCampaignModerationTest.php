<?php

use App\Enums\ApprovalStatus;
use App\Enums\CampaignStatus;
use App\Enums\CitizenAdType;
use App\Models\Citizen;
use App\Models\CitizenCampaign;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    if (class_exists(\Spatie\Permission\Models\Role::class)) {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin',   'guard_name' => 'web']);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'citizen', 'guard_name' => 'web']);
    }
});

function makeAdminUser(): User
{
    $user = User::factory()->create(['platform' => 'standalone']);
    $user->assignRole('admin');
    skipOnboarding($user, 'admin');
    return $user;
}

function makePendingBallotCampaign(array $attrs = []): CitizenCampaign
{
    $citizen = Citizen::factory()->create();

    // Seed sufficient wallet credits so approval-time budget reservation succeeds.
    $budget = (float) ($attrs['total_budget'] ?? 100.00);
    \App\Models\CitizenCredit::factory()->create([
        'citizen_id'      => $citizen->id,
        'transaction_type' => 'purchase',
        'amount'          => $budget,
        'balance_after'   => $budget,
        'description'     => 'Test opening balance',
    ]);
    $citizen->syncCreditBalance();

    return CitizenCampaign::factory()->create(array_merge([
        'citizen_id'          => $citizen->id,
        'citizen_ad_type'     => CitizenAdType::BallotIssue->value,
        'status'              => CampaignStatus::PendingApproval->value,
        'approval_status'     => ApprovalStatus::Pending->value,
        'pac_registration_id' => 'C00123456',
        'media_url'           => 'https://www.youtube.com/watch?v=abc',
    ], $attrs));
}

// ── Pending queue ─────────────────────────────────────────────────────────────

test('admin sees pending citizen ballot-issue campaign in queue', function () {
    $admin    = makeAdminUser();
    $campaign = makePendingBallotCampaign(['title' => 'Vote Yes on Measure Z']);

    $this->actingAs($admin)
        ->get(route('admin.campaigns.pending'))
        ->assertOk()
        ->assertSee('Citizen Campaigns')
        ->assertSee('Vote Yes on Measure Z')
        ->assertSee('C00123456');
});

test('pending citizen campaign row includes an ad preview panel', function () {
    $admin    = makeAdminUser();
    $campaign = makePendingBallotCampaign([
        'title' => 'Vote Yes on Measure Z',
        'message_summary' => 'Please support Measure Z on the upcoming ballot.',
        'media_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        'media_type' => 'youtube',
    ]);

    $response = $this->actingAs($admin)->get(route('admin.campaigns.pending'));

    $response->assertOk();
    // The "Show / hide details" toggle and its target panel are present in the DOM
    // (hidden via CSS class, not omitted from the response), so the admin can review
    // the actual ad content before approving.
    $response->assertSee('id="citizen-details-' . $campaign->id . '"', false);
    $response->assertSee('Ad Preview');
    $response->assertSee('Please support Measure Z on the upcoming ballot.');
});

test('citizen ad preview panel shows video duration, min watch percent, and revenue per view', function () {
    $admin    = makeAdminUser();
    $campaign = makePendingBallotCampaign([
        'media_duration' => 45,
        'min_watch_time_percent' => 90,
        'revenue_per_view' => 1.25,
    ]);

    $response = $this->actingAs($admin)->get(route('admin.campaigns.pending'));

    $response->assertOk();
    $response->assertSee('Video Duration');
    $response->assertSee('45s (0.8 min)', false);
    $response->assertSee('Min Watch %');
    $response->assertSee('90%');
    $response->assertSee('Revenue / View');
    $response->assertSee('$1.25', false);
});

test('non-admin cannot access the pending campaigns page', function () {
    $citizenUser = User::factory()->create(['platform' => 'standalone']);
    \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'citizen', 'guard_name' => 'web']);
    $citizenUser->assignRole('citizen');
    Citizen::factory()->create(['user_id' => $citizenUser->id]);
    skipOnboarding($citizenUser, 'citizen');

    $this->actingAs($citizenUser)
        ->get(route('admin.campaigns.pending'))
        ->assertForbidden();
});

// ── Approve ───────────────────────────────────────────────────────────────────

test('admin can approve a citizen ballot-issue campaign', function () {
    $admin    = makeAdminUser();
    $campaign = makePendingBallotCampaign();

    $this->actingAs($admin)
        ->post(route('admin.citizen-campaigns.approve', $campaign))
        ->assertRedirect();

    $campaign->refresh();
    expect($campaign->approval_status->value)->toBe('approved');
    expect($campaign->status->value)->toBe('active');
    expect($campaign->approved_at)->not->toBeNull();
});

test('approving a scheduled citizen campaign sets status to scheduled', function () {
    $admin    = makeAdminUser();
    $campaign = makePendingBallotCampaign([
        'scheduled_start_at' => now()->addDay(),
    ]);

    $this->actingAs($admin)
        ->post(route('admin.citizen-campaigns.approve', $campaign))
        ->assertRedirect();

    $campaign->refresh();
    expect($campaign->status->value)->toBe('scheduled');
    expect($campaign->approval_status->value)->toBe('approved');
});

// ── Reject ────────────────────────────────────────────────────────────────────

test('admin can reject a citizen campaign with a reason', function () {
    $admin    = makeAdminUser();
    $campaign = makePendingBallotCampaign();

    $this->actingAs($admin)
        ->post(route('admin.citizen-campaigns.reject', $campaign), [
            'reason' => 'Missing required PAC documentation.',
        ])
        ->assertRedirect();

    $campaign->refresh();
    expect($campaign->approval_status->value)->toBe('rejected');
    expect($campaign->status->value)->toBe('draft');
    expect($campaign->rejection_reason)->toBe('Missing required PAC documentation.');
});

test('rejection without reason uses default message', function () {
    $admin    = makeAdminUser();
    $campaign = makePendingBallotCampaign();

    $this->actingAs($admin)
        ->post(route('admin.citizen-campaigns.reject', $campaign))
        ->assertRedirect();

    $campaign->refresh();
    expect($campaign->approval_status->value)->toBe('rejected');
    expect($campaign->rejection_reason)->not->toBeEmpty();
});
