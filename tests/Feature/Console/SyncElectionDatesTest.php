<?php

use App\Models\StateElectionDate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('fails fast when the votesmart api key is not configured', function () {
    config(['services.votesmart.api_key' => null]);

    $this->artisan('elections:sync-dates', ['--state' => 'CA'])
        ->assertExitCode(1);
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
