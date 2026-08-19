<?php

use App\Services\GoogleCivicService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Cache::flush();
});

it('returns empty array from listUpcomingElections when service is not configured', function () {
    config(['services.google.civic_api_key' => null]);

    $service = new GoogleCivicService();

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

    $stages = (new GoogleCivicService())->listUpcomingElections();

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

    $service = new GoogleCivicService();
    $service->listUpcomingElections();
    $service->listUpcomingElections();

    Http::assertSentCount(1);
});

it('returns empty array when the API call fails', function () {
    config(['services.google.civic_api_key' => 'DEMO_KEY']);

    Http::fake([
        'civicinfo.googleapis.com/civicinfo/v2/elections*' => Http::response([], 500),
    ]);

    expect((new GoogleCivicService())->listUpcomingElections())->toBe([]);
});
