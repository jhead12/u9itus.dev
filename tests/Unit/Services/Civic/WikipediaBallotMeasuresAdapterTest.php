<?php

use App\Models\ElectionDataSource;
use App\Services\Civic\Adapters\WikipediaBallotMeasuresAdapter;
use Illuminate\Support\Facades\Http;

function wikiArticleHtml(): string
{
    // Shape of the real "<year> United States ballot measures" article: a
    // per-state <h3 id="State"> in a .mw-heading wrapper, then a wikitable.
    return <<<'HTML'
    <div class="mw-heading mw-heading2"><h2 id="By_state">By state</h2></div>
    <div class="mw-heading mw-heading3"><h3 id="California">California</h3></div>
    <table class="wikitable">
      <tbody>
        <tr><th>Origin</th><th>Status</th><th>Measure</th><th>Description (Result of a "yes" vote)</th><th>Date</th><th>Yes</th><th>No</th></tr>
        <tr>
          <td>Citizens</td><td>On ballot</td>
          <td>Proposition 1: California Veteran and Housing Assistance Programs Bond Measure</td>
          <td>Issue $11.25 billion in bonds to fund housing programs.<sup class="reference">[1]</sup></td>
          <td>Nov 3</td><td colspan="2">Awaiting official results</td>
        </tr>
        <tr>
          <td>Legislature</td><td>Approved</td>
          <td>Proposition 2: Rainy Day Fund Amendment</td>
          <td>Raise the Budget Stabilization Account cap to 20%.</td>
          <td>Nov 3</td><td colspan="2">Passed</td>
        </tr>
      </tbody>
    </table>
    <div class="mw-heading mw-heading3"><h3 id="Colorado">Colorado</h3></div>
    <table class="wikitable">
      <tbody>
        <tr><th>Origin</th><th>Status</th><th>Measure</th><th>Description (Result of a "yes" vote)</th><th>Date</th></tr>
        <tr><td>Citizens</td><td>On ballot</td><td>Colorado Income Tax Cap Initiative</td><td>Cap the income tax rate at 4.4%.</td><td>Nov 3</td></tr>
      </tbody>
    </table>
    <div class="mw-heading mw-heading3"><h3 id="Wyoming">Wyoming</h3></div>
    <p>No statewide measures.</p>
    HTML;
}

function fakeWikiApi(?string $html = null): void
{
    Http::fake([
        'en.wikipedia.org/w/api.php*' => Http::response([
            'parse' => ['title' => '2026 United States ballot measures', 'text' => ['*' => $html ?? wikiArticleHtml()]],
        ], 200),
    ]);
}

function wikiRow(string $state): ElectionDataSource
{
    return new ElectionDataSource(['state' => $state, 'level' => 'state']);
}

it('parses a state section into measures with yes_meaning from the description column', function () {
    fakeWikiApi();

    $measures = (new WikipediaBallotMeasuresAdapter)->fetchMeasures(wikiRow('CA'));

    expect($measures)->toHaveCount(2);

    expect($measures[0]['title'])->toBe('Proposition 1: California Veteran and Housing Assistance Programs Bond Measure')
        ->and($measures[0]['measure_number'])->toBe('1')
        ->and($measures[0]['yes_meaning'])->toBe('Issue $11.25 billion in bonds to fund housing programs.') // citation stripped
        ->and($measures[0]['summary'])->toBe('Issue $11.25 billion in bonds to fund housing programs.')
        ->and($measures[0]['election_date'])->toBe('2026-11-03')
        ->and($measures[0]['status'])->toBe('upcoming')
        ->and($measures[0]['source_url'])->toContain('#California');

    expect($measures[1]['status'])->toBe('passed'); // "Approved"
});

it('scopes to the requested state only', function () {
    fakeWikiApi();

    $co = (new WikipediaBallotMeasuresAdapter)->fetchMeasures(wikiRow('CO'));

    expect($co)->toHaveCount(1)
        ->and($co[0]['title'])->toBe('Colorado Income Tax Cap Initiative')
        ->and($co[0]['measure_number'])->toBeNull(); // no "Proposition N" designator
});

it('returns [] for a state section with no table', function () {
    fakeWikiApi();

    expect((new WikipediaBallotMeasuresAdapter)->fetchMeasures(wikiRow('WY')))->toBe([]);
});

it('returns [] for a state absent from the article', function () {
    fakeWikiApi();

    expect((new WikipediaBallotMeasuresAdapter)->fetchMeasures(wikiRow('TX')))->toBe([]);
});

it('fetches the article once per instance across states', function () {
    fakeWikiApi();

    $adapter = new WikipediaBallotMeasuresAdapter;
    $adapter->fetchMeasures(wikiRow('CA'));
    $adapter->fetchMeasures(wikiRow('CO'));

    Http::assertSentCount(1);
});

it('returns [] on an API failure', function () {
    Http::fake(['en.wikipedia.org/w/api.php*' => Http::response('nope', 500)]);

    expect((new WikipediaBallotMeasuresAdapter)->fetchMeasures(wikiRow('CA')))->toBe([]);
});
