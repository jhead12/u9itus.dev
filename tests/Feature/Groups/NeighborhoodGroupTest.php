<?php

use App\Models\Citizen;
use App\Models\NeighborhoodGroup;
use App\Models\Politician;
use App\Models\User;
use App\Models\Voter;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Spatie caches role/permission assignments; RefreshDatabase resets
    // auto-increment IDs between tests, so without this a freshly-created
    // user can inherit another test's stale cached role for the same ID.
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'voter', 'guard_name' => 'web']);
    \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'citizen', 'guard_name' => 'web']);
    \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'politician', 'guard_name' => 'web']);
});

// ── Helpers ───────────────────────────────────────────────────────────────────

function makeVoterForGroups(): User
{
    // user_type must match the assigned Spatie role — CheckUserRole's
    // repair logic (app/Services/UserRoleService.php) re-grants whatever
    // role `user_type` names, so a mismatch silently adds an extra role.
    $user = User::factory()->create(['platform' => 'standalone', 'user_type' => 'voter']);
    $user->assignRole('voter');
    Voter::factory()->create(['user_id' => $user->id]);
    skipOnboarding($user, 'voter');

    return $user->load('voter');
}

function makeCitizenForGroups(): User
{
    $user = User::factory()->create(['platform' => 'standalone', 'user_type' => 'citizen']);
    $user->assignRole('citizen');
    Citizen::factory()->create(['user_id' => $user->id]);
    skipOnboarding($user, 'citizen');

    return $user->load('citizen');
}

function makePoliticianForGroups(): User
{
    $user = User::factory()->create(['platform' => 'standalone', 'user_type' => 'politician']);
    $user->assignRole('politician');
    Politician::factory()->create(['user_id' => $user->id]);
    skipOnboarding($user, 'politician');

    return $user->load('politician');
}

// ── Creation ──────────────────────────────────────────────────────────────────

test('voter can create a neighborhood group', function () {
    $user = makeVoterForGroups();

    $response = $this->actingAs($user)->post(route('groups.store'), [
        'name' => 'Riverside Neighbors',
        'city' => 'Riverside',
        'state' => 'CA',
    ]);

    $group = NeighborhoodGroup::firstWhere('name', 'Riverside Neighbors');
    $response->assertRedirect(route('groups.public.show', $group));

    $this->assertDatabaseHas('neighborhood_groups', [
        'name' => 'Riverside Neighbors',
        'admin_user_id' => $user->id,
    ]);
    $this->assertDatabaseHas('group_memberships', [
        'neighborhood_group_id' => $group->id,
        'user_id' => $user->id,
        'role' => 'admin',
    ]);
});

test('citizen can create a neighborhood group', function () {
    $user = makeCitizenForGroups();

    $response = $this->actingAs($user)->post(route('groups.store'), [
        'name' => 'Downtown Coalition',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('neighborhood_groups', [
        'name' => 'Downtown Coalition',
        'admin_user_id' => $user->id,
    ]);
});

test('politician cannot create a neighborhood group', function () {
    $user = makePoliticianForGroups();

    $this->actingAs($user)
        ->post(route('groups.store'), ['name' => 'Should Not Exist'])
        ->assertForbidden();

    $this->assertDatabaseMissing('neighborhood_groups', ['name' => 'Should Not Exist']);
});

test('guest cannot create a neighborhood group', function () {
    $this->post(route('groups.store'), ['name' => 'Should Not Exist'])
        ->assertRedirect(route('login'));
});

test('group creation generates a unique slug for identically named groups', function () {
    $userA = makeVoterForGroups();
    $userB = makeVoterForGroups();

    $this->actingAs($userA)->post(route('groups.store'), ['name' => 'Main Street', 'city' => 'Springfield']);
    $this->actingAs($userB)->post(route('groups.store'), ['name' => 'Main Street', 'city' => 'Springfield']);

    $slugs = NeighborhoodGroup::where('name', 'Main Street')->pluck('slug');
    expect($slugs)->toHaveCount(2);
    expect($slugs[0])->not->toBe($slugs[1]);
});

// ── Membership ────────────────────────────────────────────────────────────────

test('voter can join a group', function () {
    $admin = makeVoterForGroups();
    $group = NeighborhoodGroup::create(['name' => 'Test Group', 'admin_user_id' => $admin->id]);
    $group->members()->attach($admin->id, ['role' => 'admin', 'joined_at' => now()]);

    $member = makeVoterForGroups();

    $this->actingAs($member)
        ->post(route('groups.join', $group))
        ->assertRedirect();

    $this->assertDatabaseHas('group_memberships', [
        'neighborhood_group_id' => $group->id,
        'user_id' => $member->id,
        'role' => 'member',
    ]);
});

test('joining twice is idempotent', function () {
    $admin = makeVoterForGroups();
    $group = NeighborhoodGroup::create(['name' => 'Test Group', 'admin_user_id' => $admin->id]);
    $group->members()->attach($admin->id, ['role' => 'admin', 'joined_at' => now()]);

    $member = makeVoterForGroups();

    $this->actingAs($member)->post(route('groups.join', $group));
    $this->actingAs($member)->post(route('groups.join', $group));

    expect($group->members()->where('user_id', $member->id)->count())->toBe(1);
});

test('member can leave a group', function () {
    $admin = makeVoterForGroups();
    $group = NeighborhoodGroup::create(['name' => 'Test Group', 'admin_user_id' => $admin->id]);
    $group->members()->attach($admin->id, ['role' => 'admin', 'joined_at' => now()]);

    $member = makeVoterForGroups();
    $group->members()->attach($member->id, ['role' => 'member', 'joined_at' => now()]);

    $this->actingAs($member)
        ->delete(route('groups.leave', $group))
        ->assertRedirect();

    $this->assertDatabaseMissing('group_memberships', [
        'neighborhood_group_id' => $group->id,
        'user_id' => $member->id,
    ]);
});

test('group admin cannot leave their own group', function () {
    $admin = makeVoterForGroups();
    $group = NeighborhoodGroup::create(['name' => 'Test Group', 'admin_user_id' => $admin->id]);
    $group->members()->attach($admin->id, ['role' => 'admin', 'joined_at' => now()]);

    $this->actingAs($admin)
        ->delete(route('groups.leave', $group))
        ->assertRedirect()
        ->assertSessionHasErrors('group');

    $this->assertDatabaseHas('group_memberships', [
        'neighborhood_group_id' => $group->id,
        'user_id' => $admin->id,
    ]);
});

// ── Public pages ──────────────────────────────────────────────────────────────

test('guest sees the public groups directory', function () {
    $this->get(route('groups.directory'))->assertOk();
});

test('guest sees a public group show page', function () {
    $admin = makeVoterForGroups();
    $group = NeighborhoodGroup::create(['name' => 'Test Group', 'admin_user_id' => $admin->id]);
    $group->members()->attach($admin->id, ['role' => 'admin', 'joined_at' => now()]);

    $this->get(route('groups.public.show', $group))
        ->assertOk()
        ->assertSee('Test Group')
        ->assertSee('1 member');
});

test('unknown group slug returns 404', function () {
    $this->get('/groups/does-not-exist')->assertNotFound();
});

// ── Scope / canonical URL ────────────────────────────────────────────────────

test('group with a scope renders directly at its canonical scoped URL', function () {
    $admin = makeVoterForGroups();
    $group = NeighborhoodGroup::create(['name' => 'Scoped Group', 'scope' => 'District', 'admin_user_id' => $admin->id]);
    $group->members()->attach($admin->id, ['role' => 'admin', 'joined_at' => now()]);

    $this->get("/groups/{$group->slug}/district")
        ->assertOk()
        ->assertSee('Scoped Group')
        ->assertSee('District');
});

test('bare group URL redirects to the canonical scoped URL when a scope is set', function () {
    $admin = makeVoterForGroups();
    $group = NeighborhoodGroup::create(['name' => 'Scoped Group', 'scope' => 'National', 'admin_user_id' => $admin->id]);
    $group->members()->attach($admin->id, ['role' => 'admin', 'joined_at' => now()]);

    $this->get("/groups/{$group->slug}")
        ->assertRedirect("/groups/{$group->slug}/national");
});

test('a stale scope segment redirects to the correct canonical URL', function () {
    $admin = makeVoterForGroups();
    $group = NeighborhoodGroup::create(['name' => 'Scoped Group', 'scope' => 'District', 'admin_user_id' => $admin->id]);
    $group->members()->attach($admin->id, ['role' => 'admin', 'joined_at' => now()]);

    $this->get("/groups/{$group->slug}/state")
        ->assertRedirect("/groups/{$group->slug}/district");
});

test('a group with no scope still renders at the bare URL with no redirect', function () {
    $admin = makeVoterForGroups();
    $group = NeighborhoodGroup::create(['name' => 'Unscoped Group', 'admin_user_id' => $admin->id]);
    $group->members()->attach($admin->id, ['role' => 'admin', 'joined_at' => now()]);

    $this->get("/groups/{$group->slug}")->assertOk();
});

test('directory can be filtered by scope', function () {
    $admin = makeVoterForGroups();
    NeighborhoodGroup::create(['name' => 'District Group', 'scope' => 'District', 'admin_user_id' => $admin->id]);
    NeighborhoodGroup::create(['name' => 'National Group', 'scope' => 'National', 'admin_user_id' => $admin->id]);

    $response = $this->get(route('groups.directory', ['scope' => 'District']));

    $response->assertOk()->assertSee('District Group')->assertDontSee('National Group');
});

// ── Settings (admin only) ────────────────────────────────────────────────────

test('group admin can update group settings', function () {
    $admin = makeVoterForGroups();
    $group = NeighborhoodGroup::create(['name' => 'Old Name', 'city' => 'Old City', 'admin_user_id' => $admin->id]);
    $group->members()->attach($admin->id, ['role' => 'admin', 'joined_at' => now()]);

    $response = $this->actingAs($admin)->put(route('groups.update', $group), [
        'name' => 'New Name',
        'description' => 'Updated description.',
        'city' => 'New City',
        'state' => 'NY',
    ]);

    $response->assertRedirect(route('groups.public.show', $group));
    $this->assertDatabaseHas('neighborhood_groups', [
        'id' => $group->id,
        'name' => 'New Name',
        'city' => 'New City',
        'state' => 'NY',
    ]);
});

test('non-admin member cannot update group settings', function () {
    $admin = makeVoterForGroups();
    $group = NeighborhoodGroup::create(['name' => 'Original', 'admin_user_id' => $admin->id]);
    $group->members()->attach($admin->id, ['role' => 'admin', 'joined_at' => now()]);

    $member = makeVoterForGroups();
    $group->members()->attach($member->id, ['role' => 'member', 'joined_at' => now()]);

    $this->actingAs($member)
        ->put(route('groups.update', $group), ['name' => 'Hijacked'])
        ->assertForbidden();

    $this->assertDatabaseHas('neighborhood_groups', ['id' => $group->id, 'name' => 'Original']);
});

test('unrelated voter cannot update someone else group settings', function () {
    $admin = makeVoterForGroups();
    $group = NeighborhoodGroup::create(['name' => 'Original', 'admin_user_id' => $admin->id]);
    $group->members()->attach($admin->id, ['role' => 'admin', 'joined_at' => now()]);

    $stranger = makeVoterForGroups();

    $this->actingAs($stranger)
        ->get(route('groups.edit', $group))
        ->assertForbidden();
});

test('group admin can delete their group', function () {
    $admin = makeVoterForGroups();
    $group = NeighborhoodGroup::create(['name' => 'Doomed Group', 'admin_user_id' => $admin->id]);
    $group->members()->attach($admin->id, ['role' => 'admin', 'joined_at' => now()]);

    $this->actingAs($admin)
        ->delete(route('groups.destroy', $group))
        ->assertRedirect(route('groups.directory'));

    $this->assertDatabaseMissing('neighborhood_groups', ['id' => $group->id]);
    // Cascade delete: memberships go with it.
    $this->assertDatabaseMissing('group_memberships', ['neighborhood_group_id' => $group->id]);
});

test('non-admin member cannot delete the group', function () {
    $admin = makeVoterForGroups();
    $group = NeighborhoodGroup::create(['name' => 'Protected Group', 'admin_user_id' => $admin->id]);
    $group->members()->attach($admin->id, ['role' => 'admin', 'joined_at' => now()]);

    $member = makeVoterForGroups();
    $group->members()->attach($member->id, ['role' => 'member', 'joined_at' => now()]);

    $this->actingAs($member)
        ->delete(route('groups.destroy', $group))
        ->assertForbidden();

    $this->assertDatabaseHas('neighborhood_groups', ['id' => $group->id]);
});

test('guest cannot reach group settings', function () {
    $admin = makeVoterForGroups();
    $group = NeighborhoodGroup::create(['name' => 'Test Group', 'admin_user_id' => $admin->id]);
    $group->members()->attach($admin->id, ['role' => 'admin', 'joined_at' => now()]);

    $this->get(route('groups.edit', $group))->assertRedirect(route('login'));
});
