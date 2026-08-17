<?php

use App\Models\Politician;
use App\Models\User;
use App\Services\PlatformSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'voter', 'guard_name' => 'web']);
    RateLimiter::clear('guest-provision:127.0.0.1');
});

function enableGuestTrial(int $days = 30): void
{
    PlatformSettingsService::set('guest_trial_duration_days', $days);
    PlatformSettingsService::set('guest_trial_mode_enabled', '1', [
        'is_active' => true,
        'effective_from' => now(),
        'effective_until' => now()->addDays($days),
    ]);
}

test('trial inactive: anonymous visit is redirected to login as normal', function () {
    $this->get(route('voter.dashboard'))
        ->assertRedirect(route('login'));

    $this->assertDatabaseCount('users', 0);
});

test('trial active: anonymous visit auto-provisions a guest voter and renders the page', function () {
    enableGuestTrial();

    $response = $this->get(route('voter.dashboard'));

    $response->assertOk();

    $this->assertDatabaseHas('users', ['is_guest' => true]);
    $guest = User::where('is_guest', true)->firstOrFail();
    expect($guest->hasRole('voter'))->toBeTrue();
    expect($guest->voter)->not->toBeNull();
});

test('guest can favorite a politician with no login screen', function () {
    enableGuestTrial();
    $politician = Politician::factory()->create();

    $this->get(route('voter.dashboard'))->assertOk();
    $guest = User::where('is_guest', true)->firstOrFail();

    $this->actingAs($guest)
        ->post(route('voter.favorites.store', $politician->id))
        ->assertRedirect();

    $this->assertDatabaseHas('voter_favorite_politicians', [
        'voter_id' => $guest->voter->id,
        'politician_id' => $politician->id,
    ]);
});

test('guest is blocked from money-related routes', function () {
    enableGuestTrial();

    $this->get(route('voter.dashboard'))->assertOk();
    $guest = User::where('is_guest', true)->firstOrFail();

    $this->actingAs($guest)
        ->get(route('voter.earnings'))
        ->assertForbidden();
});

test('guest is never redirected to 2fa setup even when enforcement is on', function () {
    enableGuestTrial();
    PlatformSettingsService::set('voter_2fa_enforced', true, ['is_active' => true]);

    $this->get(route('voter.dashboard'))->assertOk();
    $guest = User::where('is_guest', true)->firstOrFail();

    $this->actingAs($guest)
        ->get(route('voter.dashboard'))
        ->assertOk();
});

test('guest upgrading to a real account preserves an existing favorite', function () {
    enableGuestTrial();
    $politician = Politician::factory()->create();

    $this->get(route('voter.dashboard'))->assertOk();
    $guest = User::where('is_guest', true)->firstOrFail();
    $guestUserId = $guest->id;
    $guestVoterId = $guest->voter->id;

    $this->actingAs($guest)
        ->post(route('voter.favorites.store', $politician->id))
        ->assertRedirect();

    $this->actingAs($guest)->post(route('register.voter.submit'), [
        'first_name' => 'Real',
        'last_name' => 'Person',
        'email' => 'real.person@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
        'zip_code' => '90210',
        'state' => 'CA',
        'terms' => '1',
    ])->assertRedirect();

    $upgraded = User::find($guestUserId);
    expect($upgraded->is_guest)->toBeFalse();
    expect($upgraded->guest_expires_at)->toBeNull();
    expect($upgraded->email)->toBe('real.person@example.com');

    $this->assertDatabaseHas('voters', [
        'id' => $guestVoterId,
        'user_id' => $guestUserId,
        'email' => 'real.person@example.com',
    ]);

    $this->assertDatabaseHas('voter_favorite_politicians', [
        'voter_id' => $guestVoterId,
        'politician_id' => $politician->id,
    ]);
});

test('rate limiting stops a 4th guest from being provisioned from the same IP within an hour', function () {
    enableGuestTrial();

    for ($i = 0; $i < 3; $i++) {
        $this->app['session']->flush();
        auth()->logout();
        $this->get(route('voter.dashboard'))->assertOk();
    }

    $this->assertDatabaseCount('users', 3);

    auth()->logout();
    $this->app['session']->flush();
    $this->get(route('voter.dashboard'));

    $this->assertDatabaseCount('users', 3);
});

test('prune command deletes guests past their expiry grace period and leaves others untouched', function () {
    $expired = User::factory()->create([
        'platform' => 'standalone',
        'user_type' => 'voter',
        'is_guest' => true,
        'guest_expires_at' => now()->subDays(20),
    ]);
    \App\Models\Voter::factory()->create(['user_id' => $expired->id]);

    $recentlyExpired = User::factory()->create([
        'platform' => 'standalone',
        'user_type' => 'voter',
        'is_guest' => true,
        'guest_expires_at' => now()->subDays(2),
    ]);

    $realVoter = User::factory()->create([
        'platform' => 'standalone',
        'user_type' => 'voter',
        'is_guest' => false,
    ]);

    $this->artisan('guests:prune-expired')->assertSuccessful();

    $this->assertDatabaseMissing('users', ['id' => $expired->id]);
    $this->assertDatabaseMissing('voters', ['user_id' => $expired->id]);
    $this->assertDatabaseHas('users', ['id' => $recentlyExpired->id]);
    $this->assertDatabaseHas('users', ['id' => $realVoter->id]);
});
