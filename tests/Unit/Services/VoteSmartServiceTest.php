<?php

use App\Services\VoteSmartService;
use Illuminate\Support\Facades\Http;

it('returns empty array from getElectionDates when service is not configured', function () {
    config(['services.votesmart.api_key' => null]);

    $service = new VoteSmartService();

    expect($service->getElectionDates('CA', 2026))->toBe([]);
});

it('parses election dates and filing deadlines when a single stage is returned unwrapped', function () {
    config(['services.votesmart.api_key' => 'DEMO_KEY']);

    // Vote Smart's XML→JSON translation doesn't wrap a single result in an
    // array — same quirk already handled elsewhere in this service
    // (getRatings(), getKeyVotes(), etc.), so a lone stage/election comes
    // back as a plain object instead of a list.
    Http::fake([
        'api.votesmart.org/Election.getElectionByYearState*' => Http::response([
            'elections' => [
                'election' => [
                    'electionId' => '2701',
                    'name' => 'California General Election',
                    'stateId' => 'CA',
                    'electionYear' => '2026',
                    'stage' => [
                        'name' => 'General',
                        'electionDate' => '11/03/2026',
                        'filingDeadline' => '08/07/2026',
                    ],
                ],
            ],
        ], 200),
    ]);

    $service = new VoteSmartService();
    $stages = $service->getElectionDates('CA', 2026);

    expect($stages)->toBe([
        [
            'stage_name' => 'General',
            'election_date' => '2026-11-03',
            'filing_deadline' => '2026-08-07',
            'votesmart_election_id' => '2701',
        ],
    ]);
});

it('parses multiple elections each with multiple stages', function () {
    config(['services.votesmart.api_key' => 'DEMO_KEY']);

    Http::fake([
        'api.votesmart.org/Election.getElectionByYearState*' => Http::response([
            'elections' => [
                'election' => [
                    [
                        'electionId' => '2701',
                        'name' => 'California Primary',
                        'stage' => [
                            ['name' => 'Primary', 'electionDate' => '06/02/2026', 'filingDeadline' => '03/06/2026'],
                            ['name' => 'General', 'electionDate' => '11/03/2026', 'filingDeadline' => '08/07/2026'],
                        ],
                    ],
                ],
            ],
        ], 200),
    ]);

    $service = new VoteSmartService();
    $stages = $service->getElectionDates('CA', 2026);

    expect($stages)->toHaveCount(2);
    expect($stages[0]['stage_name'])->toBe('Primary');
    expect($stages[0]['election_date'])->toBe('2026-06-02');
    expect($stages[1]['stage_name'])->toBe('General');
    expect($stages[1]['election_date'])->toBe('2026-11-03');
});

it('returns empty array when the request fails', function () {
    config(['services.votesmart.api_key' => 'DEMO_KEY']);

    Http::fake([
        'api.votesmart.org/Election.getElectionByYearState*' => Http::response([], 500),
    ]);

    $service = new VoteSmartService();

    expect($service->getElectionDates('CA', 2026))->toBe([]);
});

it('returns empty array when a state has no elections that year', function () {
    config(['services.votesmart.api_key' => 'DEMO_KEY']);

    Http::fake([
        'api.votesmart.org/Election.getElectionByYearState*' => Http::response(['elections' => []], 200),
    ]);

    $service = new VoteSmartService();

    expect($service->getElectionDates('WY', 2026))->toBe([]);
});
