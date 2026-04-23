<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
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

test('admin profile page renders when admin 2fa secret payload is invalid', function () {
    $admin = makeAdminForProfileTest([
        'admin_two_factor_confirmed_at' => now(),
    ]);

    // Simulate legacy/corrupt ciphertext that fails encrypted cast decryption.
    DB::table('users')
        ->where('id', $admin->id)
        ->update(['admin_two_factor_secret' => 'not-valid-encrypted-payload']);

    $this->actingAs($admin->fresh())
        ->get(route('admin.profile'))
        ->assertOk()
        ->assertSee('Account Settings')
        ->assertSee('Manage 2FA')
        ->assertSee('Status: Disabled');
});
