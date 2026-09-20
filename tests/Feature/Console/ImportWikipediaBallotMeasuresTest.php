<?php

use App\Models\BallotMeasure;
use App\Models\ElectionDataSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    Http::preventStrayRequests();
    Http::fake(['en.wikipedia.org/w/api.php*' => Http::response(['parse' => ['text' => ['*' => '
        <div class="mw-heading"><h3 id="California">California</h3></div>
        <table class="wikitable"><tr><th>Measure</th><th>Description (Result of a yes vote)</th><th>Date</th><th>Status</th></tr>
        <tr><td>Proposition 1</td><td>Fund housing.</td><td>Nov 5</td><td>Approved</td></tr></table>
        <div class="mw-heading"><h3 id="Colorado">Colorado</h3></div>
        <table class="wikitable"><tr><th>Measure</th><th>Description</th><th>Date</th><th>Status</th></tr>
        <tr><td>Amendment 2</td><td>Change tax rates.</td><td>Nov 5</td><td>Defeated</td></tr></table>
    ']]])]);
});

test('imports wikipedia measures for a state and year without registry setup and is repeatable', function () {
    $options = ['--state' => 'ca', '--year' => '2024'];
    $this->artisan('ballot-measures:import-wikipedia', $options)->assertSuccessful();
    $this->artisan('ballot-measures:import-wikipedia', $options)->assertSuccessful();
    $measure = BallotMeasure::sole();
    expect($measure->state)->toBe('CA')
        ->and($measure->source)->toBe('wikipedia')
        ->and($measure->source_url)->toContain('2024_United_States_ballot_measures#California')
        ->and($measure->election_date->toDateString())->toBe('2024-11-05')
        ->and($measure->yes_meaning)->toBe('Fund housing.')
        ->and($measure->no_meaning)->toBeNull()
        ->and($measure->status)->toBe('passed')
        ->and(ElectionDataSource::count())->toBe(0);
    Http::assertSent(fn ($request) => $request['page'] === '2024 United States ballot measures');
});

test('dry run scans all states with one fetch and no writes', function () {
    $this->artisan('ballot-measures:import-wikipedia', ['--year' => 2024, '--dry-run' => true])
        ->expectsOutputToContain('Found: 2')->assertSuccessful();
    expect(BallotMeasure::count())->toBe(0)->and(ElectionDataSource::count())->toBe(0);
    Http::assertSentCount(1);
});

test('preserves existing text unless refreshed and always preserves original source', function () {
    $measure = BallotMeasure::create(['state' => 'CA', 'level' => 'state', 'title' => 'Proposition 1',
        'measure_number' => '1', 'election_date' => '2024-11-05', 'summary' => 'Reviewed summary', 'source' => 'manual']);
    $options = ['--state' => 'CA', '--year' => 2024];
    $this->artisan('ballot-measures:import-wikipedia', $options)->assertSuccessful();
    expect($measure->fresh()->summary)->toBe('Reviewed summary');
    $this->artisan('ballot-measures:import-wikipedia', $options + ['--refresh' => true])->assertSuccessful();
    expect($measure->fresh()->summary)->toBe('Fund housing.')->and($measure->fresh()->source)->toBe('manual');
});

test('invalid arguments do not fetch or write', function () {
    $this->artisan('ballot-measures:import-wikipedia', ['--state' => 'ZZ'])->assertFailed();
    $this->artisan('ballot-measures:import-wikipedia', ['--year' => 'invalid'])->assertFailed();
    Http::assertNothingSent();
});

test('missing article data is reported as a failed import', function () {
    Http::swap(new \Illuminate\Http\Client\Factory);
    Http::fake(['en.wikipedia.org/w/api.php*' => Http::response(['error' => ['code' => 'missingtitle']])]);
    $this->artisan('ballot-measures:import-wikipedia', ['--state' => 'CA'])->assertFailed();
    expect(BallotMeasure::count())->toBe(0);
});
