<?php

use App\Models\CandidateLead;
use App\Models\ElectionCandidateRecord;
use App\Services\CandidateDiscovery\CandidateLeadPromoter;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function pendingLead(string $name): CandidateLead
{
    return CandidateLead::create([
        'source_key' => 'rss_google_news',
        'full_name' => $name,
        'state' => 'CA',
        'office_hint' => 'Governor',
        'source_url' => 'https://news.example/'.fake()->unique()->slug(),
        'source_hash' => hash('sha256', fake()->unique()->uuid()),
        'discovered_at' => now(),
        'status' => CandidateLead::STATUS_VERIFIED,
        'confidence' => 0.95,
        'verified_payload' => ['political_office' => 'Governor', 'governance_level' => 'state'],
    ]);
}

it('blocks a headline-fragment name at the ElectionCandidateRecord write-guard', function () {
    $record = new ElectionCandidateRecord([
        'source' => ElectionCandidateRecord::DISCOVERY_SOURCE,
        'external_candidate_id' => 'disc-1',
        'full_name' => 'Former L.A. Mayor Antonio',
        'political_office' => 'Governor',
        'governance_level' => 'state',
        'state' => 'CA',
    ]);

    expect($record->save())->toBeFalse();
    $this->assertDatabaseMissing('election_candidate_records', ['external_candidate_id' => 'disc-1']);
});

it('allows a clean name from the discovery source', function () {
    $record = ElectionCandidateRecord::create([
        'source' => ElectionCandidateRecord::DISCOVERY_SOURCE,
        'external_candidate_id' => 'disc-2',
        'full_name' => 'Katie Porter',
        'political_office' => 'Governor',
        'governance_level' => 'state',
        'state' => 'CA',
    ]);

    expect($record->exists)->toBeTrue();
});

it('does not guard non-discovery sources', function () {
    // Curated feeds/imports have their own review; the model guard is scoped
    // to candidate_discovery so a legitimate title-like surname is not lost.
    $record = ElectionCandidateRecord::create([
        'source' => 'ballotpedia',
        'external_candidate_id' => 'bp-1',
        'full_name' => 'Reality TV',
        'political_office' => 'Governor',
        'governance_level' => 'state',
        'state' => 'CA',
    ]);

    expect($record->exists)->toBeTrue();
});

it('rejects the lead instead of promoting a headline-fragment name', function () {
    $lead = pendingLead('Eric Swalwell Won More');

    $result = app(CandidateLeadPromoter::class)->promote($lead);

    expect($result)->toBeNull();
    expect($lead->fresh()->status)->toBe(CandidateLead::STATUS_REJECTED);
    $this->assertDatabaseMissing('election_candidate_records', [
        'source' => ElectionCandidateRecord::DISCOVERY_SOURCE,
        'full_name' => 'Eric Swalwell Won More',
    ]);
});

it('promotes a lead with a clean name', function () {
    $lead = pendingLead('Eric Swalwell');

    $result = app(CandidateLeadPromoter::class)->promote($lead);

    expect($result)->not->toBeNull();
    expect($lead->fresh()->status)->toBe(CandidateLead::STATUS_PROMOTED);
});
