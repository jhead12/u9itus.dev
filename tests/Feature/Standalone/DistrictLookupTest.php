<?php

use App\Models\Politician;
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
