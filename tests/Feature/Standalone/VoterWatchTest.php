<?php

use App\Enums\CampaignStatus;
use App\Enums\ApprovalStatus;
use App\Enums\PaymentStatus;
use App\Enums\ViewSessionStatus;
use App\Models\AdViewToken;
use App\Models\EngagementSurveyResponse;
use App\Models\PoliticalCampaign;
use App\Models\User;
use App\Models\ViewSession;
use App\Models\VoterWatchReport;
use App\Models\Voter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

// ── Helpers ───────────────────────────────────────────────────────────────────

function voterUser(array $voterOverrides = []): array
{
    Role::firstOrCreate(['name' => 'voter', 'guard_name' => 'web']);

    $user  = User::factory()->create();
    $user->assignRole('voter');

    // Skip onboarding for test
    skipOnboarding($user, 'voter');

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
        ->assertViewHas('reason', 'invalid');
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

test('hls media type watch page renders HLS player mode', function () {
    [$user, $voter] = voterUser();
    $campaign = watchableCampaign([
        'media_type' => 'hls_stream',
        'media_url' => 'https://example.com/stream/index.m3u8',
    ]);
    $adToken = validToken($voter, $campaign);

    $this->actingAs($user)
        ->get(route('voter.watch', ['token' => $adToken->token]))
        ->assertOk()
        ->assertSee("const playerMode    = 'hls';", false)
        ->assertSee('hls.js@1.5.15', false);
});

test('watch page includes compact public q and a preview for approved campaign questions', function () {
    [$user, $voter] = voterUser();
    $campaign = watchableCampaign();
    $campaign->politician->update([
        'slug' => 'watch-preview-politician',
        'page_published' => true,
    ]);
    $adToken = validToken($voter, $campaign);

    VoterWatchReport::create([
        'voter_id' => $voter->id,
        'campaign_id' => $campaign->id,
        'type' => 'message',
        'body' => 'How will you reduce commute times?',
        'status' => 'resolved',
        'public_visibility' => 'approved',
        'is_public_board' => true,
        'public_alias' => 'Voter #104',
        'campaign_reply' => 'We will add more frequent bus lanes during peak hours.',
        'campaign_replied_at' => now()->subHour(),
        'published_at' => now()->subMinutes(30),
    ]);

    VoterWatchReport::create([
        'voter_id' => $voter->id,
        'campaign_id' => $campaign->id,
        'type' => 'message',
        'body' => 'This should stay hidden until approved.',
        'status' => 'open',
        'public_visibility' => 'pending',
        'is_public_board' => false,
    ]);

    $this->actingAs($user)
        ->get(route('voter.watch', ['token' => $adToken->token]))
        ->assertOk()
        ->assertSee('Recent Voter Q&amp;A', false)
        ->assertSee('See what voters asked')
        ->assertSee('How will you reduce commute times?')
        ->assertSee('We will add more frequent bus lanes during peak hours.')
        ->assertDontSee('This should stay hidden until approved.');
});

// ── startWatching ─────────────────────────────────────────────────────────────

test('startWatching creates view session and marks token used', function () {
    [$user, $voter] = voterUser();
    $campaign       = watchableCampaign();
    $adToken        = validToken($voter, $campaign);

    $response = $this->actingAs($user)
        ->postJson(route('voter.watch.start', ['token' => $adToken->token]));

    $response->assertOk()
        ->assertJsonStructure(['session_id', 'status']);

    // Token should now be consumed
    expect($adToken->fresh()->is_used)->toBeTrue();

    // A session should exist
    $uuid = $response->json('session_id');
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
        'status'                => ViewSessionStatus::InProgress->value,
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
        'status'                => ViewSessionStatus::InProgress->value,
        'watch_time_seconds'    => 55,
    ]);

    $this->actingAs($user)
        ->postJson(route('voter.session.complete', ['sessionUuid' => $session->uuid]), [
            'total_seconds_watched' => 55,
        ])
        ->assertOk()
        ->assertJsonStructure(['qualified', 'payout_earned', 'status']);
});

test('markComplete uses client media duration when campaign duration is missing', function () {
    [$user, $voter] = voterUser();
    $campaign = watchableCampaign([
        'media_duration' => null,
        'min_watch_time_percent' => 100,
    ]);

    $session = ViewSession::factory()->create([
        'voter_id'              => $voter->id,
        'political_campaign_id' => $campaign->id,
        'status'                => ViewSessionStatus::InProgress->value,
        'watch_time_seconds'    => 60,
    ]);

    $this->actingAs($user)
        ->postJson(route('voter.session.complete', ['sessionUuid' => $session->uuid]), [
            'total_seconds_watched' => 60,
            'media_duration_seconds' => 120,
        ])
        ->assertOk()
        ->assertJsonPath('qualified', false);
});

test('markComplete falls back to configured duration when campaign and client durations are missing', function () {
    [$user, $voter] = voterUser();
    config()->set('u9itus.max_video_duration', 180);

    $campaign = watchableCampaign([
        'media_duration' => null,
        'min_watch_time_percent' => 100,
    ]);

    $session = ViewSession::factory()->create([
        'voter_id'              => $voter->id,
        'political_campaign_id' => $campaign->id,
        'status'                => ViewSessionStatus::InProgress->value,
        'watch_time_seconds'    => 60,
    ]);

    $this->actingAs($user)
        ->postJson(route('voter.session.complete', ['sessionUuid' => $session->uuid]), [
            'total_seconds_watched' => 60,
        ])
        ->assertOk()
        ->assertJsonPath('qualified', false);
});

test('submitSurvey stores response for completed session', function () {
    [$user, $voter] = voterUser();
    $campaign = watchableCampaign([
        'engagement_survey' => [
            'question' => 'Do you support this proposal?',
            'options' => [
                ['text' => 'Yes', 'value' => 'A'],
                ['text' => 'No', 'value' => 'B'],
            ],
        ],
    ]);

    $session = ViewSession::factory()->create([
        'voter_id'              => $voter->id,
        'political_campaign_id' => $campaign->id,
        'status'                => ViewSessionStatus::Completed->value,
    ]);

    $this->actingAs($user)
        ->postJson(route('voter.session.survey', ['sessionUuid' => $session->uuid]), [
            'response_value' => 'A',
            'response_text' => 'Strong support',
        ])
        ->assertOk()
        ->assertJson(['success' => true]);

    expect(EngagementSurveyResponse::where('view_session_id', $session->id)->exists())->toBeTrue();
});

test('submitSurvey rejects responses before session completion', function () {
    [$user, $voter] = voterUser();
    $campaign = watchableCampaign([
        'engagement_survey' => [
            'question' => 'Do you support this proposal?',
            'options' => [
                ['text' => 'Yes', 'value' => 'A'],
                ['text' => 'No', 'value' => 'B'],
            ],
        ],
    ]);

    $session = ViewSession::factory()->create([
        'voter_id'              => $voter->id,
        'political_campaign_id' => $campaign->id,
        'status'                => ViewSessionStatus::InProgress->value,
    ]);

    $this->actingAs($user)
        ->postJson(route('voter.session.survey', ['sessionUuid' => $session->uuid]), [
            'response_value' => 'A',
        ])
        ->assertStatus(422)
        ->assertJsonStructure(['error']);
});

test('askQuestion stores voter question for politician campaign', function () {
    [$user, $voter] = voterUser();
    $campaign = watchableCampaign();
    $adToken = validToken($voter, $campaign);

    $this->actingAs($user)
        ->postJson(route('voter.watch.ask-question', ['token' => $adToken->token]), [
            'body' => 'What specific policy steps will you take in your first 100 days?',
        ])
        ->assertOk()
        ->assertJson(['success' => true]);

    $report = VoterWatchReport::where('campaign_id', $campaign->id)
        ->where('voter_id', $voter->id)
        ->where('type', 'message')
        ->first();

    expect($report)->not->toBeNull()
        ->and($report->public_visibility)->toBe('pending')
        ->and($report->is_public_board)->toBeFalse()
        ->and($report->public_alias)->toStartWith('Voter #');
});

test('askQuestion rejects blocked terms', function () {
    config()->set('u9itus.q_and_a.moderation.blocked_terms', ['forbiddenword']);

    [$user, $voter] = voterUser();
    $campaign = watchableCampaign();
    $adToken = validToken($voter, $campaign);

    $this->actingAs($user)
        ->postJson(route('voter.watch.ask-question', ['token' => $adToken->token]), [
            'body' => 'This contains forbiddenword and should fail.',
        ])
        ->assertStatus(422)
        ->assertJsonPath('success', false);

    expect(VoterWatchReport::query()
        ->where('campaign_id', $campaign->id)
        ->where('voter_id', $voter->id)
        ->where('type', 'message')
        ->exists())->toBeFalse();
});

test('askQuestion is rate limited', function () {
    config()->set('u9itus.q_and_a.rate_limit.max_attempts', 2);
    config()->set('u9itus.q_and_a.rate_limit.decay_seconds', 3600);

    [$user, $voter] = voterUser();
    $campaign = watchableCampaign();
    $adToken = validToken($voter, $campaign);

    $this->actingAs($user)
        ->postJson(route('voter.watch.ask-question', ['token' => $adToken->token]), [
            'body' => 'First question attempt',
        ])
        ->assertOk();

    $this->actingAs($user)
        ->postJson(route('voter.watch.ask-question', ['token' => $adToken->token]), [
            'body' => 'Second question attempt',
        ])
        ->assertOk();

    $this->actingAs($user)
        ->postJson(route('voter.watch.ask-question', ['token' => $adToken->token]), [
            'body' => 'Third question should be blocked',
        ])
        ->assertStatus(429)
        ->assertJsonPath('success', false);
});

// ── Dashboard ─────────────────────────────────────────────────────────────────

test('voter dashboard loads successfully', function () {
    [$user] = voterUser(['wallet_balance' => 1.25, 'total_views' => 5]);

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
        ->put(route('voter.preferences.update'), ['payment_method' => 'paypal', 'paypal_email' => 'test@example.com'])
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
            'full_name' => 'Test Full Name',
            'state'     => 'TX',
            'zip_code'  => '78701',
            'city'      => 'Austin',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($voter->fresh()->state)->toBe('TX')
        ->and($voter->fresh()->zip_code)->toBe('78701');
});
