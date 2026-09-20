<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
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
            ->assertJsonPath('error', 'Provide an address or ZIP code, or valid numeric lat and lng query parameters.');
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

    // ── Address / ZIP lookups ─────────────────────────────────────────────
    // A full address (or the device location) pins one district. A ZIP can
    // span several, so it must never resolve by picking the first match.

    private function censusAddressMatch(string $district = '03'): array
    {
        return [
            'result' => ['addressMatches' => [[
                'matchedAddress' => '1 S HIGH ST, COLUMBUS, OH, 43215',
                'addressComponents' => ['state' => 'OH'],
                'coordinates' => ['x' => -83.0, 'y' => 39.96],
                'geographies' => ['119th Congressional Districts' => [['CD119FP' => $district, 'NAME' => "Congressional District {$district}"]]],
            ]]],
        ];
    }

    public function test_full_address_resolves_to_one_district_with_address_precision(): void
    {
        Cache::flush();
        Http::fake(['https://geocoding.geo.census.gov/*' => Http::response($this->censusAddressMatch(), 200)]);

        $this->getJson('/api/v1/map/geocode?address='.urlencode('1 S High St, Columbus, OH 43215'))
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'state' => 'OH',
                'district_number' => '3',
                'precision' => 'address',
                'matched_address' => '1 S HIGH ST, COLUMBUS, OH, 43215',
            ]);
    }

    public function test_unmatched_address_is_a_404_that_says_how_to_fix_it(): void
    {
        Cache::flush();
        config()->set('services.google.civic_api_key', null);
        Http::fake(['https://geocoding.geo.census.gov/*' => Http::response(['result' => ['addressMatches' => []]], 200)]);

        $this->getJson('/api/v1/map/geocode?address='.urlencode('nowhere'))
            ->assertNotFound()
            ->assertJson(['ok' => false])
            ->assertJsonPath('error', fn ($e) => str_contains($e, 'street, city, state'));
    }

    public function test_zip_spanning_several_districts_returns_them_all_and_picks_none(): void
    {
        Cache::flush();
        config()->set('services.google.civic_api_key', 'test-key');
        Http::fake([
            'https://civicinfo.googleapis.com/*' => Http::response(['divisions' => [
                'ocd-division/country:us/state:oh/cd:3' => ['name' => 'OH-3'],
                'ocd-division/country:us/state:oh/cd:15' => ['name' => 'OH-15'],
            ]], 200),
        ]);

        $response = $this->getJson('/api/v1/map/geocode?address=43215')->assertOk();

        $response->assertJson(['ok' => true, 'ambiguous' => true, 'precision' => 'zip']);
        $this->assertSame(['OH-03', 'OH-15'], collect($response->json('candidates'))->pluck('district_code')->all());
        $this->assertArrayNotHasKey('district_code', $response->json());
    }

    public function test_zip_inside_a_single_district_resolves_with_zip_precision(): void
    {
        Cache::flush();
        config()->set('services.google.civic_api_key', 'test-key');
        Http::fake([
            'https://civicinfo.googleapis.com/*' => Http::response(['divisions' => [
                'ocd-division/country:us/state:oh/cd:3' => ['name' => 'OH-3'],
            ]], 200),
        ]);

        $this->getJson('/api/v1/map/geocode?address=43215')
            ->assertOk()
            ->assertJson(['ok' => true, 'district_code' => 'OH-03', 'precision' => 'zip'])
            ->assertJsonMissing(['ambiguous' => true]);
    }

    public function test_unresolvable_zip_asks_for_a_full_address(): void
    {
        Cache::flush();
        config()->set('services.google.civic_api_key', null);

        $this->getJson('/api/v1/map/geocode?address=43215')
            ->assertNotFound()
            ->assertJson(['ok' => false, 'needs_address' => true]);
    }

    public function test_failed_zip_lookup_is_not_cached_for_later_requests(): void
    {
        Cache::flush();
        config()->set('services.google.civic_api_key', 'test-key');
        Http::fakeSequence('https://civicinfo.googleapis.com/*')
            ->push([], 500)
            ->push(['divisions' => ['ocd-division/country:us/state:oh/cd:3' => ['name' => 'OH-3']]], 200);

        $this->getJson('/api/v1/map/geocode?address=43215')->assertNotFound();
        $this->getJson('/api/v1/map/geocode?address=43215')->assertOk()->assertJson(['district_code' => 'OH-03']);
    }

    public function test_empty_and_oversized_addresses_are_rejected_before_any_lookup(): void
    {
        Http::fake();

        $this->getJson('/api/v1/map/geocode?address=')->assertStatus(422);
        $this->getJson('/api/v1/map/geocode?address='.str_repeat('a', 201))->assertStatus(422);
        Http::assertNothingSent();
    }

    public function test_coordinate_lookups_report_location_precision(): void
    {
        Http::fake(['geocoding.geo.census.gov/*' => Http::response([
            'result' => ['geographies' => [
                'States' => [['STATE' => '39']],
                '119th Congressional Districts' => [['CD119' => '03', 'NAME' => 'Ohio District 3']],
            ]],
        ])]);

        $this->getJson('/api/v1/map/geocode?lat=39.96&lng=-83.0')
            ->assertOk()
            ->assertJson(['ok' => true, 'state' => 'OH', 'precision' => 'location']);
    }
}
