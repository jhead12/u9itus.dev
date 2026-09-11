<?php

use App\Models\Politician;
use App\Models\PoliticianCleanupReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

it('leaves a clean name untouched', function () {
    $clean = politicianJunk(['full_name' => 'Xavier Becerra']);

    $this->artisan('politicians:repair-names', ['--state' => 'CA', '--apply' => true])
        ->assertExitCode(0);

    expect($clean->refresh()->full_name)->toBe('Xavier Becerra');
});

it('enqueues an unrepairable name for review with --apply --enqueue-review', function () {
    $unrepairable = politicianJunk(['full_name' => 'Former California']);

    $this->artisan('politicians:repair-names', ['--state' => 'CA', '--apply' => true, '--enqueue-review' => true])
        ->assertExitCode(0);

    expect($unrepairable->refresh()->full_name)->toBe('Former California');

    $this->assertDatabaseHas('politician_cleanup_reviews', [
        'review_type' => PoliticianCleanupReview::TYPE_NAME_REJECT,
        'politician_id' => $unrepairable->id,
        'status' => PoliticianCleanupReview::STATUS_PENDING,
    ]);
});

it('does not enqueue an unrepairable name without --enqueue-review', function () {
    politicianJunk(['full_name' => 'Former California']);

    $this->artisan('politicians:repair-names', ['--state' => 'CA', '--apply' => true])
        ->assertExitCode(0);

    $this->assertDatabaseCount('politician_cleanup_reviews', 0);
});

it('does not duplicate a name_reject review (null duplicate_politician_id) on a re-run', function () {
    politicianJunk(['full_name' => 'Former California']);

    $this->artisan('politicians:repair-names', ['--state' => 'CA', '--apply' => true, '--enqueue-review' => true])->assertExitCode(0);
    $this->artisan('politicians:repair-names', ['--state' => 'CA', '--apply' => true, '--enqueue-review' => true])->assertExitCode(0);

    $this->assertDatabaseCount('politician_cleanup_reviews', 1);
});
