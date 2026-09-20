<?php

use App\Models\Politician;
use App\Models\PoliticianCleanupReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/**
 * Seed a Politician row bypassing the model's saving-hook auto-repair, so
 * tests can reproduce junk full_name rows that predate PoliticianNameRepairer
 * — same approach PruneJunkEcrsCommandTest uses for ElectionCandidateRecord.
 */
function politicianJunk(array $attrs = []): Politician
{
    $model = Politician::factory()->make(array_merge([
        'full_name' => 'Former California',
        'state' => 'CA',
        'slug' => Str::uuid().'-politician',
    ], $attrs));
    $model->saveQuietly();

    return $model->refresh();
}

it('reports repairs without writing in dry-run mode', function () {
    $junk = politicianJunk(['full_name' => 'California Gavin Newsom']);

    $this->artisan('politicians:repair-names', ['--state' => 'CA'])
        ->expectsOutputToContain('Gavin Newsom')
        ->assertExitCode(0);

    expect($junk->refresh()->full_name)->toBe('California Gavin Newsom');
});

it('repairs a junk name in place with --apply', function () {
    $junk = politicianJunk(['full_name' => 'Independent Michael Shellenberger']);

    $this->artisan('politicians:repair-names', ['--state' => 'CA', '--apply' => true])
        ->assertExitCode(0);

    expect($junk->refresh()->full_name)->toBe('Michael Shellenberger');
});

it('rebuilds the slug of an unpublished profile when it repairs the name', function () {
    $junk = politicianJunk([
        'full_name' => 'Meet Mike Rogers', 'political_office' => 'U.S. Senator',
        'slug' => '04505-us-senator-meet-mike-rogers', 'page_published' => false,
    ]);

    $this->artisan('politicians:repair-names', ['--state' => 'CA', '--apply' => true])->assertExitCode(0);

    expect($junk->refresh()->slug)->toContain('mike-rogers')->not->toContain('meet');
});

it('keeps the slug of a published profile when it repairs the name', function () {
    $junk = politicianJunk([
        'full_name' => 'Meet Mike Rogers', 'political_office' => 'U.S. Senator',
        'slug' => '04505-us-senator-meet-mike-rogers', 'page_published' => true,
    ]);

    $this->artisan('politicians:repair-names', ['--state' => 'CA', '--apply' => true])->assertExitCode(0);

    expect($junk->refresh()->slug)->toBe('04505-us-senator-meet-mike-rogers');
});

it('leaves a clean name untouched', function () {
    $clean = politicianJunk(['full_name' => 'Xavier Becerra']);

    $this->artisan('politicians:repair-names', ['--state' => 'CA', '--apply' => true])
        ->assertExitCode(0);

    expect($clean->refresh()->full_name)->toBe('Xavier Becerra');
});

it('enqueues an unrepairable name for review with --apply --enqueue-review', function () {
    $unrepairable = politicianJunk(['full_name' => 'Former California Newsom']);

    $this->artisan('politicians:repair-names', ['--state' => 'CA', '--apply' => true, '--enqueue-review' => true])
        ->assertExitCode(0);

    expect($unrepairable->refresh()->full_name)->toBe('Former California Newsom');

    $this->assertDatabaseHas('politician_cleanup_reviews', [
        'review_type' => PoliticianCleanupReview::TYPE_NAME_REJECT,
        'politician_id' => $unrepairable->id,
        'status' => PoliticianCleanupReview::STATUS_PENDING,
    ]);
});

it('does not enqueue an unrepairable name without --enqueue-review', function () {
    politicianJunk(['full_name' => 'Former California Newsom']);

    $this->artisan('politicians:repair-names', ['--state' => 'CA', '--apply' => true])
        ->assertExitCode(0);

    $this->assertDatabaseCount('politician_cleanup_reviews', 0);
});

it('does not duplicate a name_reject review (null duplicate_politician_id) on a re-run', function () {
    politicianJunk(['full_name' => 'Former California Newsom']);

    $this->artisan('politicians:repair-names', ['--state' => 'CA', '--apply' => true, '--enqueue-review' => true])->assertExitCode(0);
    $this->artisan('politicians:repair-names', ['--state' => 'CA', '--apply' => true, '--enqueue-review' => true])->assertExitCode(0);

    $this->assertDatabaseCount('politician_cleanup_reviews', 1);
});

it('busts the state map cache when it repairs a name', function () {
    politicianJunk(['full_name' => 'Independent Michael Shellenberger']);
    Cache::put('map_state_candidates_CA', ['stale' => true], 3600);

    $this->artisan('politicians:repair-names', ['--state' => 'CA', '--apply' => true])->assertExitCode(0);

    expect(Cache::has('map_state_candidates_CA'))->toBeFalse();
});

it('retires an unclaimed profile whose name is only a place or an election-page title, instead of queueing it', function () {
    $place = politicianJunk(['full_name' => 'California', 'is_active' => true, 'page_published' => true, 'user_id' => null, 'fec_candidate_id' => null]);
    $title = politicianJunk(['full_name' => "California's 2nd Congressional District election, 2026", 'is_active' => true, 'page_published' => true, 'user_id' => null, 'fec_candidate_id' => null]);

    $this->artisan('politicians:repair-names', ['--state' => 'CA', '--apply' => true, '--enqueue-review' => true])
        ->expectsOutputToContain('not a person')
        ->assertExitCode(0);

    expect($place->refresh()->is_active)->toBeFalse()
        ->and($place->page_published)->toBeFalse()
        ->and($title->refresh()->is_active)->toBeFalse()
        ->and(PoliticianCleanupReview::where('status', PoliticianCleanupReview::STATUS_PENDING)->count())->toBe(0)
        ->and(PoliticianCleanupReview::where('status', PoliticianCleanupReview::STATUS_APPROVED)->count())->toBe(2);
});

it('only reports a not-a-person name in dry-run', function () {
    $place = politicianJunk(['full_name' => 'California', 'is_active' => true, 'page_published' => true]);

    $this->artisan('politicians:repair-names', ['--state' => 'CA'])->expectsOutputToContain('would retire')->assertExitCode(0);

    expect($place->refresh()->is_active)->toBeTrue();
});

it('never retires a claimed or FEC-identified profile, and queues it instead', function () {
    $claimed = politicianJunk(['full_name' => 'California', 'is_active' => true, 'page_published' => true, 'user_id' => App\Models\User::factory()->create()->id]);
    $fec = politicianJunk(['full_name' => 'Former California', 'is_active' => true, 'page_published' => true, 'user_id' => null, 'fec_candidate_id' => 'H0CA00001']);

    $this->artisan('politicians:repair-names', ['--state' => 'CA', '--apply' => true, '--enqueue-review' => true])->assertExitCode(0);

    expect($claimed->refresh()->is_active)->toBeTrue()
        ->and($fec->refresh()->is_active)->toBeTrue()
        ->and(PoliticianCleanupReview::where('status', PoliticianCleanupReview::STATUS_PENDING)->count())->toBe(2);
});

it('keeps a leftover surname for review rather than retiring it', function () {
    $person = politicianJunk(['full_name' => 'Former California Newsom', 'is_active' => true, 'page_published' => true, 'user_id' => null]);

    $this->artisan('politicians:repair-names', ['--state' => 'CA', '--apply' => true, '--enqueue-review' => true])->assertExitCode(0);

    expect($person->refresh()->is_active)->toBeTrue()
        ->and(PoliticianCleanupReview::where('politician_id', $person->id)->where('status', PoliticianCleanupReview::STATUS_PENDING)->exists())->toBeTrue();
});

it('stays quiet about an unrepairable name that is already hidden', function () {
    politicianJunk(['full_name' => 'California', 'is_active' => false, 'page_published' => false]);

    $this->artisan('politicians:repair-names', ['--state' => 'CA', '--apply' => true, '--enqueue-review' => true])
        ->doesntExpectOutputToContain('needs manual review')
        ->assertExitCode(0);

    expect(PoliticianCleanupReview::count())->toBe(0);
});

it('retires a name whose "repair" would only leave a question or an office name', function (string $junk) {
    $row = politicianJunk(['full_name' => $junk, 'is_active' => true, 'page_published' => true, 'user_id' => null, 'fec_candidate_id' => null]);

    $this->artisan('politicians:repair-names', ['--state' => 'CA', '--apply' => true, '--enqueue-review' => true])->assertExitCode(0);

    expect($row->refresh()->full_name)->toBe($junk)
        ->and($row->is_active)->toBeFalse();
})->with(['How do I run for office?', 'California State Senate', 'California Assembly District 30']);

it('still repairs a real name with a leading qualifier', function () {
    $row = politicianJunk(['full_name' => 'California Gavin Newsom', 'is_active' => true, 'page_published' => true]);

    $this->artisan('politicians:repair-names', ['--state' => 'CA', '--apply' => true])->assertExitCode(0);

    expect($row->refresh()->full_name)->toBe('Gavin Newsom')->and($row->is_active)->toBeTrue();
});

it('retires office, body and party names that a qualifier strip would leave behind', function (string $junk) {
    $row = politicianJunk(['full_name' => $junk, 'is_active' => true, 'page_published' => true, 'user_id' => null, 'fec_candidate_id' => null]);

    $this->artisan('politicians:repair-names', ['--state' => 'CA', '--apply' => true, '--enqueue-review' => true])->assertExitCode(0);

    expect($row->refresh()->full_name)->toBe($junk)->and($row->is_active)->toBeFalse();
})->with(['The Indiana Attorney General', 'The Nevada Attorney General', 'Democratic Party', 'Texas Municipal Police Association', 'Orange County Board of Commissioners']);
