<?php

use App\Models\BallotMeasure;
use App\Models\ElectionDataSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
    config(['services.google.civic_api_key' => 'DEMO_KEY']);

    ElectionDataSource::create([
        'ocd_id' => 'ocd-division/country:us/state:de/county:new_castle',
        'level' => 'county',
        'state' => 'DE',
        'jurisdiction_name' => 'New Castle County',
        'source_of_record' => 'google_civic',
    ]);
});

function civicPullFake(array $contests): void
{
    Http::fake([
        'civicinfo.googleapis.com/civicinfo/v2/elections*' => Http::response([
            'elections' => [
                ['id' => '9468', 'name' => 'Delaware Primary Election', 'electionDay' => '2026-09-15', 'ocdDivisionId' => 'ocd-division/country:us/state:de'],
            ],
        ], 200),
        'civicinfo.googleapis.com/civicinfo/v2/voterinfo*' => Http::response([
            'election' => ['id' => '9468', 'name' => 'Delaware Primary Election', 'electionDay' => '2026-09-15'],
            'contests' => $contests,
            'state' => [['name' => 'Delaware']],
        ], 200),
    ]);
}

it('ingests a Referendum contest into ballot_measures', function () {
    civicPullFake([
        ['type' => 'General', 'office' => 'Governor'],
        [
            'type' => 'Referendum',
            'referendumTitle' => 'Measure A',
            'referendumSubtitle' => 'County bond for schools',
            'referendumText' => 'Shall New Castle County issue $50M in bonds for school construction?',
            'referendumUrl' => 'https://voteinfo.net/de-new-castle/measure-a',
            'district' => ['name' => 'New Castle County', 'scope' => 'countywide'],
        ],
    ]);

    $this->artisan('civic:pull-measures', ['--state' => 'DE'])->assertExitCode(0);

    $measure = BallotMeasure::first();
    expect($measure->state)->toBe('DE')
        ->and($measure->county)->toBe('New Castle County')
        ->and($measure->title)->toBe('Measure A')
        ->and($measure->measure_number)->toBe('A')
        ->and($measure->summary)->toBe('County bond for schools')
        ->and($measure->election_date->toDateString())->toBe('2026-09-15')
        ->and($measure->source)->toBe('google_civic')
        ->and($measure->source_url)->toBe('https://voteinfo.net/de-new-castle/measure-a');

    // The registry row is stamped as a confirmed measures source.
    $row = ElectionDataSource::first();
    expect($row->last_scraped_at)->not->toBeNull()
        ->and($row->scrape_status)->toBe('ok');
});

it('is idempotent — re-running does not duplicate the measure', function () {
    civicPullFake([[
        'type' => 'Referendum',
        'referendumTitle' => 'Proposition 1',
        'referendumText' => 'A question.',
    ]]);

    $this->artisan('civic:pull-measures', ['--state' => 'DE'])->assertExitCode(0);
    Cache::flush();
    $this->artisan('civic:pull-measures', ['--state' => 'DE'])->assertExitCode(0);

    expect(BallotMeasure::where('title', 'Proposition 1')->count())->toBe(1);
});

it('a feed with no referendums stamps last_scraped_at but not scrape_status=ok', function () {
    civicPullFake([['type' => 'General', 'office' => 'Governor']]);

    $this->artisan('civic:pull-measures', ['--state' => 'DE'])
        ->expectsOutputToContain('0/1 rows with a feed had measures')
        ->assertExitCode(0);

    expect(BallotMeasure::count())->toBe(0);

    $row = ElectionDataSource::first();
    expect($row->last_scraped_at)->not->toBeNull()
        ->and($row->scrape_status)->toBe('unverified');
});

it('does not clobber a Ballotpedia-sourced measure\'s provenance', function () {
    BallotMeasure::create([
        'state' => 'DE',
        'title' => 'Measure A',
        'election_date' => '2026-09-15',
        'source' => 'ballotpedia',
        'summary' => 'Original summary',
    ]);

    civicPullFake([[
        'type' => 'Referendum',
        'referendumTitle' => 'Measure A',
        'referendumSubtitle' => 'Civic summary',
        'referendumUrl' => 'https://voteinfo.net/x',
    ]]);

    $this->artisan('civic:pull-measures', ['--state' => 'DE', '--refresh' => true])->assertExitCode(0);

    $measure = BallotMeasure::where('title', 'Measure A')->sole();
    expect($measure->source)->toBe('ballotpedia')          // provenance untouched
        ->and($measure->summary)->toBe('Civic summary')     // --refresh still fills data
        ->and($measure->source_url)->toBe('https://voteinfo.net/x');
});

it('--dry-run writes nothing', function () {
    civicPullFake([[
        'type' => 'Referendum',
        'referendumTitle' => 'Measure A',
    ]]);

    $this->artisan('civic:pull-measures', ['--state' => 'DE', '--dry-run' => true])->assertExitCode(0);

    expect(BallotMeasure::count())->toBe(0);
    expect(ElectionDataSource::first()->last_scraped_at)->toBeNull();
});
