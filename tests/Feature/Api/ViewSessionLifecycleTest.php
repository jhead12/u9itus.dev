<?php

use App\Enums\CampaignStatus;
use App\Enums\ApprovalStatus;
use App\Enums\PaymentStatus;
use App\Enums\ViewSessionStatus;
use App\Enums\ViewPaymentStatus;
use App\Models\PoliticalCampaign;
use App\Models\Politician;
use App\Models\User;
use App\Models\ReferralEarning;
use App\Models\ViewSession;
use App\Models\Voter;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Create an active (approved) campaign ready to receive views.
 */
function activeCampaign(array $overrides = []): PoliticalCampaign
{
    $user = User::factory()->create(['user_type' => 'politician']);
    $politician = Politician::factory()->create([
        'user_id' => $user->id,
        'is_active' => true,
    ]);

    return PoliticalCampaign::factory()->create(array_merge([
        'politician_id' => $politician->id,
        'status'          => CampaignStatus::Active->value,
        'approval_status' => ApprovalStatus::Approved->value,
        'payment_status'  => PaymentStatus::Captured->value,
        'stripe_payment_intent_id' => 'pi_test_campaign_lifecycle',
        'media_duration'  => 60,
        'min_watch_time_percent' => 80,
        'total_views_requested'  => 100,
        'views_completed'        => 0,
    ], $overrides));
}

/**
 * Create a verified, active voter.
 */
function activeVoter(array $overrides = []): Voter
{
    return Voter::factory()->create(array_merge([
        'is_verified'       => true,
        'is_active'         => true,
        'flagged_for_fraud' => false,
    ], $overrides));
}

// ── Campaign availability ─────────────────────────────────────────────────────

test('voter can get available campaigns', function () {
    $voter = activeVoter(['state' => null]);  // no state → skips JSON targeting filter
    activeCampaign(['target_states' => null]);
    activeCampaign(['target_states' => null]);

    $response = $this->getJson("/api/v1/voters/{$voter->uuid}/campaigns");

    $response->assertOk()
        ->assertJsonStructure(['campaigns'])
        ->assertJsonCount(2, 'campaigns');
});

test('completed campaigns are excluded from available list', function () {
    $voter    = activeVoter();
    $campaign = activeCampaign();

    // Voter already completed this campaign
    ViewSession::factory()->create([
        'voter_id'             => $voter->id,
        'political_campaign_id' => $campaign->id,
        'status'               => ViewSessionStatus::Completed->value,
    ]);

    $response = $this->getJson("/api/v1/voters/{$voter->uuid}/campaigns");
    $response->assertOk()->assertJsonCount(0, 'campaigns');
});

// ── Start view ────────────────────────────────────────────────────────────────

test('voter can start a view session', function () {
    $voter    = activeVoter();
    $campaign = activeCampaign();

    $response = $this->postJson(
        "/api/v1/voters/{$voter->uuid}/campaigns/{$campaign->uuid}/watch"
    );

    $response->assertCreated()
        ->assertJsonStructure([
            'message',
            'session' => ['uuid', 'status', 'payment_status', 'expires_at'],
            'media_url',
            'must_watch',
        ])
        ->assertJsonPath('session.status', ViewSessionStatus::InProgress->value);

    $this->assertDatabaseHas('view_sessions', [
        'voter_id'             => $voter->id,
        'political_campaign_id' => $campaign->id,
        'status'               => ViewSessionStatus::InProgress->value,
    ]);
});

test('fraudulent voter cannot start view session', function () {
    $voter    = activeVoter(['flagged_for_fraud' => true]);
    $campaign = activeCampaign();

    $response = $this->postJson(
        "/api/v1/voters/{$voter->uuid}/campaigns/{$campaign->uuid}/watch"
    );

    $response->assertStatus(429);
});

test('campaign that reached view goal returns 410', function () {
    $voter    = activeVoter();
    $campaign = activeCampaign([
        'total_views_requested' => 10,
        'views_completed'       => 10,
    ]);

    $response = $this->postJson(
        "/api/v1/voters/{$voter->uuid}/campaigns/{$campaign->uuid}/watch"
    );

    $response->assertStatus(410);
});

// ── Progress heartbeat ────────────────────────────────────────────────────────

test('progress heartbeat updates watch time', function () {
    $voter    = activeVoter();
    $campaign = activeCampaign();
    $session  = ViewSession::factory()->create([
        'voter_id'             => $voter->id,
        'political_campaign_id' => $campaign->id,
        'status'               => ViewSessionStatus::InProgress->value,
        'watch_time_seconds'   => 0,
    ]);

    $response = $this->postJson("/api/v1/sessions/{$session->uuid}/progress", [
        'seconds_watched' => 30,
    ]);

    $response->assertOk()->assertJsonPath('status', 'ok');

    $this->assertDatabaseHas('view_sessions', [
        'id'                 => $session->id,
        'watch_time_seconds' => 30,
    ]);
});

// ── Complete view ─────────────────────────────────────────────────────────────

test('completing a qualifying view credits voter pending earnings', function () {
    $voter    = activeVoter();
    $campaign = activeCampaign([
        'media_duration'         => 60,
        'min_watch_time_percent' => 80,
        'voter_payout_per_view'  => 0.25,
        'revenue_per_view'       => 0.60,
    ]);
    $session = ViewSession::factory()->create([
        'voter_id'             => $voter->id,
        'political_campaign_id' => $campaign->id,
        'status'               => ViewSessionStatus::InProgress->value,
    ]);

    // 50/60 sec = 83.3% ≥ 80% → qualifies
    $response = $this->postJson("/api/v1/sessions/{$session->uuid}/complete", [
        'total_seconds_watched' => 50,
    ]);

    $response->assertOk()
        ->assertJsonStructure(['message', 'session'])
        ->assertJsonPath('session.status', ViewSessionStatus::Completed->value)
        ->assertJsonPath('session.payment_status', ViewPaymentStatus::Pending->value);

    // Voter pending_earnings incremented
    $this->assertDatabaseHas('voters', [
        'id'              => $voter->id,
        'pending_earnings' => 0.25,
    ]);

    // Campaign views_completed incremented
    $this->assertDatabaseHas('political_campaigns', [
        'id'             => $campaign->id,
        'views_completed' => 1,
    ]);
});

test('completing a non-qualifying view does not credit voter', function () {
    $voter    = activeVoter();
    $campaign = activeCampaign([
        'media_duration'         => 60,
        'min_watch_time_percent' => 80,
        'voter_payout_per_view'  => 0.25,
    ]);
    $session = ViewSession::factory()->create([
        'voter_id'             => $voter->id,
        'political_campaign_id' => $campaign->id,
        'status'               => ViewSessionStatus::InProgress->value,
    ]);

    // 20/60 sec = 33.3% < 80% → rejected
    $response = $this->postJson("/api/v1/sessions/{$session->uuid}/complete", [
        'total_seconds_watched' => 20,
    ]);

    $response->assertOk()
        ->assertJsonPath('session.payment_status', ViewPaymentStatus::Rejected->value);

    $this->assertDatabaseHas('voters', [
        'id'              => $voter->id,
        'pending_earnings' => 0.00,
    ]);
});

test('completing a view creates referral earning for referrer', function () {
    $referrer = activeVoter();
    $voter    = activeVoter(['referred_by_voter_id' => $referrer->id]);
    $campaign = activeCampaign([
        'media_duration'         => 60,
        'min_watch_time_percent' => 80,
        'voter_payout_per_view'  => 0.25,
        'revenue_per_view'       => 0.60,
    ]);
    $session = ViewSession::factory()->create([
        'voter_id'             => $voter->id,
        'political_campaign_id' => $campaign->id,
        'status'               => ViewSessionStatus::InProgress->value,
    ]);

    // 50/60 sec = 83.3% → qualifies
    $this->postJson("/api/v1/sessions/{$session->uuid}/complete", [
        'total_seconds_watched' => 50,
    ])->assertOk();

    // No internal referral_earning row — voter-view commissions are handled
    // exclusively by Early-bank.com via the voter.earned outbound webhook.
    $this->assertDatabaseMissing('referral_earnings', [
        'referrer_voter_id' => $referrer->id,
        'referred_voter_id' => $voter->id,
        'view_session_id'   => $session->id,
    ]);

    // Referrer's pending_earnings unchanged; Early-bank handles the commission.
    $referrer->refresh();
    expect((float) $referrer->pending_earnings)->toBe(0.0);
});

// ── View history ──────────────────────────────────────────────────────────────

test('voter can retrieve their view history', function () {
    $voter    = activeVoter();
    $campaign = activeCampaign();

    ViewSession::factory()->count(3)->create([
        'voter_id'             => $voter->id,
        'political_campaign_id' => $campaign->id,
    ]);

    $response = $this->getJson("/api/v1/voters/{$voter->uuid}/history");

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [['uuid', 'status', 'payment_status', 'watch_time_seconds']],
            'meta',
            'links',
        ])
        ->assertJsonCount(3, 'data');
});

// ── Earnings ─────────────────────────────────────────────────────────────────

test('voter earnings summary returns correct totals', function () {
    $voter = activeVoter([
        'total_earned'    => 5.00,
        'pending_earnings' => 1.25,
        'wallet_balance'  => 3.75,
        'total_views'     => 20,
    ]);

    $response = $this->getJson("/api/v1/voters/{$voter->uuid}/earnings");

    $response->assertOk()
        ->assertJsonPath('total_earned', '5.00')
        ->assertJsonPath('pending_earnings', '1.25')
        ->assertJsonPath('wallet_balance', '3.75')
        ->assertJsonPath('total_views', 20);
});

// ── Analytics helpers ─────────────────────────────────────────────────────────

test('ViewSession byStatus groups sessions correctly', function () {
    $campaign = activeCampaign();
    $voter    = activeVoter();

    ViewSession::factory()->count(2)->create([
        'political_campaign_id' => $campaign->id,
        'voter_id'             => $voter->id,
        'status'               => ViewSessionStatus::Completed->value,
    ]);
    ViewSession::factory()->create([
        'political_campaign_id' => $campaign->id,
        'voter_id'             => $voter->id,
        'status'               => ViewSessionStatus::InProgress->value,
    ]);

    $counts = ViewSession::byStatus($campaign->id);

    expect((int) $counts[ViewSessionStatus::Completed->value])->toBe(2)
        ->and((int) $counts[ViewSessionStatus::InProgress->value])->toBe(1);
});

test('ViewSession scopePendingPayout returns qualifying sessions', function () {
    $campaign = activeCampaign();
    $voter    = activeVoter();

    // Two completed-but-pending-payout sessions
    ViewSession::factory()->count(2)->create([
        'political_campaign_id' => $campaign->id,
        'voter_id'             => $voter->id,
        'status'               => ViewSessionStatus::Completed->value,
        'payment_status'       => ViewPaymentStatus::Approved->value,
    ]);
    // One already paid
    ViewSession::factory()->create([
        'political_campaign_id' => $campaign->id,
        'voter_id'             => $voter->id,
        'status'               => ViewSessionStatus::Completed->value,
        'payment_status'       => ViewPaymentStatus::Paid->value,
    ]);

    expect(ViewSession::pendingPayout()->count())->toBe(2);
});
