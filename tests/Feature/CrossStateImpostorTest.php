<?php

use App\Models\CandidateLead;
use App\Models\ElectionCandidateRecord;
use App\Models\Politician;
use App\Models\PoliticianCleanupReview;
use App\Models\User;
use App\Services\CandidateDiscovery\CandidateLeadPromoter;
use App\Support\CrossStateImpostors;
use App\Support\MapCandidateHygiene;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(fn () => Cache::flush());

function abbottTexas(array $over = []): Politician
{
    return Politician::factory()->create(array_merge([
        'full_name' => 'Greg Abbott', 'state' => 'TX', 'political_office' => 'Governor',
        'governance_level' => 'state', 'term_status' => 'seated', 'is_active' => true,
        'slug' => 'abbott-tx-'.fake()->unique()->numerify('####'),
    ], $over));
}

function unclaimedAbbott(string $state, array $over = []): Politician
{
    $p = Politician::factory()->make(array_merge([
        'full_name' => 'Greg Abbott', 'state' => $state, 'political_office' => 'Governor',
        'governance_level' => 'state', 'term_status' => 'running', 'is_running_candidate' => true,
        'is_active' => true, 'user_id' => null, 'verified_official' => false,
        'slug' => 'abbott-'.strtolower($state).'-'.fake()->unique()->numerify('####'),
    ], $over));
    $p->saveQuietly();

    return $p;
}

function leadFor(string $name, string $state): CandidateLead
{
    return CandidateLead::create([
        'source_key' => 'rss_google_news', 'full_name' => $name, 'state' => $state,
        'office_hint' => 'Governor', 'source_url' => 'https://news.example/'.fake()->unique()->slug(),
        'source_hash' => hash('sha256', fake()->unique()->uuid()), 'discovered_at' => now(),
        'status' => CandidateLead::STATUS_VERIFIED, 'confidence' => 0.95,
        'verified_payload' => ['political_office' => 'Governor', 'governance_level' => 'state'],
    ]);
}

function discoveryEcr(string $name, string $state): ElectionCandidateRecord
{
    return ElectionCandidateRecord::create([
        'source' => ElectionCandidateRecord::DISCOVERY_SOURCE,
        'external_candidate_id' => 'disc:'.strtolower($state).':governor:'.str($name)->slug(),
        'full_name' => $name, 'political_office' => 'Governor', 'governance_level' => 'state',
        'state' => $state, 'party_affiliation' => 'Republican',
        'election_date' => now()->addMonths(2)->toDateString(),
        // Survives the map's "unverified discovery" gate, so only the impostor filter can hide it.
        'payload' => ['primary_result' => 'advanced_to_general', 'status' => 'running'],
    ]);
}

it('treats a trailing possessive as headline text and as the same person', function () {
    expect(MapCandidateHygiene::nameProblem("Greg Abbott's"))->not->toBeNull()
        ->and(MapCandidateHygiene::nameProblem('Greg Abbott'))->toBeNull()
        ->and(MapCandidateHygiene::identityKey("Greg Abbott's"))->toBe(MapCandidateHygiene::identityKey('Greg Abbott'))
        ->and(MapCandidateHygiene::nameProblem("Beto O'Rourke"))->toBeNull();
});

it('finds the seated holder of the same statewide office in another state only', function () {
    abbottTexas();
    $holders = CrossStateImpostors::seatedHolders();

    expect(CrossStateImpostors::holderElsewhere('Greg Abbott', 'Governor', 'NC', $holders)['state'])->toBe('TX')
        ->and(CrossStateImpostors::holderElsewhere('Greg Abbott', 'Governor', 'TX', $holders))->toBeNull()
        ->and(CrossStateImpostors::holderElsewhere('Greg Abbott', 'Attorney General', 'NC', $holders))->toBeNull()
        ->and(CrossStateImpostors::holderElsewhere('Greg Abbott', 'Mayor', 'NC', $holders))->toBeNull()
        ->and(CrossStateImpostors::holderElsewhere('Jane Nobody', 'Governor', 'NC', $holders))->toBeNull();
});

it('rejects a discovery lead that is really a sitting governor of another state', function () {
    abbottTexas();
    $lead = leadFor('Greg Abbott', 'NC');

    expect(app(CandidateLeadPromoter::class)->promote($lead))->toBeNull();

    $lead->refresh();
    expect($lead->status)->toBe(CandidateLead::STATUS_REJECTED)
        ->and($lead->reason)->toContain('cross-state: seated Governor in TX')
        ->and(ElectionCandidateRecord::count())->toBe(0);
});

it('still promotes a genuine candidate with an unrelated name', function () {
    abbottTexas();

    expect(app(CandidateLeadPromoter::class)->promote(leadFor('Katie Porter', 'CA')))->not->toBeNull();
});

it('does not create public profiles from impostor or headline-text discovery records', function () {
    abbottTexas();
    discoveryEcr('Greg Abbott', 'NC');
    ElectionCandidateRecord::query()->insert([
        'source' => 'candidate_discovery', 'external_candidate_id' => 'disc:tx:governor:greg-abbotts',
        'full_name' => "Greg Abbott's", 'political_office' => 'Governor', 'governance_level' => 'state',
        'state' => 'TX', 'created_at' => now(), 'updated_at' => now(),
    ]);
    discoveryEcr('Katie Porter', 'CA');
    // A genuine candidate is corroborated by a record from a non-news source.
    ElectionCandidateRecord::query()->insert([
        'source' => 'ballotpedia', 'external_candidate_id' => 'katie-porter-ca-gov',
        'full_name' => 'Katie Porter', 'political_office' => 'Governor', 'governance_level' => 'state',
        'state' => 'CA', 'created_at' => now(), 'updated_at' => now(),
    ]);

    $before = Politician::count();
    $this->artisan('politicians:reconcile-missing-profiles')->assertExitCode(0);

    expect(Politician::count())->toBe($before + 1)
        ->and(Politician::where('full_name', 'Katie Porter')->exists())->toBeTrue()
        ->and(Politician::where('state', 'NC')->exists())->toBeFalse();
});

it('keeps the impostor off the map governor list', function () {
    abbottTexas();
    discoveryEcr('Greg Abbott', 'NC');
    discoveryEcr('Katie Porter', 'NC');

    $json = $this->getJson('/api/v1/map/state-candidates?state=NC')->assertOk()->json();
    $names = collect($json['offices'])->firstWhere('office', 'Governor')['candidates'] ?? [];
    $names = collect($names)->pluck('full_name')->all();

    expect($names)->toContain('Katie Porter')->not->toContain('Greg Abbott')
        ->and($json['quality']['cross_state'])->toBe(1);
});

it('shows the same row when nobody seated holds that office elsewhere', function () {
    discoveryEcr('Greg Abbott', 'NC');

    $json = $this->getJson('/api/v1/map/state-candidates?state=NC')->assertOk()->json();
    $names = collect(collect($json['offices'])->firstWhere('office', 'Governor')['candidates'] ?? [])->pluck('full_name')->all();

    expect($names)->toContain('Greg Abbott')->and($json['quality']['cross_state'])->toBe(0);
});

it('deactivates confirmed impostors and mangled duplicates automatically, and queues other headline names', function () {
    $real = abbottTexas();
    $nc = unclaimedAbbott('NC');
    $ny = unclaimedAbbott('NY');
    $possessive = unclaimedAbbott('TX', ['full_name' => "Greg Abbott's"]);
    $agenda = unclaimedAbbott('NY', ['full_name' => 'Hochul Agenda', 'political_office' => 'Governor', 'slug' => 'hochul-agenda']);
    $claimed = unclaimedAbbott('FL', ['user_id' => User::factory()->create()->id]);
    $verified = unclaimedAbbott('OH', ['verified_official' => true]);
    $honest = unclaimedAbbott('CA', ['full_name' => 'Katie Porter']);

    // Report-only by default.
    $this->artisan('politicians:flag-suspect-profiles')->expectsOutputToContain('WOULD DEACTIVATE')->assertExitCode(0);
    expect(PoliticianCleanupReview::count())->toBe(0)
        ->and($nc->refresh()->is_active)->toBeTrue();

    $this->artisan('politicians:flag-suspect-profiles', ['--apply' => true])->assertExitCode(0);
    $this->artisan('politicians:flag-suspect-profiles', ['--apply' => true])->assertExitCode(0); // idempotent

    // Confirmed against the sitting official → unpublished, with an approved audit row.
    foreach ([$nc, $ny, $possessive] as $row) {
        $row->refresh();
        expect($row->is_active)->toBeFalse()->and($row->page_published)->toBeFalse();
        $review = PoliticianCleanupReview::where('politician_id', $row->id)->sole();
        expect($review->status)->toBe('approved')
            ->and($review->payload['auto_approved'])->toBeTrue()
            ->and($review->payload['kept_politician_id'])->toBe($real->id);
    }
    expect(PoliticianCleanupReview::where('politician_id', $nc->id)->value('reason'))->toContain('TX');

    // A headline-shaped name with no official to confirm it against is only queued.
    expect($agenda->refresh()->is_active)->toBeTrue()
        ->and(PoliticianCleanupReview::where('politician_id', $agenda->id)->sole()->status)->toBe('pending');

    // Real, claimed, verified and unrelated profiles are untouched.
    foreach ([$real, $claimed, $verified, $honest] as $row) {
        expect($row->refresh()->is_active)->toBeTrue()
            ->and(PoliticianCleanupReview::where('politician_id', $row->id)->exists())->toBeFalse();
    }
});

it('recognises an incumbent stored as "running" as the holder when the profile is verified', function () {
    abbottTexas(['term_status' => 'running', 'is_running_candidate' => true, 'verified_official' => true]);
    $nc = unclaimedAbbott('NC');

    $this->artisan('politicians:flag-suspect-profiles', ['--apply' => true])->assertExitCode(0);

    expect($nc->refresh()->is_active)->toBeFalse();
});

it('resolves an earlier pending review instead of leaving a duplicate behind', function () {
    abbottTexas();
    $nc = unclaimedAbbott('NC');
    PoliticianCleanupReview::enqueue(PoliticianCleanupReview::TYPE_DEACTIVATE, $nc->id, null, ['source' => 'flag-suspect-profiles'], 'queued earlier');

    $this->artisan('politicians:flag-suspect-profiles', ['--apply' => true])->assertExitCode(0);

    $review = PoliticianCleanupReview::where('politician_id', $nc->id)->sole();
    expect($review->status)->toBe('approved')->and($review->payload['auto_approved'])->toBeTrue();
});

it('caps automatic deactivations per run and queues the overflow', function () {
    abbottTexas();
    $rows = collect(['NC', 'NY', 'FL'])->map(fn ($st) => unclaimedAbbott($st));

    $this->artisan('politicians:flag-suspect-profiles', ['--apply' => true, '--max-auto' => 2])->assertExitCode(0);

    expect($rows->filter(fn ($r) => ! $r->refresh()->is_active))->toHaveCount(2)
        ->and(PoliticianCleanupReview::where('status', 'pending')->count())->toBe(1);
});

it('limits the flag command to the requested state', function () {
    abbottTexas();
    $nc = unclaimedAbbott('NC');
    $ny = unclaimedAbbott('NY');

    $this->artisan('politicians:flag-suspect-profiles', ['--apply' => true, '--state' => 'NC'])->assertExitCode(0);

    expect($nc->refresh()->is_active)->toBeFalse()->and($ny->refresh()->is_active)->toBeTrue();
});

it('flags headline words the rules do not list when they follow a sitting official\'s surname', function () {
    Politician::factory()->create([
        'full_name' => 'Kathy Hochul', 'state' => 'NY', 'political_office' => 'Governor', 'governance_level' => 'state',
        'term_status' => 'seated', 'is_active' => true, 'slug' => 'kathy-hochul-'.fake()->unique()->numerify('####'),
    ]);
    $stub = unclaimedAbbott('NY', ['full_name' => 'Hochul Budget', 'slug' => 'hochul-budget']);
    $stranger = unclaimedAbbott('NY', ['full_name' => 'Elise Stefanik', 'slug' => 'elise-stefanik']);
    $elsewhere = unclaimedAbbott('CA', ['full_name' => 'Hochul Budget', 'slug' => 'hochul-budget-ca']);

    $this->artisan('politicians:flag-suspect-profiles', ['--apply' => true])->assertExitCode(0);

    $review = PoliticianCleanupReview::where('politician_id', $stub->id)->sole();
    expect($review->status)->toBe('pending')            // a hint, so a person decides
        ->and($review->reason)->toContain('Kathy Hochul')
        ->and($stub->refresh()->is_active)->toBeTrue()
        ->and(PoliticianCleanupReview::where('politician_id', $stranger->id)->exists())->toBeFalse()
        ->and(PoliticianCleanupReview::where('politician_id', $elsewhere->id)->exists())->toBeFalse(); // only that state's official
});

it('recognises headline words as junk names but leaves real names alone', function (string $name, bool $junk) {
    $violation = \App\Support\PoliticianDataRules::headlineWordViolation($name);

    expect($violation !== null)->toBe($junk)
        ->and(\App\Support\MapCandidateHygiene::nameProblem($name) !== null)->toBe($junk);
})->with([
    'agenda' => ['Hochul Agenda', true],
    'statewide' => ['Hochul Statewide', true],
    'unprecedented' => ['Hochul Unprecedented', true],
    'news label + title' => ['UPDATE Lt. Gov. Anthony', true],
    'trailing modal' => ['Marsha Blackburn Will', true],
    'shouted label' => ['BREAKING Jane Smith', true],
    'George Will' => ['George Will', false],
    'Kim Won' => ['Kim Won', false],
    'Kathy Hochul' => ['Kathy Hochul', false],
    'Al Green' => ['Al Green', false],
    'Jamie Raskin' => ['Jamie Raskin', false],
    'JD initials' => ['J. D. Vance', false],
]);

it('repairs a state-and-title prefix on a real official\'s name', function (string $raw, string $expected) {
    expect(\App\Support\PoliticianNameRepairer::repair($raw)['name'])->toBe($expected);
})->with([
    ['Oklahoma Gov. Kevin Stitt', 'Kevin Stitt'],
    ['Pa. Rep. Patty Kim', 'Patty Kim'],
    ['New York Gov. Kathy Hochul', 'Kathy Hochul'],
    ['Al Green', 'Al Green'],
    ['Jamie Raskin', 'Jamie Raskin'],
]);

it('does not queue a profile for deactivation when its name can simply be repaired', function () {
    $stitt = unclaimedAbbott('OK', ['full_name' => 'Oklahoma Gov. Kevin Stitt', 'slug' => 'stitt-junk']);

    $this->artisan('politicians:flag-suspect-profiles', ['--apply' => true])->assertExitCode(0);

    expect(PoliticianCleanupReview::where('politician_id', $stitt->id)->exists())->toBeFalse()
        ->and($stitt->refresh()->is_active)->toBeTrue();
});

function seatedSenator(string $state, array $over = []): Politician
{
    return Politician::factory()->create(array_merge([
        'full_name' => 'Bernie Sanders', 'state' => $state, 'political_office' => 'U.S. Senator',
        'governance_level' => 'Federal', 'term_status' => 'seated', 'is_active' => true,
        'slug' => 'sanders-'.strtolower($state).'-'.fake()->unique()->numerify('####'),
    ], $over));
}

/**
 * An unclaimed, discovery-only federal profile — same shape as the Bernie-Sanders-in-Nevada
 * bug: a phantom generated purely from a candidate_discovery row, identity-linked to it.
 */
function discoveryOnlySenatePhantom(string $state, string $name = 'Bernie Sanders'): Politician
{
    $p = unclaimedAbbott($state, [
        'full_name' => $name, 'political_office' => 'U.S. Senator', 'governance_level' => 'Federal',
        'slug' => 'senate-phantom-'.strtolower($state).'-'.fake()->unique()->numerify('####'),
    ]);

    $ecr = ElectionCandidateRecord::create([
        'source' => ElectionCandidateRecord::DISCOVERY_SOURCE,
        'external_candidate_id' => 'disc:'.strtolower($state).':senate:'.str($name)->slug().'-'.fake()->unique()->numerify('####'),
        'full_name' => $name, 'political_office' => 'U.S. Senator', 'governance_level' => 'Federal',
        'state' => $state, 'party_affiliation' => 'Independent',
        'election_date' => now()->addMonths(2)->toDateString(),
        'payload' => [],
    ]);
    \App\Models\CandidateIdentityLink::create([
        'politician_id' => $p->id, 'election_candidate_record_id' => $ecr->id,
        'match_score' => 1.0, 'link_source' => 'system', 'linked_at' => now(),
    ]);

    return $p;
}

it('federalNameCollisionElsewhere only fires for federal offices and returns the sitting state', function () {
    $byName = CrossStateImpostors::seatedStatesByName();

    expect(CrossStateImpostors::federalNameCollisionElsewhere('Bernie Sanders', 'NV', 'U.S. Senator', $byName))->toBeNull();

    seatedSenator('VT');
    $byName = CrossStateImpostors::seatedStatesByName();

    expect(CrossStateImpostors::federalNameCollisionElsewhere('Bernie Sanders', 'NV', 'U.S. Senator', $byName))->toBe('VT')
        ->and(CrossStateImpostors::federalNameCollisionElsewhere('Bernie Sanders', 'VT', 'U.S. Senator', $byName))->toBeNull()
        ->and(CrossStateImpostors::federalNameCollisionElsewhere('Bernie Sanders', 'NV', 'Governor', $byName))->toBeNull()
        ->and(CrossStateImpostors::federalNameCollisionElsewhere('Bernie Sanders', 'NV', 'State Senate District 5', $byName))->toBeNull();
});

it('queues (never auto-deactivates) a federal name-collision phantom, and never flags it once corroborated', function () {
    seatedSenator('VT');
    $phantom = discoveryOnlySenatePhantom('NV');

    $this->artisan('politicians:flag-suspect-profiles', ['--apply' => true])->assertExitCode(0);

    expect($phantom->refresh()->is_active)->toBeTrue(); // queue-only, never auto-deactivated
    $review = PoliticianCleanupReview::where('politician_id', $phantom->id)->sole();
    expect($review->status)->toBe('pending')
        ->and($review->payload['source'])->toBe('flag-suspect-profiles-federal-collision')
        ->and($review->payload['collision_state'])->toBe('VT');

    // A different state, same name, but a non-news record now corroborates a real NV candidacy:
    // no finding at all.
    ElectionCandidateRecord::query()->insert([
        'source' => 'ballotpedia', 'external_candidate_id' => 'bernie-nv-senate',
        'full_name' => 'Bernie Sanders', 'political_office' => 'U.S. Senator', 'governance_level' => 'Federal',
        'state' => 'NV', 'created_at' => now(), 'updated_at' => now(),
    ]);
    PoliticianCleanupReview::query()->delete();

    $this->artisan('politicians:flag-suspect-profiles', ['--apply' => true])->assertExitCode(0);

    expect(PoliticianCleanupReview::where('politician_id', $phantom->id)->exists())->toBeFalse();
});

it('retires an earlier pending review once the name has been repaired, so approving it cannot unpublish a real profile', function () {
    $stitt = unclaimedAbbott('OK', ['full_name' => 'Kevin Stitt', 'slug' => 'stitt-clean']);
    $stale = PoliticianCleanupReview::enqueue(PoliticianCleanupReview::TYPE_DEACTIVATE, $stitt->id, null, ['source' => 'flag-suspect-profiles'], 'Name is headline text');
    $genuine = unclaimedAbbott('OK', ['full_name' => 'Hochul Agenda', 'slug' => 'still-junk']);
    $pending = PoliticianCleanupReview::enqueue(PoliticianCleanupReview::TYPE_DEACTIVATE, $genuine->id, null, ['source' => 'flag-suspect-profiles'], 'Name is headline text');

    $this->artisan('politicians:flag-suspect-profiles', ['--apply' => true])
        ->expectsOutputToContain('Retired 1 earlier review')
        ->assertExitCode(0);

    expect($stale->refresh()->status)->toBe('rejected')
        ->and($stale->reason)->toContain('No longer suspect')
        ->and($pending->refresh()->status)->toBe('pending');
});
