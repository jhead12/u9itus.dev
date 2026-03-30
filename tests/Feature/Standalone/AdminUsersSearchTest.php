<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function makeAdminForUserSearchTests(): User
{
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    $admin = User::factory()->create([
        'user_type' => 'admin',
    ]);

    $admin->assignRole('admin');
    skipOnboarding($admin, 'admin');

    return $admin;
}

test('admin can search users by email and name fragments', function () {
    $admin = makeAdminForUserSearchTests();

    User::factory()->create([
        'name' => 'Search Match Person',
        'email' => 'target.user@example.org',
        'user_type' => 'voter',
    ]);

    User::factory()->create([
        'name' => 'Different Person',
        'email' => 'other.user@example.org',
        'user_type' => 'voter',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.users.index', ['search' => 'target.user@']))
        ->assertOk()
        ->assertSee('Search Match Person')
        ->assertDontSee('Different Person');
});

test('admin can filter users by role kyc and account status', function () {
    $admin = makeAdminForUserSearchTests();

    User::factory()->create([
        'name' => 'Approved Active Politician',
        'email' => 'approved.politician@example.org',
        'user_type' => 'politician',
        'kyc_status' => 'approved',
        'email_verified_at' => now(),
        'suspended_at' => null,
    ]);

    User::factory()->create([
        'name' => 'Pending Politician',
        'email' => 'pending.politician@example.org',
        'user_type' => 'politician',
        'kyc_status' => 'pending',
        'email_verified_at' => now(),
        'suspended_at' => null,
    ]);

    User::factory()->create([
        'name' => 'Approved Suspended Politician',
        'email' => 'suspended.politician@example.org',
        'user_type' => 'politician',
        'kyc_status' => 'approved',
        'email_verified_at' => now(),
        'suspended_at' => now(),
    ]);

    User::factory()->create([
        'name' => 'Approved Active Voter',
        'email' => 'approved.voter@example.org',
        'user_type' => 'voter',
        'kyc_status' => 'approved',
        'email_verified_at' => now(),
        'suspended_at' => null,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.users.index', [
            'role' => 'politician',
            'kyc' => 'approved',
            'account_status' => 'active',
        ]))
        ->assertOk()
        ->assertSee('Approved Active Politician')
        ->assertDontSee('Pending Politician')
        ->assertDontSee('Approved Suspended Politician')
        ->assertDontSee('Approved Active Voter');
});
