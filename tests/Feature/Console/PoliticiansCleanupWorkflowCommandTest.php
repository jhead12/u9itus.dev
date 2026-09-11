<?php

use App\Models\ElectionCandidateRecord;
use App\Models\Politician;
use App\Models\PoliticianCleanupReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

// politicians:reconcile-status (federal) is one of the pipeline steps and
// hits the congress-legislators feed over HTTP — fake it so these tests
// don't depend on network access.
beforeEach(function () {
    Http::fake([
        '*legislators-current.json' => Http::response([], 200),
        '*legislators-historical.json' => Http::response([], 200),
    ]);
});

function workflowJunkPolitician(array $attrs = []): Politician
{
    $model = Politician::factory()->make(array_merge([
        'full_name' => 'Former California',
        'state' => 'CA',
        'slug' => Str::uuid().'-politician',
    ], $attrs));
    $model->saveQuietly();

    return $model->refresh();
}

it('runs every step and repairs a junk name end to end', function () {
    $junk = workflowJunkPolitician(['full_name' => 'California Gavin Newsom']);

    $this->artisan('politicians:cleanup-workflow')
        ->assertExitCode(0);

    expect($junk->refresh()->full_name)->toBe('Gavin Newsom');
});

it('enqueues a duplicate pair for review instead of merging it', function () {
    $survivor = workflowJunkPolitician(['full_name' => 'Mark Takano', 'political_office' => 'United States Representative', 'governance_level' => 'Federal', 'verified_official' => true]);
    $loser = workflowJunkPolitician(['full_name' => 'Mark Takano', 'political_office' => 'United States Representative', 'governance_level' => 'Federal', 'verified_official' => false]);

    $this->artisan('politicians:cleanup-workflow')
        ->assertExitCode(0);

    expect(Politician::find($survivor->id))->not->toBeNull()
        ->and(Politician::find($loser->id))->not->toBeNull();

    $this->assertDatabaseHas('politician_cleanup_reviews', [
        'review_type' => PoliticianCleanupReview::TYPE_MERGE,
        'status' => PoliticianCleanupReview::STATUS_PENDING,
    ]);
});

it('--dry-run makes no changes at all', function () {
    $junk = workflowJunkPolitician(['full_name' => 'California Gavin Newsom']);

    $this->artisan('politicians:cleanup-workflow', ['--dry-run' => true])
        ->assertExitCode(0);

    expect($junk->refresh()->full_name)->toBe('California Gavin Newsom');
    $this->assertDatabaseCount('politician_cleanup_reviews', 0);
});

it('prunes junk election candidate records as part of the pipeline', function () {
    $junkEcr = new ElectionCandidateRecord([
        'source' => 'ballotpedia',
        'external_candidate_id' => 'ext-1',
        'full_name' => 'Job Creator',
        'political_office' => 'Governor',
        'governance_level' => 'state',
        'state' => 'CA',
        'election_date' => now()->addMonths(2)->toDateString(),
        'payload' => [],
        'last_seen_at' => now(),
    ]);
    $junkEcr->saveQuietly();

    $this->artisan('politicians:cleanup-workflow')
        ->assertExitCode(0);

    $this->assertDatabaseMissing('election_candidate_records', ['id' => $junkEcr->id]);
});
