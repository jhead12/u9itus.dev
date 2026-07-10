<?php

use App\Enums\ViewSessionStatus;
use App\Models\CampaignTransaction;
use App\Models\EarlyBankWebhookLog;
use App\Models\PoliticalCampaign;
use App\Models\User;
use App\Models\ViewSession;
use App\Models\Voter;
use App\Services\EarlyBankWebhookService;
use App\Services\PlatformSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    if (class_exists(\Spatie\Permission\Models\Role::class)) {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'voter', 'guard_name' => 'web']);
    }
});

function makeAdminForEbLog(): User
{
    $admin = User::factory()->create(['platform' => 'standalone', 'user_type' => 'admin']);
    if (method_exists($admin, 'assignRole')) {
        $admin->assignRole('admin');
    }
    skipOnboarding($admin, 'admin');
    return $admin;
}

// ── Dispatch logging ──────────────────────────────────────────────────────

test('dispatch logs a delivered record when EB webhook returns 200', function () {
    config([
        'services.earlybank.enabled'        => true,
        'services.earlybank.webhook_url'    => 'https://fake-eb.test/webhook',
        'services.earlybank.webhook_secret' => 'test-secret',
    ]);

    Http::fake(['https://fake-eb.test/webhook' => Http::response('ok', 200)]);

    $voterUuid   = (string) Str::uuid();
    $memberId    = (string) Str::uuid();

    app(EarlyBankWebhookService::class)->dispatch('voter.registered', [
        'voter_uuid'          => $voterUuid,
        'earlybank_member_id' => $memberId,
        'registered_at'       => now()->toIso8601String(),
    ]);

    $log = EarlyBankWebhookLog::first();

    expect($log)->not->toBeNull();
    expect($log->event_type)->toBe('voter.registered');
    expect($log->voter_uuid)->toBe($voterUuid);
    expect($log->earlybank_member_id)->toBe($memberId);
    expect($log->delivered)->toBeTrue();
    expect($log->http_status)->toBe(200);
    expect($log->error_message)->toBeNull();
    expect($log->delivered_at)->not->toBeNull();
});

test('dispatch logs a failed record when EB webhook returns non-2xx', function () {
    config([
        'services.earlybank.enabled'        => true,
        'services.earlybank.webhook_url'    => 'https://fake-eb.test/webhook',
        'services.earlybank.webhook_secret' => 'test-secret',
    ]);

    Http::fake(['https://fake-eb.test/webhook' => Http::response('error', 503)]);

    app(EarlyBankWebhookService::class)->dispatch('voter.earned', [
        'voter_uuid'          => (string) Str::uuid(),
        'earlybank_member_id' => (string) Str::uuid(),
        'session_uuid'        => (string) Str::uuid(),
        'payout_amount'       => 0.50,
        'completed_at'        => now()->toIso8601String(),
    ]);

    $log = EarlyBankWebhookLog::first();
    expect($log->delivered)->toBeFalse();
    expect($log->http_status)->toBe(503);
    expect($log->error_message)->not->toBeNull();
    expect($log->delivered_at)->toBeNull();
    expect((float) $log->payout_amount)->toBe(0.5);
});

test('dispatch logs missing-config case without throwing', function () {
    config([
        'services.earlybank.enabled'        => true,
        'services.earlybank.webhook_url'    => '',
        'services.earlybank.webhook_secret' => '',
    ]);

    app(EarlyBankWebhookService::class)->dispatch('voter.registered', [
        'voter_uuid'          => (string) Str::uuid(),
        'earlybank_member_id' => (string) Str::uuid(),
        'registered_at'       => now()->toIso8601String(),
    ]);

    $log = EarlyBankWebhookLog::first();
    expect($log)->not->toBeNull();
    expect($log->delivered)->toBeFalse();
    expect($log->error_message)->toContain('not configured');
});

test('voter.referred log captures payout_amount as null for registration events', function () {
    config([
        'services.earlybank.enabled'        => true,
        'services.earlybank.webhook_url'    => 'https://fake-eb.test/webhook',
        'services.earlybank.webhook_secret' => 'test-secret',
    ]);

    Http::fake(['https://fake-eb.test/webhook' => Http::response('ok', 200)]);

    app(EarlyBankWebhookService::class)->dispatch('voter.referred', [
        'voter_uuid'          => (string) Str::uuid(),
        'earlybank_member_id' => (string) Str::uuid(),
        'first_session_uuid'  => (string) Str::uuid(),
    ]);

    $log = EarlyBankWebhookLog::first();
    expect($log->event_type)->toBe('voter.referred');
    expect($log->payout_amount)->toBeNull();
    expect($log->view_session_uuid)->not->toBeNull(); // extracted from first_session_uuid
});

// ── EarlyBankWebhookLog model scopes ─────────────────────────────────────

test('EarlyBankWebhookLog scopeForVoter filters by voter_uuid', function () {
    $uuid1 = (string) Str::uuid();
    $uuid2 = (string) Str::uuid();

    EarlyBankWebhookLog::create(['event_type' => 'voter.registered', 'voter_uuid' => $uuid1, 'earlybank_member_id' => Str::uuid(), 'payload' => [], 'delivered' => true]);
    EarlyBankWebhookLog::create(['event_type' => 'voter.registered', 'voter_uuid' => $uuid2, 'earlybank_member_id' => Str::uuid(), 'payload' => [], 'delivered' => true]);

    expect(EarlyBankWebhookLog::forVoter($uuid1)->count())->toBe(1);
    expect(EarlyBankWebhookLog::forVoter($uuid2)->count())->toBe(1);
});

test('EarlyBankWebhookLog eventLabel returns human-readable text', function () {
    $log = new EarlyBankWebhookLog(['event_type' => 'voter.referred']);
    expect($log->eventLabel())->toBe('$10 referral bonus triggered');

    $log2 = new EarlyBankWebhookLog(['event_type' => 'voter.earned']);
    expect($log2->eventLabel())->toBe('10% view commission');

    $log3 = new EarlyBankWebhookLog(['event_type' => 'voter.registered']);
    expect($log3->eventLabel())->toBe('Registration attributed');
});

// ── Admin user details page ───────────────────────────────────────────────

test('admin user detail page shows EB webhook log for voter with events', function () {
    $admin = makeAdminForEbLog();

    $voterUser = User::factory()->create(['platform' => 'standalone', 'user_type' => 'voter']);
    $voter = Voter::factory()->create([
        'user_id'                  => $voterUser->id,
        'earlybank_own_member_uuid' => Str::uuid(),
        'earlybank_member_id'      => Str::uuid(),
    ]);

    EarlyBankWebhookLog::create([
        'event_type'          => 'voter.registered',
        'voter_uuid'          => $voter->uuid,
        'earlybank_member_id' => $voter->earlybank_member_id,
        'payload'             => ['voter_uuid' => $voter->uuid],
        'delivered'           => true,
        'http_status'         => 200,
        'delivered_at'        => now(),
    ]);

    EarlyBankWebhookLog::create([
        'event_type'          => 'voter.referred',
        'voter_uuid'          => $voter->uuid,
        'earlybank_member_id' => $voter->earlybank_member_id,
        'view_session_uuid'   => Str::uuid(),
        'payload'             => ['voter_uuid' => $voter->uuid],
        'delivered'           => true,
        'http_status'         => 200,
        'delivered_at'        => now(),
    ]);

    $this->actingAs($admin)
        ->get(route('admin.users.show', $voterUser->id))
        ->assertOk()
        ->assertSee('Early-bank Event Log')
        ->assertSee('voter.registered')
        ->assertSee('voter.referred')
        ->assertSee('Registration attributed')
        ->assertSee('$10 referral bonus triggered');
});

test('admin user detail page hides EB log section when no events exist', function () {
    $admin = makeAdminForEbLog();

    $voterUser = User::factory()->create(['platform' => 'standalone', 'user_type' => 'voter']);
    Voter::factory()->create(['user_id' => $voterUser->id]);

    $this->actingAs($admin)
        ->get(route('admin.users.show', $voterUser->id))
        ->assertOk()
        ->assertDontSee('Early-bank Event Log');
});

// ── Stripe Connect gate on notifyViewSessionCompleted ─────────────────────

function makeEbAttributedVoter(array $overrides = []): Voter
{
    $voterUser = User::factory()->create(['platform' => 'standalone', 'user_type' => 'voter']);
    return Voter::factory()->create(array_merge([
        'user_id'             => $voterUser->id,
        'earlybank_member_id' => (string) Str::uuid(),
        'earlybank_linked_at' => now(),
    ], $overrides));
}

function makeTestCampaignForEbGate(): PoliticalCampaign
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

test('notifyViewSessionCompleted fires both events in first_verified_view mode', function () {
    config([
        'services.earlybank.enabled'        => true,
        'services.earlybank.webhook_url'    => 'https://fake-eb.test/webhook',
        'services.earlybank.webhook_secret' => 'test-secret',
    ]);
    Http::fake(['https://fake-eb.test/webhook' => Http::response('ok', 200)]);

    PlatformSettingsService::set('earlybank_referral_bonus_trigger', 'first_verified_view', ['category' => 'earlybank']);

    $voter    = makeEbAttributedVoter(['stripe_account_status' => 'active']);
    $campaign = makeTestCampaignForEbGate();

    $session = ViewSession::factory()->completed()->create([
        'political_campaign_id' => $campaign->id,
        'voter_id'              => $voter->id,
        'status'                => ViewSessionStatus::Completed->value,
        'voter_payout_amount'   => 0.50,
    ]);
    $session->setRelation('voter', $voter->fresh());

    app(EarlyBankWebhookService::class)->notifyViewSessionCompleted($session);

    $logs = EarlyBankWebhookLog::orderBy('id')->get();
    expect($logs)->toHaveCount(2);
    expect($logs[0]->event_type)->toBe('voter.referred');   // $10 — first session
    expect($logs[1]->event_type)->toBe('voter.earned');     // 10% commission
    expect($logs->every(fn ($l) => $l->delivered))->toBeTrue();
});

test('notifyViewSessionCompleted fires no events when voter stripe account is not active', function () {
    config([
        'services.earlybank.enabled'        => true,
        'services.earlybank.webhook_url'    => 'https://fake-eb.test/webhook',
        'services.earlybank.webhook_secret' => 'test-secret',
    ]);
    Http::fake(['https://fake-eb.test/webhook' => Http::response('ok', 200)]);

    // Voter has EB attribution but has NOT completed Stripe Connect
    $voter    = makeEbAttributedVoter(['stripe_account_status' => 'pending']);
    $campaign = makeTestCampaignForEbGate();

    $session = ViewSession::factory()->completed()->create([
        'political_campaign_id' => $campaign->id,
        'voter_id'              => $voter->id,
        'status'                => ViewSessionStatus::Completed->value,
        'voter_payout_amount'   => 0.50,
    ]);
    $session->setRelation('voter', $voter->fresh());

    app(EarlyBankWebhookService::class)->notifyViewSessionCompleted($session);

    expect(EarlyBankWebhookLog::count())->toBe(0);
    Http::assertNothingSent();
});

test('notifyViewSessionCompleted fires no events when stripe account status is pending', function () {
    config([
        'services.earlybank.enabled'        => true,
        'services.earlybank.webhook_url'    => 'https://fake-eb.test/webhook',
        'services.earlybank.webhook_secret' => 'test-secret',
    ]);
    Http::fake(['https://fake-eb.test/webhook' => Http::response('ok', 200)]);

    $voter    = makeEbAttributedVoter(['stripe_account_status' => 'pending']);
    $campaign = makeTestCampaignForEbGate();

    $session = ViewSession::factory()->completed()->create([
        'political_campaign_id' => $campaign->id,
        'voter_id'              => $voter->id,
        'status'                => ViewSessionStatus::Completed->value,
    ]);
    $session->setRelation('voter', $voter->fresh());

    app(EarlyBankWebhookService::class)->notifyViewSessionCompleted($session);

    expect(EarlyBankWebhookLog::count())->toBe(0);
});

// ── Trigger mode: stripe_verification (recommended default) ──────────────

test('dispatchReferralBonusOnVerification fires voter.referred when voter has EB attribution', function () {
    config([
        'services.earlybank.enabled'        => true,
        'services.earlybank.webhook_url'    => 'https://fake-eb.test/webhook',
        'services.earlybank.webhook_secret' => 'test-secret',
    ]);
    Http::fake(['https://fake-eb.test/webhook' => Http::response('ok', 200)]);

    $voter = makeEbAttributedVoter(['stripe_account_status' => 'active']);

    app(EarlyBankWebhookService::class)->dispatchReferralBonusOnVerification($voter);

    $log = EarlyBankWebhookLog::first();
    expect($log)->not->toBeNull();
    expect($log->event_type)->toBe('voter.referred');
    expect($log->delivered)->toBeTrue();
});

test('dispatchReferralBonusOnVerification does not double-fire when voter.referred already delivered', function () {
    config([
        'services.earlybank.enabled'        => true,
        'services.earlybank.webhook_url'    => 'https://fake-eb.test/webhook',
        'services.earlybank.webhook_secret' => 'test-secret',
    ]);
    Http::fake(['https://fake-eb.test/webhook' => Http::response('ok', 200)]);

    $voter = makeEbAttributedVoter(['stripe_account_status' => 'active']);

    // Pre-existing delivered log
    EarlyBankWebhookLog::create([
        'event_type'          => 'voter.referred',
        'voter_uuid'          => $voter->uuid,
        'earlybank_member_id' => $voter->earlybank_member_id,
        'payload'             => [],
        'delivered'           => true,
        'http_status'         => 200,
    ]);

    app(EarlyBankWebhookService::class)->dispatchReferralBonusOnVerification($voter);

    // Should still only be 1 log row (the pre-existing one)
    expect(EarlyBankWebhookLog::where('event_type', 'voter.referred')->count())->toBe(1);
});

test('stripe_verification mode: notifyViewSessionCompleted does NOT fire voter.referred', function () {
    config([
        'services.earlybank.enabled'        => true,
        'services.earlybank.webhook_url'    => 'https://fake-eb.test/webhook',
        'services.earlybank.webhook_secret' => 'test-secret',
    ]);
    Http::fake(['https://fake-eb.test/webhook' => Http::response('ok', 200)]);

    // Default setting = stripe_verification
    PlatformSettingsService::set('earlybank_referral_bonus_trigger', 'stripe_verification', ['category' => 'earlybank']);

    $voter    = makeEbAttributedVoter(['stripe_account_status' => 'active']);
    $campaign = makeTestCampaignForEbGate();

    $session = ViewSession::factory()->completed()->create([
        'political_campaign_id' => $campaign->id,
        'voter_id'              => $voter->id,
        'status'                => ViewSessionStatus::Completed->value,
        'voter_payout_amount'   => 0.50,
    ]);
    $session->setRelation('voter', $voter->fresh());

    app(EarlyBankWebhookService::class)->notifyViewSessionCompleted($session);

    // Only voter.earned should be logged — voter.referred is handled by Stripe webhook
    $types = EarlyBankWebhookLog::pluck('event_type')->toArray();
    expect($types)->not->toContain('voter.referred');
    expect($types)->toContain('voter.earned');
});

test('first_verified_view mode: notifyViewSessionCompleted fires voter.referred on first session', function () {
    config([
        'services.earlybank.enabled'        => true,
        'services.earlybank.webhook_url'    => 'https://fake-eb.test/webhook',
        'services.earlybank.webhook_secret' => 'test-secret',
    ]);
    Http::fake(['https://fake-eb.test/webhook' => Http::response('ok', 200)]);

    PlatformSettingsService::set('earlybank_referral_bonus_trigger', 'first_verified_view', ['category' => 'earlybank']);

    $voter    = makeEbAttributedVoter(['stripe_account_status' => 'active']);
    $campaign = makeTestCampaignForEbGate();

    $session = ViewSession::factory()->completed()->create([
        'political_campaign_id' => $campaign->id,
        'voter_id'              => $voter->id,
        'status'                => ViewSessionStatus::Completed->value,
        'voter_payout_amount'   => 0.50,
    ]);
    $session->setRelation('voter', $voter->fresh());

    app(EarlyBankWebhookService::class)->notifyViewSessionCompleted($session);

    $types = EarlyBankWebhookLog::pluck('event_type')->toArray();
    expect($types)->toContain('voter.referred');
    expect($types)->toContain('voter.earned');
});

// ── voter.referred fires only once (existing test, now label clarified) ──

test('voter.referred fires only once across multiple sessions when stripe is active (first_verified_view mode)', function () {
    config([
        'services.earlybank.enabled'        => true,
        'services.earlybank.webhook_url'    => 'https://fake-eb.test/webhook',
        'services.earlybank.webhook_secret' => 'test-secret',
    ]);
    Http::fake(['https://fake-eb.test/webhook' => Http::response('ok', 200)]);

    PlatformSettingsService::set('earlybank_referral_bonus_trigger', 'first_verified_view', ['category' => 'earlybank']);

    $voter    = makeEbAttributedVoter(['stripe_account_status' => 'active']);
    $campaign = makeTestCampaignForEbGate();

    // First session
    $session1 = ViewSession::factory()->completed()->create([
        'political_campaign_id' => $campaign->id,
        'voter_id'              => $voter->id,
        'status'                => ViewSessionStatus::Completed->value,
        'voter_payout_amount'   => 0.50,
    ]);
    $session1->setRelation('voter', $voter->fresh());
    app(EarlyBankWebhookService::class)->notifyViewSessionCompleted($session1);

    // Second session
    $session2 = ViewSession::factory()->completed()->create([
        'political_campaign_id' => $campaign->id,
        'voter_id'              => $voter->id,
        'status'                => ViewSessionStatus::Completed->value,
        'voter_payout_amount'   => 0.50,
    ]);
    $session2->setRelation('voter', $voter->fresh());
    app(EarlyBankWebhookService::class)->notifyViewSessionCompleted($session2);

    $referred = EarlyBankWebhookLog::where('event_type', 'voter.referred')->count();
    $earned   = EarlyBankWebhookLog::where('event_type', 'voter.earned')->count();

    expect($referred)->toBe(1);  // $10 bonus fired exactly once
    expect($earned)->toBe(2);    // 10% commission on each session
});
