<?php

use App\Models\User;
use App\Models\Voter;
use App\Support\GuestBoundaryCookie;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'voter', 'guard_name' => 'web']);
    // postJson/getJson drop cookies unless withCredentials() is set.
    $this->withCredentials();
});

function mergeTestVoterUser(): User
{
    $user = User::factory()->create(['platform' => 'standalone']);
    $user->assignRole('voter');
    Voter::factory()->create(['user_id' => $user->id]);
    skipOnboarding($user, 'voter');
    return $user->load('voter');
}

test('guest cookie favorites merge into the real voter on the next authenticated request', function () {
    $saveRes = $this->postJson(route('map.boundaries.store'), [
        'type' => 'district', 'state_abbr' => 'CA', 'district_number' => '12', 'label' => "California's 12th",
    ]);
    $cookieValue = $saveRes->getCookie(GuestBoundaryCookie::BOUNDARIES_COOKIE)->getValue();

    $user = mergeTestVoterUser();

    $res = $this->actingAs($user)
        ->withCookie(GuestBoundaryCookie::BOUNDARIES_COOKIE, $cookieValue)
        ->getJson(route('voter.boundaries.index'));

    $res->assertOk();

    $this->assertDatabaseHas('voter_favorite_boundaries', [
        'voter_id' => $user->voter->id,
        'boundary_type' => 'district',
        'state_abbr' => 'CA',
        'district_number' => '12',
    ]);

    // Cookie should be cleared (expired) once merged.
    $forgotten = $res->getCookie(GuestBoundaryCookie::BOUNDARIES_COOKIE, false);
    expect($forgotten)->not->toBeNull();
    expect($forgotten->getExpiresTime())->toBeLessThan(time());
});

test('merging is idempotent across repeated logins', function () {
    $saveRes = $this->postJson(route('map.boundaries.store'), [
        'type' => 'district', 'state_abbr' => 'CA', 'district_number' => '12', 'label' => "California's 12th",
    ]);
    $cookieValue = $saveRes->getCookie(GuestBoundaryCookie::BOUNDARIES_COOKIE)->getValue();

    $user = mergeTestVoterUser();

    // Manually pre-seed the same boundary the voter already saved directly,
    // so the merge has to de-dupe against an existing row.
    $user->voter->favoriteBoundaries()->create([
        'boundary_type' => 'district', 'state_abbr' => 'CA', 'district_number' => '12', 'label' => "California's 12th",
    ]);

    $this->actingAs($user)
        ->withCookie(GuestBoundaryCookie::BOUNDARIES_COOKIE, $cookieValue)
        ->getJson(route('voter.boundaries.index'));

    expect($user->voter->favoriteBoundaries()->count())->toBe(1);
});

test('pending guest digest-optin voter rows are reparented onto the real voter on login', function () {
    $pending = Voter::factory()->create([
        'user_id' => null,
        'email' => 'pending-guest@example.com',
        'digest_opt_in_pending' => true,
        'digest_confirmed_at' => now(),
    ]);
    $pending->favoriteBoundaries()->create([
        'boundary_type' => 'city', 'state_abbr' => 'CA', 'city_name' => 'Los Angeles',
        'label' => 'Los Angeles, CA', 'lat' => 34.05, 'lng' => -118.24,
    ]);

    $user = mergeTestVoterUser();

    $this->actingAs($user)
        ->withCookie(GuestBoundaryCookie::VOTER_COOKIE, $pending->uuid)
        ->getJson(route('voter.boundaries.index'));

    $this->assertDatabaseHas('voter_favorite_boundaries', [
        'voter_id' => $user->voter->id,
        'boundary_type' => 'city',
        'city_name' => 'Los Angeles',
    ]);
    $this->assertDatabaseMissing('voters', ['id' => $pending->id]);
    $this->assertDatabaseHas('notification_preferences', [
        'user_id' => $user->id,
        'email_boundary_digest' => true,
    ]);
});
