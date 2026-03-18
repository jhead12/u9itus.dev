<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::findOrCreate('admin', 'web');
});

test('admin password can be updated from settings', function () {
    $user = User::factory()->create(['platform' => 'standalone', 'user_type' => 'admin']);
    $user->assignRole('admin');
    skipOnboarding($user, 'admin');

    $response = $this
        ->actingAs($user)
        ->from(route('admin.settings'))
        ->put(route('admin.settings.password'), [
            'current_password' => 'password',
            'new_password' => 'new-password-123',
            'new_password_confirmation' => 'new-password-123',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertSessionHas('password_success')
        ->assertRedirect(route('admin.settings'));

    $this->assertTrue(Hash::check('new-password-123', $user->refresh()->password));
});

test('current password is required to update admin password', function () {
    $user = User::factory()->create(['platform' => 'standalone', 'user_type' => 'admin']);
    $user->assignRole('admin');
    skipOnboarding($user, 'admin');

    $response = $this
        ->actingAs($user)
        ->from(route('admin.settings'))
        ->put(route('admin.settings.password'), [
            'current_password' => 'wrong-password',
            'new_password' => 'new-password-123',
            'new_password_confirmation' => 'new-password-123',
        ]);

    $response
        ->assertSessionHasErrorsIn('updatePassword', 'current_password')
        ->assertRedirect(route('admin.settings'));
});
