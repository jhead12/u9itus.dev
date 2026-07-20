<?php

use App\Enums\CivicEventStatus;
use App\Enums\CivicEventType;
use App\Enums\EventRsvpStatus;
use App\Models\Citizen;
use App\Models\CivicEvent;
use App\Models\EventRsvp;
use App\Models\Politician;
use App\Mail\EventHostRsvpMail;
use App\Mail\EventReminderMail;
use App\Models\EventReminder;
use App\Models\PoliticianTopic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    foreach (['citizen', 'politician', 'voter'] as $role) {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    }
});

function makeCitizenHost(): array
{
    $user = User::factory()->create();
    $user->assignRole('citizen');
    $citizen = Citizen::factory()->create(['user_id' => $user->id]);
    skipOnboarding($user, 'citizen');
    return [$user, $citizen];
}

function makePoliticianHost(): array
{
    $user = User::factory()->create();
    $user->assignRole('politician');
    $politician = Politician::factory()->create(['user_id' => $user->id]);
    skipOnboarding($user, 'politician');
    return [$user, $politician];
}

function makeVoter(): array
{
    $user = User::factory()->create();
    $user->assignRole('voter');
    $voter = \App\Models\Voter::factory()->create(['user_id' => $user->id]);
    skipOnboarding($user, 'voter');
    return [$user, $voter];
}

function validEventPayload(array $overrides = []): array
{
    return array_merge([
        'event_type' => CivicEventType::TownHall->value,
        'status' => CivicEventStatus::Published->value,
        'title' => 'Town Hall on Parks',
        'description' => '<p>Join us to discuss the neighborhood parks plan.</p>',
        'location_name' => 'Springfield, IL',
        'venue_name' => 'City Library',
        'address' => '123 Main St',
        'city' => 'Springfield',
        'state' => 'IL',
        'zip' => '62701',
        'latitude' => 39.7817,
        'longitude' => -89.6501,
        'starts_at' => now()->addDay()->format('Y-m-d\\TH:i'),
        'ends_at' => now()->addDay()->addHours(2)->format('Y-m-d\\TH:i'),
        'timezone' => 'America/Chicago',
        'capacity' => 50,
        'rsvp_requires_approval' => false,
        'is_virtual' => false,
        'virtual_url' => null,
        'topics' => [],
    ], $overrides);
}

it('allows a citizen to create and view an event', function (): void {
    [$user, $citizen] = makeCitizenHost();
    $topic = PoliticianTopic::factory()->create();

    $response = $this->actingAs($user)->post(route('citizen.events.store'), validEventPayload([
        'topics' => [$topic->id],
    ]));

    $response->assertRedirect(route('citizen.events.index'));
    $response->assertSessionHas('success');

    $event = CivicEvent::first();
    expect($event)->not->toBeNull();
    expect($event->host_type)->toBe(Citizen::class);
    expect($event->host_id)->toBe($citizen->id);
    expect($event->topics->pluck('id')->toArray())->toContain($topic->id);

    $this->actingAs($user)->get(route('citizen.events.index'))
        ->assertOk()
        ->assertSee($event->title);
});

it('allows a politician to create and update an event', function (): void {
    [$user, $politician] = makePoliticianHost();

    $this->actingAs($user)->post(route('politician.events.store'), validEventPayload())
        ->assertRedirect(route('politician.events.index'));

    $event = $politician->events()->first();
    expect($event)->not->toBeNull();

    $this->actingAs($user)
        ->put(route('politician.events.update', $event), validEventPayload([
            'title' => 'Updated Town Hall',
        ]))
        ->assertRedirect(route('politician.events.index'));

    $event->refresh();
    expect($event->title)->toBe('Updated Town Hall');
});

it('prevents a citizen from editing another hosts event', function (): void {
    [$user, $citizen] = makeCitizenHost();
    [, $other] = makeCitizenHost();

    $event = $other->events()->create(validEventPayload());

    $this->actingAs($user)
        ->get(route('citizen.events.edit', $event))
        ->assertForbidden();
});

it('allows a host to cancel their event', function (): void {
    [$user, $citizen] = makeCitizenHost();
    $event = $citizen->events()->create(validEventPayload());

    $this->actingAs($user)
        ->patch(route('citizen.events.cancel', $event))
        ->assertRedirect();

    $event->refresh();
    expect($event->status->value)->toBe(CivicEventStatus::Cancelled->value);
});

it('lists public events and shows a single event', function (): void {
    [, $citizen] = makeCitizenHost();
    $event = $citizen->events()->create(validEventPayload());

    $this->get(route('events.index'))
        ->assertOk()
        ->assertSee($event->title);

    $this->get(route('events.show', $event->slug))
        ->assertOk()
        ->assertSee($event->title)
        ->assertSee('Add to calendar');
});

it('returns an ICS calendar file', function (): void {
    [, $citizen] = makeCitizenHost();
    $event = $citizen->events()->create(validEventPayload());

    $response = $this->get(route('events.ics', $event->slug));
    if ($response->exception) {
        throw $response->exception;
    }
    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/calendar; charset=utf-8');
    $response->assertSee('BEGIN:VCALENDAR');
    $response->assertSee('SUMMARY:' . $event->title);
});

it('allows a voter to RSVP yes to an event', function (): void {
    [, $citizen] = makeCitizenHost();
    [$voterUser] = makeVoter();
    $event = $citizen->events()->create(validEventPayload());

    $response = $this->actingAs($voterUser)->post(route('events.rsvp', $event->slug), [
        'status' => EventRsvpStatus::Yes->value,
        'guest_count' => 2,
        'notes' => 'Looking forward to it',
    ]);
    if ($response->exception) {
        throw $response->exception;
    }

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $rsvp = EventRsvp::first();
    expect($rsvp->status)->toBe(EventRsvpStatus::Yes);
    expect($rsvp->guest_count)->toBe(2);
    expect($event->fresh()->attendingCount())->toBe(2);
});

it('places later RSVPs on the waitlist when capacity is reached', function (): void {
    [, $citizen] = makeCitizenHost();
    [$firstUser] = makeVoter();
    [$secondUser] = makeVoter();

    $event = $citizen->events()->create(validEventPayload([
        'capacity' => 3,
    ]));

    $this->actingAs($firstUser)->post(route('events.rsvp', $event->slug), [
        'status' => EventRsvpStatus::Yes->value,
        'guest_count' => 3,
    ])->assertSessionHas('success');

    expect($event->fresh()->isFull())->toBeTrue();

    $response = $this->actingAs($secondUser)->post(route('events.rsvp', $event->slug), [
        'status' => EventRsvpStatus::Yes->value,
        'guest_count' => 1,
    ]);

    $response->assertSessionHas('success');

    $secondRsvp = EventRsvp::where('user_id', $secondUser->id)->first();
    expect($secondRsvp->status)->toBe(EventRsvpStatus::Waitlist);
});

it('allows a user to change their RSVP to no', function (): void {
    [, $citizen] = makeCitizenHost();
    [$voterUser] = makeVoter();
    $event = $citizen->events()->create(validEventPayload());

    $this->actingAs($voterUser)->post(route('events.rsvp', $event->slug), [
        'status' => EventRsvpStatus::Yes->value,
        'guest_count' => 2,
    ]);

    $this->actingAs($voterUser)->post(route('events.rsvp', $event->slug), [
        'status' => EventRsvpStatus::No->value,
        'guest_count' => 1,
    ])->assertSessionHas('success');

    expect($event->fresh()->attendingCount())->toBe(0);
});

it('returns events in the map content API', function (): void {
    [, $citizen] = makeCitizenHost();
    $event = $citizen->events()->create(validEventPayload());

    $response = $this->getJson('/api/v1/map/content?south=39&west=-90&north=40&east=-89');

    $response->assertOk();
    $response->assertJsonPath('events.0.id', $event->uuid);
    $response->assertJsonPath('events.0.type', 'event');
});

it('sends 24-hour and 1-hour reminder emails to attendees', function (): void {
    Mail::fake();

    [, $citizen] = makeCitizenHost();
    [$voterUser] = makeVoter();
    $event = $citizen->events()->create(validEventPayload([
        'starts_at' => now()->addHours(24)->format('Y-m-d\\TH:i'),
        'ends_at' => now()->addHours(26)->format('Y-m-d\\TH:i'),
    ]));

    EventRsvp::create([
        'civic_event_id' => $event->id,
        'user_id' => $voterUser->id,
        'status' => EventRsvpStatus::Yes,
        'guest_count' => 1,
    ]);

    Artisan::call('events:send-reminders');

    Mail::assertSent(EventReminderMail::class, fn ($mail) => $mail->event->id === $event->id && $mail->hoursUntilStart === 24);
    expect(EventReminder::where(['civic_event_id' => $event->id, 'user_id' => $voterUser->id, 'hours_before' => 24])->exists())->toBeTrue();
});

it('suppresses duplicate event reminders', function (): void {
    Mail::fake();

    [, $citizen] = makeCitizenHost();
    [$voterUser] = makeVoter();
    $event = $citizen->events()->create(validEventPayload([
        'starts_at' => now()->addHours(1)->format('Y-m-d\\TH:i'),
        'ends_at' => now()->addHours(3)->format('Y-m-d\\TH:i'),
    ]));

    EventRsvp::create([
        'civic_event_id' => $event->id,
        'user_id' => $voterUser->id,
        'status' => EventRsvpStatus::Yes,
        'guest_count' => 1,
    ]);

    EventReminder::create([
        'civic_event_id' => $event->id,
        'user_id' => $voterUser->id,
        'hours_before' => 1,
    ]);

    Artisan::call('events:send-reminders');

    Mail::assertNotSent(EventReminderMail::class);
});

it('notifies the host when someone RSVPs', function (): void {
    Mail::fake();

    [$hostUser, $citizen] = makeCitizenHost();
    $citizen->update(['receipt_email' => $hostUser->email]);
    [$voterUser] = makeVoter();
    $event = $citizen->events()->create(validEventPayload());

    $this->actingAs($voterUser)->post(route('events.rsvp', $event->slug), [
        'status' => EventRsvpStatus::Yes->value,
        'guest_count' => 2,
        'notes' => 'Bringing a friend',
    ]);

    Mail::assertSent(EventHostRsvpMail::class, fn ($mail) => $mail->hasTo($hostUser->email) && $mail->event->id === $event->id);
});
