<?php

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;

test('login screen can be rendered', function () {
    $response = $this->get('/login');

    $response->assertStatus(200);
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->create();

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('voter.dashboard', absolute: false));
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/logout');

    $this->assertGuest();
    $response->assertRedirect('/');
});

test('logout request with stale csrf token still signs out in test environment', function () {
    $user = User::factory()->create();

    VerifyCsrfToken::flushState();
    $this->withMiddleware();

    $response = $this->actingAs($user)->post('/logout', [], [
        'X-CSRF-TOKEN' => 'stale-token',
    ]);

    $response->assertStatus(302);
    $response->assertRedirect('/');
});
