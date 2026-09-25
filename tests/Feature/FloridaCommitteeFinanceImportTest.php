<?php

use App\Models\BallotMeasure;
use App\Models\BallotMeasureCommittee;
use App\Models\CommitteeDonor;
use App\Models\CommitteeFiler;
use App\Models\CommitteeFinanceSnapshot;
use App\Models\CommitteeTransfer;
use App\Services\CampaignFinance\FloridaElectionsClient;
use App\Support\MeasureCommitteeRules;
use App\Support\MeasureFunding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    app()->instance(FloridaElectionsClient::class, new FloridaElectionsClient(pauseMs: 0));
});

const FL_CONTRIB_HEADER = "Candidate/Committee\tDate\tAmount\tTyp\tContributor Name\tAddress\tCity State Zip\tOccupation\tInkind Desc";
const FL_EXPEND_HEADER = "Candidate/Committee\tDate\tAmount\tPayee Name\tAddress\tCity State Zip\tPurpose\tType";

/**
 * Fakes the Division of Elections: committee detail pages, the active committee list, and
 * the contribution/expenditure query forms, keyed by the committee name searched. Row
 * shapes follow real responses (e.g. "Vote Yes on 3" received $18M from Florida Realtors).
 *
 * @param  array<string, string>  $details  account => committee name
 * @param  array<string, list<string>>  $contributions  committee name => tab-separated rows
 * @param  array<string, list<string>>  $expenditures
 */
function fakeFlorida(array $details, array $contributions = [], array $expenditures = [], ?string $failFor = null): void
{
    $list = "AcctNum\tName\tType\tTypeDesc\n"
        ."93318\tVote Yes on 3\tPAC\tPolitical Committee\n"
        ."92021\tVote No on 3 Florida, Inc.\tPAC\tPolitical Committee\n"
        ."94000\tHomeowners For Yes on 3\tPAC\tPolitical Committee\n"
        ."74124\tOrlando PAC\tPAC\tPolitical Committee\n";

    Http::fake(function (Request $request) use ($details, $contributions, $expenditures, $list, $failFor) {
        $url = $request->url();
        if (str_contains($url, 'ComDetail.asp')) {
            parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
            $name = $details[$query['account'] ?? ''] ?? null;

            return Http::response($name === null
                ? '<html><body>Committee Tracking System Type: Status: </body></html>'
                : "<html><body><h1>Committee Tracking System</h1><p>&nbsp; &nbsp; {$name} &nbsp; &nbsp;</p><p>Type: Political Committee</p><p>Status: Active</p></body></html>");
        }
        if (str_contains($url, 'extractComList.asp')) {
            return Http::response($list);
        }

        $name = $request->data()['ComName'] ?? '';
        if ($failFor !== null && str_starts_with($failFor, $name)) {
            return Http::response(FL_CONTRIB_HEADER."\n<H1>Error in /cgi-bin/contrib.exe</H1>\nOverflow Error Number = 6");
        }
        if (str_contains($url, 'contrib.exe')) {
            return Http::response(FL_CONTRIB_HEADER."\n".implode("\n", $contributions[$name] ?? []));
        }

        return Http::response(FL_EXPEND_HEADER."\n".implode("\n", $expenditures[$name] ?? []));
    });
}

function flMeasure(): BallotMeasure
{
    return BallotMeasure::create([
        'state' => 'FL', 'level' => 'state', 'measure_number' => '3', 'title' => 'Amendment 3',
        'status' => 'upcoming', 'election_date' => now()->addDays(40)->toDateString(),
    ]);
}

function flLink(BallotMeasure $measure, string $account, string $name, string $position, string $status = 'verified'): BallotMeasureCommittee
{
    return BallotMeasureCommittee::create([
        'ballot_measure_id' => $measure->id, 'state' => 'FL', 'committee_id' => $account, 'committee_name' => $name,
        'position' => $position, 'source_url' => "https://dos.elections.myflorida.com/committees/ComDetail.asp?account={$account}", 'status' => $status,
    ]);
}

it('sums a committee\'s itemized year into totals and donors, skipping loans and other committees sharing the name prefix', function () {
    $measure = flMeasure();
    flLink($measure, '93318', 'Vote Yes on 3', 'support');
    $year = now()->year;
    fakeFlorida(
        ['93318' => 'Vote Yes on 3'],
        ['Vote Yes on 3' => [
            "Vote Yes on 3 (PAC)\t06/05/{$year}\t18000000.00\tCHE\tFLORIDA REALTORS\t200 S MONROE ST\tTALLAHASSEE, FL 32301\tTRADE ASSOCIATION\t",
            "Vote Yes on 3 (PAC)\t07/01/{$year}\t25000.00\tINK\tFLORIDA REALTORS\t200 S MONROE ST\tTALLAHASSEE, FL 32301\tTRADE ASSOCIATION\tPOLLING",
            "Vote Yes on 3 (PAC)\t07/02/{$year}\t500000.00\tLOA\tSOME LENDER\t1 MAIN ST\tMIAMI, FL 33101\tBANK\t",
            "Vote Yes on 3 (PAC)\t08/01/{$year}\t10000.00\tCHE\tORLANDO PAC\t1 ORANGE AVE\tORLANDO, FL 32801\tPAC\t",
            // A different committee whose name starts the same way.
            "Vote Yes on 3 Now (PAC)\t08/02/{$year}\t999999.00\tCHE\tSOMEONE ELSE\t1 MAIN ST\tMIAMI, FL 33101\t\t",
        ]],
        ['Vote Yes on 3' => [
            "Vote Yes on 3 (PAC)\t09/16/{$year}\t2906450.00\tAD AGENCY\t1 MAIN ST\tMIAMI, FL 33101\tADVERTISING\tMON",
        ]],
    );

    $this->artisan('ballot-measures:import-florida')->assertExitCode(0);

    $snapshot = CommitteeFinanceSnapshot::sole();
    expect($snapshot->filing_id)->toBe("FL-{$year}")
        ->and($snapshot->contributions_ytd)->toBe(18035000.0)
        ->and($snapshot->nonmonetary_ytd)->toBe(25000.0)
        ->and($snapshot->expenditures_ytd)->toBe(2906450.0)
        ->and($snapshot->cash_on_hand)->toBeNull()
        ->and($snapshot->period_end->toDateString())->toBe("{$year}-09-16");

    $donors = CommitteeDonor::orderByDesc('amount')->get();
    expect($donors->pluck('donor_name')->all())->toBe(['FLORIDA REALTORS', 'ORLANDO PAC'])
        ->and($donors[0]->amount)->toBe(18025000.0)
        ->and($donors[1]->donor_committee_id)->toBe('74124');

    $filer = CommitteeFiler::for('FL', '93318');
    expect($filer->filer_name)->toBe('Vote Yes on 3')->and($filer->found)->toBeTrue();
});

it('flags an account number that does not exist', function () {
    $measure = flMeasure();
    $link = flLink($measure, '11111', 'Typo Committee', 'support', 'pending');
    fakeFlorida([]);

    $this->artisan('ballot-measures:import-florida')->assertExitCode(0);

    expect(MeasureCommitteeRules::flags($link))->toContain('filing_missing');
});

it('keeps the last good data when the state\'s query fails', function () {
    $measure = flMeasure();
    flLink($measure, '93318', 'Vote Yes on 3', 'support');
    CommitteeFinanceSnapshot::create([
        'state' => 'FL', 'committee_id' => '93318', 'source' => 'fl-doe', 'filing_id' => 'FL-'.now()->year,
        'form_type' => 'ITEMIZED', 'contributions_ytd' => 18000000,
    ]);
    fakeFlorida(['93318' => 'Vote Yes on 3'], failFor: 'Vote Yes on 3');

    $this->artisan('ballot-measures:import-florida')->assertExitCode(0);

    expect(CommitteeFinanceSnapshot::sole()->contributions_ytd)->toBe(18000000.0);
    $metric = DB::table('politician_cleanup_run_metrics')->where('step', 'fl-finance')->sole();
    expect(json_decode($metric->breakdown, true))->toBe(['fetch_failed' => 1]);
});

it('records gifts to other committees as transfers, nets them within a side, and flags funding the other side', function () {
    $measure = flMeasure();
    flLink($measure, '93318', 'Vote Yes on 3', 'support');
    flLink($measure, '94000', 'Homeowners For Yes on 3', 'support');
    $mixed = flLink($measure, '74124', 'Orlando PAC', 'support', 'pending');
    $year = now()->year;
    fakeFlorida(
        ['93318' => 'Vote Yes on 3', '94000' => 'Homeowners For Yes on 3', '74124' => 'Orlando PAC'],
        [
            'Vote Yes on 3' => ["Vote Yes on 3 (PAC)\t06/05/{$year}\t1000000.00\tCHE\tFLORIDA REALTORS\t\t\t\t"],
            'Homeowners For Yes on 3' => ["Homeowners For Yes on 3 (PAC)\t07/05/{$year}\t400000.00\tCHE\tVOTE YES ON 3\t\t\t\t"],
        ],
        [
            'Vote Yes on 3' => ["Vote Yes on 3 (PAC)\t07/05/{$year}\t400000.00\tHOMEOWNERS FOR YES ON 3\t\t\tCONTRIBUTION\tMON"],
            'Orlando PAC' => ["Orlando PAC (PAC)\t07/06/{$year}\t5000.00\tVOTE NO ON 3 FLORIDA, INC.\t\t\tCONTRIBUTION\tMON"],
        ],
    );

    $this->artisan('ballot-measures:import-florida')->assertExitCode(0);

    expect(CommitteeTransfer::where('from_committee_id', '93318')->sole()->measure_number)->toBe('3');

    $support = MeasureFunding::forCommittees($measure->committees()->verified()->get())['support'];
    expect($support['raised'])->toBe(1400000.0)
        ->and($support['transfers'])->toBe(400000.0)
        ->and($support['net_raised'])->toBe(1000000.0);

    // Orlando PAC is linked as supporting, but gave to the "Vote No on 3" committee.
    expect(MeasureCommitteeRules::flags($mixed->refresh()))->toContain('transfer_side_conflict');
});
