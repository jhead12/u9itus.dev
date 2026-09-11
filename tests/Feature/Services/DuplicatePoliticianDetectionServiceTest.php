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
