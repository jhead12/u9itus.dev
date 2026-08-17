<?php

use App\Models\CandidateIdentityLink;
use App\Models\ElectionCandidateRecord;
use App\Models\Politician;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('a federal candidate record is picked up and classified as eliminated', function () {
    Http::fake([
        'ballotpedia.org/*' => Http::response('<html>He lost the primary to the incumbent.</html>', 200),
    ]);

    $record = ElectionCandidateRecord::factory()->create([
        'full_name' => 'Jane Doe',
        'governance_level' => 'federal',
        'political_office' => 'U.S. Representative',
        'state' => 'CA',
        'election_date' => now()->subDays(10)->format('Y-m-d'),
        'payload' => [],
    ]);

    Artisan::call('politicians:sync-primary-results', ['--state' => 'CA']);

    $record->refresh();
    expect($record->payload['primary_result'])->toBe('eliminated');
});

test('a state-level candidate record is still classified (existing behavior preserved)', function () {
    Http::fake([
        'ballotpedia.org/*' => Http::response('<html>She advanced to the general election.</html>', 200),
    ]);

    $record = ElectionCandidateRecord::factory()->create([
        'full_name' => 'Jane Advances',
        'governance_level' => 'state',
        'political_office' => 'Governor',
        'state' => 'TX',
        'election_date' => now()->subDays(10)->format('Y-m-d'),
        'payload' => [],
    ]);

    Artisan::call('politicians:sync-primary-results', ['--state' => 'TX']);

    $record->refresh();
    expect($record->payload['primary_result'])->toBe('advanced_to_general');
});

test('a local-level candidate record is ignored', function () {
    Http::fake([
        'ballotpedia.org/*' => Http::response('<html>He lost the primary.</html>', 200),
    ]);

    $record = ElectionCandidateRecord::factory()->create([
        'full_name' => 'City Council Person',
        'governance_level' => 'City',
        'state' => 'NY',
        'election_date' => now()->subDays(10)->format('Y-m-d'),
        'payload' => [],
    ]);

    Artisan::call('politicians:sync-primary-results', ['--state' => 'NY']);

    $record->refresh();
    expect($record->payload['primary_result'] ?? null)->toBeNull();
});

test('an eliminated result propagates to the linked politician term_status', function () {
    Http::fake([
        'ballotpedia.org/*' => Http::response('<html>He lost the primary to the incumbent.</html>', 200),
    ]);

    $record = ElectionCandidateRecord::factory()->create([
        'full_name' => 'Losing Candidate',
        'governance_level' => 'federal',
        'state' => 'CA',
        'election_date' => now()->subDays(10)->format('Y-m-d'),
        'payload' => [],
    ]);

    $politician = Politician::factory()->create([
        'full_name' => 'Losing Candidate',
        'state' => 'CA',
        'is_running_candidate' => true,
        'term_status' => 'running',
    ]);

    CandidateIdentityLink::firstOrCreate(
        [
            'politician_id' => $politician->id,
            'election_candidate_record_id' => $record->id,
        ],
        [
            'match_score' => 0.95,
            'link_source' => 'system',
            'linked_at' => now(),
        ]
    );

    Artisan::call('politicians:sync-primary-results', ['--state' => 'CA']);

    $politician->refresh();
    expect($politician->term_status)->toBe('eliminated');
    expect($politician->is_running_candidate)->toBeFalse();
});

test('--dry-run does not write to the linked politician', function () {
    Http::fake([
        'ballotpedia.org/*' => Http::response('<html>He lost the primary to the incumbent.</html>', 200),
    ]);

    $record = ElectionCandidateRecord::factory()->create([
        'full_name' => 'Dry Run Candidate',
        'governance_level' => 'federal',
        'state' => 'CA',
        'election_date' => now()->subDays(10)->format('Y-m-d'),
        'payload' => [],
    ]);

    $politician = Politician::factory()->create([
        'full_name' => 'Dry Run Candidate',
        'state' => 'CA',
        'is_running_candidate' => true,
        'term_status' => 'running',
    ]);

    CandidateIdentityLink::firstOrCreate(
        [
            'politician_id' => $politician->id,
            'election_candidate_record_id' => $record->id,
        ],
        [
            'match_score' => 0.95,
            'link_source' => 'system',
            'linked_at' => now(),
        ]
    );

    Artisan::call('politicians:sync-primary-results', ['--state' => 'CA', '--dry-run' => true]);

    $record->refresh();
    $politician->refresh();
    expect($record->payload['primary_result'] ?? null)->toBeNull();
    expect($politician->term_status)->toBe('running');
});
