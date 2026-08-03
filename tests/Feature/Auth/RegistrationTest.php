<?php

use App\Models\User;
use App\Models\Voter;
use Spatie\Permission\Models\Role;

test('registration screen can be rendered', function () {
    $response = $this->get('/register');

    $response->assertStatus(200);
});

test('new users can register', function () {
    Role::findOrCreate('voter', 'web');
    $email = 'test-voter@example.com';

    $response = $this->post('/register/voter', [
        'first_name' => 'Test',
        'last_name' => 'Voter',
        'email' => $email,
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
        'terms' => '1',
        'zip_code' => '90210',
    ]);

    $response->assertRedirect(route('verification.notice', absolute: false));

    $this->assertDatabaseHas('users', [
        'email' => $email,
        'user_type' => 'voter',
    ]);

    $user = User::where('email', $email)->first();

    expect($user)->not->toBeNull();
    expect($user->hasRole('voter'))->toBeTrue();

    $this->assertAuthenticated();
});

test('registering absorbs a confirmed pending guest digest opt-in with the same email', function () {
    Role::findOrCreate('voter', 'web');
    $email = 'pending-digest@example.com';

    $pending = Voter::factory()->create([
        'user_id' => null,
        'email' => $email,
        'digest_opt_in_pending' => true,
        'digest_confirmed_at' => now(),
    ]);

    $this->post('/register/voter', [
        'first_name' => 'Test',
        'last_name' => 'Voter',
        'email' => $email,
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
        'terms' => '1',
        'zip_code' => '90210',
    ])->assertRedirect(route('verification.notice', absolute: false));

    $user = User::where('email', $email)->first();
    $voter = $pending->fresh();

    expect($voter->user_id)->toBe($user->id);
    expect($voter->digest_opt_in_pending)->toBeFalse();
    expect($voter->digest_confirmed_at)->toBeNull();

    $this->assertDatabaseHas('notification_preferences', [
        'user_id' => $user->id,
        'email_boundary_digest' => true,
    ]);
});
