<?php

use App\Enums\ApprovalStatus;
use App\Enums\CampaignStatus;
use App\Enums\PaymentStatus;
use App\Enums\ViewPaymentStatus;
use App\Enums\ViewSessionStatus;
use App\Models\PoliticalCampaign;
use App\Models\ViewSession;
use App\Models\Voter;
use App\Services\CampaignBillingService;
use App\Services\FraudPreventionService;
use App\Services\PoliticalViewService;
use App\Services\StripePaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

uses(RefreshDatabase::class);

// ── Helpers ───────────────────────────────────────────────────────────────────

function viewService(): PoliticalViewService
{
    // Wire real services — DB is refreshed between tests.
    $stripe  = Mockery::mock(StripePaymentService::class);
    $billing = new CampaignBillingService($stripe);
    $fraud   = app(FraudPreventionService::class);
    return new PoliticalViewService($fraud, $billing);
}

function readyRequest(array $serverVars = []): Request
{
    return Request::create('/test', 'GET', [], [], [], array_merge(
        ['REMOTE_ADDR' => '10.0.0.1'],
        $serverVars,
    ));
}

function activeCampaignForView(array $overrides = []): PoliticalCampaign
{
    return PoliticalCampaign::factory()->create(array_merge([
        'status'                 => CampaignStatus::Active->value,
        'approval_status'        => ApprovalStatus::Approved->value,
        'payment_status'         => PaymentStatus::Pending->value,
        'media_duration'         => 60,
        'min_watch_time_percent' => 80,
        'revenue_per_view'       => 0.60,
        'voter_payout_per_view'  => 0.25,
        'total_views_requested'  => 100,
        'views_completed'        => 0,
        'total_budget'           => 100.00,
        'amount_spent'           => 0.00,
    ], $overrides));
}

function cleanVoter(array $extra = []): Voter
{
    return Voter::factory()->create(array_merge([
        'is_verified'       => true,
        'is_active'         => true,
        'flagged_for_fraud' => false,
        'pending_earnings'  => 0.00,
        'total_views'       => 0,
    ], $extra));
}

// ── assignView() ──────────────────────────────────────────────────────────────

test('assignView creates a view session for a clean voter', function () {
    config(['u9itus.fraud.device_fingerprint_required' => false]);

    $svc      = viewService();
    $campaign = activeCampaignForView();
    $voter    = cleanVoter();
    $request  = readyRequest();

    $session = $svc->assignView($campaign, $voter, $request);

    expect($session)->toBeInstanceOf(ViewSession::class)
        ->and($session->voter_id)->toBe($voter->id)
        ->and($session->political_campaign_id)->toBe($campaign->id)
        ->and($session->status)->toBe(ViewSessionStatus::Assigned);

    $this->assertDatabaseHas('view_sessions', [
        'voter_id'              => $voter->id,
        'political_campaign_id' => $campaign->id,
        'status'                => ViewSessionStatus::Assigned->value,
    ]);
});

test('assignView throws when fraud score is too high', function () {
    config([
        'u9itus.fraud.device_fingerprint_required' => false,
        'u9itus.fraud.max_views_per_voter_per_day' => 0,  // immediate threshold breach
    ]);

    $svc      = viewService();
    $campaign = activeCampaignForView();
    $voter    = cleanVoter(['flagged_for_fraud' => true]);  // score ≥ 60 → blocked
    $request  = readyRequest();

    $this->expectException(\RuntimeException::class);
    $svc->assignView($campaign, $voter, $request);
});

// ── startView() ───────────────────────────────────────────────────────────────

test('startView marks the session as InProgress', function () {
    config(['u9itus.fraud.device_fingerprint_required' => false]);

    $svc      = viewService();
    $campaign = activeCampaignForView();
    $voter    = cleanVoter();
    $session  = $svc->assignView($campaign, $voter, readyRequest());

    $started = $svc->startView($session);

    expect($started->status)->toBe(ViewSessionStatus::InProgress)
        ->and($started->started_at)->not->toBeNull();
});

// ── trackProgress() ───────────────────────────────────────────────────────────

test('trackProgress updates watch_time_seconds', function () {
    config(['u9itus.fraud.device_fingerprint_required' => false]);

    $svc      = viewService();
    $campaign = activeCampaignForView();
    $voter    = cleanVoter();
    $session  = $svc->assignView($campaign, $voter, readyRequest());

    $updated = $svc->trackProgress($session, 45);

    expect($updated->watch_time_seconds)->toBe(45);
});

// ── completeView() — qualifying watch ─────────────────────────────────────────

test('completeView credits voter when watch threshold is met', function () {
    config([
        'u9itus.fraud.device_fingerprint_required' => false,
        'u9itus.referral_commission_percent'        => 10,
    ]);

    $svc      = viewService();
    $campaign = activeCampaignForView([
        'media_duration'         => 60,
        'min_watch_time_percent' => 80,
        'voter_payout_per_view'  => 0.25,
    ]);
    $voter   = cleanVoter(['pending_earnings' => 0.00]);
    $session = $svc->assignView($campaign, $voter, readyRequest());

    // 54 out of 60 seconds = 90% → above the 80% threshold
    $completed = $svc->completeView($session, 54);

    expect($completed->status)->toBe(ViewSessionStatus::Completed)
        ->and($completed->payment_status)->toBe(ViewPaymentStatus::Approved)
        ->and((float) $completed->voter_payout_amount)->toBe(0.25);

    $voter->refresh();
    expect((float) $voter->pending_earnings)->toBe(0.25)
        ->and($voter->total_views)->toBe(1);
});

// ── completeView() — below threshold ─────────────────────────────────────────

test('completeView rejects payment when watch time is below threshold', function () {
    config(['u9itus.fraud.device_fingerprint_required' => false]);

    $svc      = viewService();
    $campaign = activeCampaignForView([
        'media_duration'         => 60,
        'min_watch_time_percent' => 80,
        'voter_payout_per_view'  => 0.25,
    ]);
    $voter   = cleanVoter(['pending_earnings' => 0.00]);
    $session = $svc->assignView($campaign, $voter, readyRequest());

    // 30 out of 60 seconds = 50% → below 80%
    $completed = $svc->completeView($session, 30);

    expect($completed->status)->toBe(ViewSessionStatus::Completed)
        ->and($completed->payment_status)->toBe(ViewPaymentStatus::Rejected)
        ->and((float) $completed->voter_payout_amount)->toBe(0.00);

    $voter->refresh();
    expect((float) $voter->pending_earnings)->toBe(0.00);
});

// ── completeView() — idempotency ──────────────────────────────────────────────

test('completeView is idempotent — calling twice does not double-credit voter', function () {
    config([
        'u9itus.fraud.device_fingerprint_required' => false,
        'u9itus.referral_commission_percent'        => 10,
    ]);

    $svc      = viewService();
    $campaign = activeCampaignForView(['media_duration' => 60, 'min_watch_time_percent' => 80]);
    $voter    = cleanVoter(['pending_earnings' => 0.00]);
    $session  = $svc->assignView($campaign, $voter, readyRequest());

    $svc->completeView($session, 60);
    $svc->completeView($session->fresh(), 60);  // second call — should be no-op

    $voter->refresh();
    expect((float) $voter->pending_earnings)->toBe(0.25);  // credited exactly once
});

// ── availableCampaigns() ──────────────────────────────────────────────────────

test('availableCampaigns returns active approved campaigns', function () {
    config(['u9itus.fraud.device_fingerprint_required' => false]);

    $voter = cleanVoter(['state' => null]);
    activeCampaignForView(['target_states' => null]);
    activeCampaignForView(['target_states' => null]);

    $results = viewService()->availableCampaigns($voter);

    expect($results)->toHaveCount(2);
});

test('availableCampaigns excludes campaigns already completed by voter', function () {
    config(['u9itus.fraud.device_fingerprint_required' => false]);

    $voter    = cleanVoter(['state' => null]);
    $campaign = activeCampaignForView(['target_states' => null]);

    ViewSession::factory()->create([
        'voter_id'              => $voter->id,
        'political_campaign_id' => $campaign->id,
        'status'                => ViewSessionStatus::Completed->value,
    ]);

    $results = viewService()->availableCampaigns($voter);

    expect($results)->toHaveCount(0);
});

test('availableCampaigns respects state targeting', function () {
    config(['u9itus.fraud.device_fingerprint_required' => false]);

    $voter = cleanVoter(['state' => 'TX']);
    activeCampaignForView(['target_states' => ['TX']]);   // matches TX voter
    activeCampaignForView(['target_states' => ['CA']]);   // no match
    activeCampaignForView(['target_states' => null]);     // open to all

    $results = viewService()->availableCampaigns($voter);

    expect($results)->toHaveCount(2);
});

// ── voterEarningsSummary() ────────────────────────────────────────────────────

test('voterEarningsSummary returns correct totals', function () {
    $voter = cleanVoter([
        'total_earned'     => 5.00,
        'pending_earnings' => 1.25,
        'wallet_balance'   => 3.75,
        'total_views'      => 20,
    ]);

    $summary = viewService()->voterEarningsSummary($voter);

    expect((float) $summary['total_earned'])->toBe(5.00)
        ->and((float) $summary['pending_earnings'])->toBe(1.25)
        ->and((int) $summary['total_views'])->toBe(20);
});
