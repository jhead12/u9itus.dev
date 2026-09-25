<?php

use App\Models\BallotMeasure;
use App\Models\BallotMeasureCommittee;
use App\Models\CommitteeDonor;
use App\Models\CommitteeFiler;
use App\Models\CommitteeFinanceSnapshot;
use App\Models\CommitteeTransfer;
use App\Models\User;
use App\Models\Voter;
use App\Support\MeasureCommitteeRules;
use App\Support\MeasureFunding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

const CVR_HEADER = ['FILING_ID', 'AMEND_ID', 'REC_TYPE', 'FORM_TYPE', 'FILER_ID', 'FILER_NAML', 'RPT_DATE', 'FROM_DATE', 'THRU_DATE', 'BAL_NAME', 'BAL_NUM', 'BAL_JURIS', 'SUP_OPP_CD'];
const SMRY_HEADER = ['FILING_ID', 'AMEND_ID', 'LINE_ITEM', 'REC_TYPE', 'FORM_TYPE', 'AMOUNT_A', 'AMOUNT_B', 'AMOUNT_C', 'ELEC_DT'];
const RCPT_HEADER = ['FILING_ID', 'AMEND_ID', 'LINE_ITEM', 'REC_TYPE', 'FORM_TYPE', 'TRAN_ID', 'ENTITY_CD', 'CTRIB_NAML', 'CTRIB_NAMF', 'CTRIB_EMP', 'AMOUNT', 'CMTE_ID', 'MEMO_CODE'];
const S497_HEADER = ['FILING_ID', 'AMEND_ID', 'LINE_ITEM', 'REC_TYPE', 'FORM_TYPE', 'TRAN_ID', 'ENTITY_CD', 'ENTY_NAML', 'ENTY_NAMF', 'CTRIB_EMP', 'CTRIB_DATE', 'AMOUNT', 'CMTE_ID', 'BAL_NAME', 'BAL_NUM', 'BAL_JURIS', 'SUP_OPP_CD', 'MEMO_CODE'];

/**
 * A miniature CAL-ACCESS export. Building a Better California's cover and summary rows,
 * its largest itemized donors, and its transfer to the "No on Prop 40" committee are based
 * on the real September 25, 2026 export (filing 3199914 is the Form 460 in the viral
 * screenshot); the late contribution after its statement period is invented.
 *
 * @param  list<list<string>>  $extraCvr
 * @param  list<list<string>>  $extraSmry
 * @param  list<list<string>>  $extraRcpt
 * @param  list<list<string>>  $extraS497
 */
function calAccessZip(array $extraCvr = [], array $extraSmry = [], array $extraRcpt = [], array $extraS497 = []): string
{
    $cvr = [
        CVR_HEADER,
        ['3181389', '0', 'CVR', 'F460', '1486767', 'BUILDING A BETTER CALIFORNIA', '7/31/2026 12:00:00 AM', '1/1/2026 12:00:00 AM', '6/30/2026 12:00:00 AM', '', '', '', ''],
        ['3181389', '1', 'CVR', 'F460', '1486767', 'BUILDING A BETTER CALIFORNIA', '8/25/2026 12:00:00 AM', '1/1/2026 12:00:00 AM', '6/30/2026 12:00:00 AM', '', '', '', ''],
        ['3196949', '1', 'CVR', 'F497', '1486767', 'BUILDING A BETTER CALIFORNIA', '9/21/2026 12:00:00 AM', '', '', '', '', '', ''],
        ['3199914', '0', 'CVR', 'F460', '1486767', 'BUILDING A BETTER CALIFORNIA', '9/24/2026 12:00:00 AM', '7/1/2026 12:00:00 AM', '9/19/2026 12:00:00 AM', '', '', '', ''],
        ['3200500', '0', 'CVR', 'F497', '1486767', 'BUILDING A BETTER CALIFORNIA', '9/23/2026 12:00:00 AM', '', '', '', '', '', ''],
        // An unrelated filer the import must skip.
        ['578351', '0', 'CVR', 'F460', '991755', 'Scott for State Senate', '1/27/2000 12:00:00 AM', '1/1/2000 12:00:00 AM', '1/22/2000 12:00:00 AM', '', '', '', ''],
        ...$extraCvr,
    ];
    $smry = [
        SMRY_HEADER,
        ['3181389', '0', '5', 'SMRY', 'F460', '118026201.51', '118026201.51', '', ''],
        ['3181389', '1', '4', 'SMRY', 'F460', '98619.40', '98619.40', '', ''],
        ['3181389', '1', '5', 'SMRY', 'F460', '118124820.91', '118124820.91', '', ''],
        ['3181389', '1', '11', 'SMRY', 'F460', '117994857.56', '117994857.56', '', ''],
        ['3181389', '1', '16', 'SMRY', 'F460', '276000.67', '', '', ''],
        ['3199914', '0', '1', 'SMRY', 'F460', '76159501.23', '194185702.74', '', ''],
        ['3199914', '0', '4', 'SMRY', 'F460', '32422350.84', '32520970.24', '', ''],
        ['3199914', '0', '5', 'SMRY', 'F460', '108581852.07', '226706672.98', '', ''],
        ['3199914', '0', '11', 'SMRY', 'F460', '110796941.20', '228791798.76', '', ''],
        ['3199914', '0', '16', 'SMRY', 'F460', '30349927.59', '', '', ''],
        ['578351', '0', '5', 'SMRY', 'F460', '999', '999', '', ''],
        ...$extraSmry,
    ];
    $rcpt = [
        RCPT_HEADER,
        ['3199914', '0', '1', 'RCPT', 'A', 'A1', 'IND', 'BRIN', 'SERGEY', '', '20000000', '', ''],
        ['3199914', '0', '2', 'RCPT', 'C', 'C1', 'IND', 'KHOSLA', 'VINOD', '', '12561504', '', ''],
        ['3199914', '0', '3', 'RCPT', 'A', 'A2', 'IND', 'DOERR, III', 'L. JOHN', 'KLEINER PERKINS', '12500000', '', ''],
        // Schedule I (miscellaneous increases) and memo entries aren't donors.
        ['3199914', '0', '4', 'RCPT', 'I', 'I1', 'OTH', 'MORGAN STANLEY', '', '', '12542669.79', '', ''],
        ['3199914', '0', '5', 'RCPT', 'A', 'A3', 'IND', 'MEMO', 'ONLY', '', '999999', '', 'X'],
        // Superseded by amendment 1.
        ['3181389', '0', '1', 'RCPT', 'A', 'A9', 'IND', 'OLD', 'AMENDMENT', '', '5', '', ''],
        ['3181389', '1', '1', 'RCPT', 'A', 'A9', 'IND', 'BRIN', 'SERGEY', '', '82000000', '', ''],
        ...$extraRcpt,
    ];
    $s497 = [
        S497_HEADER,
        // Received before the statement closed: already on the Form 460, so not "late".
        ['3196949', '1', '1', 'S497', 'F497P1', 'INC1', 'IND', 'LARSEN', 'CHRIS', 'RIPPLE, INC.', '8/10/2026 12:00:00 AM', '10011844.80', '', '', '', '', '', ''],
        ['3196949', '1', '2', 'S497', 'F497P2', 'EXP1', 'COM', 'NO ON PROP 40 - DOCTORS, TEACHERS, SMALL BUSINESSES', '', '', '9/15/2026 12:00:00 AM', '5000000', '1492108', 'PROPOSITION 40', '', 'STATEWIDE', '', ''],
        ['3200500', '0', '1', 'S497', 'F497P1', 'INC2', 'IND', 'MEHTA', 'NEIL', 'GREENOAKS CAPITAL', '9/22/2026 12:00:00 AM', '1000000', '', '', '', '', '', ''],
        ...$extraS497,
    ];

    $tsv = fn (array $rows) => implode('', array_map(fn ($r) => implode("\t", $r)."\r\n", $rows));
    $path = tempnam(sys_get_temp_dir(), 'calaccess').'.zip';
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE);
    $zip->addFromString('CalAccess/DATA/CVR_CAMPAIGN_DISCLOSURE_CD.TSV', $tsv($cvr));
    $zip->addFromString('CalAccess/DATA/SMRY_CD.TSV', $tsv($smry));
    $zip->addFromString('CalAccess/DATA/RCPT_CD.TSV', $tsv($rcpt));
    $zip->addFromString('CalAccess/DATA/S497_CD.TSV', $tsv($s497));
    $zip->close();

    return $path;
}

/** The "No on Prop 40" committee's statement, funded entirely by Building a Better California. */
function noOn40Filing(): array
{
    return [
        [['3199916', '0', 'CVR', 'F460', '1492108', 'NO ON PROP 40 - DOCTORS, TEACHERS, SMALL BUSINESSES', '9/24/2026 12:00:00 AM', '7/1/2026 12:00:00 AM', '9/19/2026 12:00:00 AM', 'PROPOSITION 40: AG 25-0024A1 - WEALTH TAX', '', 'STATEWIDE', 'O']],
        [['3199916', '0', '5', 'SMRY', 'F460', '56500000', '56600000', '', '']],
        [
            ['3199916', '0', '1', 'RCPT', 'A', 'A1', 'COM', 'BUILDING A BETTER CALIFORNIA', '', '', '15000000', '1486767', ''],
            ['3199916', '0', '2', 'RCPT', 'A', 'A2', 'COM', 'BUILDING A BETTER CALIFORNIA', '', '', '41500000', '1486767', ''],
            ['3199916', '0', '3', 'RCPT', 'A', 'A3', 'OTH', 'CALIFORNIA MEDICAL ASSOCIATION', '', '', '100000', '', ''],
        ],
    ];
}

function financeMeasure(array $over = []): BallotMeasure
{
    return BallotMeasure::create($over + [
        'state' => 'CA', 'level' => 'state', 'measure_number' => '40',
        'title' => 'Proposition 40: Imposes One-Time Tax on Certain Taxpayers',
        'status' => 'upcoming', 'election_date' => now()->addDays(40)->toDateString(),
    ]);
}

function financeLink(BallotMeasure $measure, array $over = []): BallotMeasureCommittee
{
    return BallotMeasureCommittee::create($over + [
        'ballot_measure_id' => $measure->id, 'state' => 'CA',
        'committee_id' => '1486767', 'committee_name' => 'Building a Better California',
        'position' => 'oppose', 'source_url' => 'https://cal-access.sos.ca.gov/Campaign/Committees/Detail.aspx?id=1486767',
        'status' => 'verified',
    ]);
}

it('imports the latest amendment of each Form 460, matching the filing to the cent', function () {
    financeLink(financeMeasure());

    $this->artisan('ballot-measures:import-cal-access', ['--file' => calAccessZip()])->assertExitCode(0);

    expect(CommitteeFinanceSnapshot::count())->toBe(2);

    $latest = CommitteeFinanceSnapshot::latestFor('CA', '1486767');
    expect($latest->filing_id)->toBe('3199914')
        ->and($latest->period_end->toDateString())->toBe('2026-09-19')
        ->and($latest->contributions_period)->toBe(108581852.07)
        ->and($latest->contributions_ytd)->toBe(226706672.98)
        ->and($latest->nonmonetary_ytd)->toBe(32520970.24)
        ->and($latest->expenditures_ytd)->toBe(228791798.76)
        ->and($latest->cash_on_hand)->toBe(30349927.59);

    // The amended first-half report replaces the original.
    $firstHalf = CommitteeFinanceSnapshot::where('filing_id', '3181389')->sole();
    expect($firstHalf->amend_id)->toBe(1)->and($firstHalf->contributions_ytd)->toBe(118124820.91);

    $filer = CommitteeFiler::for('CA', '1486767');
    expect($filer->found)->toBeTrue()
        ->and($filer->filer_name)->toBe('BUILDING A BETTER CALIFORNIA')
        ->and($filer->latest_filing_on->toDateString())->toBe('2026-09-24');

    expect(CommitteeFinanceSnapshot::where('committee_id', '991755')->exists())->toBeFalse();

    $metric = DB::table('politician_cleanup_run_metrics')->where('step', 'cal-access-finance')->sole();
    expect($metric->findings_count)->toBe(0)->and($metric->auto_applied_count)->toBe(2);
});

it('flags a filer ID that is not in the state data, and a name that does not match the filings', function () {
    $measure = financeMeasure();
    $missing = financeLink($measure, ['committee_id' => '9999999', 'committee_name' => 'Typo Committee', 'status' => 'pending']);
    $wrongName = financeLink($measure, ['committee_name' => 'Californians for Fair Taxes', 'status' => 'pending']);

    $this->artisan('ballot-measures:import-cal-access', ['--file' => calAccessZip()])->assertExitCode(0);

    expect(MeasureCommitteeRules::flags($missing->refresh()))->toContain('filing_missing')
        ->and(MeasureCommitteeRules::flags($wrongName->refresh()))->toContain('filing_name_mismatch');
});

it('sends a verified link back to review when the committee\'s filing declares the other side', function () {
    $measure = financeMeasure();
    $link = financeLink($measure, ['committee_id' => '1500001', 'committee_name' => 'Yes on 40 Committee', 'position' => 'support']);

    $zip = calAccessZip(
        [['3200001', '0', 'CVR', 'F460', '1500001', 'YES ON 40 COMMITTEE', '9/24/2026 12:00:00 AM', '7/1/2026 12:00:00 AM', '9/19/2026 12:00:00 AM', 'Wealth Tax', '40', 'STATE', 'O']],
        [['3200001', '0', '5', 'SMRY', 'F460', '10', '10', '', '']],
    );
    $this->artisan('ballot-measures:import-cal-access', ['--file' => $zip])->assertExitCode(0);
    $this->artisan('ballot-measures:audit-committee-links')->assertExitCode(0);

    $link->refresh();
    expect($link->status)->toBe('pending')
        ->and($link->integrity_flags)->toContain('filing_position_conflict');
});

it('marks a link confirmed when the filing declares the same measure and side', function () {
    $measure = financeMeasure();
    $link = financeLink($measure, ['committee_id' => '1500002', 'committee_name' => 'No on 40', 'status' => 'pending']);

    $zip = calAccessZip(
        [['3200002', '0', 'CVR', 'F460', '1500002', 'NO ON 40, CALIFORNIANS AGAINST THE TAX', '9/24/2026 12:00:00 AM', '7/1/2026 12:00:00 AM', '9/19/2026 12:00:00 AM', 'Wealth Tax', 'Prop 40', 'STATE', 'O']],
    );
    $this->artisan('ballot-measures:import-cal-access', ['--file' => $zip])->assertExitCode(0);

    expect(MeasureCommitteeRules::confirmedByFiling($link->refresh()))->toBeTrue()
        ->and(MeasureCommitteeRules::flags($link))->toBe([]);
});

it('reports an anomaly when a later filing shows less raised this year', function () {
    financeLink(financeMeasure());
    CommitteeFinanceSnapshot::create([
        'state' => 'CA', 'committee_id' => '1486767', 'source' => 'cal-access', 'filing_id' => '3100000',
        'form_type' => 'F460', 'period_start' => '2026-07-01', 'period_end' => '2026-09-01', 'contributions_ytd' => 300000000,
    ]);

    $this->artisan('ballot-measures:import-cal-access', ['--file' => calAccessZip()])->assertExitCode(0);

    $metric = DB::table('politician_cleanup_run_metrics')->where('step', 'cal-access-finance')->sole();
    expect($metric->findings_count)->toBe(1)
        ->and(json_decode($metric->breakdown, true))->toBe(['total_decreased' => 1]);
});

it('shows voters dollar totals for each side', function () {
    Role::firstOrCreate(['name' => 'voter', 'guard_name' => 'web']);
    $measure = financeMeasure();
    financeLink($measure);
    $this->artisan('ballot-measures:import-cal-access', ['--file' => calAccessZip()])->assertExitCode(0);

    $user = User::factory()->create(['platform' => 'standalone']);
    $user->assignRole('voter');
    Voter::factory()->create(['user_id' => $user->id, 'state' => 'CA', 'is_verified' => true, 'is_active' => true]);
    skipOnboarding($user, 'voter');

    $this->actingAs($user)->get(route('voter.ballot-measures.show', $measure))
        ->assertOk()
        ->assertSee('$227,706,673')
        ->assertSee('$32,520,970 non-cash')
        ->assertSee('through Sep 19, 2026');
});

// ── Donors, late contributions, transfers ───────────────────────────────

it('keeps itemized donors for the statement year, adds late contributions, and skips memo and Schedule I rows', function () {
    financeLink(financeMeasure());

    $this->artisan('ballot-measures:import-cal-access', ['--file' => calAccessZip()])->assertExitCode(0);

    $donors = CommitteeDonor::where('committee_id', '1486767')->get()->keyBy('donor_name');
    expect($donors->keys()->sort()->values()->all())->toBe(['L. JOHN DOERR, III', 'NEIL MEHTA', 'SERGEY BRIN', 'VINOD KHOSLA'])
        ->and($donors['SERGEY BRIN']->amount)->toBe(102000000.0) // $82M in the amended first half + $20M
        ->and($donors['VINOD KHOSLA']->nonmonetary)->toBe(12561504.0)
        ->and($donors['L. JOHN DOERR, III']->employer)->toBe('KLEINER PERKINS')
        ->and($donors['NEIL MEHTA']->late)->toBe(1000000.0)
        ->and($donors->every(fn ($d) => $d->year === 2026))->toBeTrue();

    $filer = CommitteeFiler::for('CA', '1486767');
    expect($filer->late_contributions)->toBe(1000000.0)
        ->and($filer->late_since->toDateString())->toBe('2026-09-19');

    $transfer = CommitteeTransfer::sole();
    expect($transfer->to_committee_id)->toBe('1492108')
        ->and($transfer->measure_number)->toBe('40')
        ->and($transfer->amount)->toBe(5000000.0);
});

it('counts money passed between committees on the same side once', function () {
    $measure = financeMeasure();
    financeLink($measure);
    financeLink($measure, ['committee_id' => '1492108', 'committee_name' => 'No on Prop 40']);
    [$cvr, $smry, $rcpt] = noOn40Filing();
    $smry[] = ['3199916', '0', '11', 'SMRY', 'F460', '56400000', '56400000', '', ''];

    $this->artisan('ballot-measures:import-cal-access', ['--file' => calAccessZip($cvr, $smry, $rcpt)])->assertExitCode(0);

    $oppose = MeasureFunding::forCommittees($measure->committees()->verified()->get())['oppose'];
    expect($oppose['raised'])->toBe(284306672.98)
        ->and($oppose['transfers'])->toBe(56500000.0)
        ->and($oppose['net_raised'])->toBe(227806672.98)
        ->and($oppose['spent'])->toBe(228791798.76 + 56400000 - 56500000)
        ->and($oppose['top_donors']->pluck('name')->all())->not->toContain('BUILDING A BETTER CALIFORNIA')
        ->and($oppose['top_donors']->first()['name'])->toBe('SERGEY BRIN');
});

it('reads the declared measure from the measure name when the number is blank', function () {
    $measure = financeMeasure();
    $link = financeLink($measure, ['committee_id' => '1492108', 'committee_name' => 'No on Prop 40', 'status' => 'pending']);
    [$cvr, $smry, $rcpt] = noOn40Filing();

    $this->artisan('ballot-measures:import-cal-access', ['--file' => calAccessZip($cvr, $smry, $rcpt)])->assertExitCode(0);

    expect(CommitteeFinanceSnapshot::where('filing_id', '3199916')->sole()->declared_measure_number)->toBe('40')
        ->and(MeasureCommitteeRules::confirmedByFiling($link->refresh()))->toBeTrue();
});

it('flags a committee that funds the other side of its measure', function () {
    $measure = financeMeasure();
    $link = financeLink($measure, ['position' => 'support', 'status' => 'pending']); // really opposes

    $this->artisan('ballot-measures:import-cal-access', ['--file' => calAccessZip()])->assertExitCode(0);

    expect(MeasureCommitteeRules::flags($link->refresh()))->toContain('transfer_side_conflict');
});

it('suggests committees a linked committee funded for the measure, and links one for review', function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $measure = financeMeasure();
    financeLink($measure);
    $this->artisan('ballot-measures:import-cal-access', ['--file' => calAccessZip()])->assertExitCode(0);

    $admin = User::factory()->create(['platform' => 'standalone', 'user_type' => 'admin']);
    $admin->assignRole('admin', 'staff:Legacy administrator');
    skipOnboarding($admin, 'admin');

    $this->actingAs($admin)->get(route('admin.ballot-measures.committees', $measure))
        ->assertOk()
        ->assertSee('Suggested from filings')
        ->assertSee('NO ON PROP 40 - DOCTORS, TEACHERS, SMALL BUSINESSES')
        ->assertSee('$5,000,000');

    $this->actingAs($admin)
        ->post(route('admin.ballot-measures.committee-suggestions.link', [$measure, '1492108']), ['position' => 'oppose'])
        ->assertRedirect();

    $linked = BallotMeasureCommittee::where('committee_id', '1492108')->sole();
    expect($linked->status)->toBe('pending')
        ->and($linked->source_url)->toBe('https://cal-access.sos.ca.gov/Campaign/Committees/Detail.aspx?id=1492108');

    $this->actingAs($admin)->get(route('admin.ballot-measures.committees', $measure))->assertDontSee('Suggested from filings');
});

it('flags itemized donors that add up to more than the committee reported raising', function () {
    financeLink(financeMeasure());
    $rcpt = [['3199914', '0', '9', 'RCPT', 'A', 'A9', 'IND', 'TYPO', 'DONOR', '', '999000000', '', '']];

    $this->artisan('ballot-measures:import-cal-access', ['--file' => calAccessZip([], [], $rcpt)])->assertExitCode(0);

    $metric = DB::table('politician_cleanup_run_metrics')->where('step', 'cal-access-finance')->sole();
    expect(json_decode($metric->breakdown, true))->toBe(['itemized_exceeds_total' => 1]);
});

it('shows voters top donors and late contributions', function () {
    Role::firstOrCreate(['name' => 'voter', 'guard_name' => 'web']);
    $measure = financeMeasure();
    financeLink($measure);
    $this->artisan('ballot-measures:import-cal-access', ['--file' => calAccessZip()])->assertExitCode(0);

    $user = User::factory()->create(['platform' => 'standalone']);
    $user->assignRole('voter');
    Voter::factory()->create(['user_id' => $user->id, 'state' => 'CA', 'is_verified' => true, 'is_active' => true]);
    skipOnboarding($user, 'voter');

    $this->actingAs($user)->get(route('voter.ballot-measures.show', $measure))
        ->assertOk()
        ->assertSee('Top donors')
        ->assertSeeInOrder(['SERGEY BRIN', '$102,000,000', 'VINOD KHOSLA'])
        ->assertSee('incl. $1,000,000 in late contributions', false);
});
