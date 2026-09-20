<?php

use App\Models\CityDemographic;
use App\Services\DistrictLookupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('falls back to UCGID place geography when legacy ACS place queries return a malformed success payload', function () {
    Http::fakeSequence()
        ->push(['error: unsupported geography hierarchy'], 200)
        ->push([
            ['NAME', 'B01001_001E', 'B19013_001E', 'ucgid'],
            ['Houston city, Texas', '2304580', '62137', '1600000US4835000'],
        ], 200)
        ->push(['error: unsupported geography hierarchy'], 200)
        ->push([
            ['NAME', 'S1701_C03_001E', 'S1501_C02_015E', 'ucgid'],
            ['Houston city, Texas', '19.2', '36.8', '1600000US4835000'],
        ], 200);

    $this->mock(DistrictLookupService::class, function ($mock) {
        $mock->shouldReceive('lookupByCoordinates')
            ->once()
            ->with(29.763, -95.363)
            ->andReturn([
                'district_number' => '7',
                'district_code' => 'TX-07',
            ]);
    });

    $this->artisan('geo:sync-census-demographics', ['--year' => 2022, '--state' => ['TX']])
        ->expectsOutputToContain('retrying with UCGID')
        ->assertExitCode(0);

    expect(CityDemographic::where('state', 'TX')->where('city_name', 'Houston')->exists())->toBeTrue();

    $houston = CityDemographic::query()
        ->where('state', 'TX')
        ->where('city_name', 'Houston')
        ->first();
    expect($houston)->not->toBeNull()
        ->and($houston->district_code)->toBe('TX-07')
        ->and($houston->district_number)->toBe('7')
        ->and($houston->population)->toBe(2304580)
        ->and((float) $houston->poverty_rate)->toBe(19.2)
        ->and((float) $houston->pct_bachelors_or_higher)->toBe(36.8)
        ->and($houston->median_household_income)->toBe(62137)
        ->and($houston->source)->toBe('acs5')
        ->and($houston->census_year)->toBe(2022);

    Http::assertSentCount(4);
    Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://api.census.gov/data/2022/acs/acs5?')
        && str_contains($request->url(), 'get=NAME%2CB01001_001E%2CB19013_001E')
        && str_contains($request->url(), 'for=ucgid%3A160%7Cstate%3A48'));
    Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://api.census.gov/data/2022/acs/acs5/subject?')
        && str_contains($request->url(), 'get=NAME%2CS1701_C03_001E%2CS1501_C02_015E')
        && str_contains($request->url(), 'for=ucgid%3A160%7Cstate%3A48'));
});
