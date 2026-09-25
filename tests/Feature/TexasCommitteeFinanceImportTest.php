<?php

use App\Models\BallotMeasure;
use App\Models\BallotMeasureCommittee;
use App\Models\CommitteeDonor;
use App\Models\CommitteeFiler;
use App\Models\CommitteeFinanceSnapshot;
use App\Models\CommitteeTransfer;
use App\Support\MeasureCommitteeRules;
use App\Support\MeasureFunding;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * A miniature Texas Ethics Commission export. Committees and declarations are modeled on
 * the real September 2026 export: Texans for Opportunity (00087902) declared support for
 * 2025 Prop 4; Grassroots America (00065835) declared opposition to "Amendments 1,3,4,11,14".
 */
function texasZip(array $extra = []): string
{
    $csv = function (array $rows): string {
        $out = fopen('php://temp', 'r+');
        foreach ($rows as $row) {
            fputcsv($out, $row, ',', '"', '');
        }
        rewind($out);

        return str_replace("\n", "\r\n", (string) stream_get_contents($out));
    };

    $files = [
        'filers.csv' => [
            ['recordType', 'filerIdent', 'filerTypeCd', 'filerName'],
            ['FILER', '00087902', 'GPAC', 'Texans for Opportunity'],
            ['FILER', '00065835', 'GPAC', 'Grassroots America - We the People PAC'],
            ['FILER', '00012345', 'GPAC', 'Yes on Prop 4 Coalition'],
            ['FILER', '00099999', 'GPAC', 'UPS'],
            ['FILER', '00010066', 'COH', 'Lucero, Homero R. (Mr.)'],
        ],
        'cover.csv' => [
            ['recordType', 'formTypeCd', 'reportInfoIdent', 'receivedDt', 'infoOnlyFlag', 'filerIdent', 'filerTypeCd', 'filerName', 'reportTypeCd1', 'filedDt', 'periodStartDt', 'periodEndDt', 'totalContribAmount', 'totalExpendAmount', 'contribsMaintainedAmount'],
            ['CVR1', 'GPAC', '5001', '20250715', 'N', '00087902', 'GPAC', 'Texans for Opportunity', 'SEMIJUL', '20250715', '20250101', '20250630', '50000.00', '10000.00', '40000.00'],
            ['CVR1', 'GPAC', '5002', '20251027', 'N', '00087902', 'GPAC', 'Texans for Opportunity', 'E08DAYBEF', '20251027', '20250701', '20251025', '69190.57', '154324.72', '103786.92'],
            // Superseded by a corrected report.
            ['CVR1', 'GPAC', '5003', '20251026', 'Y', '00087902', 'GPAC', 'Texans for Opportunity', 'E08DAYBEF', '20251026', '20250701', '20251025', '999999.00', '0', '0'],
            ['CVR1', 'GPAC', '6001', '20260715', 'N', '00087902', 'GPAC', 'Texans for Opportunity', 'SEMIJUL', '20260715', '20260101', '20260630', '13000.00', '0', '13193.80'],
            ['CVR1', 'GPAC', '7001', '20251027', 'N', '00065835', 'GPAC', 'Grassroots America - We the People PAC', 'E08DAYBEF', '20251027', '20250701', '20251025', '1000.00', '500.00', '700.00'],
        ],
        'cover_t.csv' => [
            ['recordType', 'formTypeCd', 'reportInfoIdent', 'receivedDt', 'infoOnlyFlag', 'filerIdent', 'filerTypeCd', 'filerName', 'reportTypeCd1', 'filedDt', 'periodStartDt', 'periodEndDt', 'totalContribAmount', 'totalExpendAmount', 'contribsMaintainedAmount'],
            ['CVR1', 'DAILYCPAC', '6100', '20260801', 'N', '00087902', 'GPAC', 'Texans for Opportunity', 'DAILYCPAC', '20260801', '20260730', '20260801', '', '', ''],
        ],
        'purpose.csv' => [
            ['recordType', 'formTypeCd', 'reportInfoIdent', 'receivedDt', 'infoOnlyFlag', 'filerIdent', 'filerTypeCd', 'filerName', 'committeeActivityId', 'subjectCategoryCd', 'subjectPositionCd', 'subjectDescr', 'subjectBallotNumber', 'subjectElectionDt'],
            ['CVR3', 'GPAC', '5002', '20251027', 'N', '00087902', 'GPAC', 'Texans for Opportunity', '1', 'MEASURE', 'SUPPORT', "Amendment to dedicate a portion of the\nstate sales tax to water", 'Prop 4', '20251104'],
            ['CVR3', 'GPAC', '7001', '20251027', 'N', '00065835', 'GPAC', 'Grassroots America - We the People PAC', '2', 'MEASURE', 'OPPOSE', 'Amendments 1,3,4,11,14', 'Amendments', '20251104'],
            ['CVR3', 'GPAC', '8001', '20251001', 'N', '00012345', 'GPAC', 'Yes on Prop 4 Coalition', '3', 'MEASURE', 'SUPPORT', 'Constitutional amendment for water funding', 'Prop 4', '20251104'],
        ],
        'contribs_01.csv' => [
            ['recordType', 'formTypeCd', 'schedFormTypeCd', 'reportInfoIdent', 'receivedDt', 'infoOnlyFlag', 'filerIdent', 'filerTypeCd', 'filerName', 'contributionInfoId', 'contributionDt', 'contributionAmount', 'contributionDescr', 'itemizeFlag', 'travelFlag', 'contributorPersentTypeCd', 'contributorNameOrganization', 'contributorNameLast', 'contributorNameSuffixCd', 'contributorNameFirst', 'contributorNamePrefixCd', 'contributorNameShort', 'contributorStreetCity', 'contributorStreetStateCd', 'contributorStreetCountyCd', 'contributorStreetCountryCd', 'contributorStreetPostalCode', 'contributorStreetRegion', 'contributorEmployer'],
            ['RCPT', 'GPAC', 'A1', '5002', '20251027', 'N', '00087902', 'GPAC', 'Texans for Opportunity', '1', '20250901', '25000.00', '', 'Y', 'N', 'INDIVIDUAL', '', 'Holmes', '', 'William', '', '', 'Houston', 'TX', '', 'USA', '77002', '', 'Holmes Energy'],
            ['RCPT', 'GPAC', 'A2', '5002', '20251027', 'N', '00087902', 'GPAC', 'Texans for Opportunity', '2', '20250902', '5000.00', "Polling\nservices", 'Y', 'N', 'ENTITY', 'Water Texas Inc', '', '', '', '', '', 'Austin', 'TX', '', 'USA', '78701', '', ''],
            ['RCPT', 'GPAC', 'A1', '5002', '20251027', 'N', '00087902', 'GPAC', 'Texans for Opportunity', '3', '20250903', '10000.00', '', 'Y', 'N', 'ENTITY', 'Yes on Prop 4 Coalition', '', '', '', '', '', 'Austin', 'TX', '', 'USA', '78701', '', ''],
            ['RCPT', 'GPAC', 'A1', '6001', '20260715', 'N', '00087902', 'GPAC', 'Texans for Opportunity', '4', '20260301', '13000.00', '', 'Y', 'N', 'INDIVIDUAL', '', 'Saulsbury', '', 'Charles', '', '', 'Midland', 'TX', '', 'USA', '79701', '', 'Saulsbury Industries'],
            // Someone else's report that happens to mention the filer ID in a description.
            ['RCPT', 'MPAC', 'A1', '9001', '20250101', 'N', '00010883', 'MPAC', 'Other PAC', '5', '20250101', '1.00', 'ref ,00087902, note', 'Y', 'N', 'INDIVIDUAL', '', 'Doe', '', 'Jane', '', '', 'Dallas', 'TX', '', 'USA', '75201', '', ''],
        ],
        'cont_t.csv' => [
            ['recordType', 'formTypeCd', 'schedFormTypeCd', 'reportInfoIdent', 'receivedDt', 'infoOnlyFlag', 'filerIdent', 'filerTypeCd', 'filerName', 'contributionInfoId', 'contributionDt', 'contributionAmount', 'contributionDescr', 'itemizeFlag', 'travelFlag', 'contributorPersentTypeCd', 'contributorNameOrganization', 'contributorNameLast', 'contributorNameSuffixCd', 'contributorNameFirst', 'contributorNamePrefixCd', 'contributorNameShort', 'contributorStreetCity', 'contributorStreetStateCd', 'contributorStreetCountyCd', 'contributorStreetCountryCd', 'contributorStreetPostalCode', 'contributorStreetRegion', 'contributorEmployer'],
            ['RCPT', 'DAILYCPAC', 'A1', '6100', '20260801', 'N', '00087902', 'GPAC', 'Texans for Opportunity', '6', '20260731', '2000.00', '', 'Y', 'N', 'INDIVIDUAL', '', 'Gibson', '', 'John', '', '', 'Dallas', 'TX', '', 'USA', '75201', '', 'Retired'],
        ],
        'expend_01.csv' => [
            ['recordType', 'formTypeCd', 'schedFormTypeCd', 'reportInfoIdent', 'receivedDt', 'infoOnlyFlag', 'filerIdent', 'filerTypeCd', 'filerName', 'expendInfoId', 'expendDt', 'expendAmount', 'expendDescr', 'expendCatCd', 'expendCatDescr', 'itemizeFlag', 'travelFlag', 'politicalExpendCd', 'reimburseIntendedFlag', 'srcCorpContribFlag', 'capitalLivingexpFlag', 'payeePersentTypeCd', 'payeeNameOrganization'],
            ['EXPN', 'GPAC', 'F1', '7001', '20251027', 'N', '00065835', 'GPAC', 'Grassroots America - We the People PAC', '1', '20251001', '500.00', 'Contribution', 'DONATIONS', '', 'Y', 'N', 'Y', 'N', 'N', 'N', 'ENTITY', 'Yes on Prop 4 Coalition'],
            ['EXPN', 'GPAC', 'F1', '7001', '20251027', 'N', '00065835', 'GPAC', 'Grassroots America - We the People PAC', '2', '20251002', '23.37', 'Shipping', 'OVERHEAD', '', 'Y', 'N', 'Y', 'N', 'N', 'N', 'ENTITY', 'UPS'],
        ],
        'expn_t.csv' => [
            ['recordType', 'formTypeCd', 'schedFormTypeCd', 'reportInfoIdent', 'receivedDt', 'infoOnlyFlag', 'filerIdent', 'filerTypeCd', 'filerName', 'expendInfoId', 'expendDt', 'expendAmount', 'expendDescr', 'expendCatCd', 'expendCatDescr', 'itemizeFlag', 'travelFlag', 'politicalExpendCd', 'reimburseIntendedFlag', 'srcCorpContribFlag', 'capitalLivingexpFlag', 'payeePersentTypeCd', 'payeeNameOrganization'],
            // The same contribution on a daily report: must not double.
            ['EXPN', 'DAILYEPAC', 'F1', '7100', '20251002', 'N', '00065835', 'GPAC', 'Grassroots America - We the People PAC', '9', '20251001', '500.00', 'Contribution', 'DONATIONS', '', 'Y', 'N', 'Y', 'N', 'N', 'N', 'ENTITY', 'Yes on Prop 4 Coalition'],
        ],
    ];

    foreach ($extra as $file => $rows) {
        $files[$file] = [...$files[$file], ...$rows];
    }

    $path = tempnam(sys_get_temp_dir(), 'tec').'.zip';
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE);
    foreach ($files as $name => $rows) {
        $zip->addFromString($name, $csv($rows));
    }
    $zip->close();

    return $path;
}

function prop4(): BallotMeasure
{
    return BallotMeasure::create([
        'state' => 'TX', 'level' => 'state', 'measure_number' => '4', 'title' => 'Proposition 4: Water fund',
        'status' => 'passed', 'election_date' => '2025-11-04',
    ]);
}

function txLink(BallotMeasure $measure, string $id, string $name, string $position): BallotMeasureCommittee
{
    return BallotMeasureCommittee::create([
        'ballot_measure_id' => $measure->id, 'state' => 'TX', 'committee_id' => $id, 'committee_name' => $name,
        'position' => $position, 'source_url' => 'https://www.ethics.state.tx.us/search/cf/', 'status' => 'verified',
    ]);
}

it('pads Texas filer IDs to eight digits', function () {
    $link = txLink(prop4(), '87902', 'Texans for Opportunity', 'support');

    expect($link->committee_id)->toBe('00087902');
});

it('sums a year\'s period reports into year-to-date totals, skipping superseded reports', function () {
    txLink(prop4(), '87902', 'Texans for Opportunity', 'support');

    $this->artisan('ballot-measures:import-texas', ['--file' => texasZip()])->assertExitCode(0);

    expect(CommitteeFinanceSnapshot::count())->toBe(3);
    $election = CommitteeFinanceSnapshot::where('filing_id', '5002')->sole();
    expect($election->contributions_period)->toBe(69190.57)
        ->and($election->contributions_ytd)->toBe(119190.57)
        ->and($election->expenditures_ytd)->toBe(164324.72)
        ->and($election->cash_on_hand)->toBe(103786.92)
        ->and($election->nonmonetary_ytd)->toBe(5000.0)
        ->and($election->declaredMeasures()[0])->toMatchArray(['number' => '4', 'position' => 'support', 'election_date' => '2025-11-04']);

    $filer = CommitteeFiler::for('TX', '00087902');
    expect($filer->filer_name)->toBe('Texans for Opportunity')
        ->and($filer->latest_filing_on->toDateString())->toBe('2026-08-01')
        ->and($filer->late_contributions)->toBe(2000.0);
});

it('keeps donors per year, recognizes committee donors, and ignores rows that only mention the filer ID', function () {
    txLink(prop4(), '87902', 'Texans for Opportunity', 'support');

    $this->artisan('ballot-measures:import-texas', ['--file' => texasZip()])->assertExitCode(0);

    $donors2025 = CommitteeDonor::where('year', 2025)->orderByDesc('amount')->get();
    expect($donors2025->pluck('donor_name')->all())->toBe(['William Holmes', 'Yes on Prop 4 Coalition', 'Water Texas Inc'])
        ->and($donors2025[0]->employer)->toBe('Holmes Energy')
        ->and($donors2025[1]->donor_committee_id)->toBe('00012345')
        ->and($donors2025[2]->nonmonetary)->toBe(5000.0);

    $donors2026 = CommitteeDonor::where('year', 2026)->pluck('amount', 'donor_name')->all();
    expect($donors2026)->toBe(['Charles Saulsbury' => 13000.0, 'John Gibson' => 2000.0])
        ->and(CommitteeDonor::where('donor_name', 'Jane Doe')->exists())->toBeFalse();
});

it('shows a decided measure\'s election-year money', function () {
    $measure = prop4();
    txLink($measure, '87902', 'Texans for Opportunity', 'support');

    $this->artisan('ballot-measures:import-texas', ['--file' => texasZip()])->assertExitCode(0);

    $support = MeasureFunding::forCommittees($measure->committees()->verified()->get(), $measure)['support'];
    expect($support['year'])->toBe(2025)
        ->and($support['raised'])->toBe(119190.57)
        ->and($support['late'])->toBe(0.0)
        ->and($support['top_donors']->first()['name'])->toBe('William Holmes');
});

it('confirms a declared measure and side, and flags a committee that declared the other side of a list', function () {
    $measure = prop4();
    $confirmed = txLink($measure, '87902', 'Texans for Opportunity', 'support');
    $opposed = txLink($measure, '65835', 'Grassroots America - We the People PAC', 'support');

    $this->artisan('ballot-measures:import-texas', ['--file' => texasZip()])->assertExitCode(0);

    expect(MeasureCommitteeRules::confirmedByFiling($confirmed->refresh()))->toBeTrue()
        ->and(MeasureCommitteeRules::flags($confirmed))->toBe([])
        ->and(MeasureCommitteeRules::flags($opposed->refresh()))->toContain('filing_position_conflict');
});

it('records contributions to committees once, tied to the measure the recipient declared, and skips vendor payments', function () {
    $measure = prop4();
    txLink($measure, '65835', 'Grassroots America - We the People PAC', 'oppose');

    $this->artisan('ballot-measures:import-texas', ['--file' => texasZip()])->assertExitCode(0);

    $transfer = CommitteeTransfer::sole();
    expect($transfer->to_committee_id)->toBe('00012345')
        ->and($transfer->amount)->toBe(500.0)
        ->and($transfer->measure_number)->toBe('4')
        ->and($transfer->measure_jurisdiction)->toBe('STATEWIDE')
        ->and($transfer->position)->toBe('support');

    // Grassroots opposes Prop 4 but gave to a committee supporting it.
    $link = BallotMeasureCommittee::sole();
    expect(MeasureCommitteeRules::flags($link))->toContain('transfer_side_conflict');
});
