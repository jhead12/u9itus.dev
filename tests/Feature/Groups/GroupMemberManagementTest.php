<?php

use App\Models\NeighborhoodGroup;
use App\Models\User;
use App\Models\Voter;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'voter', 'guard_name' => 'web']);
});

function makeVoterForMemberMgmt(): User
{
    $user = User::factory()->create(['platform' => 'standalone', 'user_type' => 'voter']);
    $user->assignRole('voter');
    Voter::factory()->create(['user_id' => $user->id]);
    skipOnboarding($user, 'voter');

    return $user->load('voter');
}

function makeGroupForMemberMgmt(User $owner): NeighborhoodGroup
{
    $group = NeighborhoodGroup::create(['name' => 'Member Mgmt Group', 'admin_user_id' => $owner->id]);
    $group->members()->attach($owner->id, ['role' => 'admin', 'joined_at' => now()]);

    return $group;
}

test('member can view the member list', function () {
    $owner = makeVoterForMemberMgmt();
    $group = makeGroupForMemberMgmt($owner);
    $member = makeVoterForMemberMgmt();
    $group->members()->attach($member->id, ['role' => 'member', 'joined_at' => now()]);

    $this->actingAs($member)
        ->get(route('groups.members.index', $group))
        ->assertOk()
        ->assertSee($owner->name)
        ->assertSee($member->name);
});

test('non-member cannot view the member list', function () {
    $owner = makeVoterForMemberMgmt();
    $group = makeGroupForMemberMgmt($owner);
    $outsider = makeVoterForMemberMgmt();

    $this->actingAs($outsider)
        ->get(route('groups.members.index', $group))
        ->assertForbidden();
});

test('guest is redirected to login for the member list', function () {
    $owner = makeVoterForMemberMgmt();
    $group = makeGroupForMemberMgmt($owner);

    $this->get(route('groups.members.index', $group))
        ->assertRedirect(route('login'));
});

test('owner can promote a member to admin', function () {
    $owner = makeVoterForMemberMgmt();
    $group = makeGroupForMemberMgmt($owner);
    $member = makeVoterForMemberMgmt();
    $group->members()->attach($member->id, ['role' => 'member', 'joined_at' => now()]);

    $this->actingAs($owner)
        ->patch(route('groups.members.role', [$group, $member]), ['role' => 'admin'])
        ->assertRedirect();

    $this->assertDatabaseHas('group_memberships', [
        'neighborhood_group_id' => $group->id,
        'user_id' => $member->id,
        'role' => 'admin',
    ]);
});

test('promoted admin can edit group settings but cannot delete the group', function () {
    $owner = makeVoterForMemberMgmt();
    $group = makeGroupForMemberMgmt($owner);
    $promoted = makeVoterForMemberMgmt();
    $group->members()->attach($promoted->id, ['role' => 'admin', 'joined_at' => now()]);

    $this->actingAs($promoted)
        ->put(route('groups.update', $group), ['name' => 'Renamed By Co-Admin'])
        ->assertRedirect();
    $this->assertDatabaseHas('neighborhood_groups', ['id' => $group->id, 'name' => 'Renamed By Co-Admin']);

    $this->actingAs($promoted)
        ->delete(route('groups.destroy', $group))
        ->assertForbidden();
    $this->assertDatabaseHas('neighborhood_groups', ['id' => $group->id]);
});

test('promoted admin does not see the Delete Group button, only the owner does', function () {
    $owner = makeVoterForMemberMgmt();
    $group = makeGroupForMemberMgmt($owner);
    $promoted = makeVoterForMemberMgmt();
    $group->members()->attach($promoted->id, ['role' => 'admin', 'joined_at' => now()]);

    $this->actingAs($promoted)
        ->get(route('groups.edit', $group))
        ->assertOk()
        ->assertDontSee('Delete Group');

    $this->actingAs($owner)
        ->get(route('groups.edit', $group))
        ->assertOk()
        ->assertSee('Delete Group');
});

test('promoted admin cannot promote or demote anyone', function () {
    $owner = makeVoterForMemberMgmt();
    $group = makeGroupForMemberMgmt($owner);
    $promoted = makeVoterForMemberMgmt();
    $group->members()->attach($promoted->id, ['role' => 'admin', 'joined_at' => now()]);
    $member = makeVoterForMemberMgmt();
    $group->members()->attach($member->id, ['role' => 'member', 'joined_at' => now()]);

    $this->actingAs($promoted)
        ->patch(route('groups.members.role', [$group, $member]), ['role' => 'admin'])
        ->assertForbidden();
});

test("owner's role cannot be changed", function () {
    $owner = makeVoterForMemberMgmt();
    $group = makeGroupForMemberMgmt($owner);

    $this->actingAs($owner)
        ->patch(route('groups.members.role', [$group, $owner]), ['role' => 'member'])
        ->assertStatus(422);

    $this->assertDatabaseHas('group_memberships', [
        'neighborhood_group_id' => $group->id,
        'user_id' => $owner->id,
        'role' => 'admin',
    ]);
});

test('any admin can remove a regular member', function () {
    $owner = makeVoterForMemberMgmt();
    $group = makeGroupForMemberMgmt($owner);
    $promoted = makeVoterForMemberMgmt();
    $group->members()->attach($promoted->id, ['role' => 'admin', 'joined_at' => now()]);
    $member = makeVoterForMemberMgmt();
    $group->members()->attach($member->id, ['role' => 'member', 'joined_at' => now()]);

    $this->actingAs($promoted)
        ->delete(route('groups.members.destroy', [$group, $member]))
        ->assertRedirect();

    $this->assertDatabaseMissing('group_memberships', [
        'neighborhood_group_id' => $group->id,
        'user_id' => $member->id,
    ]);
});

test('the owner cannot be removed via member management', function () {
    $owner = makeVoterForMemberMgmt();
    $group = makeGroupForMemberMgmt($owner);
    $promoted = makeVoterForMemberMgmt();
    $group->members()->attach($promoted->id, ['role' => 'admin', 'joined_at' => now()]);

    $this->actingAs($promoted)
        ->delete(route('groups.members.destroy', [$group, $owner]))
        ->assertStatus(422);

    $this->assertDatabaseHas('group_memberships', [
        'neighborhood_group_id' => $group->id,
        'user_id' => $owner->id,
    ]);
});

test('regular member cannot remove anyone', function () {
    $owner = makeVoterForMemberMgmt();
    $group = makeGroupForMemberMgmt($owner);
    $memberA = makeVoterForMemberMgmt();
    $group->members()->attach($memberA->id, ['role' => 'member', 'joined_at' => now()]);
    $memberB = makeVoterForMemberMgmt();
    $group->members()->attach($memberB->id, ['role' => 'member', 'joined_at' => now()]);

    $this->actingAs($memberA)
        ->delete(route('groups.members.destroy', [$group, $memberB]))
        ->assertForbidden();
});
