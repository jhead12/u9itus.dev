<?php

use App\Models\BallotMeasure;
use App\Models\BallotMeasureCommittee;
use App\Models\Committee;
use App\Models\CommitteeDonor;
use App\Models\CommitteeFinanceSnapshot;
use App\Models\CommitteeProfile;
use App\Models\Politician;
use App\Support\CandidateSpotlight;
use App\Support\MeasureSpotlight;
use App\Support\MoneySpotlight;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function spotlightMeasure(array $over = []): BallotMeasure
{
    return BallotMeasure::create($over + [
        'state' => 'CA', 'level' => 'city', 'county' => 'Alameda County', 'locality' => 'Oakland', 'measure_number' => 'A',
        'title' => 'Parcel Tax for Fire Services', 'summary' => 'A $98 annual parcel tax to fund fire stations.',
        'yes_meaning' => 'Property owners pay $98 a year for fire services.', 'no_meaning' => 'No new parcel tax.',
        'status' => 'upcoming', 'election_date' => now()->addDays(40)->toDateString(),
    ]);
}

function spotlightLink(BallotMeasure $measure, string $id, string $name, string $position, string $status = 'verified'): BallotMeasureCommittee
{
    return BallotMeasureCommittee::create([
        'ballot_measure_id' => $measure->id, 'state' => $measure->state, 'committee_id' => $id, 'committee_name' => $name,
        'position' => $position, 'source_url' => "https://cal-access.sos.ca.gov/Campaign/Committees/Detail.aspx?id={$id}", 'status' => $status,
    ]);
}

it('spotlights a local measure: what each vote means, who is pushing it, and who funds them', function () {
    $measure = spotlightMeasure();
    spotlightLink($measure, '1500100', 'Oaklanders for Fire Safety', 'support');
    spotlightLink($measure, '1500200', 'Taxpayers Against Measure A', 'oppose');
    CommitteeFinanceSnapshot::create([
        'state' => 'CA', 'committee_id' => '1500100', 'source' => 'cal-access', 'filing_id' => '1', 'form_type' => 'F460',
        'period_end' => now()->subDays(10)->toDateString(), 'contributions_ytd' => 250000, 'expenditures_ytd' => 90000,
    ]);
    CommitteeDonor::create(['state' => 'CA', 'committee_id' => '1500100', 'year' => now()->subDays(10)->year, 'donor_name' => 'Oakland Firefighters Local 55', 'amount' => 200000]);

    $this->get('/')
        ->assertOk()
        ->assertSee('Local measure spotlight')
        ->assertSee('Measure A: Parcel Tax for Fire Services')
        ->assertSee('Pushing for a YES vote')
        ->assertSee('Property owners pay $98 a year for fire services.')
        ->assertSeeInOrder(['Oaklanders for Fire Safety', 'Funded mostly by Oakland Firefighters Local 55', '$250,000 raised'])
        ->assertSee('Taxpayers Against Measure A');
});

it('shows only verified committees and skips measures with none', function () {
    $unchecked = spotlightMeasure(['title' => 'Unchecked Measure']);
    spotlightLink($unchecked, '1500300', 'Pending Committee', 'support', 'pending');

    expect(MeasureSpotlight::pick())->toBeNull();

    $measure = spotlightMeasure();
    spotlightLink($measure, '1500100', 'Oaklanders for Fire Safety', 'support');
    spotlightLink($measure, '1500400', 'Not Yet Checked', 'oppose', 'pending');

    $this->get('/')->assertSee('Oaklanders for Fire Safety')->assertDontSee('Not Yet Checked')
        ->assertSee('No committee has registered on this side yet.');
});

it('prefers a local measure in the visitor\'s state with both sides, and skips decided ones', function () {
    $decided = spotlightMeasure(['title' => 'Old Measure', 'status' => 'passed', 'election_date' => now()->subYear()->toDateString()]);
    spotlightLink($decided, '1', 'Old Committee', 'support');
    $statewide = spotlightMeasure(['level' => 'state', 'county' => null, 'locality' => null, 'measure_number' => '40', 'title' => 'Statewide Measure']);
    spotlightLink($statewide, '2', 'State Committee', 'support');
    $oneSided = spotlightMeasure(['title' => 'One-Sided Local']);
    spotlightLink($oneSided, '3', 'Only Yes', 'support');
    $bothSides = spotlightMeasure(['title' => 'Contested Local', 'election_date' => now()->addDays(80)->toDateString()]);
    spotlightLink($bothSides, '4', 'Yes Side', 'support');
    spotlightLink($bothSides, '5', 'No Side', 'oppose');
    $elsewhere = spotlightMeasure(['state' => 'TX', 'county' => 'Travis County', 'locality' => 'Austin', 'title' => 'Austin Measure']);
    spotlightLink($elsewhere, '00085302', 'Save Austin Now PAC', 'oppose');

    expect(MeasureSpotlight::pick()['measure']->title)->toBe('Contested Local')
        ->and(MeasureSpotlight::pick('TX')['measure']->title)->toBe('Austin Measure');
});

it('falls back to a statewide measure when no local measure has verified committees', function () {
    $statewide = spotlightMeasure(['level' => 'state', 'county' => null, 'locality' => null, 'measure_number' => '40', 'title' => 'Proposition 40: One-Time Tax']);
    spotlightLink($statewide, '1486767', 'Building a Better California', 'oppose');

    $spotlight = MeasureSpotlight::pick();
    expect($spotlight['is_local'])->toBeFalse();

    $this->get('/')->assertSee('Measure spotlight')->assertDontSee('Local measure spotlight')
        ->assertSee('Proposition 40: One-Time Tax')->assertDontSee('Measure 40: Proposition 40');
});

it('keeps the section hidden when there is nothing to show', function () {
    $this->get('/')->assertOk()->assertDontSee('Spending to Sway');
});

// ── Candidates in the rotation ──────────────────────────────────────────

function spotlightCandidate(array $over = []): Politician
{
    return Politician::factory()->create($over + [
        'full_name' => 'Maria Alvarez', 'slug' => 'maria-alvarez-'.fake()->unique()->numerify('####'),
        'state' => 'AZ', 'district' => '6', 'political_office' => 'U.S. House', 'party_affiliation' => 'Democratic',
        'fec_candidate_id' => 'H6AZ06123', 'is_running_candidate' => true, 'page_published' => true, 'is_active' => true,
    ]);
}

/** A super PAC whose FEC independent expenditures support or oppose a candidate. */
function spotlightPac(string $name, Politician $candidate, string $side, float $amount, string $purpose): Committee
{
    $committee = Committee::create([
        'fec_committee_id' => 'C'.fake()->unique()->numerify('########'), 'name' => $name,
        'first_seen_at' => now(), 'last_seen_at' => now(),
    ]);

    CommitteeProfile::create([
        'committee_id' => $committee->id, 'fec_committee_id' => $committee->fec_committee_id,
        'committee_type' => 'O', 'is_super_pac' => true, 'cycle' => 2026, 'independent_expenditures' => $amount,
        'spending_by_race' => [[
            'politician_id' => $candidate->id, 'politician_slug' => $candidate->slug, 'candidate_fec_id' => $candidate->fec_candidate_id,
            'candidate_name' => strtoupper($candidate->full_name), 'office' => 'House', 'state' => $candidate->state, 'district' => $candidate->district,
            'support' => $side === 'support' ? $amount : 0, 'oppose' => $side === 'oppose' ? $amount : 0,
        ]],
        'recent_expenditures' => [[
            'candidate_fec_id' => $candidate->fec_candidate_id, 'candidate_name' => strtoupper($candidate->full_name),
            'support_oppose' => $side === 'support' ? 'S' : 'O', 'amount' => $amount, 'date' => '2026-09-01', 'purpose' => $purpose,
        ]],
        'enriched_at' => now(),
    ]);

    return $committee;
}

it('shows the groups spending to elect and to defeat a candidate, and what they pay for', function () {
    $candidate = spotlightCandidate();
    spotlightPac('Arizona Forward Action', $candidate, 'support', 1200000, 'DIGITAL ADVERTISING');
    spotlightPac('Desert Taxpayers Fund', $candidate, 'oppose', 800000, 'MAILERS');

    $this->get('/')
        ->assertOk()
        ->assertSee('Candidate spotlight')
        ->assertSee('Maria Alvarez')
        ->assertSeeInOrder(['Spending to elect Maria', 'Arizona Forward Action', 'Paying for: Digital advertising', '$1,200,000 in outside spending'])
        ->assertSeeInOrder(['Spending to defeat Maria', 'Desert Taxpayers Fund', 'Paying for: Mailers']);
});

it('leaves out candidates who are not running or whose profile is not published', function () {
    spotlightPac('Some PAC', spotlightCandidate(['is_running_candidate' => false, 'fec_candidate_id' => 'H6AZ00001']), 'support', 1000, 'ADS');
    spotlightPac('Other PAC', spotlightCandidate(['page_published' => false, 'fec_candidate_id' => 'H6AZ00002']), 'oppose', 1000, 'ADS');

    expect(CandidateSpotlight::pool())->toBe([]);
});

it('puts contested candidates first, then the visitor\'s state', function () {
    $oneSided = spotlightCandidate(['full_name' => 'Big Spender', 'fec_candidate_id' => 'H6AZ00003']);
    spotlightPac('Huge PAC', $oneSided, 'support', 9000000, 'TV');
    $contested = spotlightCandidate(['full_name' => 'Close Race', 'fec_candidate_id' => 'H6AZ00004']);
    spotlightPac('Pro PAC', $contested, 'support', 1000, 'TV');
    spotlightPac('Anti PAC', $contested, 'oppose', 1000, 'TV');
    $texan = spotlightCandidate(['full_name' => 'Lone Star', 'state' => 'TX', 'fec_candidate_id' => 'H6TX00005']);
    spotlightPac('Tex Pro', $texan, 'support', 500, 'TV');
    spotlightPac('Tex Anti', $texan, 'oppose', 500, 'TV');

    expect(collect(CandidateSpotlight::pool())->pluck('politician.full_name')->all())->toBe(['Close Race', 'Lone Star', 'Big Spender'])
        ->and(CandidateSpotlight::pool('TX')[0]['politician']->full_name)->toBe('Lone Star');
});

it('rotates between the measure and candidates each half hour', function () {
    $measure = spotlightMeasure();
    spotlightLink($measure, '1500100', 'Oaklanders for Fire Safety', 'support');
    $candidate = spotlightCandidate();
    spotlightPac('Arizona Forward Action', $candidate, 'support', 1200000, 'DIGITAL ADVERTISING');

    $slot = MoneySpotlight::SLOT_SECONDS;
    $types = collect(range(0, 3))->map(fn ($i) => MoneySpotlight::current(null, $i * $slot)['type'])->all();

    expect($types)->toBe(['measure', 'candidate', 'measure', 'candidate']);
});
