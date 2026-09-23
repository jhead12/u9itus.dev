<?php

use App\Models\CandidateIdentityLink;
use App\Models\ElectionCandidateRecord;
use App\Models\Politician;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(fn () => Cache::flush());

function statewideEcr(array $attrs): ElectionCandidateRecord
{
    $record = new ElectionCandidateRecord(array_merge([
        'source' => 'ballotpedia',
        'external_candidate_id' => 'ext-'.fake()->unique()->numerify('######'),
        'full_name' => fake()->firstName().' '.fake()->lastName(),
        'political_office' => 'Governor',
        'governance_level' => 'state',
        'state' => 'CA',
        'election_date' => now()->addMonths(3)->toDateString(),
        'payload' => ['status' => 'running'],
    ], $attrs));
    $record->saveQuietly();

    return $record->refresh();
}

function mapNames(): array
{
    $offices = test()->getJson('/api/v1/map/state-candidates?state=CA')->assertOk()->json('offices');

    return collect($offices)->flatMap(fn ($o) => collect($o['candidates'])->pluck('full_name'))->all();
}

it('hides ECR rows from a past election cycle', function () {
    statewideEcr(['full_name' => 'Casey Currentcycle', 'election_date' => now()->addMonths(3)->toDateString()]);
    statewideEcr(['full_name' => 'Oliver Oldcycle', 'election_date' => '2018-11-06']);

    $names = mapNames();

    expect($names)->toContain('Casey Currentcycle');
    expect($names)->not->toContain('Oliver Oldcycle');
});

it('hides an unverified candidate_discovery row', function () {
    statewideEcr([
        'full_name' => 'Unverified Discovery',
        'source' => ElectionCandidateRecord::DISCOVERY_SOURCE,
        'payload' => ['status' => 'running'],
    ]);

    expect(mapNames())->not->toContain('Unverified Discovery');
});

it('shows a candidate_discovery row once it is identity-linked', function () {
    $rec = statewideEcr([
        'full_name' => 'Linked Discovery',
        'source' => ElectionCandidateRecord::DISCOVERY_SOURCE,
        'payload' => ['status' => 'running'],
    ]);
    $pol = Politician::factory()->create(['full_name' => 'Linked Discovery', 'state' => 'CA']);
    CandidateIdentityLink::create([
        'politician_id' => $pol->id,
        'election_candidate_record_id' => $rec->id,
        'match_score' => 0.9,
        'link_source' => 'system',
        'linked_at' => now(),
    ]);

    expect(mapNames())->toContain('Linked Discovery');
});

it('hides a candidate_discovery row identity-linked to a now-inactive politician', function () {
    // The exact shape of the Bernie-Sanders-in-Nevada bug: a discovery row auto-linked to a
    // phantom profile that later gets deactivated (e.g. by politicians:flag-suspect-profiles)
    // must not keep rendering just because the (now-dead) link still exists.
    $rec = statewideEcr([
        'full_name' => 'Deactivated Link',
        'source' => ElectionCandidateRecord::DISCOVERY_SOURCE,
        'payload' => ['status' => 'running'],
    ]);
    $pol = Politician::factory()->create(['full_name' => 'Deactivated Link', 'state' => 'CA', 'is_active' => false]);
    CandidateIdentityLink::create([
        'politician_id' => $pol->id,
        'election_candidate_record_id' => $rec->id,
        'match_score' => 1.0,
        'link_source' => 'system',
        'linked_at' => now(),
    ]);

    expect(mapNames())->not->toContain('Deactivated Link');
});

it('shows a candidate_discovery row that carries an explicit primary_result', function () {
    statewideEcr([
        'full_name' => 'Advanced Discovery',
        'source' => ElectionCandidateRecord::DISCOVERY_SOURCE,
        'payload' => ['status' => 'running', 'primary_result' => 'advanced_to_general'],
    ]);

    expect(mapNames())->toContain('Advanced Discovery');
});
