<?php

use App\Models\ElectionDataSource;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('seeds all 51 state rows offline with curated URLs and the wikipedia adapter', function () {
    $this->artisan('civic:seed-jurisdictions', ['--source' => 'states'])->assertExitCode(0);

    $states = ElectionDataSource::where('level', 'state')->get();
    expect($states)->toHaveCount(51)
        ->and($states->whereNull('elections_home_url'))->toHaveCount(0)
        ->and($states->where('platform_template', '!=', 'wikipedia'))->toHaveCount(0);

    $ca = ElectionDataSource::firstWhere('ocd_id', 'ocd-division/country:us/state:ca');
    expect($ca->jurisdiction_name)->toBe('California')
        ->and($ca->source_of_record)->toBe('nass')
        ->and($ca->elections_home_url)->toBe(config('civic.state_election_sites.CA'))
        ->and($ca->platform_template)->toBe('wikipedia');
});

it('is idempotent — a second run writes nothing', function () {
    $this->artisan('civic:seed-jurisdictions', ['--source' => 'states'])->assertExitCode(0);
    $this->artisan('civic:seed-jurisdictions', ['--source' => 'states'])
        ->expectsOutputToContain('0 created, 0 updated')
        ->assertExitCode(0);

    expect(ElectionDataSource::count())->toBe(51);
});

it('--dry-run writes nothing', function () {
    $this->artisan('civic:seed-jurisdictions', ['--source' => 'states', '--dry-run' => true])->assertExitCode(0);

    expect(ElectionDataSource::count())->toBe(0);
});

it('seeds county rows from a local OCD CSV and derives state + FIPS', function () {
    $csv = <<<'CSV'
    id,name,census_geoid
    ocd-division/country:us/state:ca/county:los_angeles,Los Angeles County,place-06037
    ocd-division/country:us/state:ca/county:orange,Orange County,place-06059
    ocd-division/country:us/state:ca/county:los_angeles/council_district:1,LA Supervisor District 1,
    ocd-division/country:us/state:tx/county:harris,Harris County,place-48201
    ocd-division/country:us/state:pr/county:adjuntas,Adjuntas Municipio,place-72001
    CSV;

    $path = base_path('storage/app/testing-ocd-counties.csv');
    file_put_contents($path, $csv);

    $this->artisan('civic:seed-jurisdictions', ['--source' => 'counties', '--state' => 'CA', '--file' => $path])
        ->assertExitCode(0);

    @unlink($path);

    // Only the two top-level CA counties — the sub-division and the other
    // states are filtered out.
    expect(ElectionDataSource::where('level', 'county')->count())->toBe(2);

    $la = ElectionDataSource::firstWhere('ocd_id', 'ocd-division/country:us/state:ca/county:los_angeles');
    expect($la->state)->toBe('CA')
        ->and($la->jurisdiction_name)->toBe('Los Angeles County')
        ->and($la->county_fips)->toBe('06037')
        ->and($la->source_of_record)->toBe('census');
});
