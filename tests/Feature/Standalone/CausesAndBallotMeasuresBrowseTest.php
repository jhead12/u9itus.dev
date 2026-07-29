<?php

use App\Models\BallotMeasure;
use App\Models\Cause;
use App\Models\PoliticianTopic;
use App\Models\User;
use App\Models\Voter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'voter', 'guard_name' => 'web']);
});

function causeVoterUser(string $state): User
{
    $user = User::factory()->create(['platform' => 'standalone']);
    $user->assignRole('voter');
    Voter::factory()->create(['user_id' => $user->id, 'state' => $state, 'is_verified' => true, 'is_active' => true]);
    skipOnboarding($user, 'voter');

    return $user->load('voter');
}

function browseTestTopic(): PoliticianTopic
{
    return PoliticianTopic::factory()->create();
}

// ── Causes directory + show ───────────────────────────────────────────────────

test('voter can browse the causes directory', function () {
    $user = causeVoterUser('CA');
    $topic = browseTestTopic();
    Cause::create(['topic_id' => $topic->id, 'title' => 'Expand Medicaid in CA', 'state' => 'CA', 'status' => 'active']);

    $res = $this->actingAs($user)->get(route('voter.causes.index'));

    $res->assertOk()->assertSee('Causes')->assertSee('Expand Medicaid in CA');
});

test('cause show page shows nearby supporter count from a peer in the same state', function () {
    $topic = browseTestTopic();
    $cause = Cause::create(['topic_id' => $topic->id, 'title' => 'Fund Public Schools', 'state' => 'CA', 'status' => 'active']);

    $other = causeVoterUser('CA'); // peer in CA
    $other->voter->favoriteCauses()->attach($cause->id, ['favorited_at' => now()]);

    $user = causeVoterUser('CA'); // the viewer, also CA

    $res = $this->actingAs($user)->get(route('voter.causes.show', $cause));

    $res->assertOk()->assertSee('1 supporter near you')->assertSee('1 follower total');
});

test('cause show with no other in-state supporters invites being first', function () {
    $topic = browseTestTopic();
    $cause = Cause::create(['topic_id' => $topic->id, 'title' => 'Clean Water LA', 'state' => 'CA', 'status' => 'active']);

    $user = causeVoterUser('TX'); // different state — no peers near the CA cause

    $res = $this->actingAs($user)->get(route('voter.causes.show', $cause));

    $res->assertOk()->assertSee('Be the first supporter near you');
});

test('favoriting a cause as the only in-state voter does not inflate your own nearby count', function () {
    $topic = browseTestTopic();
    $cause = Cause::create(['topic_id' => $topic->id, 'title' => 'Local Parks', 'state' => 'CA', 'status' => 'active']);

    $user = causeVoterUser('CA');
    $user->voter->favoriteCauses()->attach($cause->id, ['favorited_at' => now()]);

    $res = $this->actingAs($user)->get(route('voter.causes.show', $cause));

    $res->assertOk()->assertSee('Be the first supporter near you'); // self excluded
});

test('national cause show page shows both near-you and total follower counts', function () {
    $topic = browseTestTopic();
    $cause = Cause::create(['topic_id' => $topic->id, 'title' => 'National Housing Act', 'state' => null, 'status' => 'active']);

    $viewer = causeVoterUser('CA');
    $caPeer = causeVoterUser('CA');
    $nyPeer = causeVoterUser('NY');
    $viewer->voter->favoriteCauses()->attach($cause->id, ['favorited_at' => now()]);
    $caPeer->voter->favoriteCauses()->attach($cause->id, ['favorited_at' => now()]);
    $nyPeer->voter->favoriteCauses()->attach($cause->id, ['favorited_at' => now()]);

    $res = $this->actingAs($viewer)->get(route('voter.causes.show', $cause));

    // near-you is state-scoped (CA) and peer-excluded → 1 (the CA peer); total counts all 3 favoriters nationwide.
    $res->assertOk()->assertSee('1 supporter near you')->assertSee('3 followers total');
});

test('voter can favorite and unfavorite a cause via JSON endpoints', function () {
    $topic = browseTestTopic();
    $cause = Cause::create(['topic_id' => $topic->id, 'title' => 'Transit Now', 'state' => 'CA', 'status' => 'active']);
    $user = causeVoterUser('CA');

    $store = $this->actingAs($user)->postJson(route('voter.causes.store', $cause->id));
    $store->assertOk()->assertJson(['ok' => true, 'created' => true]);
    $this->assertDatabaseHas('voter_favorite_causes', ['voter_id' => $user->voter->id, 'cause_id' => $cause->id]);

    $destroy = $this->actingAs($user)->deleteJson(route('voter.causes.destroy', $cause->id));
    $destroy->assertOk()->assertJson(['ok' => true, 'deleted' => true]);
    $this->assertDatabaseMissing('voter_favorite_causes', ['voter_id' => $user->voter->id, 'cause_id' => $cause->id]);
});

// ── Ballot measures directory + show ──────────────────────────────────────────

test('ballot measure directory defaults to the voters state', function () {
    $ca = BallotMeasure::create([
        'state' => 'CA', 'county' => 'Los Angeles', 'measure_number' => 'Prop 1',
        'title' => 'CA Transit Bond', 'summary' => 'x', 'status' => 'upcoming',
        'election_date' => now()->addMonth()->toDateString(),
    ]);
    $oh = BallotMeasure::create([
        'state' => 'OH', 'county' => 'Franklin', 'measure_number' => 'Issue 9',
        'title' => 'OH Parks Levy', 'summary' => 'x', 'status' => 'upcoming',
        'election_date' => now()->addMonth()->toDateString(),
    ]);

    $user = causeVoterUser('CA');

    $res = $this->actingAs($user)->get(route('voter.ballot-measures.index'));

    $res->assertOk()->assertSee('CA Transit Bond')->assertDontSee('OH Parks Levy');
});

test('ballot measure show page renders yes / no meaning', function () {
    $measure = BallotMeasure::create([
        'state' => 'CA', 'county' => 'Los Angeles', 'measure_number' => 'Prop 5',
        'title' => 'Bond for Schools', 'summary' => 'A bond measure.',
        'yes_meaning' => 'You support the bond.', 'no_meaning' => 'You oppose the bond.',
        'status' => 'upcoming', 'election_date' => now()->addMonth()->toDateString(),
    ]);

    $user = causeVoterUser('CA');

    $res = $this->actingAs($user)->get(route('voter.ballot-measures.show', $measure));

    $res->assertOk()->assertSee('YES vote means')->assertSee('NO vote means');
});

test('ballot measure show page shows both near-you and total follower counts', function () {
    $measure = BallotMeasure::create([
        'state' => 'CA', 'county' => 'Los Angeles', 'measure_number' => 'Prop 11',
        'title' => 'Climate Bond', 'summary' => 'x', 'status' => 'upcoming',
        'election_date' => now()->addMonth()->toDateString(),
    ]);

    $viewer = causeVoterUser('CA');
    $caPeer = causeVoterUser('CA');
    $nyPeer = causeVoterUser('NY');
    $caPeer->voter->favoriteBallotMeasures()->attach($measure->id, ['favorited_at' => now()]);
    $nyPeer->voter->favoriteBallotMeasures()->attach($measure->id, ['favorited_at' => now()]);

    $res = $this->actingAs($viewer)->get(route('voter.ballot-measures.show', $measure));

    // near-you is state-scoped (CA), peer-excluded → 1 (the CA peer); total counts both favoriters.
    $res->assertOk()->assertSee('1 supporter near you')->assertSee('2 followers total');
});

test('voter can favorite a ballot measure via JSON endpoint', function () {
    $measure = BallotMeasure::create([
        'state' => 'CA', 'county' => 'Los Angeles', 'measure_number' => 'Prop 7',
        'title' => 'Climate Bond', 'summary' => 'x', 'status' => 'upcoming',
        'election_date' => now()->addMonth()->toDateString(),
    ]);
    $user = causeVoterUser('CA');

    $res = $this->actingAs($user)->postJson(route('voter.ballot-measures.store', $measure->id));

    $res->assertOk()->assertJson(['ok' => true, 'created' => true]);
    $this->assertDatabaseHas('voter_favorite_ballot_measures', ['voter_id' => $user->voter->id, 'ballot_measure_id' => $measure->id]);
});