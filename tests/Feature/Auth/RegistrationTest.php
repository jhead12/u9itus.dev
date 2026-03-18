<?php

use App\Models\User;
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
