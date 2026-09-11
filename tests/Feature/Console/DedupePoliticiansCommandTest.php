<?php

use App\Models\Politician;
use App\Models\PoliticianCleanupReview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function federalDuplicate(array $overrides = []): Politician
{
    return Politician::factory()->create(array_merge([
        'user_id' => null,
        'full_name' => 'Mark Takano',
        'political_office' => 'United States Representative',
        'governance_level' => 'Federal',
        'state' => 'CA',
    ], $overrides));
}

test('--scope=federal deletes an orphan duplicate with --apply', function () {
    $survivor = federalDuplicate(['verified_official' => true]);
    $orphan = federalDuplicate(['verified_official' => false]);

    $this->artisan('politicians:dedupe', ['--scope' => 'federal', '--apply' => true])
        ->assertExitCode(0);

    expect(Politician::find($survivor->id))->not->toBeNull()
        ->and(Politician::find($orphan->id))->toBeNull();
});

test('--scope=federal never deletes a claimed profile', function () {
    $user = User::factory()->create();
    $claimed = federalDuplicate(['user_id' => $user->id]);
    $unclaimed = federalDuplicate();

    $this->artisan('politicians:dedupe', ['--scope' => 'federal', '--apply' => true])
        ->assertExitCode(0);

    expect(Politician::find($claimed->id))->not->toBeNull()
        ->and(Politician::find($unclaimed->id))->not->toBeNull();
});

test('--enqueue-review writes a pending review instead of deleting', function () {
    $survivor = federalDuplicate(['verified_official' => true]);
    $loser = federalDuplicate(['verified_official' => false]);

    $this->artisan('politicians:dedupe', ['--scope' => 'federal', '--enqueue-review' => true])
        ->assertExitCode(0);

    expect(Politician::find($survivor->id))->not->toBeNull()
        ->and(Politician::find($loser->id))->not->toBeNull();

    $this->assertDatabaseHas('politician_cleanup_reviews', [
        'review_type' => PoliticianCleanupReview::TYPE_MERGE,
        'politician_id' => $survivor->id,
        'duplicate_politician_id' => $loser->id,
        'status' => PoliticianCleanupReview::STATUS_PENDING,
    ]);
});

test('--enqueue-review does not duplicate an already-pending review on a re-run', function () {
    $survivor = federalDuplicate(['verified_official' => true]);
    $loser = federalDuplicate(['verified_official' => false]);

    $this->artisan('politicians:dedupe', ['--scope' => 'federal', '--enqueue-review' => true])->assertExitCode(0);
    $this->artisan('politicians:dedupe', ['--scope' => 'federal', '--enqueue-review' => true])->assertExitCode(0);

    $this->assertDatabaseCount('politician_cleanup_reviews', 1);
});

test('rejects an invalid --scope value', function () {
    $this->artisan('politicians:dedupe', ['--scope' => 'bogus'])
        ->assertExitCode(1);
});
