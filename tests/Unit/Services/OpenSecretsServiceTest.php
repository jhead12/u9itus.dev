<?php

use App\Models\Politician;
use App\Models\PoliticianDonorSnapshot;
use App\Services\OpenSecretsService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('getDisplayData returns null when the only "data" is an all-null-values summary and a guessed link', function () {
    // Reproduces the production James Gallagher case: a nightly enrich run
    // guessed an unverified OpenSecrets URL and persisted a summary table
    // that never parsed anything real. Before the hasOpenSecretsSummary()
    // fix, the all-null summary shape read as "has data" and the broken
    // link surfaced on the public profile's Dig Deeper panel.
    $politician = Politician::factory()->create();

    PoliticianDonorSnapshot::create([
        'politician_id' => $politician->id,
        'opensecrets_source_url' => 'https://www.opensecrets.org/profiles/james-gallagher/us_congress/summary',
        'opensecrets_summary' => ['total_raised' => null, 'total_spent' => null, 'cash_on_hand' => null, 'debt' => null],
        'enriched_at' => now(),
    ]);

    $result = app(OpenSecretsService::class)->getDisplayData($politician);

    expect($result)->toBeNull();
});

test('getDisplayData surfaces the summary and source url when real data exists', function () {
    $politician = Politician::factory()->create();

    PoliticianDonorSnapshot::create([
        'politician_id' => $politician->id,
        'opensecrets_source_url' => 'https://www.opensecrets.org/profiles/adam-schiff/us_congress/summary?mpid=1105090',
        'opensecrets_summary' => ['total_raised' => '$100', 'total_spent' => null, 'cash_on_hand' => null, 'debt' => null],
        'enriched_at' => now(),
    ]);

    $result = app(OpenSecretsService::class)->getDisplayData($politician);

    expect($result)->not->toBeNull();
    expect($result['source_url'])->toBe('https://www.opensecrets.org/profiles/adam-schiff/us_congress/summary?mpid=1105090');
    expect($result['sections']['summary'])->toBe(['total_raised' => '$100', 'total_spent' => null, 'cash_on_hand' => null, 'debt' => null]);
});
