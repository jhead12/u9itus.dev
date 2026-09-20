<?php

use App\Models\CandidateIdentityLink;
use App\Models\ElectionCandidateRecord;
use App\Models\Politician;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/**
 * Seed an ECR row, bypassing the model's name-quality write-guard so tests
 * can reproduce junk rows that predate it.
 */
function ecr(array $attrs): ElectionCandidateRecord
{
    $record = new ElectionCandidateRecord(array_merge([
        'source' => 'ballotpedia',
        'external_candidate_id' => 'ext-'.fake()->unique()->numerify('######'),
        'full_name' => fake()->name(),
        'political_office' => 'Governor',
        'governance_level' => 'state',
        'state' => 'CA',
        'election_date' => now()->addMonths(2)->toDateString(),
        'payload' => [],
        'last_seen_at' => now(),
    ], $attrs));
    $record->saveQuietly();

    return $record->refresh();
}

it('flags headline-fragment names but keeps real ones (dry run by default)', function () {
    $junk = ecr(['full_name' => 'Former L.A. Mayor Antonio']);
    $good = ecr(['full_name' => 'Xavier Becerra']);

    $this->artisan('politicians:prune-junk-ecrs', ['--state' => ['CA']])
        ->expectsOutputToContain('name')
        ->assertExitCode(0);

    $this->assertDatabaseHas('election_candidate_records', ['id' => $junk->id]);
    $this->assertDatabaseHas('election_candidate_records', ['id' => $good->id]);
});

it('deletes flagged rows with --apply and clears the state map cache', function () {
    $junk = ecr(['full_name' => 'Job Creator', 'state' => 'CA']);
    $good = ecr(['full_name' => 'Katie Porter', 'state' => 'CA']);
    Cache::put('map_state_candidates_CA', ['stale' => true], 3600);

    $this->artisan('politicians:prune-junk-ecrs', ['--state' => ['CA'], '--apply' => true])
        ->assertExitCode(0);

    $this->assertDatabaseMissing('election_candidate_records', ['id' => $junk->id]);
    $this->assertDatabaseHas('election_candidate_records', ['id' => $good->id]);
    expect(Cache::has('map_state_candidates_CA'))->toBeFalse();
});

it('flags stale-cycle rows unless --keep-stale is passed', function () {
    $stale = ecr(['full_name' => 'Jerry Brown', 'election_date' => '2018-11-06']);

    $this->artisan('politicians:prune-junk-ecrs', ['--state' => ['CA'], '--keep-stale' => true, '--apply' => true])
        ->assertExitCode(0);
    $this->assertDatabaseHas('election_candidate_records', ['id' => $stale->id]);

    $this->artisan('politicians:prune-junk-ecrs', ['--state' => ['CA'], '--apply' => true])
        ->assertExitCode(0);
    $this->assertDatabaseMissing('election_candidate_records', ['id' => $stale->id]);
});

it('flags a row whose office names a different state than its own state column', function () {
    $crossState = ecr([
        'full_name' => 'Ken Paxton',
        'political_office' => 'Texas Attorney General',
        'state' => 'CA',
    ]);

    $this->artisan('politicians:prune-junk-ecrs', ['--state' => ['CA'], '--apply' => true])
        ->assertExitCode(0);

    $this->assertDatabaseMissing('election_candidate_records', ['id' => $crossState->id]);
});

it('collapses duplicates keeping the higher-priority source', function () {
    $seed = ecr(['full_name' => 'Steve Hilton', 'source' => 'seed', 'political_office' => 'Governor', 'state' => 'CA']);
    $dupA = ecr(['full_name' => 'Steve Hilton', 'source' => 'candidate_discovery', 'political_office' => 'Governor', 'state' => 'CA']);
    $dupB = ecr(['full_name' => 'Steve Hilton', 'source' => 'candidate_discovery', 'political_office' => 'California Governor', 'state' => 'CA']);

    $this->artisan('politicians:prune-junk-ecrs', ['--state' => ['CA'], '--apply' => true])
        ->assertExitCode(0);

    $this->assertDatabaseHas('election_candidate_records', ['id' => $seed->id]);
    $this->assertDatabaseMissing('election_candidate_records', ['id' => $dupA->id]);
    $this->assertDatabaseMissing('election_candidate_records', ['id' => $dupB->id]);
});

it('never deletes an identity-linked row, even when it looks like junk', function () {
    $linked = ecr(['full_name' => 'Job Creator', 'state' => 'CA']);
    $politician = Politician::factory()->create(['full_name' => 'Jane Doe', 'state' => 'CA']);
    CandidateIdentityLink::create([
        'politician_id' => $politician->id,
        'election_candidate_record_id' => $linked->id,
        'match_score' => 0.9,
        'link_source' => 'system',
        'linked_at' => now(),
    ]);

    $this->artisan('politicians:prune-junk-ecrs', ['--state' => ['CA'], '--apply' => true])
        ->expectsOutputToContain('identity-linked')
        ->assertExitCode(0);

    $this->assertDatabaseHas('election_candidate_records', ['id' => $linked->id]);
});

it('respects the --state filter', function () {
    $caJunk = ecr(['full_name' => 'Job Creator', 'state' => 'CA']);
    $nyJunk = ecr(['full_name' => 'Job Creator', 'state' => 'NY', 'external_candidate_id' => 'ny-1']);

    $this->artisan('politicians:prune-junk-ecrs', ['--state' => ['CA'], '--apply' => true])
        ->assertExitCode(0);

    $this->assertDatabaseMissing('election_candidate_records', ['id' => $caJunk->id]);
    $this->assertDatabaseHas('election_candidate_records', ['id' => $nyJunk->id]);
});


it('with --wikipedia deletes a discovered name that is nowhere on the race article, and keeps the rest', function () {
    Http::fake(['en.wikipedia.org/w/api.php*' => Http::response(['parse' => ['wikitext' => "====Advanced to general====\n* [[Xavier Becerra]], former AG\n* [[Steve Hilton]], commentator\n====Withdrawn====\n* [[Eric Swalwell]], former representative\n"]])]);

    $fragment = ecr(['source' => 'candidate_discovery', 'full_name' => 'Lamont Launch', 'external_candidate_id' => 'a1']);
    $wrongRace = ecr(['source' => 'candidate_discovery', 'full_name' => 'Stacey Abrams', 'external_candidate_id' => 'a2']);
    $listed = ecr(['source' => 'candidate_discovery', 'full_name' => 'Steve Hilton', 'external_candidate_id' => 'a3']);
    $withdrawn = ecr(['source' => 'candidate_discovery', 'full_name' => 'Eric Swalwell', 'external_candidate_id' => 'a4']);
    $decorated = ecr(['source' => 'candidate_discovery', 'full_name' => 'Xavier Becerra Record-Breaking', 'external_candidate_id' => 'a5']);
    $seed = ecr(['source' => 'seed', 'full_name' => 'Someone Unlisted', 'external_candidate_id' => 'a6']);

    $this->artisan('politicians:prune-junk-ecrs', ['--state' => ['CA'], '--wikipedia' => true, '--apply' => true, '--no-dedup' => true])
        ->expectsOutputToContain('Xavier Becerra Record-Breaking → Xavier Becerra')
        ->assertExitCode(0);

    $this->assertDatabaseMissing('election_candidate_records', ['id' => $fragment->id]);
    $this->assertDatabaseMissing('election_candidate_records', ['id' => $wrongRace->id]);
    foreach ([$listed, $withdrawn, $decorated, $seed] as $kept) {
        $this->assertDatabaseHas('election_candidate_records', ['id' => $kept->id]);
    }
});

it('does nothing on Wikipedia grounds without --wikipedia', function () {
    $fragment = ecr(['source' => 'candidate_discovery', 'full_name' => 'Lamont Launch', 'external_candidate_id' => 'b1']);
    Http::fake(['en.wikipedia.org/w/api.php*' => Http::response(['parse' => ['wikitext' => "====Advanced to general====\n* [[Steve Hilton]]\n* [[Xavier Becerra]]\n"]])]);

    $this->artisan('politicians:prune-junk-ecrs', ['--state' => ['CA'], '--apply' => true, '--no-dedup' => true])->assertExitCode(0);

    $this->assertDatabaseHas('election_candidate_records', ['id' => $fragment->id]);
});

it('keeps everything when the race article cannot be found', function () {
    $fragment = ecr(['source' => 'candidate_discovery', 'full_name' => 'Lamont Launch', 'external_candidate_id' => 'b2']);
    Http::fake(['en.wikipedia.org/w/api.php*' => Http::response(['error' => ['code' => 'missingtitle']])]);

    $this->artisan('politicians:prune-junk-ecrs', ['--state' => ['CA'], '--wikipedia' => true, '--apply' => true, '--no-dedup' => true])->assertExitCode(0);

    $this->assertDatabaseHas('election_candidate_records', ['id' => $fragment->id]);
});

it('keeps a discovered nickname of a listed candidate ("Barb" for "Barbara")', function () {
    Http::fake(['en.wikipedia.org/w/api.php*' => Http::response(['parse' => ['wikitext' => "====Eliminated in primary====\n* [[Barbara Kirkmeyer]], state senator\n====Advanced to general====\n* [[Steve Hilton]]\n* [[Xavier Becerra]]\n"]])]);
    $nick = ecr(['source' => 'candidate_discovery', 'full_name' => 'Barb Kirkmeyer', 'external_candidate_id' => 'c1']);

    $this->artisan('politicians:prune-junk-ecrs', ['--state' => ['CA'], '--wikipedia' => true, '--apply' => true, '--no-dedup' => true])->assertExitCode(0);

    $this->assertDatabaseHas('election_candidate_records', ['id' => $nick->id]);
});

it('deletes no "unlisted" rows when more are flagged than --max-unlisted', function () {
    Http::fake(['en.wikipedia.org/w/api.php*' => Http::response(['parse' => ['wikitext' => "====Advanced to general====\n* [[Steve Hilton]]\n* [[Xavier Becerra]]\n"]])]);
    $rows = collect(['Lamont Launch', 'Impact Church', 'Iowa Split'])->map(fn ($n, $i) => ecr(['source' => 'candidate_discovery', 'full_name' => $n, 'external_candidate_id' => "d{$i}"]));

    $this->artisan('politicians:prune-junk-ecrs', ['--state' => ['CA'], '--wikipedia' => true, '--apply' => true, '--no-dedup' => true, '--max-unlisted' => 2])
        ->expectsOutputToContain('none deleted')
        ->assertExitCode(0);

    foreach ($rows as $row) {
        $this->assertDatabaseHas('election_candidate_records', ['id' => $row->id]);
    }
});
