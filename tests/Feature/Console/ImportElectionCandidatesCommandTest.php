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

function importCandidateRows(array $rows): void
{
    $path = storage_path('app/imports/test-local-candidates.json');
    if (! is_dir(dirname($path))) {
        mkdir(dirname($path), 0777, true);
    }
    file_put_contents($path, json_encode($rows, JSON_THROW_ON_ERROR));

    expect(Artisan::call('elections:import-candidates', ['--source' => 'local_feed', '--file' => $path]))->toBe(0);
}

test('rows without an external id get a stable id, so a reordered re-import creates no duplicates', function () {
    $raman = ['full_name' => 'Nithya Raman', 'political_office' => 'Mayor', 'governance_level' => 'City',
        'state' => 'CA', 'city' => 'Los Angeles', 'district' => 'Citywide', 'election_date' => '2026-11-03'];
    $bass = ['full_name' => 'Karen Bass'] + $raman;

    importCandidateRows([$raman, $bass]);
    importCandidateRows([$bass, $raman]);

    expect(ElectionCandidateRecord::where('source', 'local_feed')->count())->toBe(2);
    $this->assertDatabaseHas('election_candidate_records', [
        'source' => 'local_feed',
        'external_candidate_id' => 'auto:ca:mayor:los-angeles:citywide:nithya-raman:2026',
    ]);
});

test('a row without an id updates a record stored under an older derived id instead of duplicating it', function () {
    ElectionCandidateRecord::factory()->create([
        'source' => 'local_feed',
        'external_candidate_id' => str_repeat('a', 32), // old position-based hash
        'full_name' => 'Nithya Raman',
        'political_office' => 'Mayor',
        'state' => 'CA',
        'city' => 'Los Angeles',
        'district' => 'Citywide',
        'election_date' => '2026-11-03',
        'party_affiliation' => null,
    ]);

    importCandidateRows([[
        'full_name' => 'Nithya Raman', 'political_office' => 'Mayor', 'governance_level' => 'City', 'state' => 'CA',
        'city' => 'Los Angeles', 'district' => 'Citywide', 'election_date' => '2026-11-03', 'party_affiliation' => 'Democratic',
    ]]);

    expect(ElectionCandidateRecord::where('source', 'local_feed')->count())->toBe(1);
    $this->assertDatabaseHas('election_candidate_records', [
        'external_candidate_id' => str_repeat('a', 32),
        'party_affiliation' => 'Democratic',
    ]);
});

test('the same name in a different race is a separate record', function () {
    $row = ['full_name' => 'Alex Rivera', 'political_office' => 'City Council', 'state' => 'TX', 'election_date' => '2026-11-03'];

    importCandidateRows([$row + ['city' => 'Austin', 'district' => 'D1'], $row + ['city' => 'Dallas', 'district' => 'D1']]);

    expect(ElectionCandidateRecord::where('source', 'local_feed')->count())->toBe(2);
});
