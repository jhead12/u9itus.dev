<?php

use App\Enums\CampaignStatus;
use App\Enums\ApprovalStatus;
use App\Enums\PaymentStatus;
use App\Enums\ViewSessionStatus;
use App\Models\AdViewToken;
use App\Models\PoliticalCampaign;
use App\Models\User;
use App\Models\ViewSession;
use App\Models\Voter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

// ── Helpers ───────────────────────────────────────────────────────────────────

function voterUser(array $voterOverrides = []): array
{
    $user  = User::factory()->create();
    $voter = Voter::factory()->create(array_merge([
        'user_id'    => $user->id,
        'is_verified' => true,
        'is_active'   => true,
        'flagged_for_fraud' => false,
    ], $voterOverrides));

    return [$user, $voter];
}

function watchableCampaign(array $overrides = []): PoliticalCampaign
{
    return PoliticalCampaign::factory()->create(array_merge([
        'status'                 => CampaignStatus::Active->value,
        'approval_status'        => ApprovalStatus::Approved->value,
        'payment_status'         => PaymentStatus::Pending->value,
        'media_duration'         => 60,
        'min_watch_time_percent' => 80,
        'total_views_requested'  => 100,
        'views_completed'        => 0,
    ], $overrides));
}

function validToken(Voter $voter, PoliticalCampaign $campaign, array $overrides = []): AdViewToken
{
    return AdViewToken::create(array_merge([
        'token'                 => AdViewToken::generateSecureToken(),
        'political_campaign_id' => $campaign->id,
        'voter_id'              => $voter->id,
        'notification_method'   => 'email',
        'sent_to'               => $voter->email,
        'sent_at'               => now(),
        'expires_at'            => now()->addHours(24),
        'is_used'               => false,
        'is_expired'            => false,
    ], $overrides));
}

// ── Watch page ────────────────────────────────────────────────────────────────

test('valid token shows watch page', function () {
    [$user, $voter] = voterUser();
    $campaign       = watchableCampaign();
    $adToken        = validToken($voter, $campaign);

    $this->actingAs($user)
        ->get(route('voter.watch', ['token' => $adToken->token]))
        ->assertOk()
        ->assertViewIs('standalone.voter.watch')
        ->assertViewHas('adToken', fn ($t) => $t->id === $adToken->id);
});

test('non-existent token shows not_found error', function () {
    [$user] = voterUser();

    $this->actingAs($user)
        ->get(route('voter.watch', ['token' => 'bad-token']))
        ->assertOk()
        ->assertViewIs('standalone.voter.watch-error')
        ->assertViewHas('reason', 'not_found');
});

test('already used token shows already_used error', function () {
    [$user, $voter] = voterUser();
    $campaign       = watchableCampaign();
    $adToken        = validToken($voter, $campaign, ['is_used' => true]);

    $this->actingAs($user)
        ->get(route('voter.watch', ['token' => $adToken->token]))
        ->assertOk()
        ->assertViewIs('standalone.voter.watch-error')
        ->assertViewHas('reason', 'already_used');
});

test('expired token shows expired error', function () {
    [$user, $voter] = voterUser();
    $campaign       = watchableCampaign();
    $adToken        = validToken($voter, $campaign, [
        'is_expired' => true,
        'expires_at' => now()->subHour(),
    ]);

    $this->actingAs($user)
        ->get(route('voter.watch', ['token' => $adToken->token]))
        ->assertOk()
        ->assertViewIs('standalone.voter.watch-error')
        ->assertViewHas('reason', 'expired');
});

// ── startWatching ─────────────────────────────────────────────────────────────

test('startWatching creates view session and marks token used', function () {
    [$user, $voter] = voterUser();
    $campaign       = watchableCampaign();
    $adToken        = validToken($voter, $campaign);

    $response = $this->actingAs($user)
        ->postJson(route('voter.watch.start', ['token' => $adToken->token]));

    $response->assertOk()
        ->assertJsonStructure(['session_uuid', 'started_at']);

    // Token should now be consumed
    expect($adToken->fresh()->is_used)->toBeTrue();

    // A session should exist
    $uuid = $response->json('session_uuid');
    expect(ViewSession::where('uuid', $uuid)->exists())->toBeTrue();
});

test('startWatching with invalid token returns 422', function () {
    [$user] = voterUser();

    $this->actingAs($user)
        ->postJson(route('voter.watch.start', ['token' => 'fake-token']))
        ->assertStatus(422);
});

test('startWatching with already-used token returns 422', function () {
    [$user, $voter] = voterUser();
    $campaign       = watchableCampaign();
    $adToken        = validToken($voter, $campaign, ['is_used' => true]);

    $this->actingAs($user)
        ->postJson(route('voter.watch.start', ['token' => $adToken->token]))
        ->assertStatus(422);
});

// ── Heartbeat ─────────────────────────────────────────────────────────────────

test('heartbeat returns ok', function () {
    [$user, $voter] = voterUser();
    $campaign = watchableCampaign();
    $session  = ViewSession::factory()->create([
        'voter_id'              => $voter->id,
        'political_campaign_id' => $campaign->id,
        'status'                => ViewSessionStatus::Started->value,
    ]);

    $this->actingAs($user)
        ->postJson(route('voter.session.progress', ['sessionUuid' => $session->uuid]), [
            'seconds_watched' => 30,
        ])
        ->assertOk()
        ->assertJson(['ok' => true]);
});

// ── markComplete ──────────────────────────────────────────────────────────────

test('markComplete returns qualified and payout fields', function () {
    [$user, $voter] = voterUser();
    $campaign = watchableCampaign();
    $session  = ViewSession::factory()->create([
        'voter_id'              => $voter->id,
        'political_campaign_id' => $campaign->id,
        'status'                => ViewSessionStatus::Started->value,
        'watch_time_seconds'    => 55,
    ]);

    $this->actingAs($user)
        ->postJson(route('voter.session.complete', ['sessionUuid' => $session->uuid]))
        ->assertOk()
        ->assertJsonStructure(['qualified', 'payout_earned', 'payment_status']);
});

// ── Dashboard ─────────────────────────────────────────────────────────────────

test('voter dashboard loads successfully', function () {
    [$user, $voter] = voterUser(['wallet_balance' => 1.25, 'total_views' => 5]);

    $this->actingAs($user)
        ->get(route('voter.dashboard'))
        ->assertOk()
        ->assertViewIs('standalone.voter.dashboard');
});

test('voter dashboard is not accessible without auth', function () {
    $this->get(route('voter.dashboard'))
        ->assertRedirect(route('login'));
});

// ── Misc pages ────────────────────────────────────────────────────────────────

test('earnings page loads', function () {
    [$user] = voterUser();

    $this->actingAs($user)
        ->get(route('voter.earnings'))
        ->assertOk()
        ->assertViewIs('standalone.voter.earnings');
});

test('earnings history page loads', function () {
    [$user] = voterUser();

    $this->actingAs($user)
        ->get(route('voter.earnings.history'))
        ->assertOk()
        ->assertViewIs('standalone.voter.earnings-history');
});

test('referrals page loads', function () {
    [$user] = voterUser();

    $this->actingAs($user)
        ->get(route('voter.referrals'))
        ->assertOk()
        ->assertViewIs('standalone.voter.referrals');
});

test('preferences page loads', function () {
    [$user] = voterUser();

    $this->actingAs($user)
        ->get(route('voter.preferences'))
        ->assertOk()
        ->assertViewIs('standalone.voter.preferences');
});

test('preferences update redirects with success', function () {
    [$user] = voterUser();

    $this->actingAs($user)
        ->put(route('voter.preferences.update'), ['notify_email' => '1'])
        ->assertRedirect()
        ->assertSessionHas('success');
});

test('profile page loads', function () {
    [$user] = voterUser();

    $this->actingAs($user)
        ->get(route('voter.profile'))
        ->assertOk()
        ->assertViewIs('standalone.voter.profile');
});

test('profile update saves changes', function () {
    [$user, $voter] = voterUser();

    $this->actingAs($user)
        ->put(route('voter.profile.update'), [
            'name'      => 'Test User',
            'email'     => $user->email,
            'full_name' => 'Test Full Name',
            'state'     => 'TX',
            'zip_code'  => '78701',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($voter->fresh()->state)->toBe('TX')
        ->and($voter->fresh()->zip_code)->toBe('78701');
});
