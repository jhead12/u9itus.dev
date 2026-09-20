<?php

use App\Models\ElectionDataSource;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function civicUrlsRow(array $overrides = []): ElectionDataSource
{
    return ElectionDataSource::create(array_merge([
        'ocd_id' => 'ocd-division/country:us/state:ca/county:san_diego',
        'level' => 'county',
        'state' => 'CA',
        'jurisdiction_name' => 'San Diego County',
        'source_of_record' => 'census',
        'scrape_status' => 'ok',
        'last_verified_at' => now(),
    ], $overrides));
}

function civicUrlsCsv(string $body): string
{
    $path = tempnam(sys_get_temp_dir(), 'civic').'.csv';
    file_put_contents($path, $body);

    return $path;
}

const CIVIC_URLS_HEADER = "ocd_id,state,level,jurisdiction_name,ballot_measures_url,sample_ballot_url,elections_home_url,results_url,notes\n";

it('updates a matched row, marks it manual and queues it for re-verification', function () {
    $row = civicUrlsRow();
    $path = civicUrlsCsv(CIVIC_URLS_HEADER."{$row->ocd_id},,,,https://sdvote.example/guide.pdf,,,,checked by hand\n");

    $this->artisan('civic:import-source-urls', ['file' => $path])->assertSuccessful();

    $row->refresh();
    expect($row->ballot_measures_url)->toBe('https://sdvote.example/guide.pdf')
        ->and($row->source_of_record)->toBe('manual')
        ->and($row->scrape_status)->toBe('unverified')
        ->and($row->last_verified_at)->toBeNull()
        ->and($row->notes)->toBe('checked by hand');
});

it('blank cells never erase an existing value', function () {
    $row = civicUrlsRow(['sample_ballot_url' => 'https://keep.example/sample']);
    $path = civicUrlsCsv(CIVIC_URLS_HEADER."{$row->ocd_id},,,,https://new.example/measures,,,,\n");

    $this->artisan('civic:import-source-urls', ['file' => $path])->assertSuccessful();

    expect($row->fresh()->sample_ballot_url)->toBe('https://keep.example/sample');
});

it('matches on state, level and name when no ocd_id is given', function () {
    civicUrlsRow();
    $path = civicUrlsCsv(CIVIC_URLS_HEADER.",CA,county,san diego county,https://sdvote.example/measures,,,,\n");

    $this->artisan('civic:import-source-urls', ['file' => $path])->assertSuccessful();

    expect(ElectionDataSource::first()->ballot_measures_url)->toBe('https://sdvote.example/measures');
});

it('creates a new row only when it carries a full identity, and never invents an ocd_id', function () {
    $path = civicUrlsCsv(
        CIVIC_URLS_HEADER
        ."ocd-division/country:us/state:tx/place:austin,TX,municipal,Austin,https://austin.example/measures,,,,\n"
        .",TX,municipal,Nowhere City,https://nowhere.example/measures,,,,\n"
    );

    $this->artisan('civic:import-source-urls', ['file' => $path])->assertSuccessful();

    expect(ElectionDataSource::count())->toBe(1);
    $austin = ElectionDataSource::first();
    expect($austin->source_of_record)->toBe('manual')
        ->and($austin->level)->toBe('municipal')
        ->and($austin->ballot_measures_url)->toBe('https://austin.example/measures');
});

it('skips a row with an invalid URL', function () {
    $row = civicUrlsRow();
    $path = civicUrlsCsv(CIVIC_URLS_HEADER."{$row->ocd_id},,,,not-a-url,,,,\n");

    $this->artisan('civic:import-source-urls', ['file' => $path])
        ->expectsOutputToContain('not a valid http(s) URL')
        ->assertSuccessful();

    expect($row->fresh()->ballot_measures_url)->toBeNull();
});

it('--dry-run writes nothing', function () {
    $row = civicUrlsRow();
    $path = civicUrlsCsv(CIVIC_URLS_HEADER."{$row->ocd_id},,,,https://sdvote.example/guide.pdf,,,,\n");

    $this->artisan('civic:import-source-urls', ['file' => $path, '--dry-run' => true])->assertSuccessful();

    expect($row->fresh()->ballot_measures_url)->toBeNull()
        ->and($row->fresh()->source_of_record)->toBe('census');
});

it('rejects a CSV without a key column or any URL column', function () {
    $path = civicUrlsCsv("foo,bar\n1,2\n");

    $this->artisan('civic:import-source-urls', ['file' => $path])->assertFailed();
});

it('coverage counts linked and verified rows per state', function () {
    civicUrlsRow(['sample_ballot_url' => 'https://a.example/s', 'scrape_status' => 'ok']);
    civicUrlsRow(['ocd_id' => 'ocd-division/country:us/state:ca/county:kern', 'jurisdiction_name' => 'Kern County', 'ballot_measures_url' => 'https://b.example/m', 'scrape_status' => 'dead']);
    civicUrlsRow(['ocd_id' => 'ocd-division/country:us/state:ca/county:inyo', 'jurisdiction_name' => 'Inyo County']);
    civicUrlsRow(['ocd_id' => 'ocd-division/country:us/state:ny/county:kings', 'state' => 'NY', 'jurisdiction_name' => 'Kings County', 'ballot_measures_url' => 'https://c.example/m', 'scrape_status' => 'unverified']);

    $this->artisan('civic:coverage', ['--gaps' => true])
        ->expectsOutputToContain('Total: 4 row(s), 3 with a link (75%), 1 verified ok, 1 with no link.')
        ->assertSuccessful();
});

it('coverage --export-gaps writes only unlinked rows in the loader layout', function () {
    civicUrlsRow(['sample_ballot_url' => 'https://a.example/s']);
    $gap = civicUrlsRow(['ocd_id' => 'ocd-division/country:us/state:ca/county:inyo', 'jurisdiction_name' => 'Inyo County']);
    $out = sys_get_temp_dir().'/civic-gaps-'.uniqid().'.csv';

    $this->artisan('civic:coverage', ['--export-gaps' => $out])->assertSuccessful();

    $lines = array_values(array_filter(explode("\n", (string) file_get_contents($out))));
    expect($lines)->toHaveCount(2)
        ->and($lines[0])->toStartWith('ocd_id,state,level,jurisdiction_name,ballot_measures_url')
        ->and($lines[1])->toStartWith($gap->ocd_id);

    // …and the exported file loads straight back in.
    $filled = str_replace('"Inyo County",,', '"Inyo County",https://inyo.example/measures,', (string) file_get_contents($out));
    file_put_contents($out, $filled);
    $this->artisan('civic:import-source-urls', ['file' => $out])->assertSuccessful();
    expect($gap->fresh()->ballot_measures_url)->toBe('https://inyo.example/measures');
});
