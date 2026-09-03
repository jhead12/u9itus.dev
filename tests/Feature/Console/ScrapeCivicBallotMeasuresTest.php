<?php

use App\Models\BallotMeasure;
use App\Models\ElectionDataSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function scrapeCmdRow(array $overrides = []): ElectionDataSource
{
    return ElectionDataSource::create(array_merge([
        'ocd_id' => 'ocd-division/country:us/state:ca/county:alameda',
        'level' => 'county',
        'state' => 'CA',
        'jurisdiction_name' => 'Alameda County',
        'source_of_record' => 'google_civic',
        'vendor' => 'voteinfo_net',
        'ballot_measures_url' => 'https://voteinfo.net/alameda/november-3-2026',
        'scrape_status' => 'ok',
    ], $overrides));
}

const SCRAPE_HTML = <<<'HTML'
<title>November 3, 2026 General Election</title>
<div><h3>Measure A: Transportation Bond</h3><p>Shall the county issue $400 million in bonds for roads?</p></div>
<div><h3>Measure B: Library Parcel Tax</h3><p>Renews the $58 annual parcel tax funding county libraries.</p></div>
HTML;

it('scrapes measures via the voteinfo_net → generic_html adapter into ballot_measures', function () {
    scrapeCmdRow();
    Http::fake(['*' => Http::response(SCRAPE_HTML, 200)]);

    $this->artisan('civic:scrape-measures', ['--state' => 'CA'])->assertExitCode(0);

    $a = BallotMeasure::where('measure_number', 'A')->sole();
    expect($a->state)->toBe('CA')
        ->and($a->county)->toBe('Alameda County')
        ->and($a->title)->toBe('Measure A: Transportation Bond')
        ->and($a->summary)->toContain('$400 million')
        ->and($a->election_date->toDateString())->toBe('2026-11-03')
        ->and($a->source)->toBe('html_scrape');

    expect(BallotMeasure::count())->toBe(2);

    $row = ElectionDataSource::first();
    expect($row->last_scraped_at)->not->toBeNull()
        ->and($row->scrape_status)->toBe('ok');
});

it('is idempotent', function () {
    scrapeCmdRow();
    Http::fake(['*' => Http::response(SCRAPE_HTML, 200)]);

    $this->artisan('civic:scrape-measures', ['--state' => 'CA'])->assertExitCode(0);
    $this->artisan('civic:scrape-measures', ['--state' => 'CA'])->assertExitCode(0);

    expect(BallotMeasure::count())->toBe(2);
});

it('skips rows verify marked dead or blocked, and robots-disallowed rows', function () {
    scrapeCmdRow(['ocd_id' => 'x/dead', 'scrape_status' => 'dead']);
    scrapeCmdRow(['ocd_id' => 'x/blocked', 'jurisdiction_name' => 'B County', 'scrape_status' => 'blocked']);
    scrapeCmdRow(['ocd_id' => 'x/robots', 'jurisdiction_name' => 'C County', 'robots_ok' => false]);

    Http::fake(['*' => Http::response(SCRAPE_HTML, 200)]);

    $this->artisan('civic:scrape-measures', ['--state' => 'CA'])
        ->expectsOutputToContain('for 0 registry row(s)')
        ->assertExitCode(0);

    expect(BallotMeasure::count())->toBe(0);
});

it('counts rows whose vendor has no adapter mapping and does not scrape them', function () {
    scrapeCmdRow(['vendor' => 'some_unmapped_vendor']);
    Http::fake(['*' => Http::response(SCRAPE_HTML, 200)]);

    $this->artisan('civic:scrape-measures', ['--state' => 'CA'])
        ->expectsOutputToContain('1 with no adapter')
        ->assertExitCode(0);

    expect(BallotMeasure::count())->toBe(0);
    Http::assertNothingSent();
});

it('--only-empty skips a state that already has an upcoming measure', function () {
    scrapeCmdRow();
    BallotMeasure::create(['state' => 'CA', 'title' => 'Existing Prop', 'status' => 'upcoming']);
    Http::fake(['*' => Http::response(SCRAPE_HTML, 200)]);

    $this->artisan('civic:scrape-measures', ['--state' => 'CA', '--only-empty' => true])->assertExitCode(0);

    expect(BallotMeasure::count())->toBe(1); // nothing new scraped
});

it('--dry-run writes nothing', function () {
    scrapeCmdRow();
    Http::fake(['*' => Http::response(SCRAPE_HTML, 200)]);

    $this->artisan('civic:scrape-measures', ['--state' => 'CA', '--dry-run' => true])->assertExitCode(0);

    expect(BallotMeasure::count())->toBe(0);
    expect(ElectionDataSource::first()->last_scraped_at)->toBeNull();
});

it('--election-date stamps measures whose page has no parseable date', function () {
    scrapeCmdRow(['ballot_measures_url' => 'https://voteinfo.net/alameda/measures']);
    Http::fake(['*' => Http::response('<h3>Measure A</h3><p>Question.</p>', 200)]);

    $this->artisan('civic:scrape-measures', ['--state' => 'CA', '--election-date' => '2026-11-03'])->assertExitCode(0);

    expect(BallotMeasure::where('measure_number', 'A')->value('election_date')->toDateString())->toBe('2026-11-03');
});
