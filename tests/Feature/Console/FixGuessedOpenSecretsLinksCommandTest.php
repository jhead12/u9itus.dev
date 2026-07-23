<?php

use App\Models\Politician;
use App\Models\PoliticianDonorSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

test('dry run reports guessed links with no backing data but does not clear them', function () {
    $politician = Politician::factory()->create(['full_name' => 'James Gallagher']);

    $snapshot = PoliticianDonorSnapshot::create([
        'politician_id' => $politician->id,
        'opensecrets_source_url' => 'https://www.opensecrets.org/profiles/james-gallagher/us_congress/summary',
        'enriched_at' => now(),
    ]);

    $exitCode = Artisan::call('politicians:fix-opensecrets-links');

    expect($exitCode)->toBe(0);
    expect(Artisan::output())->toContain('James Gallagher');
    expect($snapshot->fresh()->opensecrets_source_url)->not->toBeNull();
});

test('fix clears a guessed link with no backing OpenSecrets data', function () {
    $politician = Politician::factory()->create(['full_name' => 'James Gallagher']);

    $snapshot = PoliticianDonorSnapshot::create([
        'politician_id' => $politician->id,
        'opensecrets_source_url' => 'https://www.opensecrets.org/profiles/james-gallagher/us_congress/summary',
        'enriched_at' => now(),
    ]);

    $exitCode = Artisan::call('politicians:fix-opensecrets-links', ['--fix' => true]);

    expect($exitCode)->toBe(0);
    expect($snapshot->fresh()->opensecrets_source_url)->toBeNull();
});

test('fix leaves a confirmed link (with mpid) untouched', function () {
    $politician = Politician::factory()->create(['full_name' => 'Adam Schiff']);

    $snapshot = PoliticianDonorSnapshot::create([
        'politician_id' => $politician->id,
        'opensecrets_source_url' => 'https://www.opensecrets.org/profiles/adam-schiff/us_congress/summary?mpid=1105090',
        'enriched_at' => now(),
    ]);

    Artisan::call('politicians:fix-opensecrets-links', ['--fix' => true]);

    expect($snapshot->fresh()->opensecrets_source_url)->toBe(
        'https://www.opensecrets.org/profiles/adam-schiff/us_congress/summary?mpid=1105090'
    );
});

test('fix leaves a guessed-shaped link untouched when real OpenSecrets data backs it', function () {
    $politician = Politician::factory()->create(['full_name' => 'Jane Doe']);

    $snapshot = PoliticianDonorSnapshot::create([
        'politician_id' => $politician->id,
        'opensecrets_source_url' => 'https://www.opensecrets.org/profiles/jane-doe/us_congress/summary',
        'top_contributors' => [['name' => 'Acme Corp Employees', 'total' => '$52,000']],
        'enriched_at' => now(),
    ]);

    Artisan::call('politicians:fix-opensecrets-links', ['--fix' => true]);

    expect($snapshot->fresh()->opensecrets_source_url)->not->toBeNull();
});
