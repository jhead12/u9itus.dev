<?php

use App\Models\Politician;
use App\Models\DistrictLookupSearch;
use App\Models\ElectionCandidateRecord;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;

const DISTRICT_LOOKUP_TEST_CITY = 'Moreno Valley';
const DISTRICT_LOOKUP_TEST_ZIP = '92555';

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
    $response->assertSee('Source: Census Geocoder');
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

test('district lookup shows zip specific guidance when zip cannot resolve district', function () {
    $city = DISTRICT_LOOKUP_TEST_CITY;
    $zip = DISTRICT_LOOKUP_TEST_ZIP;

    config()->set('services.google.civic_api_key', 'test-key');

    Http::fake([
        'https://geocoding.geo.census.gov/*' => Http::response([
            'result' => [
                'addressMatches' => [],
            ],
        ], 200),
        'https://civicinfo.googleapis.com/civicinfo/v2/divisionsByAddress*' => Http::response([
            'normalizedInput' => [
                'city' => $city,
                'state' => 'CA',
                'zip' => $zip,
            ],
            'divisions' => [
                'ocd-division/country:us' => [
                    'name' => 'United States',
                ],
                'ocd-division/country:us/state:ca' => [
                    'name' => 'California',
                ],
                'ocd-division/country:us/state:ca/county:riverside' => [
                    'name' => 'Riverside County',
                ],
            ],
        ], 200),
        'https://www.googleapis.com/civicinfo/v2/voterinfo*' => Http::response([
            'normalizedInput' => [
                'city' => $city,
                'state' => 'CA',
                'zip' => $zip,
            ],
        ], 200),
    ]);

    $response = $this->get(route('district.lookup', [
        'address' => $zip,
    ]));

    $response->assertOk();
    $response->assertSee('could not determine a congressional district from ZIP alone', false);
    $response->assertDontSee('Try including street, city, state, and ZIP', false);
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
    $zip = DISTRICT_LOOKUP_TEST_ZIP;
    $state = 'CA';
    $city = DISTRICT_LOOKUP_TEST_CITY;
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
    $response->assertSee('Source: Google Civic');

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
    expect(data_get($search?->payload, 'found_locations.0.type'))->toBe('polling_location');
    expect(data_get($search?->payload, 'found_locations.0.name'))->toBe('Community Center');
});

test('district lookup includes state assembly profiles using voterinfo district hints', function () {
    config()->set('services.google.civic_api_key', 'test-key');

    Http::fake([
        'https://geocoding.geo.census.gov/*' => Http::response([
            'result' => [
                'addressMatches' => [
                    [
                        'matchedAddress' => '22690 CACTUS AVE, MORENO VALLEY, CA, 92553',
                        'addressComponents' => [
                            'state' => 'CA',
                        ],
                        'geographies' => [
                            '119th Congressional Districts' => [
                                [
                                    'CD119FP' => '39',
                                    'NAME' => 'Congressional District 39',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], 200),
        'https://www.googleapis.com/civicinfo/v2/voterinfo*' => Http::response([
            'election' => [
                'id' => '1000',
                'name' => 'General Election',
                'electionDay' => '2026-11-03',
            ],
            'normalizedInput' => [
                'state' => 'CA',
                'city' => 'Moreno Valley',
            ],
            'contests' => [
                [
                    'office' => 'Member, California State Assembly District 60',
                    'district' => [
                        'scope' => 'stateLower',
                        'id' => '60',
                    ],
                ],
            ],
        ], 200),
        'https://www.googleapis.com/civicinfo/v2/representatives*' => Http::response([
            'offices' => [],
            'officials' => [],
            'divisions' => [],
        ], 200),
    ]);

    Politician::factory()->create([
        'full_name' => 'Dr. Corey A. Jackson',
        'state' => 'CA',
        'district' => 'AD-60',
        'page_published' => true,
        'is_active' => true,
    ]);

    $response = $this->get(route('district.lookup', [
        'address' => '22690 Cactus Ave, Moreno Valley, CA 92553',
    ]));

    $response->assertOk();
    $response->assertSee('Dr. Corey A. Jackson');
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
    expect(data_get($search?->payload, 'found_locations.0.type'))->toBe('polling_location');
    expect(data_get($search?->payload, 'found_locations.0.name'))->toBe('Main Voting Center');
});

    test('district lookup shows all running candidates and top contenders from election records', function () {
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

        ElectionCandidateRecord::factory()->create([
            'full_name' => 'Dana Carter',
            'state' => 'CA',
            'district' => 'CA-12',
            'political_office' => 'U.S. Representative',
            'party_affiliation' => 'Democratic',
            'election_date' => now()->addMonths(2)->toDateString(),
            'payload' => [
                'incumbent' => true,
                'financial_summary' => [
                    'receipts' => '$1,250,000',
                    'cash_on_hand' => '$340,000',
                ],
            ],
        ]);

        ElectionCandidateRecord::factory()->create([
            'full_name' => 'Chris Nolan',
            'state' => 'CA',
            'district' => 'District 12',
            'political_office' => 'U.S. Representative',
            'party_affiliation' => 'Republican',
            'election_date' => now()->addMonths(2)->toDateString(),
        ]);

        ElectionCandidateRecord::factory()->create([
            'full_name' => 'Morgan Lee',
            'state' => 'CA',
            'district' => '12',
            'political_office' => 'U.S. Representative',
            'party_affiliation' => 'Independent',
            'election_date' => now()->addMonths(2)->toDateString(),
        ]);

        $response = $this->get(route('district.lookup', [
            'address' => '1600 Pennsylvania Ave NW, Washington, DC 20500',
        ]));

        $response->assertOk();
        $response->assertSee('Current Running Candidates (Public Records)');
        $response->assertSee('Top Contenders');
        $response->assertSee('Dana Carter');
        $response->assertSee('Chris Nolan');
        $response->assertSee('Morgan Lee');
        $response->assertSee('Campaign finance (open data)');
        $response->assertSee('Receipts: $1,250,000');
    });

    test('district lookup shows where-to-vote locations from voterinfo, including for a name with markup', function () {
    config()->set('services.google.civic_api_key', 'test-key');

    Http::fake([
        'https://geocoding.geo.census.gov/*' => Http::response([
            'result' => [
                'addressMatches' => [
                    [
                        'matchedAddress' => '22690 CACTUS AVE, MORENO VALLEY, CA, 92553',
                        'addressComponents' => ['state' => 'CA'],
                        'geographies' => [
                            '119th Congressional Districts' => [
                                ['CD119FP' => '39', 'NAME' => 'Congressional District 39'],
                            ],
                        ],
                    ],
                ],
            ],
        ], 200),
        'https://www.googleapis.com/civicinfo/v2/voterinfo*' => Http::response([
            'normalizedInput' => ['state' => 'CA', 'city' => 'Moreno Valley'],
            'pollingLocations' => [
                [
                    'name' => '<script>alert(1)</script> Community Center',
                    'pollingHours' => '7AM-8PM',
                    'latitude' => 33.9425,
                    'longitude' => -117.2297,
                    'address' => ['line1' => '123 Main St', 'city' => 'Moreno Valley', 'state' => 'CA', 'zip' => '92553'],
                ],
            ],
            'earlyVoteSites' => [
                [
                    'name' => 'County Registrar',
                    'startDate' => '2026-10-24',
                    'endDate' => '2026-11-03',
                    'address' => ['line1' => '456 Civic Plz', 'city' => 'Moreno Valley', 'state' => 'CA', 'zip' => '92553'],
                ],
            ],
        ], 200),
        'https://www.googleapis.com/civicinfo/v2/representatives*' => Http::response([
            'offices' => [], 'officials' => [], 'divisions' => [],
        ], 200),
    ]);

    $response = $this->get(route('district.lookup', [
        'address' => '22690 Cactus Ave, Moreno Valley, CA 92553',
    ]));

    $response->assertOk();
    $response->assertSee('Where to Vote');
    $response->assertSee('Your Polling Place');
    $response->assertSee('Community Center');
    $response->assertSee('123 Main St, Moreno Valley, CA, 92553');
    $response->assertSee('7AM-8PM');
    $response->assertSee('Early Voting');
    $response->assertSee('County Registrar');
    // The raw tag must never appear unescaped anywhere in the response —
    // covers both the Blade card ({{ }} auto-escapes) and the JSON payload
    // feeding the map markers (json_encode escapes "/" to "\/", so a literal
    // "</script>" sequence can't appear there either).
    $response->assertDontSee('<script>alert(1)</script>', false);
    // Confirms the card actually rendered the escaped form, not that it was silently dropped.
    $response->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
});

test('district lookup shows current politicians for searched location from google civic officials', function () {
        Http::fake([
            'https://geocoding.geo.census.gov/*' => Http::response([
                'result' => [
                    'addressMatches' => [
                        [
                            'matchedAddress' => '6840 S PAXTON AVE, CHICAGO, IL, 60649',
                            'addressComponents' => [
                                'state' => 'IL',
                            ],
                            'geographies' => [
                                '119th Congressional Districts' => [
                                    [
                                        'CD119FP' => '02',
                                        'NAME' => 'Illinois Congressional District 2',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200),
            'https://www.googleapis.com/civicinfo/v2/voterinfo*' => Http::response([
                'normalizedInput' => [
                    'line1' => '6840 S PAXTON AVE',
                    'city' => 'CHICAGO',
                    'state' => 'IL',
                    'zip' => '60649',
                ],
            ], 200),
            'https://www.googleapis.com/civicinfo/v2/representatives*' => Http::response([
                'offices' => [
                    [
                        'name' => 'President of the United States',
                        'level' => ['federal'],
                        'divisionId' => 'ocd-division/country:us',
                        'officialIndices' => [0],
                    ],
                    [
                        'name' => 'United States House of Representatives',
                        'level' => ['federal'],
                        'divisionId' => 'ocd-division/country:us/state:il/cd:2',
                        'officialIndices' => [1],
                    ],
                ],
                'officials' => [
                    [
                        'name' => 'Jordan Adams',
                        'party' => 'Independent',
                        'urls' => ['https://example.test/president'],
                    ],
                    [
                        'name' => 'Casey Monroe',
                        'party' => 'Democratic',
                        'urls' => ['https://example.test/representative'],
                    ],
                ],
            ], 200),
        ]);

        config()->set('services.google.civic_api_key', 'test-key');

        $response = $this->get(route('district.lookup', [
            'address' => '6840 S Paxton Ave, Chicago, IL 60649',
        ]));

        $response->assertOk();
        $response->assertSee('Current Politicians For This Location');
        $response->assertSee('Jordan Adams');
        $response->assertSee('Casey Monroe');
    });

    test('district lookup falls back to local records for current politicians when google civic is empty', function () {
        Http::fake([
            'https://geocoding.geo.census.gov/*' => Http::response([
                'result' => [
                    'addressMatches' => [
                        [
                            'matchedAddress' => '6840 S PAXTON AVE, CHICAGO, IL, 60649',
                            'addressComponents' => [
                                'state' => 'IL',
                            ],
                            'geographies' => [
                                '119th Congressional Districts' => [
                                    [
                                        'CD119FP' => '02',
                                        'NAME' => 'Illinois Congressional District 2',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200),
            'https://www.googleapis.com/civicinfo/v2/voterinfo*' => Http::response([
                'normalizedInput' => [
                    'line1' => '6840 S PAXTON AVE',
                    'city' => 'CHICAGO',
                    'state' => 'IL',
                    'zip' => '60649',
                ],
            ], 200),
            'https://www.googleapis.com/civicinfo/v2/representatives*' => Http::response([
                'offices' => [],
                'officials' => [],
                'divisions' => [],
            ], 200),
        ]);

        config()->set('services.google.civic_api_key', 'test-key');
        config()->set('services.congress.api_key', null);

        ElectionCandidateRecord::factory()->create([
            'source' => 'congress_legislators',
            'full_name' => 'Robin Kelly',
            'state' => 'IL',
            'district' => 'IL-02',
            'political_office' => 'United States Representative',
            'party_affiliation' => 'Democratic',
            'election_date' => now()->addMonths(8)->toDateString(),
        ]);

        $response = $this->get(route('district.lookup', [
            'address' => '6840 S Paxton Ave, Chicago, IL 60649',
        ]));

        $response->assertOk();
        $response->assertSee('Current Politicians For This Location');
        $response->assertSee('Robin Kelly');
        $response->assertSee('Congress Legislators Import');
    });

    test('district lookup falls back to congress gov for current politicians when configured', function () {
        Http::fake([
            'https://geocoding.geo.census.gov/*' => Http::response([
                'result' => [
                    'addressMatches' => [
                        [
                            'matchedAddress' => '6840 S PAXTON AVE, CHICAGO, IL, 60649',
                            'addressComponents' => [
                                'state' => 'IL',
                            ],
                            'geographies' => [
                                '119th Congressional Districts' => [
                                    [
                                        'CD119FP' => '02',
                                        'NAME' => 'Illinois Congressional District 2',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200),
            'https://www.googleapis.com/civicinfo/v2/voterinfo*' => Http::response([
                'normalizedInput' => [
                    'line1' => '6840 S PAXTON AVE',
                    'city' => 'CHICAGO',
                    'state' => 'IL',
                    'zip' => '60649',
                ],
            ], 200),
            'https://www.googleapis.com/civicinfo/v2/representatives*' => Http::response([
                'offices' => [],
                'officials' => [],
                'divisions' => [],
            ], 200),
            'https://api.congress.gov/v3/member/IL/2*' => Http::response([
                'members' => [
                    [
                        'name' => 'Robin L. Kelly',
                        'partyName' => 'Democratic',
                        'state' => 'IL',
                        'district' => 2,
                        'url' => 'https://api.congress.gov/v3/member/K000385',
                    ],
                ],
            ], 200),
        ]);

        config()->set('services.google.civic_api_key', 'test-key');
        config()->set('services.congress.api_key', 'test-congress-key');

        $response = $this->get(route('district.lookup', [
            'address' => '6840 S Paxton Ave, Chicago, IL 60649',
        ]));

        $response->assertOk();
        $response->assertSee('Current Politicians For This Location');
        $response->assertSee('Robin L. Kelly');
        $response->assertSee('Congress.gov');
        $response->assertSee('Wikipedia');
        $response->assertSee('YouTube');
        $response->assertSee('C-SPAN');

        expect(Politician::query()
            ->where('full_name', 'Robin L. Kelly')
            ->whereNull('user_id')
            ->where('page_published', true)
            ->exists())->toBeTrue();

        expect(ElectionCandidateRecord::query()
            ->where('source', 'congress_gov')
            ->where('full_name', 'Robin L. Kelly')
            ->exists())->toBeTrue();
    });
