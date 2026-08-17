<?php

use App\Jobs\GeocodeCitizenAddress;
use App\Models\Citizen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function makeCitizenForSettings(array $citizenAttrs = []): array
{
    Role::firstOrCreate(['name' => 'citizen', 'guard_name' => 'web']);

    $user = User::factory()->create(['user_type' => 'citizen']);
    $user->assignRole('citizen');
    skipOnboarding($user, 'citizen');

    $citizen = Citizen::factory()->create(array_merge([
        'user_id' => $user->id,
        'address_line_1' => '100 Original St',
        'city' => 'Fresno',
        'state' => 'CA',
        'zip' => '93701',
        'show_on_map' => false,
    ], $citizenAttrs));

    return [$user, $citizen];
}

test('citizen can view their business settings page', function () {
    [$user, $citizen] = makeCitizenForSettings();

    $this->actingAs($user)
        ->get(route('citizen.settings'))
        ->assertOk()
        ->assertSee('Business Location')
        ->assertSee('Show my business on the map');
});

test('updating settings without changing the address does not re-geocode', function () {
    Queue::fake();
    [$user, $citizen] = makeCitizenForSettings(['latitude' => 34.0, 'longitude' => -118.0]);

    $this->actingAs($user)
        ->put(route('citizen.settings.update'), [
            'business_name' => 'Fresno Diner',
            'business_category' => 'food',
            'address_line_1' => $citizen->address_line_1,
            'city' => $citizen->city,
            'state' => $citizen->state,
            'zip' => $citizen->zip,
            'show_on_map' => '1',
        ])
        ->assertRedirect();

    $citizen->refresh();
    expect($citizen->business_name)->toBe('Fresno Diner');
    expect($citizen->business_category)->toBe('food');
    expect($citizen->show_on_map)->toBeTrue();
    // Coordinates untouched — address didn't change.
    expect((float) $citizen->latitude)->toBe(34.0);

    Queue::assertNotPushed(GeocodeCitizenAddress::class);
});

test('changing the address clears stale coordinates and queues a re-geocode', function () {
    Queue::fake();
    [$user, $citizen] = makeCitizenForSettings(['latitude' => 34.0, 'longitude' => -118.0]);

    $this->actingAs($user)
        ->put(route('citizen.settings.update'), [
            'business_name' => $citizen->business_name,
            'address_line_1' => '999 New Address Ave',
            'city' => $citizen->city,
            'state' => $citizen->state,
            'zip' => $citizen->zip,
            'show_on_map' => '0',
        ])
        ->assertRedirect();

    $citizen->refresh();
    expect($citizen->address_line_1)->toBe('999 New Address Ave');
    expect($citizen->latitude)->toBeNull();
    expect($citizen->longitude)->toBeNull();

    Queue::assertPushed(GeocodeCitizenAddress::class, fn ($job) => $job->citizenId === $citizen->id);
});

test('show_on_map defaults off and unchecking the box turns it back off', function () {
    Queue::fake();
    [$user, $citizen] = makeCitizenForSettings(['show_on_map' => true]);

    $this->actingAs($user)
        ->put(route('citizen.settings.update'), [
            'address_line_1' => $citizen->address_line_1,
            'city' => $citizen->city,
            'state' => $citizen->state,
            'zip' => $citizen->zip,
            // show_on_map omitted entirely, as an unchecked checkbox would be.
        ])
        ->assertRedirect();

    expect($citizen->fresh()->show_on_map)->toBeFalse();
});

test('settings update requires the address fields', function () {
    [$user, $citizen] = makeCitizenForSettings();

    $this->actingAs($user)
        ->put(route('citizen.settings.update'), [])
        ->assertSessionHasErrors(['address_line_1', 'city', 'state', 'zip']);
});
