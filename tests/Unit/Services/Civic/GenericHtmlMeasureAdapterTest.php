<?php

use App\Models\ElectionDataSource;
use App\Services\Civic\Adapters\GenericHtmlMeasureAdapter;
use Illuminate\Support\Facades\Http;

function scrapeRow(string $url = 'https://voteinfo.net/november-3-2026-general-election'): ElectionDataSource
{
    return new ElectionDataSource(['state' => 'CA', 'level' => 'county', 'jurisdiction_name' => 'Alameda County', 'ballot_measures_url' => $url]);
}

$sampleBallotHtml = <<<'HTML'
<!doctype html><html><head><title>Sample Ballot — November 3, 2026 General Election</title></head>
<body>
  <nav><ul><li><a href="/">Home</a></li><li><a href="/measures">Measures</a></li></ul></nav>
  <h1>What's on your ballot</h1>

  <div class="contest">
    <h3>Measure A: Alameda County Transportation Bond</h3>
    <p>Shall Alameda County issue $400 million in bonds to fund road and transit repairs, subject to citizen oversight and annual audits?</p>
    <p class="fiscal">Estimated tax impact: $19 per $100,000 of assessed value.</p>
  </div>

  <div class="contest">
    <h3>Proposition 64 — Statewide Water Quality Act</h3>
    <p>Approves state bonds for water infrastructure. Fiscal impact: increased state costs of about $200 million annually.</p>
  </div>

  <table class="measures">
    <tr><th>Question 3</th><td>Amends the county charter to move the Sheriff's budget under the Board of Supervisors.</td></tr>
  </table>

  <footer><p>Measure your civic knowledge with our quiz.</p></footer>
</body></html>
HTML;

it('extracts measures, numbers, and following summaries from a sample-ballot page', function () use ($sampleBallotHtml) {
    Http::fake(['*' => Http::response($sampleBallotHtml, 200)]);

    $measures = (new GenericHtmlMeasureAdapter)->fetchMeasures(scrapeRow());

    expect($measures)->toHaveCount(3);

    $byNumber = collect($measures)->keyBy('measure_number');

    expect($byNumber['A']['title'])->toBe('Measure A: Alameda County Transportation Bond')
        ->and($byNumber['A']['summary'])->toContain('$400 million in bonds')
        ->and($byNumber['A']['summary'])->toContain('tax impact') // grabs the following fiscal note too
        ->and($byNumber['64']['title'])->toBe('Proposition 64 — Statewide Water Quality Act')
        ->and($byNumber['64']['summary'])->toContain('water infrastructure')
        ->and($byNumber['3']['title'])->toBe('Question 3')
        ->and($byNumber['3']['summary'])->toContain("Sheriff's budget"); // from the sibling <td>
});

it('parses the election date from the page title', function () use ($sampleBallotHtml) {
    Http::fake(['*' => Http::response($sampleBallotHtml, 200)]);

    $measures = (new GenericHtmlMeasureAdapter)->fetchMeasures(scrapeRow());

    expect($measures[0]['election_date'])->toBe('2026-11-03')
        ->and($measures[0]['source_url'])->toBe('https://voteinfo.net/november-3-2026-general-election');
});

it('falls back to the URL slug for the election date', function () {
    Http::fake(['*' => Http::response('<h3>Measure A</h3><p>A thing.</p>', 200)]);

    $measures = (new GenericHtmlMeasureAdapter)->fetchMeasures(scrapeRow('https://acgov.example/elections/2026-11-03/measures'));

    expect($measures[0]['election_date'])->toBe('2026-11-03');
});

it('ignores navigation and prose that merely mentions a measure', function () {
    $html = <<<'HTML'
    <ul><li>Read about how to measure turnout</li><li><a href="/x">Ballot Measures overview</a></li></ul>
    <p>The county placed Measure A on the ballot after a petition. See the contest below.</p>
    <h3>Measure A</h3><p>The real question text.</p>
    HTML;
    Http::fake(['*' => Http::response($html, 200)]);

    $measures = (new GenericHtmlMeasureAdapter)->fetchMeasures(scrapeRow());

    expect($measures)->toHaveCount(1)
        ->and($measures[0]['measure_number'])->toBe('A')
        ->and($measures[0]['summary'])->toBe('The real question text.');
});

it('returns nothing when the page yields an implausible number of matches (boilerplate)', function () {
    $items = collect(range(1, 70))->map(fn ($n) => "<li>Measure {$n} link</li>")->implode('');
    Http::fake(['*' => Http::response("<ul>{$items}</ul>", 200)]);

    expect((new GenericHtmlMeasureAdapter)->fetchMeasures(scrapeRow()))->toBe([]);
});

it('returns [] on a non-200 response or an empty body', function () {
    Http::fake(['*' => Http::response('nope', 404)]);
    expect((new GenericHtmlMeasureAdapter)->fetchMeasures(scrapeRow()))->toBe([]);

    Http::fake(['*' => Http::response('   ', 200)]);
    expect((new GenericHtmlMeasureAdapter)->fetchMeasures(scrapeRow()))->toBe([]);
});

it('returns [] when the row has no scrapeable URL', function () {
    $row = new ElectionDataSource(['state' => 'CA', 'level' => 'county']);
    expect((new GenericHtmlMeasureAdapter)->fetchMeasures($row))->toBe([]);
});
