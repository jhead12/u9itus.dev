<?php

use App\Models\CandidateLead;
use App\Models\ElectionCandidateRecord;
use App\Models\Politician;
use App\Services\CandidateDiscovery\CandidateLeadPromoter;
use App\Support\CrossStateImpostors;
use App\Support\RaceCalendar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(fn () => Cache::flush());

function raceRecord(string $name, string $state, string $office, string $source = ElectionCandidateRecord::DISCOVERY_SOURCE): ElectionCandidateRecord
{
    $senate = str_contains($office, 'Senat');

    return ElectionCandidateRecord::create([
        'source' => $source,
        'external_candidate_id' => $source.':'.strtolower($state).':'.str($office)->slug().':'.str($name)->slug(),
        'full_name' => $name, 'political_office' => $office,
        'governance_level' => $senate ? 'federal' : 'state',
        'state' => $state, 'party_affiliation' => 'Republican',
        'election_date' => '2026-11-03',
        // Survives the map's "unverified discovery" gate, so only the rule under test can hide it.
        'payload' => ['primary_result' => 'advanced_to_general', 'status' => 'running'],
    ]);
}

function racePolitician(string $name, string $state, string $office, array $over = []): Politician
{
    return Politician::factory()->create(array_merge([
        'full_name' => $name, 'state' => $state, 'political_office' => $office,
        'governance_level' => 'state', 'term_status' => 'seated', 'is_active' => true,
        'slug' => str($name)->slug().'-'.fake()->unique()->numerify('####'),
    ], $over));
}

/** @return array<int, string> */
function raceMapNames(string $state, string $office): array
{
    $offices = test()->getJson('/api/v1/map/state-candidates?state='.$state)->assertOk()->json('offices');
    $group = collect($offices)->firstWhere('office', $office);

    return collect($group['candidates'] ?? [])->pluck('full_name')->all();
}

it('knows which states hold a Senate or Governor race in 2026', function () {
    expect(RaceCalendar::held('NY', 'U.S. Senator', 2026))->toBeFalse()
        ->and(RaceCalendar::held('TX', 'U.S. Senate', 2026))->toBeTrue()
        ->and(RaceCalendar::held('NY', 'Governor', 2026))->toBeTrue()
        ->and(RaceCalendar::held('NC', 'Governor', 2026))->toBeFalse()
        // Not covered: no evidence either way.
        ->and(RaceCalendar::held('NY', 'Attorney General', 2026))->toBeNull()
        ->and(RaceCalendar::held('AR', 'Arkansas Senate District 5', 2026))->toBeNull()
        ->and(RaceCalendar::held('NY', 'Lieutenant Governor', 2026))->toBeNull()
        ->and(RaceCalendar::held('NY', 'U.S. Senator', 2030))->toBeNull();
});

it('places a same-race record in the state where the person has a profile and that race', function () {
    racePolitician('Ken Paxton', 'TX', 'Attorney General');
    raceRecord('Ken Paxton', 'TX', 'U.S. Senator');
    $footprint = CrossStateImpostors::raceFootprint();

    expect(CrossStateImpostors::sameRaceElsewhere('Ken Paxton', 'U.S. Senator', 'NY', $footprint))->toBe('TX')
        ->and(CrossStateImpostors::sameRaceElsewhere('Ken Paxton', 'U.S. Senator', 'TX', $footprint))->toBeNull()
        // A different race in another state is not evidence: people move between offices.
        ->and(CrossStateImpostors::sameRaceElsewhere('Ken Paxton', 'Governor', 'NY', $footprint))->toBeNull()
        ->and(CrossStateImpostors::sameRaceElsewhere('Jane Nobody', 'U.S. Senator', 'NY', $footprint))->toBeNull();
});

it('prunes discovery records for a race the state is not holding, or that belongs to another state', function () {
    raceRecord('Tammy Murphy', 'NY', 'U.S. Senator');                 // NY holds no 2026 Senate race
    racePolitician('Eric Swalwell', 'CA', 'U.S. Representative', ['term_status' => 'lost', 'is_active' => false]);
    raceRecord('Eric Swalwell', 'CA', 'Governor');
    raceRecord('Eric Swalwell', 'NY', 'Governor');                     // NY holds one, but his is in CA
    raceRecord('Kathy Hochul', 'NY', 'Governor');                      // a real NY candidate
    raceRecord('Chuck Schumer', 'NY', 'U.S. Senator', 'manual');       // non-discovery: never pruned by these rules

    $this->artisan('politicians:prune-junk-ecrs', ['--apply' => true, '--no-dedup' => true])->assertExitCode(0);

    expect(ElectionCandidateRecord::pluck('full_name', 'id')->values()->sort()->values()->all())
        ->toBe(['Chuck Schumer', 'Eric Swalwell', 'Kathy Hochul'])
        ->and(ElectionCandidateRecord::where('full_name', 'Eric Swalwell')->value('state'))->toBe('CA');
});

it('hides those discovery records on the map before the next prune', function () {
    // Inactive, so his attorney-general card doesn't claim him before the Texas Senate record
    // (the map lists each person once); the profile still marks Texas as his home state.
    racePolitician('Ken Paxton', 'TX', 'Attorney General', ['is_active' => false]);
    raceRecord('Ken Paxton', 'TX', 'U.S. Senator');
    raceRecord('Ken Paxton', 'NY', 'U.S. Senator');
    raceRecord('Tammy Murphy', 'NY', 'U.S. Senator');
    raceRecord('Kathy Hochul', 'NY', 'Governor');

    expect(raceMapNames('NY', 'U.S. Senators'))->toBe([])
        ->and(raceMapNames('NY', 'Governor'))->toBe(['Kathy Hochul'])
        ->and(raceMapNames('TX', 'U.S. Senators'))->toBe(['Ken Paxton']);
});

it('rejects a discovery lead for a race the state is not holding', function () {
    $lead = CandidateLead::create([
        'source_key' => 'rss_google_news', 'full_name' => 'Tammy Murphy', 'state' => 'NY',
        'office_hint' => 'U.S. Senate', 'source_url' => 'https://news.example/murphy',
        'source_hash' => hash('sha256', 'murphy'), 'discovered_at' => now(),
        'status' => CandidateLead::STATUS_VERIFIED, 'confidence' => 0.95,
        'verified_payload' => ['political_office' => 'U.S. Senate', 'governance_level' => 'federal'],
    ]);

    expect(app(CandidateLeadPromoter::class)->promote($lead))->toBeNull()
        ->and($lead->fresh()->status)->toBe(CandidateLead::STATUS_REJECTED)
        ->and($lead->fresh()->reason)->toContain('no-race')
        ->and(ElectionCandidateRecord::count())->toBe(0);
});

it('flags races outside their control limits and records the count for the health check', function () {
    // A non-discovery row the filters leave alone, in a race NY isn't holding.
    raceRecord('Chuck Schumer', 'NY', 'U.S. Senator', 'manual');
    raceRecord('Kathy Hochul', 'NY', 'Governor', 'manual');
    // Texas Governor: Ballotpedia expects 2, the map shows 6.
    DB::table('governor_race_candidate_counts')->insert([
        'state' => 'TX', 'election_year' => 2026, 'expected_count' => 2, 'source' => 'ballotpedia_manual',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    foreach (['Greg Abbott', 'Gina Hinojosa', 'Andrew White', 'Bobby Cole', 'Chris Bell', 'Benjamin Flores'] as $name) {
        raceRecord($name, 'TX', 'Governor', 'manual');
    }

    $report = storage_path('framework/testing/race-control.json');
    $this->artisan('politicians:race-count-control', ['--state' => ['NY', 'TX'], '--no-wikipedia' => true, '--report' => $report])
        ->expectsOutputToContain('out of control')
        ->assertExitCode(0);

    $races = collect(json_decode(file_get_contents($report), true)['races'])->keyBy(fn ($r) => $r['state'].' '.$r['office']);
    expect($races['NY senate']['verdict'])->toBe('no_race')
        ->and($races['NY senate']['expected'])->toBe(0)
        ->and($races['TX Governor']['verdict'])->toBe('over')
        ->and($races['TX Governor']['reference'])->toBe('ballotpedia')
        ->and($races['TX Governor']['measured'])->toBe(6)
        ->and($races['NY Governor']['verdict'])->toBe('in_control');

    $metric = DB::table('politician_cleanup_run_metrics')->where('step', 'race-count-control')->sole();
    expect($metric->findings_count)->toBe(2)
        ->and(json_decode($metric->breakdown, true))->toBe(['no_race' => 1, 'over' => 1]);
});

it('does not record a metric row with --no-record', function () {
    $this->artisan('politicians:race-count-control', ['--state' => ['NY'], '--no-wikipedia' => true, '--no-record' => true])->assertExitCode(0);

    expect(DB::table('politician_cleanup_run_metrics')->count())->toBe(0);
});
