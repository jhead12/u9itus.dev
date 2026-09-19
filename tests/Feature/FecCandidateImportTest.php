<?php

use App\Models\ElectionCandidateRecord;
use App\Models\Politician;
use App\Models\PoliticianCleanupReview;
use App\Models\StateElectionDate;
use App\Models\User;
use App\Support\FecCandidateName;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
    config(['services.fec.api_key' => 'test-key']);
});

function fecFiler(string $id, string $name, array $over = []): array
{
    return array_merge([
        'candidate_id' => $id, 'name' => $name, 'party' => 'DEM', 'party_full' => 'DEMOCRATIC PARTY',
        'state' => 'CA', 'office' => 'H', 'district' => '43', 'candidate_status' => 'C',
        'incumbent_challenge_full' => 'Challenger', 'candidate_inactive' => false,
    ], $over);
}

function fakeFecList(array $rows): void
{
    Http::swap(new HttpFactory); // Http::fake() stacks stubs; start clean so a second call really replaces the first
    Http::fake(['api.open.fec.gov/v1/candidates/*' => Http::response(['results' => $rows, 'pagination' => ['pages' => 1, 'count' => count($rows)]])]);
}

function caRep(string $name, array $over = []): Politician
{
    return Politician::factory()->create(array_merge([
        'full_name' => $name, 'state' => 'CA', 'district' => 'CA-43', 'user_id' => null,
        'governance_level' => 'federal', 'political_office' => 'U.S. Representative',
        'term_status' => 'running', 'is_active' => true, 'fec_candidate_id' => null,
        'slug' => str($name)->slug()->append('-'.fake()->unique()->numerify('####'))->toString(),
    ], $over));
}

it('turns FEC "LAST, FIRST" capitals into a readable name', function (string $fec, string $expected) {
    expect(FecCandidateName::display($fec))->toBe($expected);
})->with([
    ['WATERS, MAXINE MS', 'Maxine Waters'],
    ['BRADFORD, STEVEN', 'Steven Bradford'],
    ['SMITH, JOHN Q JR', 'John Q. Smith Jr.'],
    ["O'BRIEN, PAT", "Pat O'Brien"],
    ['MCCARTHY, KEVIN', 'Kevin McCarthy'],
    ['GARCIA, JOHN, III', 'John Garcia III'],
    ['Cher', 'Cher'],
]);

it('records the FEC id on every matching profile, including nickname duplicates', function () {
    $a = caRep('Steven Bradford');
    $b = caRep('Steve Bradford');
    $other = caRep('Someone Else');
    fakeFecList([fecFiler('H6CA43001', 'BRADFORD, STEVEN')]);

    $this->artisan('politicians:import-fec-candidates', ['--state' => 'CA', '--office' => 'H'])->assertExitCode(0);

    expect($a->fresh()->fec_candidate_id)->toBe('H6CA43001')
        ->and($b->fresh()->fec_candidate_id)->toBe('H6CA43001')
        ->and($other->fresh()->fec_candidate_id)->toBeNull();
});

it('does not link a different chamber or another state, and never overwrites an existing id', function () {
    $senator = caRep('Steven Bradford', ['political_office' => 'U.S. Senator']);
    $texan = caRep('Steven Bradford', ['state' => 'TX']);
    $conflict = caRep('Steven Bradford', ['fec_candidate_id' => 'H0OLD0001']);
    fakeFecList([fecFiler('H6CA43001', 'BRADFORD, STEVEN')]);

    $this->artisan('politicians:import-fec-candidates', ['--state' => 'CA', '--office' => 'H'])
        ->expectsOutputToContain('1 conflict(s)')
        ->assertExitCode(0);

    expect($senator->fresh()->fec_candidate_id)->toBeNull()
        ->and($texan->fresh()->fec_candidate_id)->toBeNull()
        ->and($conflict->fresh()->fec_candidate_id)->toBe('H0OLD0001');
});

it('adds an unknown filer as a record only while the state primary is still ahead', function () {
    StateElectionDate::create(['state' => 'CA', 'election_year' => 2026, 'stage_name' => 'Primary', 'election_date' => now()->addMonth()->toDateString()]);
    fakeFecList([fecFiler('H6CA43154', 'CRISTO, EDEN')]);

    $this->artisan('politicians:import-fec-candidates', ['--state' => 'CA', '--office' => 'H'])->assertExitCode(0);

    $record = ElectionCandidateRecord::where('external_candidate_id', 'H6CA43154')->first();
    expect($record)->not->toBeNull()
        ->and($record->full_name)->toBe('Eden Cristo')
        ->and($record->source)->toBe('fec')
        ->and($record->district)->toBe('CA-43')
        ->and($record->party_affiliation)->toBe('Democratic')
        ->and($record->election_date->toDateString())->toBe(now()->addMonth()->toDateString())
        ->and(Politician::where('full_name', 'Eden Cristo')->exists())->toBeFalse();
});

it('never adds filers whose primary has passed (the FEC cannot say who lost) or has no known date', function () {
    StateElectionDate::create(['state' => 'CA', 'election_year' => 2026, 'stage_name' => 'Primary', 'election_date' => now()->subMonth()->toDateString()]);
    fakeFecList([fecFiler('H6CA43154', 'CRISTO, EDEN')]);
    $this->artisan('politicians:import-fec-candidates', ['--state' => 'CA', '--office' => 'H'])->assertExitCode(0);

    fakeFecList([fecFiler('H6TX01001', 'NODATE, NORA', ['state' => 'TX'])]);
    $this->artisan('politicians:import-fec-candidates', ['--state' => 'TX', '--office' => 'H'])->assertExitCode(0);

    expect(ElectionCandidateRecord::count())->toBe(0);
});

it('makes at-large districts AL and Senate races Statewide', function () {
    StateElectionDate::create(['state' => 'AK', 'election_year' => 2026, 'stage_name' => 'Primary', 'election_date' => now()->addMonth()->toDateString()]);
    fakeFecList([fecFiler('H6AK00001', 'ATLARGE, ANN', ['state' => 'AK', 'district' => '00'])]);
    $this->artisan('politicians:import-fec-candidates', ['--state' => 'AK', '--office' => 'H'])->assertExitCode(0);
    fakeFecList([fecFiler('S6AK00001', 'SENATOR, SAM', ['state' => 'AK', 'office' => 'S', 'district' => '00'])]);
    $this->artisan('politicians:import-fec-candidates', ['--state' => 'AK', '--office' => 'S'])->assertExitCode(0);

    expect(ElectionCandidateRecord::where('external_candidate_id', 'H6AK00001')->value('district'))->toBe('AK-AL')
        ->and(ElectionCandidateRecord::where('external_candidate_id', 'S6AK00001')->value('district'))->toBe('Statewide');
});

it('writes nothing on a dry run and fails cleanly without a key', function () {
    $a = caRep('Steven Bradford');
    fakeFecList([fecFiler('H6CA43001', 'BRADFORD, STEVEN')]);

    $this->artisan('politicians:import-fec-candidates', ['--state' => 'CA', '--office' => 'H', '--dry-run' => true])->assertExitCode(0);
    expect($a->fresh()->fec_candidate_id)->toBeNull();

    config(['services.fec.api_key' => null]);
    $this->artisan('politicians:import-fec-candidates')->assertExitCode(1);
});

it('does not turn an FEC record into a public profile until a results source says it advanced', function () {
    $make = fn (array $payload) => ElectionCandidateRecord::create([
        'source' => 'fec', 'external_candidate_id' => 'H6CA43'.fake()->unique()->numerify('###'),
        'full_name' => 'Eden Cristo', 'political_office' => 'U.S. Representative', 'governance_level' => 'federal',
        'state' => 'CA', 'district' => 'CA-43', 'election_date' => now()->addMonths(3)->toDateString(), 'payload' => $payload,
    ]);
    $make(['status' => 'running']);

    $this->artisan('politicians:reconcile-missing-profiles')->assertExitCode(0);
    expect(Politician::where('full_name', 'Eden Cristo')->exists())->toBeFalse();

    ElectionCandidateRecord::query()->delete();
    $make(['status' => 'running', 'primary_result' => 'advanced_to_general']);

    $this->artisan('politicians:reconcile-missing-profiles')->assertExitCode(0);
    expect(Politician::where('full_name', 'Eden Cristo')->exists())->toBeTrue();
});

// ── politicians:dedupe-by-fec ────────────────────────────────────────────────

function fakeFecRecord(string $id, string $name, string $state = 'CA'): void
{
    Http::swap(new HttpFactory);
    Http::fake(["api.open.fec.gov/v1/candidate/{$id}/*" => Http::response(['results' => [['candidate_id' => $id, 'name' => $name, 'state' => $state]]])]);
}

function bradfordPair(array $loserOver = []): array
{
    $keep = caRep('Steven Bradford', ['fec_candidate_id' => 'H6CA43001', 'verified_official' => true]);
    $dupe = caRep('Steve Bradford', array_merge(['fec_candidate_id' => 'H6CA43001'], $loserOver));

    return [$keep, $dupe];
}

it('reports without changing anything unless --apply is passed', function () {
    [$keep, $dupe] = bradfordPair();
    fakeFecRecord('H6CA43001', 'BRADFORD, STEVEN');

    $this->artisan('politicians:dedupe-by-fec')->expectsOutputToContain('WOULD AUTO-MERGE')->assertExitCode(0);

    expect(Politician::whereKey([$keep->id, $dupe->id])->count())->toBe(2)
        ->and(PoliticianCleanupReview::count())->toBe(0);
});

it('auto-merges a same-FEC-id duplicate that the FEC record confirms, and leaves an approved audit review', function () {
    [$keep, $dupe] = bradfordPair();
    fakeFecRecord('H6CA43001', 'BRADFORD, STEVEN');

    $this->artisan('politicians:dedupe-by-fec', ['--apply' => true])->assertExitCode(0);

    expect(Politician::whereKey($dupe->id)->exists())->toBeFalse()
        ->and(Politician::whereKey($keep->id)->exists())->toBeTrue();

    $review = PoliticianCleanupReview::first();
    expect($review->status)->toBe('approved')
        ->and($review->review_type)->toBe('merge')
        ->and($review->politician_id)->toBe($keep->id)
        ->and($review->payload['auto_approved'])->toBeTrue()
        ->and($review->payload['duplicate']['id'])->toBe($dupe->id)
        ->and($review->reviewed_at)->not->toBeNull();
});

it('queues instead of merging when a safety check fails', function (string $case, callable $arrange, string $expectedReason) {
    [$keep, $dupe] = bradfordPair($arrange($case));
    fakeFecRecord('H6CA43001', $case === 'name' ? 'JONES, DIANA' : 'BRADFORD, STEVEN');

    $this->artisan('politicians:dedupe-by-fec', ['--apply' => true])->assertExitCode(0);

    expect(Politician::whereKey($dupe->id)->exists())->toBeTrue();
    $review = PoliticianCleanupReview::first();
    expect($review->status)->toBe('pending')
        ->and($review->duplicate_politician_id)->toBe($dupe->id)
        ->and($review->payload['why_not_automatic'])->toContain($expectedReason);
})->with([
    'duplicate is a claimed account' => ['claimed', fn () => ['user_id' => User::factory()->create()->id], 'claimed account'],
    'rows in different states' => ['state', fn () => ['state' => 'TX'], 'different states'],
    'names do not match FEC record' => ['name', fn () => [], 'does not match'],
]);

it('queues when the FEC record cannot be fetched', function () {
    [$keep, $dupe] = bradfordPair();
    Http::fake(['api.open.fec.gov/*' => Http::response([], 500)]);

    $this->artisan('politicians:dedupe-by-fec', ['--apply' => true])->assertExitCode(0);

    expect(Politician::whereKey($dupe->id)->exists())->toBeTrue()
        ->and(PoliticianCleanupReview::first()->payload['why_not_automatic'])->toContain('unavailable');
});

it('caps automatic merges per run and queues the overflow', function () {
    foreach (['H6CA43001' => 'Steven Bradford', 'H6CA43002' => 'Maria Lopez'] as $id => $name) {
        caRep($name, ['fec_candidate_id' => $id]);
        caRep($name, ['fec_candidate_id' => $id]);
    }
    Http::swap(new HttpFactory);
    Http::fake([
        'api.open.fec.gov/v1/candidate/H6CA43001/*' => Http::response(['results' => [['name' => 'BRADFORD, STEVEN', 'state' => 'CA']]]),
        'api.open.fec.gov/v1/candidate/H6CA43002/*' => Http::response(['results' => [['name' => 'LOPEZ, MARIA', 'state' => 'CA']]]),
    ]);

    $this->artisan('politicians:dedupe-by-fec', ['--apply' => true, '--max-auto' => 1])->assertExitCode(0);

    expect(PoliticianCleanupReview::where('status', 'approved')->count())->toBe(1)
        ->and(PoliticianCleanupReview::where('status', 'pending')->count())->toBe(1)
        ->and(Politician::count())->toBe(3);
});

// ── middle initials in outside name searches ─────────────────────────────────

it('drops middle initials from search text but keeps initials that are the first name', function (string $name, string $expected) {
    expect(\App\Support\NameSearch::withoutMiddleInitials($name))->toBe($expected);
})->with([
    ['Hakeem S. Jeffries', 'Hakeem Jeffries'],
    ['Hakeem S Jeffries', 'Hakeem Jeffries'],
    ['Kevin J. Lincoln II', 'Kevin Lincoln II'],
    ['John Q. Adams Jr.', 'John Adams Jr.'],
    ['J. D. Vance', 'J. D. Vance'],
    ['Maxine Waters', 'Maxine Waters'],
    ['Cher', 'Cher'],
    ['Charles Patrick Wallis', 'Charles Patrick Wallis'],
]);

it('searches FEC without the middle initial when it has to look an id up by name', function () {
    Http::swap(new HttpFactory);
    Http::fake([
        'api.open.fec.gov/v1/candidates/search/*' => Http::response(['results' => [['candidate_id' => 'H2NY10092']]]),
        'api.open.fec.gov/*' => Http::response(['results' => []]),
    ]);
    $jeffries = caRep('Hakeem S. Jeffries', ['state' => 'NY', 'district' => 'NY-08', 'fec_candidate_id' => null]);

    app(\App\Services\FECService::class)->fetchCandidateFilings($jeffries);

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/candidates/search/')) {
            return false;
        }
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return ($query['q'] ?? null) === 'Hakeem Jeffries';
    });
    expect($jeffries->fresh()->fec_candidate_id)->toBe('H2NY10092');
});
