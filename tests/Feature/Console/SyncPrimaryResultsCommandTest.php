<?php

use App\Models\CandidateIdentityLink;
use App\Models\ElectionCandidateRecord;
use App\Models\Politician;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

// A result comes only from Wikipedia's race article (MediaWiki API). Stray requests
// throw (the service treats that as "no answer"), so a test never reaches the network.
beforeEach(fn () => Http::preventStrayRequests());

function raceArticle(string $wikitext): void
{
    Http::fake([
        'en.wikipedia.org/w/api.php*' => Http::response(['parse' => ['wikitext' => $wikitext]]),
        '*' => Http::response('', 404),
    ]);
}

test('a federal candidate record is picked up and classified as eliminated', function () {
    raceArticle("====Eliminated in primary====\n* [[Jane Doe]]\n");

    $record = ElectionCandidateRecord::factory()->create([
        'full_name' => 'Jane Doe',
        'governance_level' => 'federal',
        'political_office' => 'U.S. Senator',
        'state' => 'CA',
        'election_date' => now()->subDays(10)->format('Y-m-d'),
        'payload' => [],
    ]);

    Artisan::call('politicians:sync-primary-results', ['--state' => 'CA']);

    $record->refresh();
    expect($record->payload['primary_result'])->toBe('eliminated');
});

test('a state-level candidate record is still classified (existing behavior preserved)', function () {
    raceArticle("====Advanced to general election====\n* [[Jane Advances]]\n");

    $record = ElectionCandidateRecord::factory()->create([
        'full_name' => 'Jane Advances',
        'governance_level' => 'state',
        'political_office' => 'Governor',
        'state' => 'TX',
        'election_date' => now()->subDays(10)->format('Y-m-d'),
        'payload' => [],
    ]);

    Artisan::call('politicians:sync-primary-results', ['--state' => 'TX']);

    $record->refresh();
    expect($record->payload['primary_result'])->toBe('advanced_to_general');
});

test('a local-level candidate record is ignored', function () {
    Http::fake([
        'ballotpedia.org/*' => Http::response('<html>He lost the primary.</html>', 200),
    ]);

    $record = ElectionCandidateRecord::factory()->create([
        'full_name' => 'City Council Person',
        'governance_level' => 'City',
        'state' => 'NY',
        'election_date' => now()->subDays(10)->format('Y-m-d'),
        'payload' => [],
    ]);

    Artisan::call('politicians:sync-primary-results', ['--state' => 'NY']);

    $record->refresh();
    expect($record->payload['primary_result'] ?? null)->toBeNull();
});

test('an eliminated result propagates to the linked politician term_status', function () {
    raceArticle("====Eliminated in primary====\n* [[Losing Candidate]]\n");

    $record = ElectionCandidateRecord::factory()->create([
        'full_name' => 'Losing Candidate',
        'governance_level' => 'federal',
        'political_office' => 'U.S. Senator',
        'state' => 'CA',
        'election_date' => now()->subDays(10)->format('Y-m-d'),
        'payload' => [],
    ]);

    $politician = Politician::factory()->create([
        'full_name' => 'Losing Candidate',
        'state' => 'CA',
        'is_running_candidate' => true,
        'term_status' => 'running',
    ]);

    CandidateIdentityLink::firstOrCreate(
        [
            'politician_id' => $politician->id,
            'election_candidate_record_id' => $record->id,
        ],
        [
            'match_score' => 0.95,
            'link_source' => 'system',
            'linked_at' => now(),
        ]
    );

    Artisan::call('politicians:sync-primary-results', ['--state' => 'CA']);

    $politician->refresh();
    expect($politician->term_status)->toBe('eliminated');
    expect($politician->is_running_candidate)->toBeFalse();
});

test('--dry-run does not write to the linked politician', function () {
    raceArticle("====Eliminated in primary====\n* [[Dry Run Candidate]]\n");

    $record = ElectionCandidateRecord::factory()->create([
        'full_name' => 'Dry Run Candidate',
        'governance_level' => 'federal',
        'political_office' => 'U.S. Senator',
        'state' => 'CA',
        'election_date' => now()->subDays(10)->format('Y-m-d'),
        'payload' => [],
    ]);

    $politician = Politician::factory()->create([
        'full_name' => 'Dry Run Candidate',
        'state' => 'CA',
        'is_running_candidate' => true,
        'term_status' => 'running',
    ]);

    CandidateIdentityLink::firstOrCreate(
        [
            'politician_id' => $politician->id,
            'election_candidate_record_id' => $record->id,
        ],
        [
            'match_score' => 0.95,
            'link_source' => 'system',
            'linked_at' => now(),
        ]
    );

    Artisan::call('politicians:sync-primary-results', ['--state' => 'CA', '--dry-run' => true]);

    $record->refresh();
    $politician->refresh();
    expect($record->payload['primary_result'] ?? null)->toBeNull();
    expect($politician->term_status)->toBe('running');
});


test('a Wikipedia race article decides the result when Ballotpedia is blocked', function () {
    $wikitext = <<<'WIKI'
==Top-two primary==
===Candidates===
====Advanced to general election====
* [[Xavier Becerra]], former [[United States Secretary of Health and Human Services|HHS Secretary]]
* [[Steve Hilton]], political commentator
====Eliminated in primary====
* [[Steven Bradford]], state senator
* [[Ian Calderon|Ian C. Calderon]], former [[Assembly Majority Leader]]
====Withdrawn====
* [[Some Person]]
====Endorsements====
* [[Not Acandidate]]
WIKI;

    Http::fake([
        'en.wikipedia.org/w/api.php*' => Http::response(['parse' => ['title' => '2026 California gubernatorial election', 'wikitext' => $wikitext]]),
        'ballotpedia.org/*' => Http::response('', 202),
        'en.wikipedia.org/*' => Http::response('', 404),
    ]);

    $make = fn (string $name) => ElectionCandidateRecord::factory()->create([
        'full_name' => $name,
        'governance_level' => 'state',
        'political_office' => 'Governor',
        'state' => 'CA',
        'election_date' => '2026-11-03',
        'payload' => [],
    ]);
    $becerra = $make('Xavier Becerra');
    $bradford = $make('Steven Bradford');
    $calderon = $make('Ian Calderon');
    $bystander = $make('Not Acandidate');

    Artisan::call('politicians:sync-primary-results', ['--state' => 'CA']);

    expect($becerra->fresh()->payload['primary_result'])->toBe('advanced_to_general')
        ->and($becerra->fresh()->payload['result_source'])->toBe('wikipedia_race_page')
        ->and($bradford->fresh()->payload['primary_result'])->toBe('eliminated')
        ->and($calderon->fresh()->payload['primary_result'])->toBe('eliminated')
        ->and($bystander->fresh()->payload['primary_result'] ?? null)->toBeNull();
});

test('a Wikipedia race article is ignored while the state primary is still ahead', function () {
    App\Models\StateElectionDate::create([
        'state' => 'CA', 'election_year' => 2026, 'stage_name' => 'Primary Election', 'election_date' => now()->addDays(30)->toDateString(),
    ]);

    Http::fake([
        'en.wikipedia.org/w/api.php*' => Http::response(['parse' => ['wikitext' => "====Eliminated in primary====\n* [[Steven Bradford]]\n"]]),
        'ballotpedia.org/*' => Http::response('', 404),
        'en.wikipedia.org/*' => Http::response('', 404),
    ]);

    $record = ElectionCandidateRecord::factory()->create([
        'full_name' => 'Steven Bradford', 'governance_level' => 'state', 'political_office' => 'Governor',
        'state' => 'CA', 'election_date' => '2026-11-03', 'payload' => [],
    ]);

    Artisan::call('politicians:sync-primary-results', ['--state' => 'CA']);

    expect($record->fresh()->payload['primary_result'] ?? null)->toBeNull();
});

test('a House candidate is matched only within their own district section', function () {
    $wikitext = <<<'WIKI'
==District 1==
====Nominee====
* [[Pat Winner]]
====Eliminated in primary====
* [[Sam Samename]]
==District 2==
====Nominee====
* [[Sam Samename]]
WIKI;

    Http::fake([
        'en.wikipedia.org/w/api.php*' => Http::response(['parse' => ['wikitext' => $wikitext]]),
        'ballotpedia.org/*' => Http::response('', 404),
        'en.wikipedia.org/*' => Http::response('', 404),
    ]);

    $make = fn (string $name, string $district) => ElectionCandidateRecord::factory()->create([
        'full_name' => $name, 'governance_level' => 'federal', 'political_office' => 'U.S. Representative',
        'state' => 'CA', 'district' => $district, 'election_date' => '2026-11-03', 'payload' => [],
    ]);
    $d1 = $make('Sam Samename', '1');
    $d2 = $make('Sam Samename', '2');

    Artisan::call('politicians:sync-primary-results', ['--state' => 'CA']);

    expect($d1->fresh()->payload['primary_result'])->toBe('eliminated')
        ->and($d2->fresh()->payload['primary_result'])->toBe('advanced_to_general');
});

test('a candidate listed as withdrawn is taken off the map even before the primary is held', function () {
    App\Models\StateElectionDate::create([
        'state' => 'CA', 'election_year' => 2026, 'stage_name' => 'Primary Election', 'election_date' => now()->addDays(30)->toDateString(),
    ]);

    Http::fake([
        'en.wikipedia.org/w/api.php*' => Http::response(['parse' => ['wikitext' => "====Withdrawn====\n* [[Eric Swalwell]], former U.S. representative\n====Declined====\n* [[Dana Decliner]]\n"]]),
        'ballotpedia.org/*' => Http::response('', 404),
        'en.wikipedia.org/*' => Http::response('', 404),
    ]);

    $make = fn (string $name) => ElectionCandidateRecord::factory()->create([
        'full_name' => $name, 'governance_level' => 'state', 'political_office' => 'Governor',
        'state' => 'CA', 'election_date' => '2026-11-03', 'payload' => [],
    ]);
    $swalwell = $make('Eric Swalwell');
    $decliner = $make('Dana Decliner');
    $politician = Politician::factory()->create(['full_name' => 'Eric Swalwell', 'state' => 'CA', 'user_id' => null, 'term_status' => 'running', 'is_running_candidate' => true, 'slug' => 'eric-swalwell-x']);
    CandidateIdentityLink::create(['politician_id' => $politician->id, 'election_candidate_record_id' => $swalwell->id, 'match_score' => 0.9, 'link_source' => 'system']);

    Artisan::call('politicians:sync-primary-results', ['--state' => 'CA']);

    $payload = $swalwell->fresh()->payload;
    expect($payload['primary_result'])->toBe('eliminated')
        ->and($payload['withdrawn'])->toBeTrue()
        ->and($payload['elimination_note'])->toContain('Withdrew')
        ->and($payload['result_source'])->toBe('wikipedia_race_page')
        ->and($politician->fresh()->term_status)->toBe('eliminated')
        ->and($politician->fresh()->is_running_candidate)->toBeFalse()
        ->and($decliner->fresh()->payload['primary_result'] ?? null)->toBeNull();
});

test('a page that merely says a candidate is running does not stamp them as advanced', function () {
    Http::fake([
        'ballotpedia.org/*' => Http::response('<html>She is running for governor and is a candidate for the top-two primary.</html>', 200),
        'en.wikipedia.org/*' => Http::response('', 404),
    ]);

    $record = ElectionCandidateRecord::factory()->create([
        'full_name' => 'Jane Runner', 'governance_level' => 'state', 'political_office' => 'Governor',
        'state' => 'CA', 'election_date' => '2026-11-03', 'payload' => [],
    ]);

    Artisan::call('politicians:sync-primary-results', ['--state' => 'CA']);

    expect($record->fresh()->payload['primary_result'] ?? null)->toBeNull();
});

test('a discovery record stamped "running" is still checked without --force', function () {
    Http::fake([
        'en.wikipedia.org/w/api.php*' => Http::response(['parse' => ['wikitext' => "====Withdrawn====\n* [[Eric Swalwell]]\n"]]),
        'ballotpedia.org/*' => Http::response('', 404),
        'en.wikipedia.org/*' => Http::response('', 404),
    ]);

    $record = ElectionCandidateRecord::factory()->create([
        'full_name' => 'Eric Swalwell', 'governance_level' => 'State', 'political_office' => 'Governor',
        'state' => 'CA', 'election_date' => '2026-11-03', 'payload' => ['primary_result' => 'running'],
    ]);

    Artisan::call('politicians:sync-primary-results', ['--state' => 'CA']);

    expect($record->fresh()->payload['primary_result'])->toBe('eliminated')
        ->and($record->fresh()->payload['withdrawn'])->toBeTrue();
});

test('a Wikipedia bullet with no wikilink is still read as a candidate name', function () {
    Http::fake([
        'en.wikipedia.org/w/api.php*' => Http::response(['parse' => ['wikitext' => "====Withdrawn====\n* Tom Woodard, retired CEO<ref name=\"x\">cite</ref>\n* Sophia Brink, legislative aide to [[San Mateo County]] supervisor\n"]]),
        'ballotpedia.org/*' => Http::response('', 404),
        'en.wikipedia.org/*' => Http::response('', 404),
    ]);

    $make = fn (string $name) => ElectionCandidateRecord::factory()->create([
        'full_name' => $name, 'governance_level' => 'State', 'political_office' => 'Governor',
        'state' => 'CA', 'election_date' => '2026-11-03', 'payload' => ['primary_result' => 'running'],
    ]);
    $woodard = $make('Tom Woodard');
    $brink = $make('Sophia Brink');

    Artisan::call('politicians:sync-primary-results', ['--state' => 'CA']);

    expect($woodard->fresh()->payload['primary_result'])->toBe('eliminated')
        ->and($brink->fresh()->payload['primary_result'])->toBe('eliminated');
});

test('page text alone never stamps a candidate, and never touches a sitting member', function () {
    // Ballotpedia lists the people a candidate endorsed under "lost primary"; a biography says "conceded".
    Http::fake([
        'ballotpedia.org/*' => Http::response('<html>Endorsed Aaron Reitz: lost primary. He conceded and was eliminated.</html>', 200),
        'en.wikipedia.org/w/api.php*' => Http::response(['parse' => ['wikitext' => "====Nominee====\n* [[Someone Else]]\n"]]),
        'en.wikipedia.org/*' => Http::response(['extract' => 'Ted Lieu lost the primary and conceded.']),
    ]);

    $record = ElectionCandidateRecord::factory()->create([
        'full_name' => 'Ted Lieu', 'governance_level' => 'federal', 'political_office' => 'U.S. Senator',
        'state' => 'CA', 'election_date' => '2026-11-03', 'payload' => ['primary_result' => 'running'],
    ]);
    $profile = Politician::factory()->create(['full_name' => 'Ted Lieu', 'state' => 'CA', 'user_id' => null, 'term_status' => 'running', 'is_running_candidate' => true, 'slug' => 'ted-lieu-x']);
    CandidateIdentityLink::create(['politician_id' => $profile->id, 'election_candidate_record_id' => $record->id, 'match_score' => 0.9, 'link_source' => 'system']);

    Artisan::call('politicians:sync-primary-results', ['--state' => 'CA', '--force' => true]);

    expect($record->fresh()->payload['primary_result'])->toBe('running')
        ->and($profile->fresh()->term_status)->toBe('running');
    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'ballotpedia.org') || str_contains($request->url(), 'rest_v1'));
});
