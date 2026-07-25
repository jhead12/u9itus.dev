<?php

use App\Models\Citizen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

test('citizen registration creates user, assigns role, and creates citizen record', function () {
    Role::firstOrCreate(['name' => 'citizen', 'guard_name' => 'web']);

    $response = $this->post(route('register.citizen.submit'), [
        'first_name' => 'Jamie',
        'last_name' => 'Rivera',
        'email' => 'jamie.rivera@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
        'phone' => '555-333-4444',
        'business_name' => 'Rivera Bakery',
        'state' => 'CA',
        'city' => 'Fresno',
        'address_line_1' => '123 Main St',
        'address_line_2' => '',
        'zip' => '93701',
        'terms' => '1',
    ]);

    $response->assertRedirect(route('phone.verify'));

    $user = User::where('email', 'jamie.rivera@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->hasRole('citizen'))->toBeTrue();
    expect($user->user_type)->toBe('citizen');

    $citizen = Citizen::where('user_id', $user->id)->first();
    expect($citizen)->not->toBeNull();
    expect($citizen->full_name)->toBe('Jamie Rivera');
    expect($citizen->business_name)->toBe('Rivera Bakery');
    expect($citizen->state)->toBe('CA');
    expect($citizen->city)->toBe('Fresno');
    expect($citizen->address_line_1)->toBe('123 Main St');
    expect($citizen->zip)->toBe('93701');

    $this->assertDatabaseCount('citizens', 1);
});

test('citizen dashboard route resolves for authenticated citizen', function () {
    Role::firstOrCreate(['name' => 'citizen', 'guard_name' => 'web']);

    $user = User::factory()->create(['user_type' => 'citizen']);
    $user->assignRole('citizen');

    Citizen::factory()->create([
        'user_id' => $user->id,
        'full_name' => $user->name,
    ]);

    $response = $this->actingAs($user)->get(route('citizen.dashboard'));

    $response->assertOk();
    $response->assertSeeText('Campaigns');
    $response->assertSeeText('Blog Posts');
    $response->assertSeeText('Civic Events');
    $response->assertSeeText('Credit Balance');
    $response->assertSeeText('New Campaign');
    $response->assertSeeText('New Post');
    $response->assertSee('Billing & Credits');
    $response->assertSeeText('Two-Factor Auth');
});
