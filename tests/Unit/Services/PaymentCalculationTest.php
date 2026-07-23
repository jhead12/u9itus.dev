<?php

use App\Enums\ApprovalStatus;
use App\Enums\CampaignStatus;
use App\Enums\PaymentStatus;
use App\Enums\ViewPaymentStatus;
use App\Enums\ViewSessionStatus;
use App\Models\Citizen;
use App\Models\CitizenCampaign;
use App\Models\CitizenTransaction;
use App\Models\PoliticalCampaign;
use App\Models\Politician;
use App\Models\User;
use App\Models\Voter;
use App\Services\CampaignBillingService;
use App\Services\CitizenBillingService;
use App\Services\CitizenViewService;
use App\Services\FraudPreventionService;
use App\Services\PoliticalViewService;
use App\Services\StripePaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

// Pin the payment-calculation wiring shared by both campaign systems:
// platform revenue split, voter payout qualification, and citizen budget
// exhaustion. Helpers are `pay*`-prefixed to avoid redeclaring globals that
// already exist in PoliticalViewServiceTest / CitizenViewTest.

uses(RefreshDatabase::class);

beforeEach(function () {
    if (class_exists(\Spatie\Permission\Models\Role::class)) {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'voter', 'guard_name' => 'web']);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'citizen', 'guard_name' => 'web']);
    }
    config(['u9itus.fraud.device_fingerprint_required' => false]);
});

// ── Helpers ───────────────────────────────────────────────────────────────────

function payViewService(): PoliticalViewService
{
    $stripe  = Mockery::mock(StripePaymentService::class);
    $billing = new CampaignBillingService($stripe);
    $fraud   = app(FraudPreventionService::class);

    return new PoliticalViewService($fraud, $billing);
}

function payCitizenViewService(): CitizenViewService
{
    $stripe  = Mockery::mock(StripePaymentService::class);
    $billing = new CitizenBillingService($stripe);
    $fraud   = app(FraudPreventionService::class);

    return new CitizenViewService($fraud, $billing);
}

function payReadyRequest(array $serverVars = []): Request
{
    return Request::create('/test', 'GET', [], [], [], array_merge(
        ['REMOTE_ADDR' => '10.0.0.1'],
        $serverVars,
    ));
}

function payActiveCampaign(array $overrides = []): PoliticalCampaign
{
    $user       = User::factory()->create(['user_type' => 'politician']);
    $politician = Politician::factory()->create(['user_id' => $user->id, 'is_active' => true]);

    return PoliticalCampaign::factory()->create(array_merge([
        'politician_id'           => $politician->id,
        'status'                  => CampaignStatus::Active->value,
        'approval_status'         => ApprovalStatus::Approved->value,
        'payment_status'          => PaymentStatus::Captured->value,
        'stripe_payment_intent_id' => 'pi_test_pay_calc',
        'media_duration'          => 60,
        'min_watch_time_percent'  => 80,
        'revenue_per_view'        => 0.60,
        'voter_payout_per_view'   => 0.25,
        'total_views_requested'   => 100,
        'views_completed'         => 0,
        'total_budget'            => 100.00,
        'amount_spent'            => 0.00,
    ], $overrides));
}

function payCleanVoter(array $extra = []): Voter
{
    return Voter::factory()->create(array_merge([
        'is_verified'       => true,
        'is_active'         => true,
        'flagged_for_fraud' => false,
        'pending_earnings'  => 0.00,
        'total_views'       => 0,
    ], $extra));
}

/**
 * Active + approved citizen campaign with a high reserved budget so the
 * per-view charge never exhausts during the platform-revenue tests.
 */
function payCitizenCampaign(array $overrides = []): CitizenCampaign
{
    $citizen = Citizen::factory()->create();

    return CitizenCampaign::factory()->active()->create(array_merge([
        'citizen_id'             => $citizen->id,
        'total_budget'           => 100.00,
        'amount_spent'           => 0.00,
        'total_views_requested'  => 100,
        'views_completed'        => 0,
        'media_duration'         => 60,
        'min_watch_time_percent' => 80,
        'revenue_per_view'       => 0.75,
        'voter_payout_per_view'  => 0.50,
        'allow_repeat_views'     => true,
        'max_views_per_voter'    => 100,
        'repeat_view_cooldown_hours' => 0,
        'media_url'              => 'https://www.youtube.com/watch?v=abc',
    ], $overrides));
}

// ── Politician platform revenue ──────────────────────────────────────────────

test('political completeView records platform revenue = revenue_per_view minus voter payout', function () {
    $svc      = payViewService();
    $campaign = payActiveCampaign(); // 0.60 revenue, 0.25 payout → 0.35 platform
    $voter    = payCleanVoter();
    $session  = $svc->assignView($campaign, $voter, payReadyRequest());

    // 54 / 60 = 90% ≥ 80% → qualifies
    $completed = $svc->completeView($session, 54);

    expect($completed->status)->toBe(ViewSessionStatus::Completed)
        ->and($completed->payment_status)->toBe(ViewPaymentStatus::Pending)
        ->and((float) $completed->voter_payout_amount)->toBe(0.25)
        ->and((float) $completed->platform_revenue)->toBe(0.35);
});

test('political completeView records zero platform revenue and rejects when below threshold', function () {
    $svc      = payViewService();
    $campaign = payActiveCampaign();
    $voter    = payCleanVoter();
    $session  = $svc->assignView($campaign, $voter, payReadyRequest());

    // 30 / 60 = 50% < 80% → does not qualify
    $completed = $svc->completeView($session, 30);

    expect($completed->payment_status)->toBe(ViewPaymentStatus::Rejected)
        ->and((float) $completed->voter_payout_amount)->toBe(0.00)
        ->and((float) $completed->platform_revenue)->toBe(0.00);
});

// ── Citizen platform revenue ──────────────────────────────────────────────────

test('citizen completeView records platform revenue = revenue_per_view minus voter payout', function () {
    $svc      = payCitizenViewService();
    $campaign = payCitizenCampaign(); // 0.75 revenue, 0.50 payout → 0.25 platform
    $voter    = payCleanVoter();
    $session  = $svc->assignView($campaign, $voter, payReadyRequest());

    // Full watch → 100% ≥ 80% → qualifies
    $completed = $svc->completeView($session, 60, 60);

    expect($completed->status)->toBe(ViewSessionStatus::Completed)
        ->and($completed->payment_status)->toBe(ViewPaymentStatus::Pending)
        ->and((float) $completed->voter_payout_amount)->toBe(0.50)
        ->and((float) $completed->platform_revenue)->toBe(0.25);

    $voter->refresh();
    expect((float) $voter->pending_earnings)->toBe(0.50);
});

test('citizen completeView records zero platform revenue and rejects when below threshold', function () {
    $svc      = payCitizenViewService();
    $campaign = payCitizenCampaign();
    $voter    = payCleanVoter();
    $session  = $svc->assignView($campaign, $voter, payReadyRequest());

    // 30 / 60 = 50% < 80% → does not qualify
    $completed = $svc->completeView($session, 30, 60);

    expect($completed->payment_status)->toBe(ViewPaymentStatus::Rejected)
        ->and((float) $completed->voter_payout_amount)->toBe(0.00)
        ->and((float) $completed->platform_revenue)->toBe(0.00);

    $voter->refresh();
    expect((float) $voter->pending_earnings)->toBe(0.00);
});

// ── Citizen budget exhaustion (CitizenBillingService::debitForView) ───────────

test('debitForView returns null and records nothing when the reserved budget is exhausted', function () {
    $stripe  = Mockery::mock(StripePaymentService::class);
    $billing = new CitizenBillingService($stripe);

    $campaign = payCitizenCampaign([
        'total_budget' => 3.00,
        'amount_spent' => 3.00, // fully spent → remaining 0
        'views_completed' => 4,
    ]);

    $tx = $billing->debitForView($campaign, 0.75);

    expect($tx)->toBeNull();

    $campaign->refresh();
    expect((float) $campaign->amount_spent)->toBe(3.00)
        ->and($campaign->views_completed)->toBe(4);

    expect(CitizenTransaction::where('citizen_campaign_id', $campaign->id)
        ->where('transaction_type', 'view_charge')
        ->count())->toBe(0);
});

test('debitForView succeeds at the exact remaining boundary, then exhausts on the next view', function () {
    $stripe  = Mockery::mock(StripePaymentService::class);
    $billing = new CitizenBillingService($stripe);

    // 2.25 spent of 3.00 → exactly 0.75 remaining.
    $campaign = payCitizenCampaign([
        'total_budget'    => 3.00,
        'amount_spent'    => 2.25,
        'views_completed' => 3,
    ]);

    // Exactly-equal remaining succeeds.
    $tx = $billing->debitForView($campaign, 0.75);
    expect($tx)->toBeInstanceOf(CitizenTransaction::class)
        ->and((float) $tx->amount)->toBe(0.75);

    $campaign->refresh();
    expect((float) $campaign->amount_spent)->toBe(3.00)
        ->and($campaign->views_completed)->toBe(4);

    expect(CitizenTransaction::where('citizen_campaign_id', $campaign->id)
        ->where('transaction_type', 'view_charge')->count())->toBe(1);

    // Budget is now exhausted — a further view is rejected.
    $next = $billing->debitForView($campaign, 0.75);
    expect($next)->toBeNull();

    $campaign->refresh();
    expect((float) $campaign->amount_spent)->toBe(3.00)
        ->and($campaign->views_completed)->toBe(4);

    expect(CitizenTransaction::where('citizen_campaign_id', $campaign->id)
        ->where('transaction_type', 'view_charge')->count())->toBe(1);
});