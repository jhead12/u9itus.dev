<?php

use App\Models\PoliticalCampaign;
use App\Models\Politician;
use App\Models\User;
use App\Models\Voter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function makeDuplicatePolitician(array $overrides = []): Politician
{
    return Politician::factory()->create(array_merge([
        'user_id' => null,
        'full_name' => 'Gilbert Ray Cisneros',
        'political_office' => 'United States Representative',
        'state' => 'CA',
    ], $overrides));
}

test('merges duplicate unclaimed rows for the same official into the oldest one', function () {
    $canonical = makeDuplicatePolitician();
    $duplicateOne = makeDuplicatePolitician();
    $duplicateTwo = makeDuplicatePolitician();

    $this->artisan('politicians:merge-duplicates', ['--force' => true])
        ->assertExitCode(0);

    expect(Politician::find($canonical->id))->not->toBeNull()
        ->and(Politician::find($duplicateOne->id))->toBeNull()
        ->and(Politician::find($duplicateTwo->id))->toBeNull();
});

test('--dry-run reports duplicates without changing anything', function () {
    $canonical = makeDuplicatePolitician();
    $duplicate = makeDuplicatePolitician();

    $this->artisan('politicians:merge-duplicates', ['--dry-run' => true])
        ->assertExitCode(0);

    expect(Politician::find($canonical->id))->not->toBeNull()
        ->and(Politician::find($duplicate->id))->not->toBeNull();
});

test('never touches claimed profiles, even if name/office/state match', function () {
    $user = User::factory()->create();
    $claimed = makeDuplicatePolitician(['user_id' => $user->id]);
    $unclaimedDuplicate = makeDuplicatePolitician();

    $this->artisan('politicians:merge-duplicates', ['--force' => true])
        ->assertExitCode(0);

    // No unclaimed sibling for $claimed to merge with (the other unclaimed
    // row isn't grouped with it), so both survive untouched.
    expect(Politician::find($claimed->id))->not->toBeNull()
        ->and(Politician::find($unclaimedDuplicate->id))->not->toBeNull();
});

test('reassigns simple foreign keys from the duplicate to the canonical row', function () {
    $canonical = makeDuplicatePolitician();
    $duplicate = makeDuplicatePolitician();

    $campaign = PoliticalCampaign::factory()->create(['politician_id' => $duplicate->id]);

    $this->artisan('politicians:merge-duplicates', ['--force' => true])
        ->assertExitCode(0);

    expect($campaign->refresh()->politician_id)->toBe($canonical->id);
});

test('drops the duplicate side of a row that would violate a compound unique key on merge', function () {
    $canonical = makeDuplicatePolitician();
    $duplicate = makeDuplicatePolitician();
    $voter = Voter::factory()->create();

    // Same voter already favorited the canonical row...
    DB::table('voter_favorite_politicians')->insert([
        'voter_id' => $voter->id,
        'politician_id' => $canonical->id,
        'favorited_at' => now(),
    ]);
    // ...and, before the merge, also favorited the duplicate row (two
    // separate profiles that turn out to be the same person). Reassigning
    // the second row's politician_id to $canonical would collide with the
    // (voter_id, politician_id) unique constraint.
    DB::table('voter_favorite_politicians')->insert([
        'voter_id' => $voter->id,
        'politician_id' => $duplicate->id,
        'favorited_at' => now(),
    ]);

    $this->artisan('politicians:merge-duplicates', ['--force' => true])
        ->assertExitCode(0);

    expect(DB::table('voter_favorite_politicians')->where('voter_id', $voter->id)->count())->toBe(1)
        ->and(DB::table('voter_favorite_politicians')->where('politician_id', $canonical->id)->exists())->toBeTrue();
});
