<?php

use App\Models\Politician;
use App\Models\PoliticianDonorSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('hasOpenSecretsSummary is false for the all-null-values summary shape', function () {
    $politician = Politician::factory()->create();

    $snapshot = PoliticianDonorSnapshot::create([
        'politician_id' => $politician->id,
        'opensecrets_summary' => ['total_raised' => null, 'total_spent' => null, 'cash_on_hand' => null, 'debt' => null],
        'enriched_at' => now(),
    ]);

    expect($snapshot->hasOpenSecretsSummary())->toBeFalse();
    expect($snapshot->hasAnyData())->toBeFalse();
});

test('hasOpenSecretsSummary is true when at least one field has a real value', function () {
    $politician = Politician::factory()->create();

    $snapshot = PoliticianDonorSnapshot::create([
        'politician_id' => $politician->id,
        'opensecrets_summary' => ['total_raised' => '$100', 'total_spent' => null, 'cash_on_hand' => null, 'debt' => null],
        'enriched_at' => now(),
    ]);

    expect($snapshot->hasOpenSecretsSummary())->toBeTrue();
    expect($snapshot->hasAnyData())->toBeTrue();
});

test('hasOpenSecretsSummary is false when the summary is null or empty', function () {
    $politician = Politician::factory()->create();

    $snapshot = PoliticianDonorSnapshot::create([
        'politician_id' => $politician->id,
        'opensecrets_summary' => null,
        'enriched_at' => now(),
    ]);

    expect($snapshot->hasOpenSecretsSummary())->toBeFalse();
});
