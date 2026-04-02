<?php

use App\Enums\ApprovalStatus;
use App\Enums\CampaignStatus;
use App\Enums\PaymentStatus;
use App\Mail\CampaignApprovedMail;
use App\Mail\CampaignRejectedMail;
use App\Models\CampaignAuditLog;
use App\Models\EmailTemplate;
use App\Models\NotificationPreference;
use App\Models\PoliticalCampaign;
use App\Models\Politician;
use App\Models\User;
use App\Notifications\CampaignStatusChangedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

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
    expect($campaign->payment_status)->toBe(PaymentStatus::Captured);
    expect($campaign->stripe_payment_intent_id)->not->toBeNull()->not->toBe('');

    $this->assertDatabaseHas('campaign_audit_logs', [
        'campaign_id' => $campaign->id,
        'admin_id'    => $admin->id,
        'action'      => 'approved',
    ]);
});

test('approving a campaign queues a notification email to the politician', function () {
    Notification::fake();

    $admin     = makeAdmin();
    $politicianUser = User::factory()->create(['platform' => 'standalone']);
    $politician = Politician::factory()->create(['user_id' => $politicianUser->id]);
    $campaign   = makePendingCampaign(['politician_id' => $politician->id]);

    $this->actingAs($admin)
         ->post(route('admin.campaigns.approve', $campaign));

    Mail::assertQueued(CampaignApprovedMail::class, function (CampaignApprovedMail $mail) use ($campaign) {
        return $mail->campaign->is($campaign);
    });

    Notification::assertSentTo(
        $politicianUser,
        CampaignStatusChangedNotification::class,
        function (CampaignStatusChangedNotification $notification) {
            return $notification->status === 'approved';
        }
    );
});

test('approving a campaign does not queue email when campaign status email preference is disabled', function () {
    Notification::fake();

    $admin = makeAdmin();
    $politicianUser = User::factory()->create(['platform' => 'standalone']);
    NotificationPreference::create([
        'user_id' => $politicianUser->id,
        'email_campaign_status' => false,
        'inapp_campaign_status' => true,
    ]);

    $politician = Politician::factory()->create(['user_id' => $politicianUser->id]);
    $campaign = makePendingCampaign(['politician_id' => $politician->id]);

    $this->actingAs($admin)
        ->post(route('admin.campaigns.approve', $campaign));

    Mail::assertNotQueued(CampaignApprovedMail::class);

    Notification::assertSentTo(
        $politicianUser,
        CampaignStatusChangedNotification::class,
        function (CampaignStatusChangedNotification $notification) {
            return $notification->status === 'approved';
        }
    );
});

test('approving a campaign email uses admin template subject override when active', function () {
    Notification::fake();

    $admin = makeAdmin();
    $politicianUser = User::factory()->create(['platform' => 'standalone']);
    $politician = Politician::factory()->create(['user_id' => $politicianUser->id]);
    $campaign = makePendingCampaign(['politician_id' => $politician->id]);

    EmailTemplate::query()->updateOrCreate(
        ['key' => 'campaign_approved'],
        [
            'name' => 'Campaign Approved',
            'category' => 'campaign',
            'is_active' => true,
            'subject_override' => 'Custom Campaign Approval Subject',
        ]
    );

    $this->actingAs($admin)
        ->post(route('admin.campaigns.approve', $campaign));

    Mail::assertQueued(CampaignApprovedMail::class, function (CampaignApprovedMail $mail) {
        return $mail->envelope()->subject === 'Custom Campaign Approval Subject';
    });
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
    expect($campaign->status)->toBe(CampaignStatus::Draft);

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

test('rejecting a campaign sends bell notification and queued email', function () {
    Notification::fake();
    $reason = 'Policy mismatch';

    $admin = makeAdmin();
    $politicianUser = User::factory()->create(['platform' => 'standalone']);
    $politician = Politician::factory()->create(['user_id' => $politicianUser->id]);
    $campaign = makePendingCampaign(['politician_id' => $politician->id]);

    $this->actingAs($admin)
        ->post(route('admin.campaigns.reject', $campaign), ['reason' => $reason]);

    Mail::assertQueued(CampaignRejectedMail::class, function (CampaignRejectedMail $mail) use ($campaign, $reason) {
        return $mail->campaign->is($campaign) && $mail->reason === $reason;
    });

    Notification::assertSentTo(
        $politicianUser,
        CampaignStatusChangedNotification::class,
        function (CampaignStatusChangedNotification $notification) use ($reason) {
            return $notification->status === 'rejected' && $notification->reason === $reason;
        }
    );
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
