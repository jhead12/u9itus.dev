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
    // Texas holds a 2026 Senate race (config/election_races.php); California does not.
    \App\Models\StateElectionDate::create(['state' => 'TX', 'election_year' => 2026, 'stage_name' => 'General', 'election_date' => '2026-11-03', 'source' => 'civic']);
    $senate = ['political_office' => 'U.S. Senator', 'district' => null, 'state' => 'TX'];
    $p = comparisonProfile('Jamie Carter', '', $senate);
    comparisonProfile('Alex Rivera', '', $senate);
    $sitting = comparisonProfile('Robin Nelson', '', $senate + ['term_status' => 'seated', 'is_running_candidate' => false]);
    $this->getJson(comparisonUrl($p))->assertOk()->assertJsonPath('available', true)
        ->assertJsonPath('seat.label', 'U.S. Senate · TX')
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

test('a statewide office the race calendar does not cover requires its own dated candidate record within the window', function () {
    $p = comparisonProfile('Jamie Carter', '', ['political_office' => 'Attorney General', 'governance_level' => 'State']);
    $record = \App\Models\ElectionCandidateRecord::create([
        'source' => 'ballotpedia', 'external_candidate_id' => 'Jamie_Carter', 'full_name' => 'Jamie Carter',
        'state' => 'CA', 'political_office' => 'Attorney General', 'governance_level' => 'State',
        'election_date' => '2026-11-03',
    ]);
    $this->mock(\App\Http\Controllers\Api\MapStateCandidatesController::class, function ($mock) use ($p) {
        $mock->shouldReceive('__invoke')->andReturn(response()->json(['offices' => [[
            'office' => 'Attorney General', 'candidates' => [[
                'id' => $p->id, 'full_name' => $p->full_name, 'party' => 'Independent',
                'status' => 'running', 'is_running' => true, 'scrape_source' => 'ballotpedia',
                'external_candidate_id' => 'Jamie_Carter',
            ]],
        ]]]));
    });
    $this->getJson(comparisonUrl($p))->assertOk()->assertJsonPath('available', true);
    $record->update(['political_office' => 'Secretary of State']);
    $this->getJson(comparisonUrl($p))->assertOk()->assertJsonPath('available', false);
    $record->update(['political_office' => 'Attorney General', 'election_date' => now()->addDays(91)->toDateString()]);
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

test('research comparison is year round while the default remains election gated', function () {
    $p = comparisonProfile('Jamie Carter', 'CA-03');
    comparisonProfile('Alex Rivera', 'CA-03');
    $this->travelTo(\Carbon\Carbon::parse('2026-01-01'));
    $this->getJson(comparisonUrl($p))->assertOk()->assertJsonPath('available', false);
    $this->getJson(comparisonUrl($p, ['context' => 'research']))->assertOk()
        ->assertJsonPath('available', true)->assertJsonCount(2, 'candidates')
        ->assertJsonPath('seat.district', 'CA-03')->assertJsonPath('election.date', '2026-11-03');
});

test('research comparison works without an election and excludes other seats and former records', function () {
    $p = comparisonProfile('Jamie Carter', 'CA-03');
    comparisonProfile('Alex Rivera', 'CA-03');
    comparisonProfile('Morgan Parker', 'CA-04');
    comparisonProfile('Robin Nelson', 'CA-03', ['term_status' => 'former']);
    \App\Models\StateElectionDate::query()->delete();
    $this->getJson(comparisonUrl($p, ['context' => 'research']))->assertOk()
        ->assertJsonPath('available', true)->assertJsonPath('election', null)->assertJsonCount(2, 'candidates');
});

test('research context retains publication restrictions', function () {
    $p = comparisonProfile('Jamie Carter', 'CA-03', ['page_published' => false]);
    $p->initiatives()->create(['title' => 'Housing', 'description' => 'Private statement', 'is_published' => true]);
    $response = $this->getJson(comparisonUrl($p, ['context' => 'research']))->assertOk();
    $response->assertDontSee('Private statement');
});

test('comparison rejects unknown context values', function () {
    $p = comparisonProfile('Jamie Carter', 'CA-03');
    $this->getJson(comparisonUrl($p, ['context' => 'unknown']))->assertUnprocessable();
});

test('research refuses ambiguous names and senate pools without seat identifiers', function () {
    $p = comparisonProfile('Jamie Carter', 'CA-03');
    comparisonProfile('Jamie Carter', 'CA-04', ['slug' => 'jamie-carter-other']);
    $this->getJson('/api/v1/map/candidate-comparison?state=CA&full_name=Jamie%20Carter&context=research')
        ->assertOk()->assertJsonPath('available', false)->assertJsonPath('seat', null);
});

test('research opens a Senate race any time before its confirmed election, only for people running in it', function () {
    \App\Models\StateElectionDate::create(['state' => 'TX', 'election_year' => 2026, 'stage_name' => 'General', 'election_date' => '2026-11-03', 'source' => 'civic']);
    $senate = ['political_office' => 'U.S. Senator', 'district' => null, 'state' => 'TX'];
    $running = comparisonProfile('Alex Rivera', '', $senate);
    comparisonProfile('Jamie Carter', '', $senate);
    $notUp = comparisonProfile('Robin Nelson', '', $senate + ['term_status' => 'seated', 'is_running_candidate' => false]);
    // The election is 45 days past the 90-day map window.
    $this->travelTo(\Carbon\Carbon::parse('2026-06-21 12:00:00'));
    $this->getJson(comparisonUrl($running))->assertOk()->assertJsonPath('available', false);
    $this->getJson(comparisonUrl($running, ['context' => 'research']))->assertOk()->assertJsonPath('available', true)
        ->assertJsonPath('election.date', '2026-11-03')->assertJsonCount(2, 'candidates');
    $this->getJson(comparisonUrl($notUp, ['context' => 'research']))->assertOk()->assertJsonPath('available', false);
    \App\Models\StateElectionDate::query()->delete();
    $this->getJson(comparisonUrl($running, ['context' => 'research']))->assertOk()->assertJsonPath('available', false)
        ->assertJsonCount(0, 'candidates');
});

test('research keeps a resolved seat available after a shared anchor disappears', function () {
    comparisonProfile('Alex Rivera', 'CA-03');
    $this->getJson('/api/v1/map/candidate-comparison?state=CA&district=CA-03&full_name=Missing%20Person&context=research')
        ->assertOk()->assertJsonPath('available', true)->assertJsonPath('selected_key', null)->assertJsonCount(1, 'candidates');
});

test('research resolves the district formats offered by public search', function (string $district) {
    $p = comparisonProfile('Jamie Carter', 'CA-03');
    comparisonProfile('Alex Rivera', 'CA-03');
    $this->getJson(comparisonUrl($p, ['context' => 'research', 'district' => $district]))
        ->assertOk()->assertJsonPath('seat.district', 'CA-03')->assertJsonCount(2, 'candidates');
})->with(['District 3', 'CD-03', 'CD 3', 'CA-03']);

test('races lists only seats with running candidates, ready to open with them selected', function () {
    $carter = comparisonProfile('Jamie Carter', 'CA-03');
    $rivera = comparisonProfile('Alex Rivera', 'CA-3', ['party_affiliation' => 'Republican']);
    comparisonProfile('Taylor Morgan', 'CA-04', ['term_status' => 'seated', 'is_running_candidate' => false]);
    comparisonProfile('Jordan Baker', 'CA-03', ['term_status' => 'lost']);
    $governor = ['political_office' => 'Governor', 'governance_level' => 'State', 'district' => null];
    $incumbent = comparisonProfile('Morgan Parker', '', $governor + ['term_status' => 'seated', 'is_running_candidate' => false, 'party_affiliation' => null]);
    \App\Models\ElectionCandidateRecord::create(['source' => 'ballotpedia', 'external_candidate_id' => 'parker-gov', 'full_name' => $incumbent->full_name,
        'state' => 'CA', 'political_office' => 'Governor', 'governance_level' => 'State', 'party_affiliation' => 'Green', 'election_date' => '2026-11-03']);

    $races = $this->getJson('/api/v1/map/candidate-races?state=CA')->assertOk()->json('races');

    expect(array_column($races, 'label'))->toBe(['Governor · CA', 'U.S. House · CA-03'])
        ->and($races[0]['election'])->toBe(['date' => '2026-11-03', 'stage' => 'Election'])
        ->and($races[0]['running'])->toBe([['key' => 'profile:'.$incumbent->id, 'full_name' => 'Morgan Parker', 'party' => 'Green']])
        ->and($races[0]['params'])->toMatchArray(['state' => 'CA', 'office' => 'Governor', 'full_name' => 'Morgan Parker'])
        ->and($races[1]['election'])->toBe(['date' => '2026-11-03', 'stage' => 'General'])
        ->and(array_column($races[1]['running'], 'full_name'))->toBe(['Alex Rivera', 'Jamie Carter'])
        ->and($races[1]['params'])->toBe(['state' => 'CA', 'office' => 'U.S. Representative', 'district' => 'CA-03',
            'selected' => 'profile:'.$rivera->id.',profile:'.$carter->id]);

    // The race's params open the same seat and selection on the comparison endpoint.
    $params = $races[1]['params'];
    unset($params['selected']);
    $keys = array_column($this->getJson('/api/v1/map/candidate-comparison?'.http_build_query($params + ['context' => 'research']))->json('candidates'), 'key');
    expect($keys)->toContain('profile:'.$rivera->id, 'profile:'.$carter->id);
    $this->getJson('/api/v1/map/candidate-races?state=XX')->assertUnprocessable();
});

test('races include House challengers from candidate filings the map already vouches for, but not eliminated ones', function () {
    comparisonProfile('Jamie Carter', 'CA-03', ['term_status' => 'seated', 'is_running_candidate' => false]);
    foreach ([['Alex Rivera', 'advanced_to_general'], ['Morgan Parker', 'eliminated']] as [$name, $result]) {
        \App\Models\ElectionCandidateRecord::create(['source' => 'ballotpedia', 'external_candidate_id' => $name, 'full_name' => $name, 'state' => 'CA',
            'political_office' => 'U.S. Representative', 'governance_level' => 'Federal', 'district' => 'CA-03',
            'party_affiliation' => 'Independent', 'election_date' => '2026-11-03', 'payload' => ['primary_result' => $result]]);
    }
    $races = $this->getJson('/api/v1/map/candidate-races?state=CA')->assertOk()->json('races');
    expect($races)->toHaveCount(1)
        ->and(array_column($races[0]['running'], 'full_name'))->toBe(['Alex Rivera'])
        ->and($races[0]['running'][0]['key'])->toStartWith('record:');
});

test('comparison lists each profile\'s latest verified coverage and press releases, never rejected, stale, archive, or name-only matches', function () {
    $carter = comparisonProfile('Jamie Carter', 'CA-03');
    $rivera = comparisonProfile('Alex Rivera', 'CA-03');
    $article = fn (array $a) => \App\Models\CandidateNewsArticle::create(array_merge([
        'politician_id' => $carter->id, 'candidate_name' => 'Jamie Carter', 'source_name' => 'Daily News', 'provider' => 'google_news',
        'content_type' => 'news', 'verification_status' => 'verified', 'published_at' => '2026-09-01', 'source_hash' => md5(json_encode($a)),
    ], $a));
    foreach (range(1, 4) as $day) $article(['headline' => "Carter story $day - Daily News", 'source_url' => "https://news.example.com/$day", 'published_at' => "2026-09-0$day"]);
    $article(['headline' => 'Carter statement', 'source_name' => 'Carter campaign', 'source_url' => 'https://carter.example.com/p', 'content_type' => 'press_release']);
    $article(['headline' => 'Rejected match', 'source_url' => 'https://news.example.com/rejected', 'verification_status' => 'rejected', 'published_at' => '2026-09-18']);
    $article(['headline' => 'Old story', 'source_url' => 'https://news.example.com/old', 'published_at' => '2025-01-01']);
    $article(['headline' => 'Jamie Carter Archives', 'source_url' => 'https://news.example.com/tag', 'published_at' => '2026-09-18']);
    $article(['headline' => 'Jamie Carter Archives - Daily News', 'source_url' => 'https://news.example.com/tag2', 'published_at' => '2026-09-18']);
    $article(['headline' => 'Press Release: Carter backs bill', 'source_name' => 'Aggregator', 'source_url' => 'https://agg.example.com/r', 'published_at' => '2026-09-17']);
    $article(['headline' => 'Carter backs bill', 'source_name' => 'Carter campaign', 'source_url' => 'https://carter.example.com/r2', 'content_type' => 'press_release', 'published_at' => '2026-09-16']);
    $article(['politician_id' => null, 'candidate_name' => 'Alex Rivera', 'headline' => 'Another Alex Rivera', 'source_url' => 'https://news.example.com/other']);

    $candidates = collect($this->getJson(comparisonUrl($carter, ['context' => 'research']))->assertOk()->json('candidates'))->keyBy('full_name');
    expect(array_column($candidates['Jamie Carter']['news']['coverage'], 'headline'))->toBe(['Carter story 4', 'Carter story 3', 'Carter story 2'])
        ->and($candidates['Jamie Carter']['news']['coverage'][0])->toBe(['headline' => 'Carter story 4', 'source_name' => 'Daily News', 'source_url' => 'https://news.example.com/4', 'published_at' => '2026-09-04'])
        ->and(array_column($candidates['Jamie Carter']['news']['press_releases'], 'headline'))->toBe(['Press Release: Carter backs bill', 'Carter statement'])
        ->and($candidates['Alex Rivera']['news'])->toBe(['coverage' => [], 'press_releases' => []]);
});

test('a governor race the race calendar lists uses the state election date when candidate records carry none', function () {
    $governor = ['political_office' => 'Governor', 'governance_level' => 'State', 'district' => null];
    $running = comparisonProfile('Alex Rivera', '', $governor);
    comparisonProfile('Jamie Carter', '', $governor);
    \App\Models\ElectionCandidateRecord::create(['source' => 'manual_correction', 'external_candidate_id' => 'rivera', 'full_name' => 'Alex Rivera',
        'state' => 'CA', 'political_office' => 'Governor', 'governance_level' => 'State', 'election_date' => null]);

    // California holds a 2026 governor's race: dated from its general election, in both modes.
    $this->getJson(comparisonUrl($running))->assertOk()->assertJsonPath('available', true)
        ->assertJsonPath('election', ['date' => '2026-11-03', 'stage' => 'General']);
    $this->getJson(comparisonUrl($running, ['context' => 'research']))->assertOk()
        ->assertJsonPath('election', ['date' => '2026-11-03', 'stage' => 'General']);
    $races = collect($this->getJson('/api/v1/map/candidate-races?state=CA')->json('races'))->keyBy('label');
    expect($races['Governor · CA']['election'])->toBe(['date' => '2026-11-03', 'stage' => 'General']);

    // New Jersey elects its governor in odd years: no 2026 date, and the map tab stays election-gated.
    \App\Models\StateElectionDate::create(['state' => 'NJ', 'election_year' => 2026, 'stage_name' => 'General', 'election_date' => '2026-11-03', 'source' => 'civic']);
    $nj = comparisonProfile('Morgan Parker', '', $governor + ['state' => 'NJ']);
    $this->getJson(comparisonUrl($nj))->assertOk()->assertJsonPath('available', false);
    $this->getJson(comparisonUrl($nj, ['context' => 'research']))->assertOk()->assertJsonPath('election', null);
});

test('a Senate comparison is unavailable in a state the race calendar says has no Senate race that year', function () {
    $senator = comparisonProfile('Alex Rivera', '', ['political_office' => 'U.S. Senator', 'district' => null]);
    comparisonProfile('Jamie Carter', '', ['political_office' => 'U.S. Senator', 'district' => null]);
    $this->getJson(comparisonUrl($senator))->assertOk()->assertJsonPath('available', false);
    $this->getJson(comparisonUrl($senator, ['context' => 'research']))->assertOk()->assertJsonPath('available', false)
        ->assertJsonPath('message', 'This state has two Senate seats. We can compare Senate candidates once an upcoming election for this seat is confirmed.');
});
