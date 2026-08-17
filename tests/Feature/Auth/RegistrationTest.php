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

test('registration is rejected when the honeypot field is filled', function () {
    Role::findOrCreate('voter', 'web');
    $email = 'bot-honeypot@example.com';

    $response = $this->post('/register/voter', [
        'first_name' => 'Test',
        'last_name' => 'Voter',
        'email' => $email,
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
        'terms' => '1',
        'zip_code' => '90210',
        'hp_website' => 'http://spam.example',
    ]);

    $response->assertSessionHasErrors();
    $this->assertDatabaseMissing('users', ['email' => $email]);
});

test('registration is rejected for a disposable email domain', function () {
    Role::findOrCreate('voter', 'web');
    $email = 'test-voter@mailinator.com';

    $response = $this->post('/register/voter', [
        'first_name' => 'Test',
        'last_name' => 'Voter',
        'email' => $email,
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
        'terms' => '1',
        'zip_code' => '90210',
    ]);

    $response->assertSessionHasErrors();
    $this->assertDatabaseMissing('users', ['email' => $email]);
});

test('registration is rejected for a gibberish name plus gibberish email', function () {
    Role::findOrCreate('voter', 'web');
    $email = 'tcbmysdxtrjuwjshcealur@example.com';

    $response = $this->post('/register/voter', [
        'first_name' => 'tcBmySdXtrjuWJsHceaLUr',
        'last_name' => 'eydgvkWGLCQKXFnxSz',
        'email' => $email,
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
        'terms' => '1',
        'zip_code' => '90210',
    ]);

    $response->assertSessionHasErrors();
    $this->assertDatabaseMissing('users', ['email' => $email]);
});

test('a mildly suspicious registration is allowed through but flagged for review', function () {
    Role::findOrCreate('voter', 'web');
    // Gibberish first/last name (matches the fake-account pattern) but a
    // plausible email local-part, so this lands in the "flag" band (60)
    // rather than the "hard block" band (80+) — worth a human's attention,
    // not confident enough to reject outright.
    $email = 'jsmith482@example.com';

    $response = $this->post('/register/voter', [
        'first_name' => 'abXYqbhosxOsiWUERMCBP',
        'last_name' => 'eHgEylnJKlNuvavbTLEDQGl',
        'email' => $email,
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
        'terms' => '1',
        'zip_code' => '90210',
    ]);

    $response->assertRedirect(route('verification.notice', absolute: false));

    $user = User::where('email', $email)->first();
    expect($user)->not->toBeNull();
    expect($user->flagged_for_fraud)->toBeTrue();
    expect($user->fraud_score)->toBeGreaterThanOrEqual(40);
});

test('registration is throttled after repeated attempts from the same IP', function () {
    Role::findOrCreate('voter', 'web');

    for ($i = 0; $i < 5; $i++) {
        $this->post('/register/voter', [
            'first_name' => 'Test',
            'last_name' => 'Voter',
            'email' => "throttle-test-{$i}@example.com",
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'terms' => '1',
            'zip_code' => '90210',
        ]);
    }

    $response = $this->post('/register/voter', [
        'first_name' => 'Test',
        'last_name' => 'Voter',
        'email' => 'throttle-test-overflow@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
        'terms' => '1',
        'zip_code' => '90210',
    ]);

    $response->assertStatus(429);
});
