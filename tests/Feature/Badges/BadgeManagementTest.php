<?php

use App\Models\Politician;
use App\Models\PoliticianTopic;
use App\Models\ProfileBadge;
use App\Models\User;
use App\Models\Voter;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'voter',      'guard_name' => 'web']);
    \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'politician', 'guard_name' => 'web']);
});

// ── Helpers ───────────────────────────────────────────────────────────────────

function makeVoterUser(): User
{
    $user = User::factory()->create(['platform' => 'standalone']);
    $user->assignRole('voter');
    Voter::factory()->create(['user_id' => $user->id]);
    skipOnboarding($user, 'voter');
    return $user->load('voter');
}

function makePoliticianUser(): User
{
    $user = User::factory()->create(['platform' => 'standalone']);
    $user->assignRole('politician');
    Politician::factory()->create(['user_id' => $user->id]);
    skipOnboarding($user, 'politician');
    return $user->load('politician');
}

function seedTopic(string $name = 'Healthcare', string $slug = 'healthcare'): PoliticianTopic
{
    return PoliticianTopic::create([
        'name'             => $name,
        'slug'             => $slug,
        'icon'             => '🏥',
        'sort_order'       => 0,
        'is_active'        => true,
        'voter_selectable' => true,
        'auto_earned_only' => false,
    ]);
}

// ── Voter — self-declare badge ────────────────────────────────────────────────

test('voter can add a badge to their profile', function () {
    $user  = makeVoterUser();
    $voter = $user->voter;
    $topic = seedTopic();

    $response = $this->actingAs($user)
        ->post(route('voter.badges.store', $topic->id));

    $response->assertRedirect();

    $this->assertDatabaseHas('profile_badges', [
        'badgeable_type' => Voter::class,
        'badgeable_id'   => $voter->id,
        'topic_id'       => $topic->id,
        'badge_type'     => 'self_declared',
    ]);
});

test('voter cannot add a badge for an inactive topic', function () {
    $user  = makeVoterUser();
    $topic = seedTopic();
    $topic->update(['is_active' => false]);

    // Web routes redirect back with session errors on validation failure
    $this->actingAs($user)
        ->post(route('voter.badges.store', $topic->id))
        ->assertRedirect();

    $this->assertDatabaseMissing('profile_badges', ['topic_id' => $topic->id]);
});

test('voter cannot add a badge for an auto_earned_only topic', function () {
    $user  = makeVoterUser();
    $topic = seedTopic();
    $topic->update(['auto_earned_only' => true, 'voter_selectable' => false]);

    $this->actingAs($user)
        ->post(route('voter.badges.store', $topic->id))
        ->assertRedirect();

    $this->assertDatabaseMissing('profile_badges', ['topic_id' => $topic->id]);
});

test('voter adding duplicate badge is idempotent — returns success without 500', function () {
    $user  = makeVoterUser();
    $topic = seedTopic();

    $this->actingAs($user)->post(route('voter.badges.store', $topic->id));
    $response = $this->actingAs($user)->post(route('voter.badges.store', $topic->id));

    $response->assertRedirect();
    expect(ProfileBadge::count())->toBe(1);
});

test('voter can remove a self-declared badge', function () {
    $user  = makeVoterUser();
    $voter = $user->voter;
    $topic = seedTopic();
    $voter->addBadge($topic->id);

    $response = $this->actingAs($user)
        ->delete(route('voter.badges.destroy', $topic->id));

    $response->assertRedirect();

    $this->assertDatabaseMissing('profile_badges', [
        'badgeable_id' => $voter->id,
        'topic_id'     => $topic->id,
    ]);
});

test('guest cannot add a badge', function () {
    $topic = seedTopic();

    $this->post(route('voter.badges.store', $topic->id))
        ->assertRedirect(route('login'));
});

// ── Voter — badge visibility ─────────────────────────────────────────────────

test('newly self-declared voter badge defaults to private', function () {
    $user  = makeVoterUser();
    $topic = seedTopic();

    $this->actingAs($user)->post(route('voter.badges.store', $topic->id));

    $this->assertDatabaseHas('profile_badges', [
        'topic_id'   => $topic->id,
        'badge_type' => 'self_declared',
        'is_public'  => false,
    ]);
});

test('voter can update a self-declared badge visibility to public', function () {
    $user  = makeVoterUser();
    $voter = $user->voter;
    $topic = seedTopic();
    $voter->addBadge($topic->id);

    $response = $this->actingAs($user)
        ->put(route('voter.badges.visibility', $topic->id), ['is_public' => 1]);

    $response->assertRedirect();
    $this->assertDatabaseHas('profile_badges', [
        'badgeable_id' => $voter->id,
        'topic_id'     => $topic->id,
        'is_public'    => true,
    ]);
});

test('voter can update a self-declared badge visibility back to private', function () {
    $user  = makeVoterUser();
    $voter = $user->voter;
    $topic = seedTopic();
    $voter->addBadge($topic->id, 'self_declared', ['is_public' => true]);

    $response = $this->actingAs($user)
        ->put(route('voter.badges.visibility', $topic->id), ['is_public' => 0]);

    $response->assertRedirect();
    $this->assertDatabaseHas('profile_badges', [
        'badgeable_id' => $voter->id,
        'topic_id'     => $topic->id,
        'is_public'    => false,
    ]);
});

test('voter cannot update visibility of an earned badge', function () {
    $user  = makeVoterUser();
    $voter = $user->voter;
    $topic = seedTopic();
    $voter->grantEarnedBadge($topic->id, 'earned_views', 5);

    $response = $this->actingAs($user)
        ->put(route('voter.badges.visibility', $topic->id), ['is_public' => 0]);

    $response->assertRedirect()->assertSessionHasErrors('badge');
    $this->assertDatabaseHas('profile_badges', [
        'badgeable_id' => $voter->id,
        'topic_id'     => $topic->id,
        'is_public'    => true,
    ]);
});

test('guest cannot update badge visibility', function () {
    $topic = seedTopic();

    $this->put(route('voter.badges.visibility', $topic->id), ['is_public' => 1])
        ->assertRedirect(route('login'));
});

// ── Politician — self-declare badge ──────────────────────────────────────────

test('politician can add a badge to their profile', function () {
    $user       = makePoliticianUser();
    $politician = $user->politician;
    $topic      = seedTopic();

    $response = $this->actingAs($user)
        ->post(route('politician.badges.store', $topic->id));

    $response->assertRedirect();

    $this->assertDatabaseHas('profile_badges', [
        'badgeable_type' => Politician::class,
        'badgeable_id'   => $politician->id,
        'topic_id'       => $topic->id,
    ]);
});

test('politician can remove a badge', function () {
    $user       = makePoliticianUser();
    $politician = $user->politician;
    $topic      = seedTopic();
    $politician->addBadge($topic->id);

    $this->actingAs($user)
        ->delete(route('politician.badges.destroy', $topic->id))
        ->assertRedirect();

    $this->assertDatabaseMissing('profile_badges', [
        'badgeable_id' => $politician->id,
        'topic_id'     => $topic->id,
    ]);
});

// ── Public profile badges ─────────────────────────────────────────────────────

test('public politician profile is accessible and badge exists in DB', function () {
    $politician = Politician::factory()->create([
        'slug'          => 'a3f9b-governor-jane-doe-ca',
        'page_published'=> true,
    ]);
    $topic = seedTopic();
    $politician->addBadge($topic->id);

    // Badge is persisted correctly for the politician
    $this->assertDatabaseHas('profile_badges', [
        'badgeable_type' => Politician::class,
        'badgeable_id'   => $politician->id,
        'topic_id'       => $topic->id,
    ]);

    // Public profile page is reachable
    $this->get(route('politician.public.show', $politician->slug))
         ->assertOk();
});

test('a badge flipped to private no longer renders on the public profile page', function () {
    $politician = Politician::factory()->create([
        'slug' => 'a3f9b-governor-jane-doe-ca',
        'page_published' => true,
    ]);
    // A distinctive topic name avoids false matches against unrelated page
    // content (e.g. FEC/OpenSecrets donor industry-sector labels) that could
    // coincidentally contain a common word like "Healthcare".
    $topic = seedTopic('ZzzBadgeVisibilityTopic', 'zzz-badge-visibility-topic');
    $politician->addBadge($topic->id, 'self_declared', ['is_public' => true]);

    // ?refresh=1 bypasses the controller's 15-min guest page cache
    // (profile.page.{id}) so each request reflects live badge state.
    $this->get(route('politician.public.show', ['slug' => $politician->slug, 'refresh' => 1]))
        ->assertOk()
        ->assertSee($topic->name);

    $politician->setBadgeVisibility($topic->id, false);

    $this->get(route('politician.public.show', ['slug' => $politician->slug, 'refresh' => 1]))
        ->assertOk()
        ->assertDontSee($topic->name);
});
