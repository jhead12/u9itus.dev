<?php

use App\Models\BallotMeasure;
use App\Models\BallotMeasureCommittee;
use App\Models\CommitteeFiler;
use App\Models\CommitteeFinanceSnapshot;
use App\Models\User;
use App\Models\Voter;
use App\Support\MeasureCommitteeRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

const CVR_HEADER = ['FILING_ID', 'AMEND_ID', 'REC_TYPE', 'FORM_TYPE', 'FILER_ID', 'FILER_NAML', 'RPT_DATE', 'FROM_DATE', 'THRU_DATE', 'BAL_NAME', 'BAL_NUM', 'BAL_JURIS', 'SUP_OPP_CD'];
const SMRY_HEADER = ['FILING_ID', 'AMEND_ID', 'LINE_ITEM', 'REC_TYPE', 'FORM_TYPE', 'AMOUNT_A', 'AMOUNT_B', 'AMOUNT_C', 'ELEC_DT'];

/**
 * A miniature CAL-ACCESS export. The Building a Better California rows are copied from the
 * real September 25, 2026 export (filing 3199914 is the Form 460 in the viral screenshot).
 *
 * @param  list<list<string>>  $extraCvr
 * @param  list<list<string>>  $extraSmry
 */
function calAccessZip(array $extraCvr = [], array $extraSmry = []): string
{
    $cvr = [
        CVR_HEADER,
        ['3181389', '0', 'CVR', 'F460', '1486767', 'BUILDING A BETTER CALIFORNIA', '7/31/2026 12:00:00 AM', '1/1/2026 12:00:00 AM', '6/30/2026 12:00:00 AM', '', '', '', ''],
        ['3181389', '1', 'CVR', 'F460', '1486767', 'BUILDING A BETTER CALIFORNIA', '8/25/2026 12:00:00 AM', '1/1/2026 12:00:00 AM', '6/30/2026 12:00:00 AM', '', '', '', ''],
        ['3196949', '1', 'CVR', 'F497', '1486767', 'BUILDING A BETTER CALIFORNIA', '9/21/2026 12:00:00 AM', '', '', '', '', '', ''],
        ['3199914', '0', 'CVR', 'F460', '1486767', 'BUILDING A BETTER CALIFORNIA', '9/24/2026 12:00:00 AM', '7/1/2026 12:00:00 AM', '9/19/2026 12:00:00 AM', '', '', '', ''],
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

    $tsv = fn (array $rows) => implode('', array_map(fn ($r) => implode("\t", $r)."\r\n", $rows));
    $path = tempnam(sys_get_temp_dir(), 'calaccess').'.zip';
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE);
    $zip->addFromString('CalAccess/DATA/CVR_CAMPAIGN_DISCLOSURE_CD.TSV', $tsv($cvr));
    $zip->addFromString('CalAccess/DATA/SMRY_CD.TSV', $tsv($smry));
    $zip->close();

    return $path;
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
        ->assertSee('$226,706,673')
        ->assertSee('$32,520,970 non-cash')
        ->assertSee('through Sep 19, 2026');
});
