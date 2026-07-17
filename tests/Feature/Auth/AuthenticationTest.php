<?php

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Spatie\Permission\Models\Role;

test('login screen can be rendered', function () {
    $response = $this->get('/login');

    $response->assertStatus(200);
});

test('users can authenticate using the login screen', function () {
    Role::firstOrCreate(['name' => 'voter', 'guard_name' => 'web']);

    $user = User::factory()->create();
    $user->assignRole('voter');

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
    Role::firstOrCreate(['name' => 'voter', 'guard_name' => 'web']);

    $user = User::factory()->create();
    $user->assignRole('voter');

    $response = $this->actingAs($user)->post('/logout');

    $this->assertGuest();
    $response->assertRedirect('/');
});

test('logout request with stale csrf token still signs out in test environment', function () {
    Role::firstOrCreate(['name' => 'voter', 'guard_name' => 'web']);

    $user = User::factory()->create();
    $user->assignRole('voter');

    VerifyCsrfToken::flushState();
    $this->withMiddleware();

    $response = $this->actingAs($user)->post('/logout', [], [
        'X-CSRF-TOKEN' => 'stale-token',
    ]);

    $response->assertStatus(302);
    $response->assertRedirect('/');
});
