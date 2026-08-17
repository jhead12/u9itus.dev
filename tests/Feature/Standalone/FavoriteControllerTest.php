<?php

use App\Models\Politician;
use App\Models\User;
use App\Models\Voter;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'voter', 'guard_name' => 'web']);
});

// ── Helpers ───────────────────────────────────────────────────────────────────
// Distinct name from Badges\FavoritePoliticianTest::favVoterUser to avoid redeclare.

function idsVoterUser(): User
{
    $user = User::factory()->create(['platform' => 'standalone']);
    $user->assignRole('voter');
    Voter::factory()->create(['user_id' => $user->id]);
    skipOnboarding($user, 'voter');
    return $user->load('voter');
}

function idsPolitician(array $attrs = []): Politician
{
    return Politician::factory()->create(array_merge(['page_published' => true], $attrs));
}

// ── GET /voter/favorites/ids ─────────────────────────────────────────────────

test('ids endpoint returns the voters followed politician ids', function () {
    $user  = idsVoterUser();
    $voter = $user->voter;
    $followed = Politician::factory()->count(2)->create(['page_published' => true]);
    $notFollowed = idsPolitician();

    foreach ($followed as $pol) {
        $voter->favoritePoliticians()->attach($pol->id, ['favorited_at' => now()]);
    }

    $response = $this->actingAs($user)->getJson(route('voter.favorites.ids'));

    $response->assertOk();
    expect($response->json('ids'))
        ->toEqualCanonicalizing($followed->pluck('id')->all())
        ->not->toContain($notFollowed->id);
});

test('ids endpoint returns an empty array for a voter with no follows', function () {
    $user = idsVoterUser();

    $this->actingAs($user)
        ->getJson(route('voter.favorites.ids'))
        ->assertOk()
        ->assertJson(['ids' => []]);
});

test('guest is unauthenticated hitting the ids endpoint', function () {
    // The voter.* route group's auth middleware blocks guests before the
    // controller's own `if (! $voter)` guard is ever reached — that guard
    // exists for an authenticated-but-non-voter user (e.g. a politician
    // account with no Voter record), not for guests.
    $this->getJson(route('voter.favorites.ids'))
        ->assertUnauthorized();
});

// ── store()/destroy() JSON response (favorite-toggle partial requires it) ────

test('store responds with json ok when the request expects json', function () {
    $user       = idsVoterUser();
    $politician = idsPolitician();

    $this->actingAs($user)
        ->postJson(route('voter.favorites.store', $politician->id))
        ->assertOk()
        ->assertJson(['ok' => true]);
});

test('store still redirects for a plain form submission', function () {
    $user       = idsVoterUser();
    $politician = idsPolitician();

    $this->actingAs($user)
        ->post(route('voter.favorites.store', $politician->id))
        ->assertRedirect();
});

test('destroy responds with json ok when the request expects json', function () {
    $user       = idsVoterUser();
    $voter      = $user->voter;
    $politician = idsPolitician();
    $voter->favoritePoliticians()->attach($politician->id, ['favorited_at' => now()]);

    $this->actingAs($user)
        ->deleteJson(route('voter.favorites.destroy', $politician->id))
        ->assertOk()
        ->assertJson(['ok' => true]);
});

// ── Follow button on the public profile page ─────────────────────────────────

test('profile page reports isFavorited=false for a voter who has not followed', function () {
    $user       = idsVoterUser();
    $politician = idsPolitician(['slug' => 'test-not-followed-candidate']);

    $this->actingAs($user)
        ->get(route('politician.public.show', $politician->slug))
        ->assertOk()
        ->assertViewHas('isFavorited', false);
});

test('profile page reports isFavorited=true for a voter who has followed', function () {
    $user       = idsVoterUser();
    $voter      = $user->voter;
    $politician = idsPolitician(['slug' => 'test-followed-candidate']);
    $voter->favoritePoliticians()->attach($politician->id, ['favorited_at' => now()]);

    $this->actingAs($user)
        ->get(route('politician.public.show', $politician->slug))
        ->assertOk()
        ->assertViewHas('isFavorited', true);
});

test('guest sees a sign-in CTA instead of a follow button on the profile page', function () {
    $politician = idsPolitician(['slug' => 'test-guest-candidate']);

    $this->get(route('politician.public.show', $politician->slug))
        ->assertOk()
        ->assertSee('Sign in to follow this candidate');
});
