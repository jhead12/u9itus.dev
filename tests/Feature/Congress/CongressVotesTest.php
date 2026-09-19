<?php

use App\Models\CongressMemberVote;
use App\Models\CongressVote;
use App\Models\Politician;
use App\Services\CongressMemberLinker;
use App\Services\CongressVoteImporter;
use App\Services\PoliticianVotingRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function houseRollXml(int $roll, string $type, array $votes, string $legis = 'H R 1', string $question = 'On Passage'): string
{
    $recorded = '';
    foreach ($votes as [$id, $party, $vote]) {
        $recorded .= "<recorded-vote><legislator name-id=\"{$id}\" party=\"{$party}\" state=\"CA\" role=\"legislator\">X</legislator><vote>{$vote}</vote></recorded-vote>";
    }

    return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE rollcall-vote PUBLIC "-//US Congress//DTDs/vote v1.0 20031119 //EN" "../vote.dtd">
<rollcall-vote><vote-metadata><congress>119</congress><rollcall-num>{$roll}</rollcall-num><legis-num>{$legis}</legis-num>
<vote-question>{$question}</vote-question><vote-type>{$type}</vote-type><vote-result>Passed</vote-result>
<action-date>7-Jan-2026</action-date><action-time time-etz="16:57">4:57 PM</action-time><vote-desc>Providing for the SHOW Act</vote-desc></vote-metadata>
<vote-data>{$recorded}</vote-data></rollcall-vote>
XML;
}

function senateVoteXml(int $number, array $members): string
{
    $rows = '';
    foreach ($members as [$lis, $party, $cast]) {
        $rows .= "<member><party>{$party}</party><state>WA</state><vote_cast>{$cast}</vote_cast><lis_member_id>{$lis}</lis_member_id></member>";
    }

    return <<<XML
<?xml version="1.0" encoding="UTF-8"?><roll_call_vote><congress>119</congress><session>1</session>
<vote_number>{$number}</vote_number><vote_date>December 18, 2025,  09:42 PM</vote_date>
<question>On the Nomination</question><vote_title>Confirmation: Sara Bailey to be Director</vote_title>
<vote_result>Nomination Confirmed</vote_result>
<document><document_name>PN373</document_name><document_title>Sara Bailey</document_title></document>
<members>{$rows}</members></roll_call_vote>
XML;
}

function legislatorsJson(): array
{
    return [
        ['id' => ['bioguide' => 'C000127', 'lis' => 'S275'], 'name' => ['first' => 'Maria', 'last' => 'Cantwell', 'official_full' => 'Maria Cantwell'],
            'terms' => [['type' => 'sen', 'state' => 'WA']]],
        ['id' => ['bioguide' => 'P000197', 'lis' => null], 'name' => ['first' => 'Nancy', 'last' => 'Pelosi', 'official_full' => 'Nancy Pelosi'],
            'terms' => [['type' => 'rep', 'state' => 'CA', 'district' => 11]]],
        ['id' => ['bioguide' => 'S000033', 'lis' => 'S313'], 'name' => ['first' => 'Bernard', 'last' => 'Sanders', 'official_full' => 'Bernard Sanders'],
            'terms' => [['type' => 'sen', 'state' => 'VT']]],
    ];
}

it('imports House roll calls, skips quorum calls, and records party positions', function () {
    Http::fake([
        'clerk.house.gov/evs/2026/roll001.xml' => Http::response(houseRollXml(1, 'QUORUM', [['P000197', 'D', 'Present']], 'QUORUM'), 200),
        'clerk.house.gov/evs/2026/roll002.xml' => Http::response(houseRollXml(2, 'YEA-AND-NAY', [
            ['P000197', 'D', 'Nay'], ['A000001', 'D', 'Nay'], ['B000001', 'R', 'Aye'], ['C000001', 'R', 'Aye'], ['D000001', 'R', 'Not Voting'],
        ], 'H RES 977'), 200),
        'clerk.house.gov/*' => Http::response('', 404),
    ]);

    $stats = app(CongressVoteImporter::class)->importHouse(119, 2);

    expect($stats)->toBe(['imported' => 1, 'skipped' => 1, 'failed' => 0]);

    $vote = CongressVote::firstOrFail();
    expect($vote->chamber)->toBe('house')
        ->and($vote->roll_number)->toBe(2)
        ->and($vote->yeas)->toBe(2)
        ->and($vote->nays)->toBe(2)
        ->and($vote->not_voting)->toBe(1)
        ->and($vote->party_positions)->toBe(['D' => 'nay', 'R' => 'yea'])
        ->and($vote->billUrl())->toBe('https://www.congress.gov/bill/119th-congress/house-resolution/977')
        ->and(CongressMemberVote::where('bioguide_id', 'B000001')->value('vote'))->toBe('yea');

    // A second run continues after the last stored roll instead of refetching.
    Http::fake(['clerk.house.gov/*' => Http::response('', 404)]);
    expect(app(CongressVoteImporter::class)->importHouse(119, 2)['imported'])->toBe(0);
});

it('imports Senate roll calls via the LIS to Bioguide map and does not re-import stored votes', function () {
    $menu = '<vote_summary><votes><vote><vote_number>00001</vote_number><issue>PN373</issue></vote></votes></vote_summary>';

    Http::fake([
        'www.senate.gov/legislative/LIS/roll_call_lists/*' => Http::response($menu, 200),
        'www.senate.gov/legislative/LIS/roll_call_votes/*' => Http::response(senateVoteXml(1, [['S275', 'D', 'Nay'], ['S313', 'I', 'Nay'], ['S999', 'R', 'Yea']]), 200),
        'unitedstates.github.io/*' => Http::response(legislatorsJson(), 200),
    ]);

    $importer = app(CongressVoteImporter::class);
    expect($importer->importSenate(119, 1))->toBe(['imported' => 1, 'skipped' => 0, 'failed' => 0]);

    $vote = CongressVote::firstOrFail();
    expect($vote->chamber)->toBe('senate')
        ->and($vote->voted_at->toDateString())->toBe('2025-12-19') // 9:42 PM ET
        ->and($vote->bill_number)->toBe('PN373')
        ->and($vote->billUrl())->toBe('https://www.congress.gov/nomination/119th-congress/373')
        ->and($vote->title)->toBe('Confirmation: Sara Bailey to be Director')
        ->and(CongressMemberVote::pluck('vote', 'bioguide_id')->all())->toBe(['C000127' => 'nay', 'S000033' => 'nay']);

    expect($importer->importSenate(119, 1))->toBe(['imported' => 0, 'skipped' => 1, 'failed' => 0]);
});

it('normalises the many spellings of a vote', function (string $raw, string $expected) {
    expect(app(CongressVoteImporter::class)->normalizeVote($raw))->toBe($expected);
})->with([
    ['Yea', 'yea'], ['Aye', 'yea'], ['Nay', 'nay'], ['No', 'nay'], ['Present', 'present'],
    ['Not Voting', 'not_voting'], ['Johnson (LA)', 'other'], ['Guilty', 'other'],
]);

it('links senator and representative profiles to Bioguide IDs by name, nickname and district', function () {
    Http::fake(['unitedstates.github.io/*' => Http::response(legislatorsJson(), 200)]);

    $cantwell = Politician::factory()->create(['full_name' => 'Maria Cantwell', 'state' => 'WA', 'political_office' => 'United States Senator', 'governance_level' => 'Federal']);
    $pelosi = Politician::factory()->create(['full_name' => 'Nancy Pelosi', 'state' => 'CA', 'political_office' => 'United States Representative', 'district' => 'CA-11', 'governance_level' => 'Federal']);
    $sanders = Politician::factory()->create(['full_name' => 'Bernie Sanders', 'state' => 'VT', 'political_office' => 'U.S. Senator', 'governance_level' => 'Federal']);
    // Same name in another state must not be linked, and a rep with the right surname but the wrong district neither.
    $otherState = Politician::factory()->create(['full_name' => 'Maria Cantwell', 'state' => 'OR', 'political_office' => 'United States Senator', 'governance_level' => 'Federal']);
    $wrongSeat = Politician::factory()->create(['full_name' => 'Paul Pelosi', 'state' => 'CA', 'political_office' => 'United States Representative', 'district' => 'CA-12', 'governance_level' => 'Federal']);

    $dry = app(CongressMemberLinker::class)->link(true);
    expect($dry['linked'])->toBe(3)->and($cantwell->fresh()->bioguide_id)->toBeNull();

    app(CongressMemberLinker::class)->link();

    expect($cantwell->fresh()->bioguide_id)->toBe('C000127')
        ->and($pelosi->fresh()->bioguide_id)->toBe('P000197')
        ->and($sanders->fresh()->bioguide_id)->toBe('S000033')
        ->and($otherState->fresh()->bioguide_id)->toBeNull()
        ->and($wrongSeat->fresh()->bioguide_id)->toBeNull();

    expect(app(CongressMemberLinker::class)->link()['already'])->toBe(3);
});

function seedVotes(string $bioguide, int $count = 12): void
{
    foreach (range(1, $count) as $i) {
        $vote = CongressVote::create([
            'chamber' => 'senate', 'congress' => 119, 'session' => 1, 'roll_number' => $i,
            'voted_at' => now()->subDays($count - $i), 'question' => 'On Passage', 'title' => "Vote title {$i}",
            'bill_number' => 'S. '.$i, 'result' => 'Passed', 'yeas' => 51, 'nays' => 49,
            'party_positions' => ['D' => 'nay', 'R' => 'yea'],
        ]);
        // Votes 1-10 with the party (Democrat: nay), 11 against, 12 missed.
        $cast = match (true) { $i <= 10 => 'nay', $i === 11 => 'yea', default => 'not_voting' };
        CongressMemberVote::create(['congress_vote_id' => $vote->id, 'bioguide_id' => $bioguide, 'party' => 'D', 'vote' => $cast]);
    }
}

it('summarises a members record: votes cast, missed share and party-line share', function () {
    Cache::flush();
    seedVotes('C000127');
    $politician = Politician::factory()->create(['bioguide_id' => 'C000127']);

    $summary = app(PoliticianVotingRecord::class)->summary($politician);

    expect($summary['total'])->toBe(12)
        ->and($summary['yea'])->toBe(1)
        ->and($summary['nay'])->toBe(10)
        ->and($summary['not_voting'])->toBe(1)
        ->and($summary['missed_pct'])->toBe(8.3)
        ->and($summary['party_line_pct'])->toBe(90.9) // 10 of 11 cast votes matched the D majority
        ->and($summary['recent'])->toHaveCount(8)
        ->and($summary['recent']->first()->title)->toBe('Vote title 12');

    expect(app(PoliticianVotingRecord::class)->summary(Politician::factory()->create(['bioguide_id' => null])))->toBeNull();
});

it('shows the voting record on the public profile and a filterable full record page', function () {
    Cache::flush();
    seedVotes('C000127');
    $politician = Politician::factory()->create([
        'full_name' => 'Maria Cantwell', 'slug' => 'maria-cantwell', 'bioguide_id' => 'C000127',
        'page_published' => true, 'is_active' => true,
    ]);

    $this->get(route('politician.public.show', $politician->slug))
        ->assertOk()
        ->assertSee('Voting Record')
        ->assertSee('Vote title 12')
        ->assertSee('Full record');

    $this->get(route('politician.public.votes', $politician->slug))
        ->assertOk()
        ->assertSee('Vote title 1')
        ->assertSee('Vote title 12');

    $this->get(route('politician.public.votes', ['slug' => $politician->slug, 'vote' => 'yea']))
        ->assertOk()
        ->assertSee('Vote title 11')
        ->assertDontSee('Vote title 10');
});

it('has no voting record section or page for a politician without linked votes', function () {
    $politician = Politician::factory()->create(['slug' => 'no-votes', 'bioguide_id' => null, 'page_published' => true, 'is_active' => true]);

    $this->get(route('politician.public.show', $politician->slug))->assertOk()->assertDontSee('id="voting-record"', false);
    $this->get(route('politician.public.votes', $politician->slug))->assertNotFound();
});
