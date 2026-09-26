<?php

use App\Models\CandidateIdentityLink;
use App\Models\ElectionCandidateRecord;
use App\Models\Politician;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(fn () => Cache::flush());

function cityMayor(string $name, array $attrs = []): Politician
{
    return Politician::factory()->create(array_merge([
        'full_name' => $name, 'state' => 'CA', 'city' => 'Los Angeles', 'governance_level' => 'City',
        'political_office' => 'Mayor', 'is_active' => true, 'page_published' => true, 'user_id' => null,
        'slug' => str($name)->slug()->toString(),
    ], $attrs));
}

function linkCityRecord(Politician $politician, array $payload, ?string $electionDate): void
{
    $record = ElectionCandidateRecord::create([
        'source' => 'local_feed', 'external_candidate_id' => 'la-mayor-'.$politician->id,
        'full_name' => $politician->full_name, 'political_office' => 'Mayor', 'governance_level' => 'City',
        'state' => 'CA', 'city' => 'Los Angeles', 'election_date' => $electionDate, 'payload' => $payload,
    ]);
    CandidateIdentityLink::create([
        'politician_id' => $politician->id, 'election_candidate_record_id' => $record->id,
        'match_score' => 1.0, 'link_source' => 'system', 'linked_at' => now(),
    ]);
}

function laMayorCandidates(): array
{
    $groups = test()->getJson('/api/v1/map/state-candidates?state=CA')->assertOk()->json('city_officials.Los Angeles');

    return collect($groups)->firstWhere('office', 'Mayor')['candidates'];
}

it('gives city candidates the primary result and general date from their linked record', function () {
    $general = now()->addMonth()->toDateString();
    $bass = cityMayor('Karen Bass', ['term_status' => 'seated', 'is_running_candidate' => true]);
    $raman = cityMayor('Nithya Raman', ['term_status' => 'running', 'is_running_candidate' => true]);
    foreach ([$bass, $raman] as $p) {
        linkCityRecord($p, ['primary_result' => 'advanced_to_general', 'general_date' => $general], $general);
    }

    $byName = collect(laMayorCandidates())->keyBy('full_name');

    expect($byName['Nithya Raman']['primary_result'])->toBe('advanced_to_general')
        ->and($byName['Nithya Raman']['general_date'])->toBe($general)
        ->and($byName['Karen Bass']['primary_result'])->toBe('advanced_to_general');
});

it('leaves the result empty for a city candidate with no linked record or only a past cycle', function () {
    cityMayor('Karen Bass', ['term_status' => 'seated']);
    $old = cityMayor('Old Candidate', ['term_status' => 'running', 'is_running_candidate' => true]);
    linkCityRecord($old, ['primary_result' => 'advanced_to_general', 'general_date' => '2022-11-08'], '2022-11-08');

    foreach (laMayorCandidates() as $candidate) {
        expect($candidate['primary_result'])->toBeNull()
            ->and($candidate['general_date'])->toBeNull();
    }
});
