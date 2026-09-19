<?php

use App\Models\Politician;
use App\Models\PoliticianCleanupReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(fn () => Cache::flush());

function abbott(string $state, array $over = []): Politician
{
    $p = Politician::factory()->make(array_merge([
        'full_name' => 'Greg Abbott', 'state' => $state, 'political_office' => 'Governor',
        'governance_level' => 'state', 'term_status' => 'running', 'is_active' => true, 'user_id' => null,
        'verified_official' => false, 'slug' => 'abbott-'.strtolower($state).'-'.fake()->unique()->numerify('#####'),
    ], $over));
    $p->saveQuietly();

    return $p;
}

it('finds two identical unclaimed rows in the same state (control)', function () {
    abbott('TX');
    abbott('TX');

    $this->artisan('politicians:dedupe', ['--scope' => 'unclaimed-all'])
        ->expectsOutputToContain('Found 1 duplicate group')
        ->assertExitCode(0);
});

it('groups "Greg Abbott\'s" with "Greg Abbott" in the same state and keeps the clean name', function () {
    $clean = abbott('TX');
    $possessive = abbott('TX', ['full_name' => "Greg Abbott's"]);

    $this->artisan('politicians:dedupe', ['--scope' => 'unclaimed-all', '--enqueue-review' => true])
        ->expectsOutputToContain('Found 1 duplicate group')
        ->assertExitCode(0);

    $review = PoliticianCleanupReview::where('review_type', 'merge')->sole();
    expect($review->politician_id)->toBe($clean->id)
        ->and($review->duplicate_politician_id)->toBe($possessive->id);
});

it('does not — and by design cannot — group the same name across states; the impostor check owns that', function () {
    abbott('TX', ['term_status' => 'seated']);
    abbott('NC');
    abbott('NY');

    $this->artisan('politicians:dedupe', ['--scope' => 'unclaimed-all'])
        ->expectsOutputToContain('No duplicate groups')
        ->assertExitCode(0);
});

it('says so when the scan limit cut the scan short instead of silently missing rows', function () {
    foreach (range(1, 4) as $i) {
        abbott('TX');
    }

    $this->artisan('politicians:dedupe', ['--scope' => 'unclaimed-all', '--limit' => 2])
        ->expectsOutputToContain('Scanned only 2 of 4')
        ->assertExitCode(0);
});

it('finds duplicates among the newest rows, not just the first 5000 by id', function () {
    // Older, unrelated rows fill the scan window; the duplicate pair is the newest.
    foreach (range(1, 3) as $i) {
        abbott('CA', ['full_name' => "Filler Person {$i}", 'political_office' => 'Mayor']);
    }
    abbott('TX');
    abbott('TX');

    $this->artisan('politicians:dedupe', ['--scope' => 'unclaimed-all', '--limit' => 3])
        ->expectsOutputToContain('Found 1 duplicate group')
        ->assertExitCode(0);
});
