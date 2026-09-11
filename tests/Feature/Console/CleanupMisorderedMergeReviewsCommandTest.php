<?php

use App\Models\Politician;
use App\Models\PoliticianCleanupReview;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('deletes a pending merge review that kept the mangled name over the clean one', function () {
    $clean = Politician::factory()->create(['full_name' => 'Eric Swalwell']);
    $mangled = Politician::factory()->create(['full_name' => 'Eric Swalwell Officially']);

    $wrongDirection = PoliticianCleanupReview::create([
        'review_type' => PoliticianCleanupReview::TYPE_MERGE,
        'politician_id' => $mangled->id,
        'duplicate_politician_id' => $clean->id,
        'payload' => [],
        'status' => PoliticianCleanupReview::STATUS_PENDING,
    ]);

    $this->artisan('politicians:cleanup-misordered-merge-reviews', ['--apply' => true])
        ->assertExitCode(0);

    expect(PoliticianCleanupReview::find($wrongDirection->id))->toBeNull();
});

it('leaves a correctly-ordered pending merge review untouched', function () {
    $clean = Politician::factory()->create(['full_name' => 'Eric Swalwell']);
    $mangled = Politician::factory()->create(['full_name' => 'Eric Swalwell Officially']);

    $correct = PoliticianCleanupReview::create([
        'review_type' => PoliticianCleanupReview::TYPE_MERGE,
        'politician_id' => $clean->id,
        'duplicate_politician_id' => $mangled->id,
        'payload' => [],
        'status' => PoliticianCleanupReview::STATUS_PENDING,
    ]);

    $this->artisan('politicians:cleanup-misordered-merge-reviews', ['--apply' => true])
        ->assertExitCode(0);

    expect(PoliticianCleanupReview::find($correct->id))->not->toBeNull();
});

it('leaves a review where both names are headline-clean untouched', function () {
    $a = Politician::factory()->create(['full_name' => 'Jane Smith']);
    $b = Politician::factory()->create(['full_name' => 'John Doe']);

    $review = PoliticianCleanupReview::create([
        'review_type' => PoliticianCleanupReview::TYPE_MERGE,
        'politician_id' => $a->id,
        'duplicate_politician_id' => $b->id,
        'payload' => [],
        'status' => PoliticianCleanupReview::STATUS_PENDING,
    ]);

    $this->artisan('politicians:cleanup-misordered-merge-reviews', ['--apply' => true])
        ->assertExitCode(0);

    expect(PoliticianCleanupReview::find($review->id))->not->toBeNull();
});
