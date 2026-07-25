<?php

use App\Models\Citizen;
use App\Models\User;
use App\Models\Voter;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    if (class_exists(\Spatie\Permission\Models\Role::class)) {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'voter',   'guard_name' => 'web']);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'citizen', 'guard_name' => 'web']);
    }
});

function makeVoterForUpgrade(): User
{
    $user = User::factory()->create(['platform' => 'standalone']);
    $user->assignRole('voter');
    Voter::factory()->create(['user_id' => $user->id]);
    skipOnboarding($user, 'voter');
    return $user;
}

test('voter can view the add-citizen-profile form', function () {
    $user = makeVoterForUpgrade();

    $this->actingAs($user)
        ->get(route('voter.add-citizen-profile'))
        ->assertOk()
        ->assertSee('Add Citizen Profile')
        ->assertSee('Same email, same password');
});

test('voter can add a citizen profile with same email, creating a citizen row and gaining citizen role', function () {
    $user = makeVoterForUpgrade();

    $this->actingAs($user)
        ->post(route('voter.add-citizen-profile.submit'), [
            'full_name'      => 'Jay Baker',
            'business_name'  => 'Maple Bakery',
            'address_line_1' => '123 Maple St',
            'city'           => 'Springfield',
            'state'          => 'CA',
            'zip'            => '90210',
        ])
        ->assertRedirect(route('portal-pick'));

    expect(Citizen::where('user_id', $user->id)->exists())->toBeTrue();
    expect($user->fresh()->hasRole('citizen'))->toBeTrue();
    expect($user->fresh()->hasRole('voter'))->toBeTrue();
    expect($user->fresh()->user_type)->toBe('citizen');
});

test('upgraded dual-role user can access both voter and citizen dashboards', function () {
    $user = makeVoterForUpgrade();

    $this->actingAs($user)
        ->post(route('voter.add-citizen-profile.submit'), [
            'full_name'      => 'Jay Baker',
            'business_name'  => 'Maple Bakery',
            'address_line_1' => '123 Maple St',
            'city'           => 'Springfield',
            'state'          => 'CA',
            'zip'            => '90210',
        ])
        ->assertRedirect(route('portal-pick'));

    skipOnboarding($user->fresh(), 'citizen');

    $this->actingAs($user->fresh())
        ->get(route('citizen.dashboard'))
        ->assertOk();

    $this->actingAs($user->fresh())
        ->get(route('voter.dashboard'))
        ->assertOk();
});

test('voter is redirected to citizen dashboard if they already have a citizen profile', function () {
    $user = makeVoterForUpgrade();
    Citizen::factory()->create(['user_id' => $user->id]);
    $user->assignRole('citizen');
    skipOnboarding($user, 'citizen');

    $this->actingAs($user)
        ->get(route('voter.add-citizen-profile'))
        ->assertRedirect(route('citizen.dashboard'));
});

test('dual-role user sees the portal picker after login redirect', function () {
    $user = makeVoterForUpgrade();
    Citizen::factory()->create(['user_id' => $user->id]);
    $user->assignRole('citizen');
    skipOnboarding($user, 'citizen');

    $this->actingAs($user)
        ->get(route('portal-pick'))
        ->assertOk()
        ->assertSee('Voter Portal')
        ->assertSee('Citizen Portal');
});

test('add citizen profile requires full_name and address fields', function () {
    $user = makeVoterForUpgrade();

    $this->actingAs($user)
        ->post(route('voter.add-citizen-profile.submit'), [])
        ->assertSessionHasErrors(['full_name', 'address_line_1', 'city', 'state', 'zip']);
});

test('voter with citizen row but missing citizen role gets role repaired and can access citizen dashboard', function () {
    $user = makeVoterForUpgrade();

    // Simulate the partial-failure state: Citizen row exists, but the Spatie
    // role was never assigned (or was lost).
    Citizen::factory()->create(['user_id' => $user->id]);
    $user->removeRole('citizen');
    skipOnboarding($user->fresh(), 'citizen');

    $this->actingAs($user->fresh())
        ->get(route('voter.add-citizen-profile'))
        ->assertRedirect(route('citizen.dashboard'));

    expect($user->fresh()->hasRole('citizen'))->toBeTrue();
    expect($user->fresh()->hasRole('voter'))->toBeTrue();
});
