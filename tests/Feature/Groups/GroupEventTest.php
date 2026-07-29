<?php

use App\Models\CivicEvent;
use App\Models\NeighborhoodGroup;
use App\Models\User;
use App\Models\Voter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'voter', 'guard_name' => 'web']);
});

function makeVoterForGroupEvents(): User
{
    $user = User::factory()->create(['platform' => 'standalone', 'user_type' => 'voter']);
    $user->assignRole('voter');
    Voter::factory()->create(['user_id' => $user->id]);
    skipOnboarding($user, 'voter');

    return $user->load('voter');
}

function makeGroupForEvents(User $owner): NeighborhoodGroup
{
    $group = NeighborhoodGroup::create(['name' => 'Event Test Group', 'admin_user_id' => $owner->id]);
    $group->members()->attach($owner->id, ['role' => 'admin', 'joined_at' => now()]);

    return $group;
}

function groupEventPayload(array $overrides = []): array
{
    return array_merge([
        'event_type' => 'community_meeting',
        'status' => 'published',
        'title' => 'Monthly Block Meeting',
        'description' => 'Come discuss street safety.',
        'location_name' => 'Springfield, IL',
        'venue_name' => 'Community Center',
        'city' => 'Springfield',
        'state' => 'IL',
        'zip' => '62704',
        'latitude' => 39.78,
        'longitude' => -89.65,
        'starts_at' => now()->addDays(7)->format('Y-m-d\TH:i'),
        'ends_at' => now()->addDays(7)->addHours(2)->format('Y-m-d\TH:i'),
        'timezone' => 'America/Chicago',
        'is_virtual' => '0',
    ], $overrides);
}

test('group admin can create an event', function () {
    $owner = makeVoterForGroupEvents();
    $group = makeGroupForEvents($owner);

    $response = $this->actingAs($owner)->post(route('groups.events.store', $group), groupEventPayload());

    $response->assertRedirect(route('groups.events.index', $group));
    $this->assertDatabaseHas('civic_events', [
        'title' => 'Monthly Block Meeting',
        'host_type' => NeighborhoodGroup::class,
        'host_id' => $group->id,
    ]);
});

test('group admin can create a virtual event with a meeting link', function () {
    $owner = makeVoterForGroupEvents();
    $group = makeGroupForEvents($owner);

    $this->actingAs($owner)->post(route('groups.events.store', $group), groupEventPayload([
        'title' => 'Virtual Kickoff',
        'is_virtual' => '1',
        'virtual_url' => 'https://zoom.us/j/123456789',
    ]))->assertRedirect();

    $this->assertDatabaseHas('civic_events', [
        'title' => 'Virtual Kickoff',
        'is_virtual' => 1,
        'virtual_url' => 'https://zoom.us/j/123456789',
    ]);
});

test('non-admin member cannot create a group event', function () {
    $owner = makeVoterForGroupEvents();
    $group = makeGroupForEvents($owner);
    $member = makeVoterForGroupEvents();
    $group->members()->attach($member->id, ['role' => 'member', 'joined_at' => now()]);

    $this->actingAs($member)
        ->post(route('groups.events.store', $group), groupEventPayload())
        ->assertForbidden();

    $this->assertDatabaseMissing('civic_events', ['host_id' => $group->id, 'host_type' => NeighborhoodGroup::class]);
});

test('outsider cannot create a group event', function () {
    $owner = makeVoterForGroupEvents();
    $group = makeGroupForEvents($owner);
    $outsider = makeVoterForGroupEvents();

    $this->actingAs($outsider)
        ->post(route('groups.events.store', $group), groupEventPayload())
        ->assertForbidden();
});

test('a group event is publicly visible with the group as host', function () {
    $owner = makeVoterForGroupEvents();
    $group = makeGroupForEvents($owner);
    $event = CivicEvent::factory()->create([
        'host_type' => NeighborhoodGroup::class,
        'host_id' => $group->id,
        'title' => 'Public Group Event',
    ]);

    $this->get(route('events.show', $event->slug))
        ->assertOk()
        ->assertSee('Public Group Event')
        ->assertSee($group->name);
});

test('rsvp works against a group-hosted event', function () {
    $owner = makeVoterForGroupEvents();
    $group = makeGroupForEvents($owner);
    $event = CivicEvent::factory()->create([
        'host_type' => NeighborhoodGroup::class,
        'host_id' => $group->id,
    ]);
    $attendee = makeVoterForGroupEvents();

    $this->actingAs($attendee)
        ->post(route('events.rsvp', $event), ['status' => 'yes', 'guest_count' => 1])
        ->assertRedirect();

    $this->assertDatabaseHas('event_rsvps', [
        'civic_event_id' => $event->id,
        'user_id' => $attendee->id,
        'status' => 'yes',
    ]);
});

test('group admin can cancel an event without deleting it', function () {
    Mail::fake();

    $owner = makeVoterForGroupEvents();
    $group = makeGroupForEvents($owner);
    $event = CivicEvent::factory()->create([
        'host_type' => NeighborhoodGroup::class,
        'host_id' => $group->id,
    ]);

    $this->actingAs($owner)
        ->patch(route('groups.events.cancel', [$group, $event]))
        ->assertRedirect(route('groups.events.index', $group));

    $this->assertDatabaseHas('civic_events', ['id' => $event->id, 'status' => 'cancelled']);
});

test('group event edit is scoped to the correct group', function () {
    $owner = makeVoterForGroupEvents();
    $groupA = makeGroupForEvents($owner);
    $groupB = makeGroupForEvents(makeVoterForGroupEvents());
    $eventOnB = CivicEvent::factory()->create([
        'host_type' => NeighborhoodGroup::class,
        'host_id' => $groupB->id,
    ]);

    // Owner of group A trying to edit an event that belongs to group B, via group A's URL.
    $this->actingAs($owner)
        ->get(route('groups.events.edit', [$groupA, $eventOnB]))
        ->assertNotFound();
});
