<?php

use App\Models\ElectionCandidateRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

test('dry-run import reports create update and skip rows without persisting changes', function () {
    ElectionCandidateRecord::factory()->create([
        'source' => 'local_feed',
        'external_candidate_id' => 'loc-1',
        'full_name' => 'Alex Rivera',
        'political_office' => 'City Council',
        'governance_level' => 'City',
        'state' => 'TX',
        'city' => 'Austin',
        'district' => 'D1',
        'party_affiliation' => 'Independent',
    ]);

    $path = storage_path('app/imports/test-local-candidates.json');
    $dir = dirname($path);
    if (! is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    file_put_contents($path, json_encode([
        [
            'external_candidate_id' => 'loc-1',
            'full_name' => 'Alex Rivera',
            'political_office' => 'City Council',
            'governance_level' => 'City',
            'state' => 'TX',
            'city' => 'Dallas',
            'district' => 'D1',
            'party_affiliation' => 'Democrat',
        ],
        [
            'external_candidate_id' => 'loc-2',
            'full_name' => 'Jamie Brooks',
            'political_office' => 'Mayor',
            'governance_level' => 'City',
            'state' => 'TX',
            'city' => 'Houston',
            'district' => null,
            'party_affiliation' => 'Independent',
        ],
        [
            'external_candidate_id' => 'loc-3',
            'city' => 'San Antonio',
        ],
    ], JSON_THROW_ON_ERROR));

    $exitCode = Artisan::call('elections:import-candidates', [
        '--source' => 'local_feed',
        '--file' => $path,
        '--dry-run' => true,
    ]);

    expect($exitCode)->toBe(0);

    $output = Artisan::output();
    expect($output)->toContain('[DRY-RUN][UPDATE]');
    expect($output)->toContain('key=local_feed:loc-1');
    expect($output)->toContain('city:Austin=>Dallas');
    expect($output)->toContain('[DRY-RUN][CREATE]');
    expect($output)->toContain('key=local_feed:loc-2');
    expect($output)->toContain('[DRY-RUN][SKIP]');

    // Dry-run should not mutate stored records.
    $this->assertDatabaseHas('election_candidate_records', [
        'source' => 'local_feed',
        'external_candidate_id' => 'loc-1',
        'city' => 'Austin',
        'party_affiliation' => 'Independent',
    ]);

    $this->assertDatabaseMissing('election_candidate_records', [
        'source' => 'local_feed',
        'external_candidate_id' => 'loc-2',
    ]);
});
