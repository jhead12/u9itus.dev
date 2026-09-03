<?php

use App\Models\StateElectionDate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
});

it('fails when neither vote smart nor google civic is configured', function () {
    config(['services.votesmart.api_key' => null, 'services.google.civic_api_key' => null]);

    $this->artisan('elections:sync-dates', ['--state' => 'CA'])
        ->assertExitCode(1);
});

it('falls back to google civic when the votesmart api key is not configured', function () {
    config(['services.votesmart.api_key' => null, 'services.google.civic_api_key' => 'DEMO_KEY']);

    Http::fake([
        'civicinfo.googleapis.com/civicinfo/v2/elections*' => Http::response([
            'elections' => [
                ['id' => '9428', 'name' => 'Wyoming Primary Election', 'electionDay' => '2026-08-18', 'ocdDivisionId' => 'ocd-division/country:us/state:wy'],
            ],
        ], 200),
    ]);

    $this->artisan('elections:sync-dates', ['--year' => 2026, '--state' => 'WY'])
        ->assertExitCode(0);

    $this->assertDatabaseHas('state_election_dates', [
        'state' => 'WY',
        'election_year' => 2026,
        'stage_name' => 'Primary',
        'election_date' => '2026-08-18 00:00:00',
        'civic_election_id' => '9428',
        'source' => 'google_civic',
    ]);
});

it('does not let google civic overwrite a state/stage vote smart already synced this run', function () {
    config(['services.votesmart.api_key' => 'DEMO_KEY', 'services.google.civic_api_key' => 'DEMO_KEY']);

    Http::fake([
        'api.votesmart.org/Election.getElectionByYearState*' => Http::response([
            'elections' => [
                'election' => [
                    'electionId' => '2701',
                    'stage' => ['name' => 'Primary', 'electionDate' => '08/18/2026', 'filingDeadline' => '05/01/2026'],
                ],
            ],
        ], 200),
        'civicinfo.googleapis.com/civicinfo/v2/elections*' => Http::response([
            'elections' => [
                ['id' => '9428', 'name' => 'Wyoming Primary Election', 'electionDay' => '2026-08-18', 'ocdDivisionId' => 'ocd-division/country:us/state:wy'],
            ],
        ], 200),
    ]);

    $this->artisan('elections:sync-dates', ['--year' => 2026, '--state' => 'WY'])
        ->assertExitCode(0);

    // Civic did not add a second Primary row on top of Vote Smart's.
    expect(StateElectionDate::where('state', 'WY')->where('stage_name', 'Primary')->count())->toBe(1);

    $this->assertDatabaseHas('state_election_dates', [
        'state' => 'WY',
        'stage_name' => 'Primary',
        'source' => 'votesmart',
        'filing_deadline' => '2026-05-01 00:00:00',
    ]);
});

it('--skip-votesmart syncs from google civic only even when vote smart is configured', function () {
    config(['services.votesmart.api_key' => 'DEMO_KEY', 'services.google.civic_api_key' => 'DEMO_KEY']);

    Http::fake([
        'api.votesmart.org/Election.getElectionByYearState*' => Http::response([], 500),
        'civicinfo.googleapis.com/civicinfo/v2/elections*' => Http::response([
            'elections' => [
                ['id' => '9428', 'name' => 'Wyoming Primary Election', 'electionDay' => '2026-08-18', 'ocdDivisionId' => 'ocd-division/country:us/state:wy'],
            ],
        ], 200),
    ]);

    $this->artisan('elections:sync-dates', ['--year' => 2026, '--state' => 'WY', '--skip-votesmart' => true])
        ->assertExitCode(0);

    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'api.votesmart.org'));

    $this->assertDatabaseHas('state_election_dates', [
        'state' => 'WY',
        'source' => 'google_civic',
    ]);
});

it('upserts election stages for a single state', function () {
    config(['services.votesmart.api_key' => 'DEMO_KEY']);

    Http::fake([
        'api.votesmart.org/Election.getElectionByYearState*' => Http::response([
            'elections' => [
                'election' => [
                    'electionId' => '2701',
                    'name' => 'California General Election',
                    'stage' => [
                        'name' => 'General',
                        'electionDate' => '11/03/2026',
                        'filingDeadline' => '08/07/2026',
                    ],
                ],
            ],
        ], 200),
    ]);

    $this->artisan('elections:sync-dates', ['--year' => 2026, '--state' => 'CA'])
        ->assertExitCode(0);

    $this->assertDatabaseHas('state_election_dates', [
        'state' => 'CA',
        'election_year' => 2026,
        'stage_name' => 'General',
        'election_date' => '2026-11-03 00:00:00',
        'filing_deadline' => '2026-08-07 00:00:00',
        'votesmart_election_id' => '2701',
    ]);
});

it('re-running the sync updates the same row instead of duplicating it', function () {
    config(['services.votesmart.api_key' => 'DEMO_KEY']);

    Http::fake([
        'api.votesmart.org/Election.getElectionByYearState*' => Http::response([
            'elections' => [
                'election' => [
                    'electionId' => '2701',
                    'stage' => ['name' => 'General', 'electionDate' => '11/03/2026', 'filingDeadline' => '08/07/2026'],
                ],
            ],
        ], 200),
    ]);

    $this->artisan('elections:sync-dates', ['--year' => 2026, '--state' => 'CA'])->assertExitCode(0);
    $this->artisan('elections:sync-dates', ['--year' => 2026, '--state' => 'CA'])->assertExitCode(0);

    expect(StateElectionDate::where('state', 'CA')->where('election_year', 2026)->count())->toBe(1);
});

it('seeds the statutory Nov general date by default for states no live source covered', function () {
    config(['services.votesmart.api_key' => null, 'services.google.civic_api_key' => 'DEMO_KEY']);

    Http::fake([
        'civicinfo.googleapis.com/civicinfo/v2/elections*' => Http::response([
            'elections' => [
                ['id' => '9428', 'name' => 'Wyoming Primary Election', 'electionDay' => '2026-08-18', 'ocdDivisionId' => 'ocd-division/country:us/state:wy'],
            ],
        ], 200),
    ]);

    $this->artisan('elections:sync-dates', ['--year' => 2026])->assertExitCode(0);

    // Every state + DC gets a statutory General row...
    expect(StateElectionDate::where('stage_name', 'General')->where('source', 'statutory')->count())->toBe(51);
    $this->assertDatabaseHas('state_election_dates', [
        'state' => 'CA', 'election_year' => 2026, 'stage_name' => 'General',
        'election_date' => '2026-11-03 00:00:00', 'source' => 'statutory',
    ]);
});

it('--no-statutory turns the backfill off', function () {
    config(['services.votesmart.api_key' => null, 'services.google.civic_api_key' => 'DEMO_KEY']);
    Http::fake(['civicinfo.googleapis.com/civicinfo/v2/elections*' => Http::response(['elections' => []], 200)]);

    $this->artisan('elections:sync-dates', ['--year' => 2026, '--no-statutory' => true])->assertExitCode(0);

    expect(StateElectionDate::where('source', 'statutory')->count())->toBe(0);
});

it('the statutory backfill does not overwrite a General row a live source already wrote', function () {
    config(['services.votesmart.api_key' => 'DEMO_KEY']);

    Http::fake([
        'api.votesmart.org/Election.getElectionByYearState*' => Http::response([
            'elections' => ['election' => [
                'electionId' => '2701',
                'stage' => ['name' => 'General', 'electionDate' => '11/03/2026', 'filingDeadline' => '08/07/2026'],
            ]],
        ], 200),
    ]);

    $this->artisan('elections:sync-dates', ['--year' => 2026, '--state' => 'CA'])->assertExitCode(0);

    $row = StateElectionDate::where(['state' => 'CA', 'stage_name' => 'General'])->sole();
    expect($row->source)->toBe('votesmart')
        ->and(optional($row->filing_deadline)->toDateString())->toBe('2026-08-07');
});

it('the statutory backfill is a no-op in an odd year', function () {
    config(['services.google.civic_api_key' => 'DEMO_KEY']);
    Http::fake(['civicinfo.googleapis.com/civicinfo/v2/elections*' => Http::response(['elections' => []], 200)]);

    $this->artisan('elections:sync-dates', ['--year' => 2025])->assertExitCode(0);

    expect(StateElectionDate::where('source', 'statutory')->count())->toBe(0);
});

it('dry-run does not write to the database', function () {
    config(['services.votesmart.api_key' => 'DEMO_KEY']);

    Http::fake([
        'api.votesmart.org/Election.getElectionByYearState*' => Http::response([
            'elections' => [
                'election' => [
                    'electionId' => '2701',
                    'stage' => ['name' => 'General', 'electionDate' => '11/03/2026', 'filingDeadline' => '08/07/2026'],
                ],
            ],
        ], 200),
    ]);

    $this->artisan('elections:sync-dates', ['--year' => 2026, '--state' => 'CA', '--dry-run' => true])
        ->assertExitCode(0);

    expect(StateElectionDate::count())->toBe(0);
});
