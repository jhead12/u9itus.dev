<?php

use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::findOrCreate('admin', 'web');
});

function makeAdminForProfileTest(array $overrides = []): User
{
    $admin = User::factory()->create(array_merge([
        'platform' => 'standalone',
        'user_type' => 'admin',
        'email_verified_at' => now(),
    ], $overrides));

    $admin->assignRole('admin');
    skipOnboarding($admin, 'admin');

    return $admin;
}

test('admin profile page renders when platform settings table is unavailable', function () {
    $admin = makeAdminForProfileTest();

    Schema::drop('platform_settings');

    $this->actingAs($admin)
        ->get(route('admin.profile'))
        ->assertOk()
        ->assertSee('Account Settings')
        ->assertSee('Manage 2FA');
});
