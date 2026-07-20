<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MapGeocodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_validates_missing_lat_lng(): void
    {
        $response = $this->getJson('/api/v1/map/geocode');

        $response->assertStatus(422)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('error', 'Provide valid numeric lat and lng query parameters.');
    }

    public function test_validates_out_of_range_coordinates(): void
    {
        $response = $this->getJson('/api/v1/map/geocode?lat=95&lng=10');

        $response->assertStatus(422)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('error', 'Latitude and longitude are out of valid range.');
    }

    public function test_resolves_coordinates_to_district(): void
    {
        Http::fake([
            'geocoding.geo.census.gov/*' => Http::response([
                'result' => [
                    'geographies' => [
                        'States' => [
                            ['STATE' => '06', 'STATEFP' => '06'],
                        ],
                        '119th Congressional Districts' => [
                            ['CD119' => '33', 'NAME' => 'California District 33'],
                        ],
                    ],
                ],
            ]),
        ]);

        $response = $this->getJson('/api/v1/map/geocode?lat=34.0522&lng=-118.2437');

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('state', 'CA')
            ->assertJsonPath('district_number', '33')
            ->assertJsonPath('district_code', 'CA-33')
            ->assertJsonPath('district_label', 'CA 33rd Congressional District');
    }

    public function test_handles_at_large_district(): void
    {
        Http::fake([
            'geocoding.geo.census.gov/*' => Http::response([
                'result' => [
                    'geographies' => [
                        'States' => [
                            ['STATE' => '02'],
                        ],
                        '119th Congressional Districts' => [
                            ['CD119' => '00'],
                        ],
                    ],
                ],
            ]),
        ]);

        $response = $this->getJson('/api/v1/map/geocode?lat=61.2181&lng=-149.9003');

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('state', 'AK')
            ->assertJsonPath('district_number', 'AL')
            ->assertJsonPath('district_code', 'AK-AL')
            ->assertJsonPath('district_label', 'AK At-Large Congressional District');
    }

    public function test_returns_404_when_census_has_no_match(): void
    {
        Http::fake([
            'geocoding.geo.census.gov/*' => Http::response([
                'result' => ['geographies' => []],
            ]),
        ]);

        $response = $this->getJson('/api/v1/map/geocode?lat=0&lng=0');

        $response->assertStatus(404)
            ->assertJsonPath('ok', false);
    }

    public function test_returns_404_when_census_request_fails(): void
    {
        Http::fake([
            'geocoding.geo.census.gov/*' => Http::response('', 503),
        ]);

        $response = $this->getJson('/api/v1/map/geocode?lat=34.0522&lng=-118.2437');

        $response->assertStatus(404)
            ->assertJsonPath('ok', false);
    }
}
