<?php

use App\Models\User;
use App\Models\Voter;
use App\Models\VoterFavoriteBoundary;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'voter', 'guard_name' => 'web']);
});

// ── Helpers ───────────────────────────────────────────────────────────────────

/** Distinct name from FavoritePoliticianTest::favVoterUser to avoid redeclare. */
function boundaryVoterUser(): User
{
    $user = User::factory()->create(['platform' => 'standalone']);
    $user->assignRole('voter');
    Voter::factory()->create(['user_id' => $user->id]);
    skipOnboarding($user, 'voter');
    return $user->load('voter');
}

// ── Store: district ────────────────────────────────────────────────────────────

test('voter can save a district boundary', function () {
    $user  = boundaryVoterUser();
    $voter = $user->voter;

    $res = $this->actingAs($user)->postJson(route('voter.boundaries.store'), [
        'type'            => 'district',
        'state_abbr'      => 'CA',
        'district_number' => '12',
        'label'           => "California's 12th",
    ]);

    $res->assertOk()->assertJson(['ok' => true, 'created' => true]);

    $this->assertDatabaseHas('voter_favorite_boundaries', [
        'voter_id'        => $voter->id,
        'boundary_type'   => 'district',
        'state_abbr'      => 'CA',
        'district_number' => '12',
        'label'           => "California's 12th",
    ]);
});

test('saving the same district twice is idempotent', function () {
    $user  = boundaryVoterUser();
    $voter = $user->voter;

    $this->actingAs($user)->postJson(route('voter.boundaries.store'), [
        'type' => 'district', 'state_abbr' => 'CA', 'district_number' => '12', 'label' => "California's 12th",
    ]);
    $second = $this->actingAs($user)->postJson(route('voter.boundaries.store'), [
        'type' => 'district', 'state_abbr' => 'ca', 'district_number' => '12', 'label' => "California's 12th",
    ]);

    $second->assertOk()->assertJson(['ok' => true, 'created' => false]);

    expect(VoterFavoriteBoundary::where('voter_id', $voter->id)->count())->toBe(1);
});

// ── Store: city ────────────────────────────────────────────────────────────────

test('voter can save a city boundary with cached coordinates', function () {
    $user  = boundaryVoterUser();
    $voter = $user->voter;

    $res = $this->actingAs($user)->postJson(route('voter.boundaries.store'), [
        'type'       => 'city',
        'state_abbr' => 'CA',
        'city_name'  => 'Los Angeles',
        'label'      => 'Los Angeles, CA',
        'lat'        => 34.052,
        'lng'        => -118.244,
    ]);

    $res->assertOk()->assertJson(['ok' => true]);

    $this->assertDatabaseHas('voter_favorite_boundaries', [
        'voter_id'      => $voter->id,
        'boundary_type' => 'city',
        'city_name'     => 'Los Angeles',
        'lat'           => 34.052,
        'lng'           => -118.244,
    ]);
});

// ── Validation ─────────────────────────────────────────────────────────────────

test('district without district_number is rejected', function () {
    $user = boundaryVoterUser();

    $this->actingAs($user)->postJson(route('voter.boundaries.store'), [
        'type' => 'district', 'state_abbr' => 'CA', 'label' => "California's 12th",
    ])->assertStatus(422);
});

test('city without coordinates is rejected', function () {
    $user = boundaryVoterUser();

    $this->actingAs($user)->postJson(route('voter.boundaries.store'), [
        'type' => 'city', 'state_abbr' => 'CA', 'city_name' => 'Los Angeles', 'label' => 'Los Angeles, CA',
    ])->assertStatus(422);
});

test('invalid boundary type is rejected', function () {
    $user = boundaryVoterUser();

    $this->actingAs($user)->postJson(route('voter.boundaries.store'), [
        'type' => 'county', 'state_abbr' => 'CA', 'label' => 'X',
    ])->assertStatus(422);
});

// ── Index ──────────────────────────────────────────────────────────────────────

test('voter can list their saved boundaries as json', function () {
    $user  = boundaryVoterUser();
    $voter = $user->voter;

    $voter->favoriteBoundaries()->create([
        'boundary_type'   => 'district',
        'state_abbr'      => 'CA',
        'district_number' => '12',
        'label'           => "California's 12th",
    ]);

    $res = $this->actingAs($user)->getJson(route('voter.boundaries.index'));

    $res->assertOk()->assertJsonPath('ok', true)
        ->assertJsonPath('boundaries.0.district_number', '12')
        ->assertJsonPath('boundaries.0.state_abbr', 'CA');
});

test('boundaries list is empty for a new voter', function () {
    $user = boundaryVoterUser();

    $this->actingAs($user)->getJson(route('voter.boundaries.index'))
        ->assertOk()->assertJsonPath('boundaries', []);
});

// ── Destroy ────────────────────────────────────────────────────────────────────

test('voter can remove a saved boundary', function () {
    $user  = boundaryVoterUser();
    $voter = $user->voter;

    $b = $voter->favoriteBoundaries()->create([
        'boundary_type'   => 'district',
        'state_abbr'      => 'CA',
        'district_number' => '12',
        'label'           => "California's 12th",
    ]);

    $this->actingAs($user)->deleteJson(route('voter.boundaries.destroy', $b->id))
        ->assertOk()->assertJson(['ok' => true, 'deleted' => true]);

    $this->assertDatabaseMissing('voter_favorite_boundaries', ['id' => $b->id]);
});

test('voter cannot delete another voter boundary', function () {
    $owner  = boundaryVoterUser();
    $other  = boundaryVoterUser();

    $b = $owner->voter->favoriteBoundaries()->create([
        'boundary_type'   => 'district',
        'state_abbr'      => 'CA',
        'district_number' => '12',
        'label'           => "California's 12th",
    ]);

    $this->actingAs($other)->deleteJson(route('voter.boundaries.destroy', $b->id))
        ->assertOk()->assertJson(['ok' => true, 'deleted' => false]);

    $this->assertDatabaseHas('voter_favorite_boundaries', ['id' => $b->id]);
});

// ── Auth ───────────────────────────────────────────────────────────────────────

test('guest cannot save a boundary', function () {
    // Form posts (not JSON) get redirected to login by the auth middleware.
    $this->post(route('voter.boundaries.store'), [
        'type' => 'district', 'state_abbr' => 'CA', 'district_number' => '12', 'label' => "California's 12th",
    ])->assertRedirect(route('login'));
});