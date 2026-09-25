<?php

use App\Models\BallotMeasure;
use App\Models\BallotMeasureCommittee;
use App\Models\ElectionDataSource;
use App\Models\User;
use App\Models\Voter;
use App\Support\MeasureCommitteePriority;
use App\Support\MeasureCommitteeRules;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'voter', 'guard_name' => 'web']);
});

function committeeAdmin(): User
{
    $admin = User::factory()->create(['platform' => 'standalone', 'user_type' => 'admin']);
    $admin->assignRole('admin', 'staff:Legacy administrator');
    skipOnboarding($admin, 'admin');

    return $admin;
}

function committeeMeasure(array $over = []): BallotMeasure
{
    return BallotMeasure::create($over + [
        'state' => 'CA', 'level' => 'state', 'measure_number' => '40',
        'title' => 'Proposition 40: Imposes One-Time Tax on Certain Taxpayers',
        'status' => 'upcoming', 'election_date' => now()->addDays(40)->toDateString(),
    ]);
}

function committeeLink(BallotMeasure $measure, array $over = []): BallotMeasureCommittee
{
    return BallotMeasureCommittee::create($over + [
        'ballot_measure_id' => $measure->id, 'state' => $measure->state,
        'committee_id' => '1486767', 'committee_name' => 'Building a Better California',
        'position' => 'oppose', 'source_url' => 'https://cal-access.sos.ca.gov/Campaign/Committees/Detail.aspx?id=1486767',
        'status' => 'pending',
    ]);
}

function caFinanceRegistry(): void
{
    ElectionDataSource::create([
        'ocd_id' => 'ocd-division/country:us/state:ca', 'level' => 'state', 'state' => 'CA',
        'jurisdiction_name' => 'California', 'campaign_finance_url' => 'https://cal-access.sos.ca.gov/',
    ]);
}

// ── Rules ───────────────────────────────────────────────────────────────

it('blocks malformed links at the model layer', function () {
    $measure = committeeMeasure();

    expect(fn () => committeeLink($measure, ['position' => 'neutral']))->toThrow(InvalidArgumentException::class)
        ->and(fn () => committeeLink($measure, ['source_url' => 'not a url']))->toThrow(InvalidArgumentException::class)
        ->and(fn () => committeeLink($measure, ['committee_id' => 'id with spaces']))->toThrow(InvalidArgumentException::class);
});

it('flags a committee name that points at a different measure or the other side', function () {
    $measure = committeeMeasure();

    $wrongMeasure = committeeLink($measure, ['committee_id' => 'A1', 'committee_name' => 'No on Prop 41']);
    $wrongSide = committeeLink($measure, ['committee_id' => 'A2', 'committee_name' => 'Yes on 40, Tax Fairness Now', 'position' => 'oppose']);
    $fine = committeeLink($measure, ['committee_id' => 'A3', 'committee_name' => 'No on Prop 40']);

    expect(MeasureCommitteeRules::flags($wrongMeasure))->toContain('name_measure_conflict')
        ->and(MeasureCommitteeRules::flags($wrongSide))->toContain('name_position_conflict')
        ->and(MeasureCommitteeRules::flags($fine))->toBe([]);
});

it('does not read ordinary words as measure letters', function () {
    $measure = committeeMeasure();
    $link = committeeLink($measure, ['committee_name' => 'Californians for a Question of Fairness']);

    expect(MeasureCommitteeRules::flags($link))->not->toContain('name_measure_conflict');
});

it('flags evidence that is off the state\'s registered finance site', function () {
    caFinanceRegistry();
    $measure = committeeMeasure();

    $official = committeeLink($measure, ['source_url' => 'https://powersearch.sos.ca.gov/committee?id=1486767']);
    $offSite = committeeLink($measure, ['committee_id' => 'X9', 'committee_name' => 'Other Committee', 'source_url' => 'https://example.com/post']);

    expect(MeasureCommitteeRules::flags($official))->not->toContain('source_off_registry')
        ->and(MeasureCommitteeRules::flags($offSite))->toContain('source_off_registry');
});

it('never verifies a link whose state does not match the measure', function () {
    $measure = committeeMeasure();

    expect(fn () => committeeLink($measure, ['state' => 'NV', 'status' => 'verified']))
        ->toThrow(InvalidArgumentException::class);
});

it('allows one committee on only one side of a measure', function () {
    $measure = committeeMeasure();
    committeeLink($measure);

    expect(fn () => committeeLink($measure, ['position' => 'support']))
        ->toThrow(UniqueConstraintViolationException::class);
});

// ── Admin ───────────────────────────────────────────────────────────────

it('verifies a confirmed, clean link on entry', function () {
    $measure = committeeMeasure();

    $this->actingAs($admin = committeeAdmin())
        ->post(route('admin.ballot-measures.committees.store', $measure), [
            'committee_id' => '1486767', 'committee_name' => 'Building a Better California',
            'position' => 'oppose', 'source_url' => 'https://cal-access.sos.ca.gov/x', 'confirmed' => '1',
        ])->assertRedirect();

    $link = BallotMeasureCommittee::sole();
    expect($link->status)->toBe('verified')
        ->and($link->state)->toBe('CA')
        ->and($link->verified_by_user_id)->toBe($admin->id);
});

it('holds a flagged link for review even when confirmed, and scores it', function () {
    $measure = committeeMeasure();

    $this->actingAs(committeeAdmin())
        ->post(route('admin.ballot-measures.committees.store', $measure), [
            'committee_id' => 'B2', 'committee_name' => 'No on Prop 41',
            'position' => 'oppose', 'source_url' => 'https://cal-access.sos.ca.gov/x', 'confirmed' => '1',
        ])->assertSessionHas('warning');

    $link = BallotMeasureCommittee::sole();
    // Election in 40 days (severity 5) × one link with this flag (1) × detectability 2.
    expect($link->status)->toBe('pending')
        ->and($link->integrity_flags)->toBe(['name_measure_conflict'])
        ->and($link->priority_score)->toBe(10);
});

it('lets a reviewer accept a soft flag, and shows the queue riskiest first', function () {
    $measure = committeeMeasure();
    $later = committeeMeasure(['measure_number' => '2', 'title' => 'Rainy Day Fund', 'election_date' => now()->addYear()->toDateString()]);
    $low = committeeLink($later, ['committee_name' => 'Rainy Day Friends', 'committee_id' => 'R1']);
    $high = committeeLink($measure, ['committee_name' => 'No on Prop 41', 'committee_id' => 'H1']);
    MeasureCommitteePriority::scorePending();

    $admin = committeeAdmin();
    $this->actingAs($admin)->get(route('admin.ballot-measure-committees.index'))
        ->assertOk()->assertSeeInOrder(['No on Prop 41', 'Rainy Day Friends']);

    $this->actingAs($admin)->post(route('admin.ballot-measure-committees.verify', $high))->assertRedirect();

    $high->refresh();
    expect($high->status)->toBe('verified')
        ->and($high->acknowledged_flags)->toBe(['name_measure_conflict'])
        ->and($high->openFlags())->toBe([]);
});

it('saves the state finance site on the civic source registry', function () {
    $measure = committeeMeasure();

    $this->actingAs(committeeAdmin())
        ->put(route('admin.ballot-measures.finance-url', $measure), ['campaign_finance_url' => 'https://cal-access.sos.ca.gov/'])
        ->assertRedirect();

    expect(MeasureCommitteeRules::financeRegistryUrl('CA'))->toBe('https://cal-access.sos.ca.gov/')
        ->and(ElectionDataSource::sole()->jurisdiction_name)->toBe('California');
});

// ── Audit (Control) ─────────────────────────────────────────────────────

it('returns a verified link to review when its measure drifts, and reports run metrics', function () {
    $measure = committeeMeasure();
    $link = committeeLink($measure, ['committee_name' => 'No on Prop 40', 'status' => 'verified', 'acknowledged_flags' => []]);

    // The measure is renumbered after the link was verified.
    $measure->update(['measure_number' => '41']);

    $this->artisan('ballot-measures:audit-committee-links')->assertExitCode(0);

    $link->refresh();
    expect($link->status)->toBe('pending')
        ->and($link->verified_at)->toBeNull()
        ->and($link->review_note)->toContain('name_measure_conflict')
        ->and($link->priority_score)->not->toBeNull();

    $metric = DB::table('politician_cleanup_run_metrics')->where('step', 'measure-committee-links')->sole();
    expect($metric->findings_count)->toBe(1)
        ->and($metric->auto_applied_count)->toBe(1)
        ->and(json_decode($metric->breakdown, true))->toBe(['name_measure_conflict' => 1]);
});

it('leaves a verified link alone when its reviewer already accepted the flag', function () {
    $measure = committeeMeasure();
    $link = committeeLink($measure, [
        'committee_name' => 'No on Prop 41', 'status' => 'verified',
        'integrity_flags' => ['name_measure_conflict'], 'acknowledged_flags' => ['name_measure_conflict'],
    ]);

    $this->artisan('ballot-measures:audit-committee-links')->assertExitCode(0);

    expect($link->refresh()->status)->toBe('verified');
});

it('writes nothing on a dry run', function () {
    $measure = committeeMeasure();
    $link = committeeLink($measure, ['committee_name' => 'No on Prop 40', 'status' => 'verified']);
    $measure->update(['measure_number' => '41']);

    $this->artisan('ballot-measures:audit-committee-links', ['--dry-run' => true])->assertExitCode(0);

    expect($link->refresh()->status)->toBe('verified')
        ->and(DB::table('politician_cleanup_run_metrics')->count())->toBe(0);
});

// ── Voter page ──────────────────────────────────────────────────────────

it('shows voters only verified committees, split by side, plus the official filings link', function () {
    caFinanceRegistry();
    $measure = committeeMeasure();
    committeeLink($measure, ['status' => 'verified']);
    committeeLink($measure, ['committee_id' => 'P1', 'committee_name' => 'Unchecked Yes Committee', 'position' => 'support']);

    $user = User::factory()->create(['platform' => 'standalone']);
    $user->assignRole('voter');
    Voter::factory()->create(['user_id' => $user->id, 'state' => 'CA', 'is_verified' => true, 'is_active' => true]);
    skipOnboarding($user, 'voter');

    $this->actingAs($user)->get(route('voter.ballot-measures.show', $measure))
        ->assertOk()
        ->assertSee("Who's Funding This", false)
        ->assertSee('Building a Better California')
        ->assertDontSee('Unchecked Yes Committee')
        ->assertSee('View official filings');
});
