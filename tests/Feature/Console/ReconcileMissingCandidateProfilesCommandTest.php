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

function staleLinkRecord(string $name = 'Abdul El-Sayed'): ElectionCandidateRecord
{
    return ElectionCandidateRecord::factory()->create([
        'source' => 'ballotpedia',
        'external_candidate_id' => 'bp-stale-'.str($name)->slug(),
        'full_name' => $name,
        'political_office' => 'U.S. Senator',
        'governance_level' => 'Federal',
        'state' => 'MI',
        'payload' => ['result_status' => null],
        'election_date' => now()->toDateString(),
    ]);
}

function linkTo(ElectionCandidateRecord $record, string $polName, array $attrs = []): Politician
{
    $pol = Politician::factory()->create(array_merge([
        'user_id' => null, 'full_name' => $polName, 'political_office' => 'U.S. Senator',
        'governance_level' => 'Federal', 'state' => 'MI',
        'term_status' => 'lost', 'is_active' => false, 'is_running_candidate' => false,
    ], $attrs));
    // Creating a profile already queues an auto-match that may have linked it.
    CandidateIdentityLink::updateOrCreate(
        ['politician_id' => $pol->id, 'election_candidate_record_id' => $record->id],
        ['match_score' => 0.9, 'link_source' => 'system'],
    );

    return $pol;
}

test('replaces a link to an inactive lost profile with a junk name by a proper profile', function () {
    $record = staleLinkRecord();
    $junk = linkTo($record, 'Abdul El-Sayed Billboards');

    $this->artisan('politicians:reconcile-missing-profiles', ['--state' => ['MI']])
        ->expectsOutputToContain('[STALE LINK]')
        ->assertExitCode(0);

    $fresh = Politician::where('full_name', 'Abdul El-Sayed')->where('state', 'MI')->first();
    expect($fresh)->not->toBeNull()
        ->and($fresh->is_active)->toBeTrue()
        ->and($fresh->term_status)->toBe('running');
    $this->assertDatabaseHas('candidate_identity_links', ['politician_id' => $fresh->id, 'election_candidate_record_id' => $record->id]);
    $this->assertDatabaseMissing('candidate_identity_links', ['politician_id' => $junk->id, 'election_candidate_record_id' => $record->id]);
    expect($junk->refresh()->is_active)->toBeFalse();
});

test('leaves a real loser linked to their own lost profile', function () {
    $record = staleLinkRecord('Steve Hilton');
    $lost = linkTo($record, 'Steven Hilton');

    $this->artisan('politicians:reconcile-missing-profiles', ['--state' => ['MI']])->assertExitCode(0);

    expect(Politician::where('full_name', 'like', '%Hilton%')->count())->toBe(1);
    $this->assertDatabaseHas('candidate_identity_links', ['politician_id' => $lost->id, 'election_candidate_record_id' => $record->id]);
});

test('leaves a record linked to an active profile alone', function () {
    $record = staleLinkRecord();
    $active = linkTo($record, 'Abdul El-Sayed Billboards', ['is_active' => true, 'term_status' => 'running']);

    $this->artisan('politicians:reconcile-missing-profiles', ['--state' => ['MI']])->assertExitCode(0);

    expect(Politician::where('full_name', 'Abdul El-Sayed')->exists())->toBeFalse();
    $this->assertDatabaseHas('candidate_identity_links', ['politician_id' => $active->id, 'election_candidate_record_id' => $record->id]);
});

test('reports a stale link replacement without writing in dry-run', function () {
    $record = staleLinkRecord();
    $junk = linkTo($record, 'Abdul El-Sayed Billboards');

    $this->artisan('politicians:reconcile-missing-profiles', ['--state' => ['MI'], '--dry-run' => true])
        ->expectsOutputToContain('1 replaced a stale link')
        ->assertExitCode(0);

    expect(Politician::where('full_name', 'Abdul El-Sayed')->exists())->toBeFalse();
    $this->assertDatabaseHas('candidate_identity_links', ['politician_id' => $junk->id, 'election_candidate_record_id' => $record->id]);
});
