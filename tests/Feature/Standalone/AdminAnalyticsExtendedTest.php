<?php

use App\Models\CitizenCampaign;
use App\Models\Citizen;
use App\Models\CampaignTransaction;
use App\Models\PayoutAttempt;
use App\Models\PoliticalCampaign;
use App\Models\ReferralVisit;
use App\Models\User;
use App\Models\ViewSession;
use App\Models\Voter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

// ── Shared helpers ────────────────────────────────────────────────────────

beforeEach(function () {
    if (class_exists(\Spatie\Permission\Models\Role::class)) {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'voter', 'guard_name' => 'web']);
    }
});

function makeAdminForExtended(): User
{
    $admin = User::factory()->create([
        'platform'   => 'standalone',
        'user_type'  => 'admin',
    ]);

    if (method_exists($admin, 'assignRole')) {
        $admin->assignRole('admin');
    }

    skipOnboarding($admin, 'admin');

    return $admin;
}

/** Create a PoliticalCampaign with a test-mode CampaignTransaction so it
 *  is included by modeScopedCampaignIds(). */
function makeTestCampaign(): PoliticalCampaign
{
    $campaign = PoliticalCampaign::factory()->create();

    CampaignTransaction::query()->create([
        'campaign_id'      => $campaign->id,
        'politician_id'    => $campaign->politician_id,
        'transaction_type' => 'charge',
        'amount'           => 10.00,
        'currency'         => 'USD',
        'status'           => 'succeeded',
        'metadata'         => ['payment_mode' => 'test'],
    ]);

    return $campaign;
}

// ── Early-bank enrollment ─────────────────────────────────────────────────

test('admin analytics shows early-bank enrolled and attributed counts', function () {
    $admin = makeAdminForExtended();

    // Voter 1: EB member (own UUID) — also attributed
    $v1 = User::factory()->create(['platform' => 'standalone', 'user_type' => 'voter']);
    Voter::factory()->create([
        'user_id'                  => $v1->id,
        'earlybank_own_member_uuid' => Str::uuid(),
        'earlybank_member_id'      => Str::uuid(),
    ]);

    // Voter 2: attributed only (referred by someone else)
    $v2 = User::factory()->create(['platform' => 'standalone', 'user_type' => 'voter']);
    Voter::factory()->create([
        'user_id'             => $v2->id,
        'earlybank_member_id' => Str::uuid(),
    ]);

    // Voter 3: no EB relationship
    $v3 = User::factory()->create(['platform' => 'standalone', 'user_type' => 'voter']);
    Voter::factory()->create(['user_id' => $v3->id]);

    $this->actingAs($admin)
        ->get(route('admin.analytics'))
        ->assertOk()
        ->assertSee('Early-bank Enrollment')
        ->assertSee('EB Members (own UUID)')
        ->assertSee('EB-Attributed Voters');
});

test('admin dashboard shows early-bank enrolled count', function () {
    $admin = makeAdminForExtended();

    $v1 = User::factory()->create(['platform' => 'standalone', 'user_type' => 'voter']);
    Voter::factory()->create([
        'user_id'                  => $v1->id,
        'earlybank_own_member_uuid' => Str::uuid(),
    ]);

    $response = $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk();

    $response->assertSee('Early-bank Members');
});

// ── Citizen campaigns ─────────────────────────────────────────────────────

test('admin analytics shows citizen campaign stats', function () {
    $admin = makeAdminForExtended();

    $citizenUser = User::factory()->create(['platform' => 'standalone', 'user_type' => 'citizen']);
    $citizen = Citizen::factory()->create(['user_id' => $citizenUser->id]);

    // 1 active citizen campaign
    CitizenCampaign::factory()->create([
        'citizen_id'      => $citizen->id,
        'status'          => 'active',
        'approval_status' => 'approved',
        'amount_spent'    => 15.50,
        'views_completed' => 20,
    ]);

    // 1 pending citizen campaign
    CitizenCampaign::factory()->create([
        'citizen_id'      => $citizen->id,
        'status'          => 'pending_approval',
        'approval_status' => 'pending',
        'amount_spent'    => 0,
        'views_completed' => 0,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.analytics'))
        ->assertOk()
        ->assertSee('Citizen Campaigns')
        ->assertSee('Active')
        ->assertSee('Pending Approval');
});

test('admin dashboard shows citizen campaign pending count', function () {
    $admin = makeAdminForExtended();

    $citizenUser = User::factory()->create(['platform' => 'standalone', 'user_type' => 'citizen']);
    $citizen = Citizen::factory()->create(['user_id' => $citizenUser->id]);

    CitizenCampaign::factory()->create([
        'citizen_id'      => $citizen->id,
        'status'          => 'pending_approval',
        'approval_status' => 'pending',
        'amount_spent'    => 0,
        'views_completed' => 0,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Citizen Campaigns');
});

// ── Referral funnel ───────────────────────────────────────────────────────

test('admin analytics shows referral funnel stats', function () {
    $admin = makeAdminForExtended();

    $voterUser = User::factory()->create(['platform' => 'standalone', 'user_type' => 'voter']);
    $voter = Voter::factory()->create(['user_id' => $voterUser->id]);

    // 3 visits, 1 conversion
    ReferralVisit::query()->create([
        'referral_code'    => $voter->referral_code,
        'referrer_voter_id' => $voter->id,
        'session_id'       => Str::random(20),
        'first_seen_at'    => now(),
        'last_seen_at'     => now(),
    ]);
    ReferralVisit::query()->create([
        'referral_code'    => $voter->referral_code,
        'referrer_voter_id' => $voter->id,
        'session_id'       => Str::random(20),
        'first_seen_at'    => now(),
        'last_seen_at'     => now(),
    ]);
    ReferralVisit::query()->create([
        'referral_code'      => $voter->referral_code,
        'referrer_voter_id'  => $voter->id,
        'session_id'         => Str::random(20),
        'converted_user_id'  => $voterUser->id,
        'converted_user_type' => 'voter',
        'converted_at'       => now(),
        'first_seen_at'      => now(),
        'last_seen_at'       => now(),
    ]);

    $this->actingAs($admin)
        ->get(route('admin.analytics'))
        ->assertOk()
        ->assertSee('Referral Funnel')
        ->assertSee('Total Link Visits')
        ->assertSee('Conversions');
});

// ── Payout health ─────────────────────────────────────────────────────────

test('admin analytics shows payout health stats including failure rate', function () {
    $admin = makeAdminForExtended();
    $campaign = makeTestCampaign();

    $voterUser = User::factory()->create(['platform' => 'standalone', 'user_type' => 'voter']);
    $voter = Voter::factory()->create([
        'user_id'        => $voterUser->id,
        'wallet_balance' => 5.00,
    ]);

    // A completed session with pending payout (unpaid liability)
    ViewSession::factory()->completed()->create([
        'political_campaign_id' => $campaign->id,
        'voter_id'              => $voter->id,
        'status'                => 'completed',
        'payment_status'        => 'pending',
        'voter_payout_amount'   => 0.50,
    ]);

    // 1 paid payout attempt + 1 failed
    PayoutAttempt::query()->create([
        'voter_id'         => $voter->id,
        'idempotency_key'  => Str::uuid(),
        'processor'        => 'paypal',
        'status'           => 'paid',
        'amount'           => 5.00,
        'session_ids'      => [],
    ]);
    PayoutAttempt::query()->create([
        'voter_id'         => $voter->id,
        'idempotency_key'  => Str::uuid(),
        'processor'        => 'paypal',
        'status'           => 'failed',
        'amount'           => 5.00,
        'session_ids'      => [],
    ]);

    $this->actingAs($admin)
        ->get(route('admin.analytics'))
        ->assertOk()
        ->assertSee('Payout Health')
        ->assertSee('Unpaid Session Liability')
        ->assertSee('Failure Rate');
});

test('admin dashboard shows unpaid wallet liability', function () {
    $admin = makeAdminForExtended();

    $v = User::factory()->create(['platform' => 'standalone', 'user_type' => 'voter']);
    Voter::factory()->create([
        'user_id'        => $v->id,
        'wallet_balance' => 12.75,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Unpaid Wallet Liability');
});

// ── Voter payment method breakdown ────────────────────────────────────────

test('admin analytics shows voter payment method distribution', function () {
    $admin = makeAdminForExtended();

    $u1 = User::factory()->create(['platform' => 'standalone', 'user_type' => 'voter']);
    Voter::factory()->create(['user_id' => $u1->id, 'payment_method' => 'paypal']);

    $u2 = User::factory()->create(['platform' => 'standalone', 'user_type' => 'voter']);
    Voter::factory()->create(['user_id' => $u2->id, 'payment_method' => 'cashapp']);

    $u3 = User::factory()->create(['platform' => 'standalone', 'user_type' => 'voter']);
    Voter::factory()->create(['user_id' => $u3->id, 'payment_method' => 'paypal']);

    $this->actingAs($admin)
        ->get(route('admin.analytics'))
        ->assertOk()
        ->assertSee('Voter Payout Method Distribution');
});

// ── Fraud session rate ────────────────────────────────────────────────────

test('admin analytics shows fraud session rate', function () {
    $admin = makeAdminForExtended();
    $campaign = makeTestCampaign();

    $voterUser = User::factory()->create(['platform' => 'standalone', 'user_type' => 'voter']);
    $voter = Voter::factory()->create(['user_id' => $voterUser->id]);

    // 1 clean session + 1 fraud session
    ViewSession::factory()->completed()->create([
        'political_campaign_id' => $campaign->id,
        'voter_id'              => $voter->id,
        'status'                => 'completed',
        'fraud_score'           => 10,
    ]);
    ViewSession::factory()->completed()->create([
        'political_campaign_id' => $campaign->id,
        'voter_id'              => $voter->id,
        'status'                => 'completed',
        'fraud_score'           => 80,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.analytics'))
        ->assertOk()
        ->assertSee('Fraud Session Rate');
});

// ── User growth chart ─────────────────────────────────────────────────────

test('admin analytics shows user growth when registrations exist', function () {
    $admin = makeAdminForExtended();

    // Create a few users to populate the growth chart
    User::factory()->count(3)->create([
        'platform'  => 'standalone',
        'user_type' => 'voter',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.analytics'))
        ->assertOk()
        ->assertSee('New Users');
});
