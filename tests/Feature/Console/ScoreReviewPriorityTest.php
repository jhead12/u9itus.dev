<?php

use App\Models\CandidateMatchReview;
use App\Models\ElectionCandidateRecord;
use App\Models\Politician;
use App\Models\PoliticianCleanupReview;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function reviewPolitician(array $over = []): Politician
{
    return Politician::factory()->create(array_merge([
        'full_name' => 'Some Profile', 'state' => 'CA', 'political_office' => 'Governor',
        'slug' => 'some-profile-'.fake()->unique()->numerify('####'),
    ], $over));
}

it('scores a published profile higher than an already-inactive one, and orders the admin queue accordingly', function () {
    $published = reviewPolitician(['page_published' => true, 'is_active' => true]);
    $inactive = reviewPolitician(['page_published' => false, 'is_active' => false]);

    $highReview = PoliticianCleanupReview::enqueue(PoliticianCleanupReview::TYPE_DEACTIVATE, $published->id, null, ['source' => 'flag-suspect-profiles'], 'r1');
    $lowReview = PoliticianCleanupReview::enqueue(PoliticianCleanupReview::TYPE_DEACTIVATE, $inactive->id, null, ['source' => 'flag-suspect-profiles'], 'r2');

    $this->artisan('politicians:score-review-priority')->assertExitCode(0);

    expect($highReview->refresh()->severity)->toBe(5)
        ->and($highReview->priority_score)->toBeGreaterThan($lowReview->refresh()->priority_score)
        ->and($lowReview->severity)->toBe(1);
});

it('caps occurrence at 5 and groups by source + politician name', function () {
    $reviews = collect(range(1, 6))->map(function () {
        $pol = reviewPolitician(['full_name' => 'Repeat Name']);

        return PoliticianCleanupReview::enqueue(PoliticianCleanupReview::TYPE_DEACTIVATE, $pol->id, null, ['source' => 'flag-suspect-profiles-federal-collision'], 'dup');
    });
    $unrelated = PoliticianCleanupReview::enqueue(
        PoliticianCleanupReview::TYPE_DEACTIVATE,
        reviewPolitician(['full_name' => 'Different Person'])->id,
        null,
        ['source' => 'flag-suspect-profiles'],
        'other'
    );

    $this->artisan('politicians:score-review-priority')->assertExitCode(0);

    expect($reviews->first()->refresh()->occurrence)->toBe(5)
        ->and($unrelated->refresh()->occurrence)->toBe(1);
});

it('scores a pending candidate match review using the shared MapVisibility rule', function () {
    $pol = reviewPolitician(['page_published' => false, 'is_active' => false]);
    $ecr = ElectionCandidateRecord::create([
        'source' => ElectionCandidateRecord::DISCOVERY_SOURCE,
        'external_candidate_id' => 'disc:ca:governor:some-profile',
        'full_name' => 'Some Profile', 'political_office' => 'Governor', 'governance_level' => 'state',
        'state' => 'CA', 'payload' => [],
    ]);
    $review = CandidateMatchReview::create([
        'politician_id' => $pol->id, 'election_candidate_record_id' => $ecr->id,
        'match_score' => 0.8, 'status' => CandidateMatchReview::STATUS_PENDING,
    ]);

    $this->artisan('politicians:score-review-priority')->assertExitCode(0);

    // Approving would flip this discovery-sourced, primary_result-less ECR from hidden to
    // visible on the map (politician would become active) — so severity is 3, not 1.
    expect($review->refresh()->severity)->toBe(3)
        ->and($review->detectability)->toBe(3)
        ->and($review->priority_score)->toBe(3 * 1 * 3);
});
