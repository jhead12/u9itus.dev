<?php

use App\Models\Politician;
use App\Models\DistrictLookupSearch;
use App\Models\ElectionCandidateRecord;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('district lookup page renders for guests', function () {
    $response = $this->get(route('district.lookup'));

    $response->assertOk();
    $response->assertSee('Find Your District');
});

test('district lookup resolves address and lists matching candidates', function () {
    Http::fake([
        'https://geocoding.geo.census.gov/*' => Http::response([
            'result' => [
                'addressMatches' => [
                    [
                        'matchedAddress' => '1600 PENNSYLVANIA AVE NW, WASHINGTON, DC, 20500',
                        'addressComponents' => [
                            'state' => 'CA',
                        ],
                        'geographies' => [
                            '119th Congressional Districts' => [
                                [
                                    'CD119FP' => '12',
                                    'NAME' => 'Congressional District 12',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], 200),
    ]);

    Politician::factory()->create([
        'full_name' => 'Alex Rivera',
        'state' => 'CA',
        'district' => 'District 12',
        'page_published' => true,
        'is_active' => true,
    ]);

    Politician::factory()->create([
        'full_name' => 'Jordan Hall',
        'state' => 'NY',
        'district' => 'District 07',
        'page_published' => true,
        'is_active' => true,
    ]);

    $response = $this->get(route('district.lookup', [
        'address' => '1600 Pennsylvania Ave NW, Washington, DC 20500',
    ]));

    $response->assertOk();
    $response->assertSee('CA-12');
    $response->assertSee('Alex Rivera');
    $response->assertDontSee('Jordan Hall');
});

test('district lookup shows helpful error when address cannot be resolved', function () {
    Http::fake([
        'https://geocoding.geo.census.gov/*' => Http::response([
            'result' => [
                'addressMatches' => [],
            ],
        ], 200),
    ]);

    $response = $this->get(route('district.lookup', [
        'address' => 'not a real address',
    ]));

    $response->assertOk();
    $response->assertSee('could not resolve that address', false);
});

test('district lookup matches state full name and district code variants', function () {
    Http::fake([
        'https://geocoding.geo.census.gov/*' => Http::response([
            'result' => [
                'addressMatches' => [
                    [
                        'matchedAddress' => 'SAN FRANCISCO, CA',
                        'addressComponents' => [
                            'state' => 'CA',
                        ],
                        'geographies' => [
                            '119th Congressional Districts' => [
                                [
                                    'CD119FP' => '12',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], 200),
    ]);

    Politician::factory()->create([
        'full_name' => 'Taylor Brooks',
        'state' => 'California',
        'district' => 'CA-12',
        'page_published' => true,
        'is_active' => true,
    ]);

    Politician::factory()->create([
        'full_name' => 'Robin Miles',
        'state' => 'CA',
        'district' => 'District 14',
        'page_published' => true,
        'is_active' => true,
    ]);

    $response = $this->get(route('district.lookup', [
        'address' => 'Market St, San Francisco, CA',
    ]));

    $response->assertOk();
    $response->assertSee('Taylor Brooks');
    $response->assertDontSee('Robin Miles');
});

test('district lookup handles at-large districts', function () {
    Http::fake([
        'https://geocoding.geo.census.gov/*' => Http::response([
            'result' => [
                'addressMatches' => [
                    [
                        'matchedAddress' => 'JUNEAU, AK',
                        'addressComponents' => [
                            'state' => 'AK',
                        ],
                        'geographies' => [
                            '119th Congressional Districts' => [
                                [
                                    'CD119FP' => '00',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], 200),
    ]);

    Politician::factory()->create([
        'full_name' => 'Morgan Shaw',
        'state' => 'AK',
        'district' => 'AK-AL',
        'page_published' => true,
        'is_active' => true,
    ]);

    $response = $this->get(route('district.lookup', [
        'address' => 'Juneau, AK',
    ]));

    $response->assertOk();
    $response->assertSee('AK-AL');
    $response->assertSee('Morgan Shaw');
});

test('district lookup without address does not call geocoder', function () {
    Http::fake();

    $response = $this->get(route('district.lookup'));

    $response->assertOk();
    Http::assertNothingSent();
});

test('district lookup falls back to google civic for zip-only searches', function () {
    $officialName = 'Priya Sharma';
    $zip = '92555';
    $state = 'CA';
    $city = 'Moreno Valley';
    $electionDay = '2026-11-03';

    config()->set('services.google.civic_api_key', 'test-key');

    Http::fake([
        'https://geocoding.geo.census.gov/*' => Http::response([
            'result' => [
                'addressMatches' => [],
            ],
        ], 200),
        'https://civicinfo.googleapis.com/civicinfo/v2/divisionsByAddress*' => Http::response([
            'normalizedInput' => [
                'state' => $state,
            ],
            'divisions' => [
                'ocd-division/country:us/state:ca/cd:18' => [
                    'name' => 'California Congressional District 18',
                ],
            ],
        ], 200),
        'https://www.googleapis.com/civicinfo/v2/voterinfo*' => Http::response([
            'election' => [
                'id' => '9000',
                'name' => 'General Election',
                'electionDay' => $electionDay,
            ],
            'normalizedInput' => [
                'line1' => $zip,
                'state' => $state,
                'zip' => $zip,
            ],
            'pollingLocations' => [
                [
                    'name' => 'Community Center',
                    'pollingHours' => '7AM-8PM',
                    'address' => [
                        'line1' => '123 Main St',
                        'city' => $city,
                        'state' => $state,
                        'zip' => $zip,
                    ],
                ],
            ],
            'contests' => [
                [
                    'type' => 'General',
                    'office' => 'United States Representative, District 18',
                    'district' => [
                        'scope' => 'congressional',
                        'id' => '18',
                    ],
                    'candidates' => [
                        [
                            'name' => $officialName,
                            'party' => 'Democratic',
                        ],
                    ],
                ],
            ],
        ], 200),
        'https://www.googleapis.com/civicinfo/v2/representatives*' => Http::response([
            'offices' => [
                [
                    'name' => 'United States House of Representatives',
                    'level' => ['federal'],
                    'divisionId' => 'ocd-division/country:us/state:ca/cd:18',
                    'officialIndices' => [0],
                ],
            ],
            'officials' => [
                [
                    'name' => $officialName,
                    'party' => 'Democratic',
                    'urls' => ['https://example.test/priya'],
                ],
            ],
            'divisions' => [
                'ocd-division/country:us/state:ca/cd:18' => [
                    'name' => 'California Congressional District 18',
                ],
            ],
        ], 200),
    ]);

    $response = $this->get(route('district.lookup', [
        'address' => $zip,
    ]));

    $response->assertOk();
    $response->assertSee('CA-18');
    $response->assertSee($officialName);

    expect(Politician::query()
        ->where('full_name', $officialName)
        ->whereNull('user_id')
        ->where('page_published', true)
        ->exists())->toBeTrue();

    expect(ElectionCandidateRecord::query()
        ->where('source', 'google_civic')
        ->where('full_name', $officialName)
        ->exists())->toBeTrue();

    expect(DistrictLookupSearch::query()
        ->where('query_address', $zip)
        ->where('resolved', true)
        ->where('district_code', 'CA-18')
        ->where('source', 'google_civic')
        ->exists())->toBeTrue();

    $search = DistrictLookupSearch::query()
        ->where('query_address', $zip)
        ->latest('id')
        ->first();

    expect(data_get($search?->payload, 'voter_info.normalized_input.state'))->toBe('CA');
    expect(data_get($search?->payload, 'voter_info.polling_locations.0.name'))->toBe('Community Center');
    expect(data_get($search?->payload, 'voter_info.contests.0.office'))->toBe('United States Representative, District 18');
});

test('district lookup voterinfo picks earliest election from otherElections and re-queries with electionId', function () {
    $zip = '92555';
    $city = 'Moreno Valley';
    $state = 'CA';
    $earliestElectionDay = '2026-11-03';

    config()->set('services.google.civic_api_key', 'test-key');

    Http::fake([
        'https://geocoding.geo.census.gov/*' => Http::response([
            'result' => [
                'addressMatches' => [],
            ],
        ], 200),
        'https://civicinfo.googleapis.com/civicinfo/v2/divisionsByAddress*' => Http::response([
            'normalizedInput' => [
                'state' => $state,
            ],
            'divisions' => [
                'ocd-division/country:us/state:ca/cd:39' => [
                    'name' => "California's 39th congressional district",
                ],
            ],
        ], 200),
        'https://www.googleapis.com/civicinfo/v2/voterinfo*' => function (HttpRequest $request) use ($zip, $city, $state, $earliestElectionDay) {
            $electionId = (string) ($request->data()['electionId'] ?? '');

            if ($electionId === '') {
                return Http::response([
                    'election' => [
                        'id' => '9000',
                        'name' => 'Default Election',
                        'electionDay' => '2028-11-07',
                    ],
                    'normalizedInput' => [
                        'line1' => '14747 Grandview Drive',
                        'city' => $city,
                        'state' => $state,
                        'zip' => $zip,
                    ],
                    'otherElections' => [
                        [
                            'id' => '2000',
                            'name' => 'Later Election',
                            'electionDay' => '2028-11-07',
                        ],
                        [
                            'id' => '1000',
                            'name' => 'Earlier Election',
                            'electionDay' => $earliestElectionDay,
                        ],
                    ],
                ], 200);
            }

            return Http::response([
                'election' => [
                    'id' => $electionId,
                    'name' => 'Earlier Election',
                    'electionDay' => $earliestElectionDay,
                ],
                'normalizedInput' => [
                    'line1' => '14747 Grandview Drive',
                    'city' => $city,
                    'state' => $state,
                    'zip' => $zip,
                ],
                'pollingLocations' => [
                    [
                        'name' => 'Main Voting Center',
                        'address' => [
                            'line1' => '100 Civic Plz',
                            'city' => $city,
                            'state' => $state,
                            'zip' => '92553',
                        ],
                    ],
                ],
                'contests' => [
                    [
                        'office' => 'Mayor',
                        'district' => [
                            'scope' => 'citywide',
                            'id' => '1',
                        ],
                    ],
                ],
            ], 200);
        },
        'https://www.googleapis.com/civicinfo/v2/representatives*' => Http::response([
            'offices' => [],
            'officials' => [],
            'divisions' => [],
        ], 200),
    ]);

    $response = $this->get(route('district.lookup', [
        'address' => '14747 Grandview Dr, Moreno Valley, CA 92555',
    ]));

    $response->assertOk();
    $response->assertSee('CA-39');

    Http::assertSent(function (HttpRequest $request) {
        return str_contains($request->url(), '/voterinfo')
            && ! array_key_exists('electionId', $request->data());
    });

    Http::assertSent(function (HttpRequest $request) {
        return str_contains($request->url(), '/voterinfo')
            && (string) ($request->data()['electionId'] ?? '') === '1000';
    });

    $search = DistrictLookupSearch::query()
        ->where('query_address', '14747 Grandview Dr, Moreno Valley, CA 92555')
        ->latest('id')
        ->first();

    expect(data_get($search?->payload, 'voter_info.selection_reason'))->toBe('earliest_other_election');
    expect(data_get($search?->payload, 'voter_info.selected_election_id'))->toBe('1000');
    expect(data_get($search?->payload, 'voter_info.polling_locations.0.name'))->toBe('Main Voting Center');
    expect(data_get($search?->payload, 'voter_info.contests.0.office'))->toBe('Mayor');
});
