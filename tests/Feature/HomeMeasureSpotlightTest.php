<?php

use App\Models\BallotMeasure;
use App\Models\BallotMeasureCommittee;
use App\Models\CommitteeDonor;
use App\Models\CommitteeFinanceSnapshot;
use App\Support\MeasureSpotlight;
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
