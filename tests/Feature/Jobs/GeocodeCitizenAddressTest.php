<?php

use App\Jobs\GeocodeCitizenAddress;
use App\Models\Citizen;
use Illuminate\Support\Facades\Http;

test('geocodes a citizen address and stores lat/lng from the census response', function () {
    $citizen = Citizen::factory()->create([
        'address_line_1' => '1600 Pennsylvania Ave NW',
        'city' => 'Washington',
        'state' => 'DC',
        'zip' => '20500',
        'latitude' => null,
        'longitude' => null,
    ]);

    Http::fake([
        'https://geocoding.geo.census.gov/*' => Http::response([
            'result' => [
                'addressMatches' => [
                    [
                        'matchedAddress' => '1600 PENNSYLVANIA AVE NW, WASHINGTON, DC, 20500',
                        'coordinates' => ['x' => -77.0365, 'y' => 38.8977],
                        'addressComponents' => ['state' => 'DC'],
                        'geographies' => [],
                    ],
                ],
            ],
        ], 200),
    ]);

    (new GeocodeCitizenAddress($citizen->id))->handle(app(\App\Services\DistrictLookupService::class));

    $citizen->refresh();
    expect((float) $citizen->latitude)->toBe(38.8977);
    expect((float) $citizen->longitude)->toBe(-77.0365);
});

test('leaves lat/lng null when the address cannot be resolved', function () {
    $citizen = Citizen::factory()->create([
        'address_line_1' => 'not a real address',
        'latitude' => null,
        'longitude' => null,
    ]);

    Http::fake([
        'https://geocoding.geo.census.gov/*' => Http::response([
            'result' => ['addressMatches' => []],
        ], 200),
    ]);

    (new GeocodeCitizenAddress($citizen->id))->handle(app(\App\Services\DistrictLookupService::class));

    $citizen->refresh();
    expect($citizen->latitude)->toBeNull();
    expect($citizen->longitude)->toBeNull();
});

test('does nothing when the citizen no longer exists', function () {
    Http::fake();

    (new GeocodeCitizenAddress(999999))->handle(app(\App\Services\DistrictLookupService::class));

    Http::assertNothingSent();
});
