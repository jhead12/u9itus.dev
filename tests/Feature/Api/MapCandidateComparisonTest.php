<?php

use App\Models\Politician;
use App\Models\PoliticianPage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->travelTo(\Carbon\Carbon::parse('2026-09-19 12:00:00'));
    \App\Models\StateElectionDate::create(['state' => 'CA', 'election_year' => 2026, 'stage_name' => 'General', 'election_date' => '2026-11-03', 'source' => 'civic']);
    Cache::flush();
    Http::preventStrayRequests();
});

function comparisonProfile(string $name, string $district, array $attributes = []): Politician
{
    return Politician::factory()->create(array_merge([
        'full_name' => $name, 'political_office' => 'U.S. Representative',
        'governance_level' => 'Federal', 'state' => 'CA', 'district' => $district,
        'term_status' => 'running', 'is_running_candidate' => true, 'is_active' => true,
        'slug' => str_replace(' ', '-', strtolower($name)), 'page_published' => true,
    ], $attributes));
}

function comparisonUrl(Politician $p, array $parameters = []): string
{
    return '/api/v1/map/candidate-comparison?'.http_build_query(array_merge([
        'state' => $p->state, 'full_name' => $p->full_name, 'id' => $p->id,
    ], $parameters));
}

test('comparison groups only the selected House seat and preserves incumbency independently of candidacy', function () {
    $incumbent = comparisonProfile('Jamie Carter', 'CA-03', ['term_status' => 'seated', 'is_running_candidate' => false]);
    $challenger = comparisonProfile('Alex Rivera', 'CA-3', ['party_affiliation' => 'Republican']);
    comparisonProfile('Taylor Morgan', 'CA-04');
    comparisonProfile('Robin Nelson', 'TX-03', ['state' => 'TX']);
    comparisonProfile('Jordan Baker', 'CA-03', ['term_status' => 'lost']);
    $response = $this->getJson(comparisonUrl($incumbent))->assertOk()->assertJsonCount(2, 'candidates')
        ->assertJsonPath('seat.label', 'U.S. House · CA-03')
        ->assertJsonPath('selected_key', 'profile:'.$incumbent->id);
    $byName = collect($response->json('candidates'))->keyBy('full_name');
    expect($byName['Jamie Carter']['incumbency'])->toBe('Current officeholder')
        ->and($byName['Jamie Carter']['candidacy'])->toBe('Candidacy not confirmed')
        ->and($byName['Alex Rivera']['incumbency'])->toBe('Challenger')
        ->and($byName['Alex Rivera']['party'])->toBe('Republican');
});

test('comparison exposes published sourced statements but never draft or hidden profile positions', function () {
    $p = comparisonProfile('Jamie Carter', 'CA-03');
    $p->initiatives()->create(['title' => 'Housing', 'description' => '<p>Build more affordable homes.</p>', 'is_published' => true]);
    $p->initiatives()->create(['title' => 'Draft policy', 'description' => 'Private draft.', 'is_published' => false]);
    $this->getJson(comparisonUrl($p))->assertOk()->assertJsonCount(1, 'candidates.0.stances')
        ->assertJsonPath('candidates.0.stances.0.text', 'Build more affordable homes.')
        ->assertJsonPath('candidates.0.stances.0.source_label', 'Published profile position');
    PoliticianPage::create(array_merge(PoliticianPage::defaults($p->id), ['show_initiatives' => false]));
    $this->getJson(comparisonUrl($p))->assertOk()->assertJsonCount(0, 'candidates.0.stances');
    $p->page->update(['show_initiatives' => true]);
    $p->update(['page_published' => false]);
    $this->getJson(comparisonUrl($p))->assertOk()->assertJsonCount(0, 'candidates.0.stances');
});

test('cached Vote Smart positions are sourced and respect the data visibility preference', function () {
    $p = comparisonProfile('Jamie Carter', 'CA-03', ['show_votesmart_data' => true, 'votesmart_id' => '123']);
    Cache::put('votesmart.politician.'.$p->id, ['issue_positions' => [['issue' => 'Education', 'position' => 'Expand school funding.']]], 600);
    $this->getJson(comparisonUrl($p))->assertOk()
        ->assertJsonPath('candidates.0.stances.0.source_label', 'Vote Smart')
        ->assertJsonPath('candidates.0.stances.0.source_url', 'https://justfacts.votesmart.org/candidate/political-courage-test/123');
    $p->update(['show_votesmart_data' => false]);
    $this->getJson(comparisonUrl($p))->assertJsonCount(0, 'candidates.0.stances');
    Http::assertNothingSent();
});

test('unnumbered council seats are not combined', function () {
    $p = comparisonProfile('Jamie Carter', '', ['political_office' => 'City Council Member', 'governance_level' => 'City', 'city' => 'Oakland']);
    comparisonProfile('Alex Rivera', '', ['political_office' => 'City Council Member', 'governance_level' => 'City', 'city' => 'Oakland']);
    $this->getJson(comparisonUrl($p))->assertOk()->assertJsonPath('seat', null)->assertJsonCount(0, 'candidates');
});

test('a Senate race compares only the people running, not the senator whose seat is not up', function () {
    $senate = ['political_office' => 'U.S. Senator', 'district' => null];
    $p = comparisonProfile('Jamie Carter', '', $senate);
    comparisonProfile('Alex Rivera', '', $senate);
    $sitting = comparisonProfile('Robin Nelson', '', $senate + ['term_status' => 'seated', 'is_running_candidate' => false]);
    $this->getJson(comparisonUrl($p))->assertOk()->assertJsonPath('available', true)
        ->assertJsonPath('seat.label', 'U.S. Senate · CA')
        ->assertJsonCount(2, 'candidates');
    $this->getJson(comparisonUrl($sitting))->assertOk()->assertJsonPath('available', false);
});

test('a dated ballot record for the same seat fills a missing candidacy and party', function () {
    $governor = ['political_office' => 'Governor', 'governance_level' => 'State', 'district' => null];
    $incumbent = comparisonProfile('Jamie Carter', '', $governor + ['term_status' => 'seated', 'is_running_candidate' => false, 'party_affiliation' => null]);
    $challenger = comparisonProfile('Alex Rivera', '', $governor + ['party_affiliation' => 'Democratic']);
    foreach ([[$incumbent, 'Republican', 'Governor'], [$challenger, 'Democratic', 'Governor'], [$incumbent, 'Green', 'Lieutenant Governor']] as [$person, $party, $office]) {
        \App\Models\ElectionCandidateRecord::create(['source' => 'ballotpedia', 'external_candidate_id' => $person->slug.$office, 'full_name' => $person->full_name,
            'state' => 'CA', 'political_office' => $office, 'governance_level' => 'State', 'party_affiliation' => $party, 'election_date' => '2026-11-03']);
    }
    $response = $this->getJson(comparisonUrl($challenger))->assertOk()->assertJsonPath('available', true);
    $carter = collect($response->json('candidates'))->firstWhere('full_name', 'Jamie Carter');
    expect($carter['candidacy'])->toBe('Running')
        ->and($carter['incumbency'])->toBe('Current officeholder')
        ->and($carter['party'])->toBe('Republican');
});

test('comparison shows campaign finance, issue focus, and a sitting member\'s record and floor positions', function () {
    $p = comparisonProfile('Jamie Carter', 'CA-03', ['bioguide_id' => 'C000001']);
    $topic = \App\Models\PoliticianTopic::create(['name' => 'Housing', 'slug' => 'housing', 'is_active' => true, 'sort_order' => 1]);
    $p->addBadge($topic->id, 'inferred_discourse', ['is_public' => true, 'earned_at' => now()]);
    \App\Models\PoliticianDonorSnapshot::create(['politician_id' => $p->id, 'enriched_at' => now(), 'election_cycle' => 2026,
        'fec_summary' => ['cycle' => 2026, 'receipts' => '$1,200,000', 'disbursements' => '$800,000', 'cash_on_hand' => '$400,000'],
        'fec_source_url' => 'https://www.fec.gov/data/candidate/H0CA03000/']);
    \App\Models\CongressMemberLegislation::create(['bioguide_id' => 'C000001', 'since_congress' => 118, 'sponsored_total' => 12, 'cosponsored_total' => 90, 'policy_areas' => [], 'topics' => []]);
    \App\Models\CongressCommitteeAssignment::create(['bioguide_id' => 'C000001', 'committee_code' => 'HSBA', 'name' => 'House Committee on Financial Services', 'chamber' => 'house']);
    \App\Models\CongressFloorSpeech::create(['granule_id' => 'G1', 'bioguide_id' => 'C000001', 'chamber' => 'house', 'spoken_on' => '2026-07-15', 'title' => 'HOUSING',
        'body' => 'Text.', 'source_url' => 'https://www.govinfo.gov/g1.htm', 'topic_key' => 'housing', 'stance' => 'support',
        'position_summary' => 'Supports building more homes.', 'quote' => 'We need more homes.']);
    \App\Models\CongressFloorSpeech::create(['granule_id' => 'G2', 'bioguide_id' => 'C000001', 'chamber' => 'house', 'spoken_on' => '2026-07-16', 'title' => 'HOUSING ACT',
        'body' => 'Text.', 'source_url' => 'https://www.govinfo.gov/g2.htm', 'topic_key' => 'housing']);

    $this->getJson(comparisonUrl($p))->assertOk()
        ->assertJsonPath('candidates.0.finance.receipts', '$1,200,000')
        ->assertJsonPath('candidates.0.finance.source_url', 'https://www.fec.gov/data/candidate/H0CA03000/')
        ->assertJsonPath('candidates.0.issue_focus', ['Housing'])
        ->assertJsonPath('candidates.0.legislation.sponsored', 12)
        ->assertJsonPath('candidates.0.legislation.committees', ['House Committee on Financial Services'])
        // Only a speech with a read position becomes a stance; a keyword-tagged title does not.
        ->assertJsonCount(1, 'candidates.0.stances')
        ->assertJsonPath('candidates.0.stances.0.topic', 'Housing')
        ->assertJsonPath('candidates.0.stances.0.quote', 'We need more homes.')
        ->assertJsonPath('candidates.0.stances.0.source_label', 'Congressional Record floor speech');
});

test('a single recorded candidate does not imply an uncontested election', function () {
    $p = comparisonProfile('Jamie Carter', 'CA-03');
    $this->getJson(comparisonUrl($p))->assertOk()->assertJsonCount(1, 'candidates')
        ->assertJsonPath('message', 'No other candidates for this seat are recorded yet. This does not mean the race is uncontested.');
});

test('comparison rejects invalid states and requires a candidate', function () {
    $this->getJson('/api/v1/map/candidate-comparison?state=XX&full_name=Jamie')->assertUnprocessable();
    $this->getJson('/api/v1/map/candidate-comparison?state=CA')->assertUnprocessable();
});

test('a statewide date does not establish an election for a city seat', function () {
    $p = comparisonProfile('Jamie Carter', '', ['political_office' => 'City Council Member, Seat 2', 'governance_level' => 'City', 'city' => 'Oakland']);
    comparisonProfile('Alex Rivera', '', ['political_office' => 'City Council Member, Seat 2', 'governance_level' => 'City', 'city' => 'Oakland']);
    comparisonProfile('Taylor Morgan', '', ['political_office' => 'City Council Member, Seat 3', 'governance_level' => 'City', 'city' => 'Oakland']);
    comparisonProfile('Robin Nelson', '', ['political_office' => 'City Council Member, Seat 2', 'governance_level' => 'City', 'city' => 'Berkeley']);
    $this->getJson(comparisonUrl($p))->assertOk()->assertJsonPath('available', false)->assertJsonCount(0, 'candidates');
});


test('comparison is available only from 90 days before through election day', function (int $days, bool $available) {
    $p = comparisonProfile('Jamie Carter', 'CA-03');
    \App\Models\StateElectionDate::query()->update(['election_date' => now()->addDays($days)->toDateString()]);
    $this->getJson(comparisonUrl($p))->assertOk()->assertJsonPath('available', $available);
})->with([[91, false], [90, true], [1, true], [0, true], [-1, false]]);

test('unknown dates and seats with no recorded candidacy stay unavailable', function () {
    $p = comparisonProfile('Jamie Carter', 'CA-03', ['term_status' => 'seated', 'is_running_candidate' => false]);
    $this->getJson(comparisonUrl($p))->assertOk()->assertJsonPath('available', false);
    $p->update(['is_running_candidate' => true]);
    \App\Models\StateElectionDate::query()->delete();
    $this->getJson(comparisonUrl($p))->assertOk()->assertJsonPath('available', false);
});

test('saved district comparisons resolve without a selected candidate', function () {
    comparisonProfile('Jamie Carter', 'CA-03');
    comparisonProfile('Alex Rivera', 'CA-04');
    $this->getJson('/api/v1/map/candidate-comparison?state=CA&district=CA-03')
        ->assertOk()->assertJsonPath('available', true)->assertJsonCount(1, 'candidates')
        ->assertJsonPath('candidates.0.full_name', 'Jamie Carter');
});

test('a statewide office requires its own dated candidate record within the window', function () {
    $p = comparisonProfile('Jamie Carter', '', ['political_office' => 'Governor', 'governance_level' => 'State']);
    $record = \App\Models\ElectionCandidateRecord::create([
        'source' => 'ballotpedia', 'external_candidate_id' => 'Jamie_Carter', 'full_name' => 'Jamie Carter',
        'state' => 'CA', 'political_office' => 'Governor', 'governance_level' => 'State',
        'election_date' => '2026-11-03',
    ]);
    $this->mock(\App\Http\Controllers\Api\MapStateCandidatesController::class, function ($mock) use ($p) {
        $mock->shouldReceive('__invoke')->andReturn(response()->json(['offices' => [[
            'office' => 'Governor', 'candidates' => [[
                'id' => $p->id, 'full_name' => $p->full_name, 'party' => 'Independent',
                'status' => 'running', 'is_running' => true, 'scrape_source' => 'ballotpedia',
                'external_candidate_id' => 'Jamie_Carter',
            ]],
        ]]]));
    });
    $this->getJson(comparisonUrl($p))->assertOk()->assertJsonPath('available', true);
    $record->update(['political_office' => 'Lieutenant Governor']);
    $this->getJson(comparisonUrl($p))->assertOk()->assertJsonPath('available', false);
    $record->update(['political_office' => 'Governor', 'election_date' => now()->addDays(91)->toDateString()]);
    $this->getJson(comparisonUrl($p))->assertOk()->assertJsonPath('available', false);
});


test('regular House primary dates enable comparison during their own 90 day window', function () {
    $this->travelTo(\Carbon\Carbon::parse('2026-04-01 12:00:00'));
    $p = comparisonProfile('Jamie Carter', 'CA-03');
    \App\Models\StateElectionDate::create(['state' => 'CA', 'election_year' => 2026, 'stage_name' => 'Primary', 'election_date' => '2026-06-02', 'source' => 'civic']);
    $this->getJson(comparisonUrl($p))->assertOk()->assertJsonPath('available', true)
        ->assertJsonPath('election.stage', 'Primary');
});

test('an odd-year state election does not establish a regular House election', function () {
    $this->travelTo(\Carbon\Carbon::parse('2027-09-19 12:00:00'));
    $p = comparisonProfile('Jamie Carter', 'CA-03');
    \App\Models\StateElectionDate::query()->update(['election_year' => 2027, 'election_date' => '2027-11-02']);
    $this->getJson(comparisonUrl($p))->assertOk()->assertJsonPath('available', false);
});
