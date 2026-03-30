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

test('admin can bulk suspend selected users while skipping admins', function () {
    $admin = makeAdminForUserSearchTests();

    $targetUser = User::factory()->create([
        'name' => 'Bulk Suspend Target',
        'email' => 'bulk.suspend.target@example.org',
        'user_type' => 'voter',
        'suspended_at' => null,
    ]);

    $otherAdmin = User::factory()->create([
        'name' => 'Should Not Suspend Admin',
        'email' => 'should.not.suspend.admin@example.org',
        'user_type' => 'admin',
        'suspended_at' => null,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.users.bulk-action'), [
            'action' => 'suspend',
            'user_ids' => [$targetUser->id, $otherAdmin->id],
        ])
        ->assertRedirect();

    expect($targetUser->fresh()->suspended_at)->not->toBeNull();
    expect($otherAdmin->fresh()->suspended_at)->toBeNull();
});

test('admin can bulk approve kyc for selected users', function () {
    $admin = makeAdminForUserSearchTests();

    $targetUser = User::factory()->create([
        'name' => 'Bulk KYC Approve Target',
        'email' => 'bulk.kyc.target@example.org',
        'user_type' => 'voter',
        'kyc_status' => 'pending',
        'kyc_reviewed_at' => null,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.users.bulk-action'), [
            'action' => 'kyc_approve',
            'user_ids' => [$targetUser->id],
        ])
        ->assertRedirect();

    $targetUser = $targetUser->fresh();

    expect($targetUser->kyc_status)->toBe('approved');
    expect($targetUser->kyc_reviewed_at)->not->toBeNull();
    expect($targetUser->kyc_reviewer_id)->toBe($admin->id);
});
