<?php

use App\Services\GoogleCivicService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Cache::flush();
});

it('returns empty array from listUpcomingElections when service is not configured', function () {
    config(['services.google.civic_api_key' => null]);

    $service = new GoogleCivicService;

    expect($service->listUpcomingElections())->toBe([]);
});

it('normalizes elections by state parsed from ocdDivisionId and infers stage_name', function () {
    config(['services.google.civic_api_key' => 'DEMO_KEY']);

    Http::fake([
        'civicinfo.googleapis.com/civicinfo/v2/elections*' => Http::response([
            'elections' => [
                [
                    'id' => '2000',
                    'name' => 'VIP Test Election',
                    'electionDay' => '2031-12-06',
                    'ocdDivisionId' => 'ocd-division/country:us',
                ],
                [
                    'id' => '9428',
                    'name' => 'Wyoming Primary Election',
                    'electionDay' => '2026-08-18',
                    'ocdDivisionId' => 'ocd-division/country:us/state:wy',
                ],
                [
                    'id' => '11263',
                    'name' => 'Pennsylvania Special Election - State House District 12',
                    'electionDay' => '2026-08-18',
                    'ocdDivisionId' => 'ocd-division/country:us/state:pa',
                ],
            ],
        ], 200),
    ]);

    $stages = (new GoogleCivicService)->listUpcomingElections();

    expect($stages)->toHaveCount(2) // nationwide "country:us" entry is skipped
        ->and($stages[0])->toBe([
            'state' => 'WY',
            'stage_name' => 'Primary',
            'election_date' => '2026-08-18',
            'civic_election_id' => '9428',
        ])
        ->and($stages[1])->toBe([
            'state' => 'PA',
            'stage_name' => 'Special',
            'election_date' => '2026-08-18',
            'civic_election_id' => '11263',
        ]);
});

it('caches the nationwide elections list so repeated calls do not re-hit the API', function () {
    config(['services.google.civic_api_key' => 'DEMO_KEY']);

    Http::fake([
        'civicinfo.googleapis.com/civicinfo/v2/elections*' => Http::response([
            'elections' => [
                ['id' => '9428', 'name' => 'Wyoming Primary Election', 'electionDay' => '2026-08-18', 'ocdDivisionId' => 'ocd-division/country:us/state:wy'],
            ],
        ], 200),
    ]);

    $service = new GoogleCivicService;
    $service->listUpcomingElections();
    $service->listUpcomingElections();

    Http::assertSentCount(1);
});

it('returns empty array when the API call fails', function () {
    config(['services.google.civic_api_key' => 'DEMO_KEY']);

    Http::fake([
        'civicinfo.googleapis.com/civicinfo/v2/elections*' => Http::response([], 500),
    ]);

    expect((new GoogleCivicService)->listUpcomingElections())->toBe([]);
});

it('voterInfoQuery flattens the state → local_jurisdiction admin-body chain and filters referendums', function () {
    config(['services.google.civic_api_key' => 'DEMO_KEY']);

    Http::fake([
        'civicinfo.googleapis.com/civicinfo/v2/voterinfo*' => Http::response([
            'election' => [
                'id' => '9468',
                'name' => 'Delaware Primary Election',
                'electionDay' => '2026-09-15',
                'ocdDivisionId' => 'ocd-division/country:us/state:de',
            ],
            'contests' => [
                ['type' => 'General', 'office' => 'Governor'],
                [
                    'type' => 'Referendum',
                    'referendumTitle' => 'Measure A',
                    'referendumText' => 'Shall the county issue bonds?',
                    'referendumUrl' => 'https://elections.newcastlede.gov/measure-a',
                    'referendumBallotResponses' => ['Yes', 'No'],
                    'district' => ['name' => 'New Castle County', 'scope' => 'countywide'],
                ],
            ],
            'state' => [[
                'name' => 'Delaware',
                'electionAdministrationBody' => [
                    'name' => 'Delaware Department of Elections',
                    'electionInfoUrl' => 'https://elections.delaware.gov',
                ],
                'local_jurisdiction' => [
                    'name' => 'New Castle County',
                    'id' => 'ocd-division/country:us/state:de/county:new_castle',
                    'electionAdministrationBody' => [
                        'name' => 'New Castle County Board of Elections',
                        'electionInfoUrl' => 'https://elections.newcastlede.gov',
                        'ballotInfoUrl' => 'https://elections.newcastlede.gov/whats-on-my-ballot',
                    ],
                ],
            ]],
        ], 200),
    ]);

    $result = (new GoogleCivicService)->voterInfoQuery('Wilmington, DE', '9468');

    expect($result['election']['id'])->toBe('9468')
        ->and($result['admin_bodies'])->toHaveCount(2)
        ->and($result['admin_bodies'][0]['scope'])->toBe('state')
        ->and($result['admin_bodies'][0]['name'])->toBe('Delaware Department of Elections')
        ->and($result['admin_bodies'][1]['scope'])->toBe('local')
        ->and($result['admin_bodies'][1]['ocd_id'])->toBe('ocd-division/country:us/state:de/county:new_castle')
        ->and($result['admin_bodies'][1]['ballot_info_url'])->toBe('https://elections.newcastlede.gov/whats-on-my-ballot')
        ->and($result['referendums'])->toHaveCount(1)
        ->and($result['referendums'][0]['title'])->toBe('Measure A')
        ->and($result['referendums'][0]['url'])->toBe('https://elections.newcastlede.gov/measure-a');
});

it('voterInfoQuery returns null on a 400 (no VIP feed for that address/election)', function () {
    config(['services.google.civic_api_key' => 'DEMO_KEY']);

    Http::fake([
        'civicinfo.googleapis.com/civicinfo/v2/voterinfo*' => Http::response(['error' => ['message' => 'Election unknown']], 400),
    ]);

    expect((new GoogleCivicService)->voterInfoQuery('Nowhere, ZZ', '1'))->toBeNull();
});

it('voterInfoQuery returns null when the service is not configured', function () {
    config(['services.google.civic_api_key' => null]);

    expect((new GoogleCivicService)->voterInfoQuery('Wilmington, DE'))->toBeNull();
});
