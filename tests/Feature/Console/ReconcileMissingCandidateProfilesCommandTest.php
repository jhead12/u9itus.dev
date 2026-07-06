<?php

use App\Models\CandidateIdentityLink;
use App\Models\ElectionCandidateRecord;
use App\Models\Politician;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('creates unclaimed politician and identity link for unlinked candidate record', function () {
    $record = ElectionCandidateRecord::factory()->create([
        'source' => 'ballotpedia',
        'external_candidate_id' => 'bp-1001',
        'full_name' => 'Xavier Becerra',
        'political_office' => 'Governor',
        'governance_level' => 'State',
        'state' => 'CA',
        'district' => null,
        'party_affiliation' => 'Democratic',
        'payload' => [
            'ballotpedia_url' => 'https://ballotpedia.org/Xavier_Becerra',
            'result_status' => null,
        ],
        'election_date' => now()->toDateString(),
    ]);

    $this->artisan('politicians:reconcile-missing-profiles', [
        '--state' => ['CA'],
        '--election-year' => (string) now()->year,
    ])->assertExitCode(0);

    $politician = Politician::query()
        ->where('full_name', 'Xavier Becerra')
        ->where('state', 'CA')
        ->first();

    expect($politician)->not->toBeNull();
    expect($politician->user_id)->toBeNull();
    expect($politician->verification_status)->toBe('unverified');
    expect($politician->is_running_candidate)->toBeTrue();
    expect($politician->ballotpedia_id)->toBe('Xavier_Becerra');

    $this->assertDatabaseHas('candidate_identity_links', [
        'politician_id' => $politician->id,
        'election_candidate_record_id' => $record->id,
        'link_source' => 'system',
    ]);
});

test('links to existing politician without creating duplicate profile', function () {
    $existing = Politician::factory()->create([
        'user_id' => null,
        'full_name' => 'Steve Hilton',
        'political_office' => 'Governor',
        'state' => 'CA',
        'district' => null,
        'party_affiliation' => null,
    ]);

    $record = ElectionCandidateRecord::factory()->create([
        'source' => 'ballotpedia',
        'external_candidate_id' => 'bp-1002',
        'full_name' => 'Steve Hilton',
        'political_office' => 'Governor',
        'governance_level' => 'State',
        'state' => 'CA',
        'party_affiliation' => 'Republican',
        'payload' => ['result_status' => null],
        'election_date' => now()->toDateString(),
    ]);

    $this->artisan('politicians:reconcile-missing-profiles', [
        '--state' => ['CA'],
        '--election-year' => (string) now()->year,
    ])->assertExitCode(0);

    expect(Politician::query()->where('full_name', 'Steve Hilton')->count())->toBe(1);

    $this->assertDatabaseHas('candidate_identity_links', [
        'politician_id' => $existing->id,
        'election_candidate_record_id' => $record->id,
    ]);

    expect($existing->fresh()->party_affiliation)->toBe('Republican');
});

test('skips lost or eliminated records', function () {
    ElectionCandidateRecord::factory()->create([
        'full_name' => 'Lost Candidate',
        'state' => 'CA',
        'election_date' => now()->toDateString(),
        'payload' => ['result_status' => 'lost'],
    ]);

    ElectionCandidateRecord::factory()->create([
        'full_name' => 'Eliminated Candidate',
        'state' => 'CA',
        'election_date' => now()->toDateString(),
        'payload' => ['primary_result' => 'eliminated'],
    ]);

    $this->artisan('politicians:reconcile-missing-profiles', [
        '--state' => ['CA'],
        '--election-year' => (string) now()->year,
    ])->assertExitCode(0);

    expect(Politician::query()->count())->toBe(0);
    expect(CandidateIdentityLink::query()->count())->toBe(0);
});
