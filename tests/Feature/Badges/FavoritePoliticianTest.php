<?php

use App\Models\Politician;
use App\Models\User;
use App\Models\Voter;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'voter',      'guard_name' => 'web']);
    \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'politician', 'guard_name' => 'web']);
});

// ── Helpers ───────────────────────────────────────────────────────────────────

function favVoterUser(): User
{
    $user = User::factory()->create(['platform' => 'standalone']);
    $user->assignRole('voter');
    Voter::factory()->create(['user_id' => $user->id]);
    skipOnboarding($user, 'voter');
    return $user->load('voter');
}

function favPolitician(array $attrs = []): Politician
{
    return Politician::factory()->create(array_merge(['page_published' => true], $attrs));
}

// ── Add favorite ─────────────────────────────────────────────────────────────

test('voter can favorite a politician', function () {
    $user       = favVoterUser();
    $voter      = $user->voter;
    $politician = favPolitician();

    $response = $this->actingAs($user)
        ->post(route('voter.favorites.store', $politician->id));

    $response->assertRedirect();

    $this->assertDatabaseHas('voter_favorite_politicians', [
        'voter_id'      => $voter->id,
        'politician_id' => $politician->id,
    ]);
});

test('favoriting the same politician twice does not create duplicate', function () {
    $user       = favVoterUser();
    $voter      = $user->voter;
    $politician = favPolitician();

    $this->actingAs($user)->post(route('voter.favorites.store', $politician->id));
    $this->actingAs($user)->post(route('voter.favorites.store', $politician->id));

    expect(\DB::table('voter_favorite_politicians')
        ->where('voter_id', $voter->id)
        ->where('politician_id', $politician->id)
        ->count()
    )->toBe(1);
});

test('guest cannot favorite a politician', function () {
    $politician = favPolitician();

    $this->post(route('voter.favorites.store', $politician->id))
        ->assertRedirect(route('login'));
});

// ── Remove favorite ───────────────────────────────────────────────────────────

test('voter can unfavorite a politician', function () {
    $user       = favVoterUser();
    $voter      = $user->voter;
    $politician = favPolitician();

    $voter->favoritePoliticians()->attach($politician->id, ['favorited_at' => now()]);

    $response = $this->actingAs($user)
        ->delete(route('voter.favorites.destroy', $politician->id));

    $response->assertRedirect();

    $this->assertDatabaseMissing('voter_favorite_politicians', [
        'voter_id'      => $voter->id,
        'politician_id' => $politician->id,
    ]);
});

// ── List favorites ────────────────────────────────────────────────────────────

test('voter can view their favorites list', function () {
    $user       = favVoterUser();
    $voter      = $user->voter;
    $politician = favPolitician(['full_name' => 'Governor Jane Smith']);

    $voter->favoritePoliticians()->attach($politician->id, ['favorited_at' => now()]);

    // Verify the relationship is wired correctly
    expect($user->voter->favoritePoliticians()->count())->toBe(1);

    $this->actingAs($user)
        ->get(route('voter.favorites.index'))
        ->assertSuccessful();
});

test('favorites list is empty for a new voter', function () {
    $user = favVoterUser();

    $this->actingAs($user)
        ->get(route('voter.favorites.index'))
        ->assertSuccessful();
});

// ── Politician supporter count ────────────────────────────────────────────────

test('politician profile shows supporter count', function () {
    $politician = favPolitician([
        'slug'           => 'b2c3d-governor-jane-doe-tx',
        'full_name'      => 'Governor Jane Doe',
        'page_published' => true,
    ]);

    // 3 voters favorite this politician
    $voters = Voter::factory()->count(3)->create();
    foreach ($voters as $v) {
        \DB::table('voter_favorite_politicians')->insert([
            'voter_id'      => $v->id,
            'politician_id' => $politician->id,
            'favorited_at'  => now(),
        ]);
    }

    $this->get(route('politician.public.show', $politician->slug))
        ->assertOk()
        ->assertSee('3'); // supporter count appears on page
});

// ── Relation direction ────────────────────────────────────────────────────────

test('politician knows which voters have favorited them', function () {
    $politician = favPolitician();
    $voter      = Voter::factory()->create();

    \DB::table('voter_favorite_politicians')->insert([
        'voter_id'      => $voter->id,
        'politician_id' => $politician->id,
        'favorited_at'  => now(),
    ]);

    expect($politician->favoritedByVoters()->count())->toBe(1);
});

test('voter knows which politicians they follow', function () {
    $user       = favVoterUser();
    $voter      = $user->voter;
    $politician = favPolitician();

    $voter->favoritePoliticians()->attach($politician->id, ['favorited_at' => now()]);

    expect($voter->favoritePoliticians()->count())->toBe(1);
});
