<?php

namespace Tests\Feature\Api;

use App\Models\DistrictLookupSearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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
                'boundary_congress' => 119,
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

    private function seedCrosswalk(string $zip, array $districts, string $state = 'OH'): void
    {
        foreach ($districts as $number) {
            DB::table('zip_district_crosswalk')->insert([
                'zip' => $zip, 'state' => $state, 'district_number' => (string) $number, 'land_area' => 1000, 'congress' => 119,
            ]);
        }
    }

    public function test_zip_spanning_several_districts_returns_them_all_and_picks_none(): void
    {
        Cache::flush();
        $this->seedCrosswalk('43215', [15, 3]);

        $response = $this->getJson('/api/v1/map/geocode?address=43215')->assertOk();

        $response->assertJson(['ok' => true, 'ambiguous' => true, 'precision' => 'zip']);
        $this->assertSame(['OH-03', 'OH-15'], collect($response->json('candidates'))->pluck('district_code')->all());
        $this->assertArrayNotHasKey('district_code', $response->json());
    }

    public function test_zip_inside_a_single_district_resolves_with_zip_precision(): void
    {
        Cache::flush();
        $this->seedCrosswalk('43215', [3]);

        $this->getJson('/api/v1/map/geocode?address=43215-1234')
            ->assertOk()
            ->assertJson(['ok' => true, 'district_code' => 'OH-03', 'precision' => 'zip'])
            ->assertJsonMissing(['ambiguous' => true]);
    }

    public function test_single_district_from_partial_records_is_offered_not_resolved(): void
    {
        Cache::flush();
        Http::fake();
        DistrictLookupSearch::create([
            'query_address' => '1 S High St, Columbus, OH 43215', 'matched_address' => '1 S HIGH ST, COLUMBUS, OH, 43215',
            'state' => 'OH', 'district_number' => '3', 'resolved' => true, 'source' => 'census_geocoder',
        ]);

        $response = $this->getJson('/api/v1/map/geocode?address=43215')->assertOk();

        $response->assertJson(['ok' => true, 'ambiguous' => true]);
        $this->assertSame(['OH-03'], collect($response->json('candidates'))->pluck('district_code')->all());
    }

    public function test_unresolvable_zip_asks_for_a_full_address(): void
    {
        Cache::flush();
        config()->set('services.google.civic_api_key', null);

        $this->getJson('/api/v1/map/geocode?address=43215')
            ->assertNotFound()
            ->assertJson(['ok' => false, 'needs_address' => true]);
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
    public function test_map_post_does_not_store_or_return_address_or_coordinates(): void
    {
        Cache::spy();
        Http::fake(['geocoding.geo.census.gov/*' => Http::response($this->censusAddressMatch())]);
        $response = $this->postJson('/api/v1/map/geocode', ['address' => '1 S High St, Columbus, OH 43215']);
        $response->assertOk()->assertJsonMissingPath('matched_address')->assertJsonMissingPath('input_address');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        Cache::shouldHaveReceived('put')->once()->withArgs(function ($key, $value, $ttl) {
            return str_starts_with($key, 'map.district.v1.119.')
                && ! str_contains($key, 'High')
                && array_keys($value) === ['state', 'district_number', 'district_code', 'district_label', 'boundary_congress'];
        });
        Http::assertSent(fn ($request) => $request['vintage'] === 'ACS2025_Current');
    }

    public function test_map_rejects_a_different_congress_even_with_a_valid_district_number(): void
    {
        Cache::flush();
        $match = $this->censusAddressMatch();
        $match['result']['addressMatches'][0]['geographies'] = ['120th Congressional Districts' => [['CD120' => '03']]];
        Http::fake(['geocoding.geo.census.gov/*' => Http::response($match)]);
        $this->postJson('/api/v1/map/geocode', ['address' => '1 S High St, Columbus, OH 43215'])->assertNotFound();
    }

    public function test_failure_logs_exclude_the_raw_address_and_exception_url(): void
    {
        Cache::flush();
        \Illuminate\Support\Facades\Log::spy();
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('Request failed: 1 S High St secret-key'));
        $this->postJson('/api/v1/map/geocode', ['address' => '1 S High St, Columbus, OH 43215'])->assertNotFound();
        \Illuminate\Support\Facades\Log::shouldHaveReceived('warning')->once()->withArgs(function ($message, $context) {
            return array_keys($context) === ['input_hash', 'failure_type']
                && ! str_contains(json_encode($context), 'High')
                && ! str_contains(json_encode($context), 'secret-key');
        });
    }

    public function test_regular_map_requests_do_not_use_up_the_geocode_allowance(): void
    {
        Cache::flush();
        for ($i = 0; $i < 31; $i++) {
            $this->getJson('/api/v1/map/district-config')->assertOk();
        }
        Http::fake(['geocoding.geo.census.gov/*' => Http::response($this->censusAddressMatch())]);
        $this->postJson('/api/v1/map/geocode', ['address' => '1 S High St, Columbus, OH 43215'])->assertOk();
    }

}
