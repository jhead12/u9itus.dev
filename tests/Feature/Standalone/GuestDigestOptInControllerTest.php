<?php

use App\Mail\GuestDigestConfirmationMail;
use App\Models\User;
use App\Models\Voter;
use App\Support\GuestBoundaryCookie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

beforeEach(function () {
    \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'voter', 'guard_name' => 'web']);
    // postJson/getJson drop cookies unless withCredentials() is set.
    $this->withCredentials();
});

function digestOptInVoterUser(): User
{
    $user = User::factory()->create(['platform' => 'standalone']);
    $user->assignRole('voter');
    Voter::factory()->create(['user_id' => $user->id]);
    skipOnboarding($user, 'voter');
    return $user->load('voter');
}

// ── Authenticated voter path ─────────────────────────────────────────────────

test('authenticated voter opt-in just flips the notification preference, no email sent', function () {
    Mail::fake();
    $user = digestOptInVoterUser();

    $this->actingAs($user)
        ->postJson(route('map.boundaries.digest-optin'))
        ->assertOk()->assertJson(['ok' => true, 'status' => 'confirmed']);

    $this->assertDatabaseHas('notification_preferences', [
        'user_id' => $user->id,
        'email_boundary_digest' => true,
    ]);
    Mail::assertNothingSent();
});

// ── Guest path ───────────────────────────────────────────────────────────────

test('guest opt-in creates a pending voter row and queues a confirmation email', function () {
    Mail::fake();

    $res = $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class)
        ->postJson(route('map.boundaries.digest-optin'), ['email' => 'guest@example.com']);

    $res->assertOk()->assertJson(['ok' => true, 'status' => 'confirmation_sent']);

    $voter = Voter::whereNull('user_id')->where('email', 'guest@example.com')->first();
    expect($voter)->not->toBeNull();
    expect($voter->digest_opt_in_pending)->toBeTrue();
    expect($voter->digest_confirmed_at)->toBeNull();

    Mail::assertQueued(GuestDigestConfirmationMail::class, fn ($mail) => $mail->voter->is($voter));

    $voterCookie = $res->getCookie(GuestBoundaryCookie::VOTER_COOKIE);
    expect($voterCookie)->not->toBeNull();
    expect($voterCookie->getValue())->toBe($voter->uuid);
});

test('guest opt-in merges any cookie-saved boundaries into the new pending voter', function () {
    Mail::fake();

    $saveRes = $this->postJson(route('map.boundaries.store'), [
        'type' => 'district', 'state_abbr' => 'CA', 'district_number' => '12', 'label' => "California's 12th",
    ]);
    $cookieValue = $saveRes->getCookie(GuestBoundaryCookie::BOUNDARIES_COOKIE)->getValue();

    $res = $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class)
        ->withCookie(GuestBoundaryCookie::BOUNDARIES_COOKIE, $cookieValue)
        ->postJson(route('map.boundaries.digest-optin'), ['email' => 'guest2@example.com']);

    $res->assertOk();

    $voter = Voter::whereNull('user_id')->where('email', 'guest2@example.com')->first();
    $this->assertDatabaseHas('voter_favorite_boundaries', [
        'voter_id' => $voter->id,
        'boundary_type' => 'district',
        'state_abbr' => 'CA',
        'district_number' => '12',
    ]);
});

test('guest opt-in with the same email reuses the existing pending voter', function () {
    Mail::fake();
    $throttleFree = fn () => $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);

    $throttleFree()->postJson(route('map.boundaries.digest-optin'), ['email' => 'repeat@example.com']);
    $throttleFree()->postJson(route('map.boundaries.digest-optin'), ['email' => 'repeat@example.com']);

    expect(Voter::whereNull('user_id')->where('email', 'repeat@example.com')->count())->toBe(1);
});

// ── Confirm ────────────────────────────────────────────────────────────────────

test('confirm link marks the pending voter as confirmed', function () {
    $voter = Voter::factory()->create([
        'user_id' => null,
        'email' => 'confirm-me@example.com',
        'digest_opt_in_pending' => true,
        'digest_confirmed_at' => null,
    ]);

    $url = URL::temporarySignedRoute('map.boundaries.digest.confirm', now()->addDays(3), [
        'voter' => $voter->uuid,
        'hash' => sha1($voter->email),
    ]);

    $this->get($url)->assertOk();

    expect($voter->fresh()->digest_confirmed_at)->not->toBeNull();
});

test('confirm link with a tampered hash is rejected', function () {
    $voter = Voter::factory()->create([
        'user_id' => null,
        'email' => 'tampered@example.com',
        'digest_opt_in_pending' => true,
        'digest_confirmed_at' => null,
    ]);

    $url = URL::temporarySignedRoute('map.boundaries.digest.confirm', now()->addDays(3), [
        'voter' => $voter->uuid,
        'hash' => sha1('someone-else@example.com'),
    ]);

    $this->get($url)->assertStatus(403);
    expect($voter->fresh()->digest_confirmed_at)->toBeNull();
});

test('expired confirm link is rejected', function () {
    $voter = Voter::factory()->create([
        'user_id' => null,
        'email' => 'expired@example.com',
        'digest_opt_in_pending' => true,
        'digest_confirmed_at' => null,
    ]);

    $url = URL::temporarySignedRoute('map.boundaries.digest.confirm', now()->subMinute(), [
        'voter' => $voter->uuid,
        'hash' => sha1($voter->email),
    ]);

    $this->get($url)->assertStatus(403);
});

// ── Unsubscribe ───────────────────────────────────────────────────────────────

test('unsubscribe link turns off a confirmed guest opt-in', function () {
    $voter = Voter::factory()->create([
        'user_id' => null,
        'email' => 'unsub-me@example.com',
        'digest_opt_in_pending' => true,
        'digest_confirmed_at' => now(),
    ]);

    $url = URL::signedRoute('map.boundaries.digest.unsubscribe', [
        'voter' => $voter->uuid,
        'hash' => sha1($voter->email),
    ]);

    $this->get($url)->assertOk();

    $voter->refresh();
    expect($voter->digest_opt_in_pending)->toBeFalse();
    expect($voter->digest_confirmed_at)->toBeNull();
});

test('unsubscribe link is idempotent when clicked twice', function () {
    $voter = Voter::factory()->create([
        'user_id' => null,
        'email' => 'unsub-twice@example.com',
        'digest_opt_in_pending' => true,
        'digest_confirmed_at' => now(),
    ]);

    $url = URL::signedRoute('map.boundaries.digest.unsubscribe', [
        'voter' => $voter->uuid,
        'hash' => sha1($voter->email),
    ]);

    $this->get($url)->assertOk();
    $this->get($url)->assertOk();

    expect($voter->fresh()->digest_opt_in_pending)->toBeFalse();
});

test('unsubscribe link with a tampered hash is rejected', function () {
    $voter = Voter::factory()->create([
        'user_id' => null,
        'email' => 'unsub-tampered@example.com',
        'digest_opt_in_pending' => true,
        'digest_confirmed_at' => now(),
    ]);

    $url = URL::signedRoute('map.boundaries.digest.unsubscribe', [
        'voter' => $voter->uuid,
        'hash' => sha1('someone-else@example.com'),
    ]);

    $this->get($url)->assertStatus(403);
    expect($voter->fresh()->digest_opt_in_pending)->toBeTrue();
});
