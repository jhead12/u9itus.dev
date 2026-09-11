<?php

use App\Models\Politician;
use App\Services\PoliticianDedup\DuplicatePoliticianDetectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/**
 * A junk-named row (e.g. "Party" left over from a "Democratic Party"
 * placeholder, bypassing the write-guard via saveQuietly() the way real
 * legacy junk rows do) must never be picked as the merge survivor over a
 * row with a real name — otherwise approving the queued review keeps the
 * junk name and deletes the good one.
 */
it('never prefers a row with an invalid full_name over one with a valid name', function () {
    $service = new DuplicatePoliticianDetectionService;

    $junk = Politician::factory()->make([
        'full_name' => 'Party',
        'political_office' => 'U.S. Representative',
        'state' => 'CA',
        'slug' => Str::uuid().'-politician',
        'verified_official' => true,
    ]);
    $junk->saveQuietly();

    $clean = Politician::factory()->create([
        'full_name' => 'Jane Smith',
        'political_office' => 'U.S. Representative',
        'state' => 'CA',
        'verified_official' => false,
    ]);

    expect($service->scoreSurvivor($junk, $clean)->id)->toBe($clean->id)
        ->and($service->scoreSurvivor($clean, $junk)->id)->toBe($clean->id);
});

/**
 * The RSS discovery pipeline's NAME_STOPWORDS list doesn't catch every
 * trailing headline artifact, so "Eric Swalwell" and "Eric Swalwell
 * Officially" ended up as two separate Politician rows for the same CA
 * governor race. Exact full_name matching alone never grouped them as
 * duplicates — findGroups() must also catch a name that's another name
 * plus a trailing word-boundary fragment, within the same office+state.
 */
it('groups a name with a trailing fragment as a duplicate of the shorter clean name', function () {
    $service = new DuplicatePoliticianDetectionService;

    $clean = Politician::factory()->create([
        'full_name' => 'Eric Swalwell',
        'political_office' => 'Governor',
        'state' => 'CA',
        'governance_level' => 'State',
    ]);
    $leaked = Politician::factory()->create([
        'full_name' => 'Eric Swalwell Officially',
        'political_office' => 'Governor',
        'state' => 'CA',
        'governance_level' => 'State',
    ]);
    // Same first two words but a genuinely different person — must not be
    // clustered in just because it shares a prefix token-for-token up to a
    // point; "Eric Swalwell Jr" is not "Eric Swalwell".
    $unrelated = Politician::factory()->create([
        'full_name' => 'Someone Else',
        'political_office' => 'Governor',
        'state' => 'CA',
        'governance_level' => 'State',
    ]);

    $groups = $service->findGroups(Politician::query()->where('state', 'CA'), DuplicatePoliticianDetectionService::STRATEGY_NAME_OFFICE_STATE);

    expect($groups)->toHaveCount(1);
    $ids = $groups->first()->pluck('id')->sort()->values()->all();
    expect($ids)->toBe(collect([$clean->id, $leaked->id])->sort()->values()->all())
        ->and($ids)->not->toContain($unrelated->id);
});

/**
 * Regression: the first production run of the fix above correctly grouped
 * "Eric Swalwell" with "Eric Swalwell Officially" — but then kept the
 * mangled "Officially" variant as the merge survivor, because both names
 * pass the lenient nameViolation() check and the scorer fell through to
 * unrelated tiebreaks (recency/id). scoreSurvivor() must prefer whichever
 * name also passes the stricter headlineFragmentViolation() check.
 */
it('prefers the headline-clean name as survivor even when both names are individually valid', function () {
    $service = new DuplicatePoliticianDetectionService;

    $clean = Politician::factory()->create([
        'full_name' => 'Eric Swalwell',
        'political_office' => 'Governor',
        'state' => 'CA',
    ]);
    // Created later / higher id, which would otherwise win the tiebreak.
    $mangled = Politician::factory()->create([
        'full_name' => 'Eric Swalwell Officially',
        'political_office' => 'Governor',
        'state' => 'CA',
    ]);

    expect($mangled->id)->toBeGreaterThan($clean->id);
    expect($service->scoreSurvivor($clean, $mangled)->id)->toBe($clean->id)
        ->and($service->scoreSurvivor($mangled, $clean)->id)->toBe($clean->id);
});
