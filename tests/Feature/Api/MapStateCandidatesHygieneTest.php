<?php

use App\Models\ElectionCandidateRecord;
use App\Models\Politician;
use App\Models\StateElectionDate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(fn () => Cache::flush());

function houseRow(string $name, string $district, array $attrs = []): Politician
{
    return Politician::factory()->create(array_merge([
        'full_name' => $name,
        'state' => 'CA',
        'district' => $district,
        'governance_level' => 'federal',
        'political_office' => 'U.S. Representative',
        'term_status' => 'seated',
        'is_active' => true,
        'slug' => str($name)->slug()->append('-'.fake()->unique()->numerify('###'))->toString(),
    ], $attrs));
}

function caPayload(): array
{
    return test()->getJson('/api/v1/map/state-candidates?state=CA')->assertOk()->json();
}

it('ignores a server-local file cache after an external cleanup', function () {
    config(['cache.default' => 'file']);
    houseRow('Linda Sánchez', 'CA-38');
    Cache::put('map_state_candidates_CA', ['stale' => true], 3600);

    try {
        $response = test()->getJson('/api/v1/map/state-candidates?state=CA')->assertOk();
        expect($response->json('house_candidates.CA-38.0.full_name'))->toBe('Linda Sánchez')
            ->and($response->headers->get('Cache-Control'))->toContain('no-store');
    } finally {
        Cache::forget('map_state_candidates_CA');
    }
});

it('hides the organization and incomplete title names seen on the Michigan map', function (string $name) {
    $row = new ElectionCandidateRecord([
        'source' => 'ballotpedia', 'external_candidate_id' => 'legacy-junk',
        'full_name' => $name, 'political_office' => 'Governor',
        'state' => 'MI', 'governance_level' => 'State',
        'election_date' => now()->addMonths(3)->toDateString(),
        'payload' => ['status' => 'running'],
    ]);
    $row->saveQuietly();

    $response = test()->getJson('/api/v1/map/state-candidates?state=MI')->assertOk();
    expect($response->getContent())->not->toContain($name);
})->with(['Michigan Secretary', 'Michigan GOP', 'Genesee County Sheriff Chris']);

it('lists a representative once even when two rows exist for them', function () {
    houseRow('Linda Sánchez', 'CA-38');
    houseRow('Linda T. Sánchez', 'CA-38');

    $rows = caPayload()['house_candidates']['CA-38'];

    expect($rows)->toHaveCount(1)
        ->and(caPayload()['quality']['merged_duplicates'])->toBe(1);
});

it('merges a nickname duplicate among House candidates', function () {
    houseRow('Steven Bradford', 'CA-43', ['term_status' => 'running', 'is_running_candidate' => true]);
    ElectionCandidateRecord::create([
        'source' => 'ballotpedia', 'external_candidate_id' => 'steve-bradford',
        'full_name' => 'Steve Bradford', 'political_office' => 'U.S. Representative',
        'party_affiliation' => 'Democratic', 'state' => 'CA', 'governance_level' => 'federal',
        'district' => 'CA-43', 'election_date' => now()->addMonths(3)->toDateString(),
        'payload' => ['status' => 'running'],
    ]);

    $names = collect(caPayload()['house_candidates']['CA-43'])->pluck('full_name')->all();

    expect($names)->toBe(['Steven Bradford']);
});

it('hides placeholder-named scraped candidates but never a seated official', function () {
    foreach (['WI Candidate David', 'Huntington Beach'] as $i => $junk) {
        ElectionCandidateRecord::create([
            'source' => 'ballotpedia', 'external_candidate_id' => "junk-{$i}",
            'full_name' => $junk, 'political_office' => 'U.S. Representative',
            'state' => 'CA', 'governance_level' => 'federal', 'district' => 'CA-38',
            'election_date' => now()->addMonths(3)->toDateString(),
            'payload' => ['status' => 'running'],
        ]);
    }
    houseRow('Linda Sánchez', 'CA-38');

    $payload = caPayload();
    $names = collect($payload['house_candidates']['CA-38'])->pluck('full_name')->all();

    expect($names)->toBe(['Linda Sánchez'])
        ->and($payload['quality']['hidden_names'])->toBe(2);
});

it('hides a running candidate named after a city in the state', function () {
    Politician::factory()->create([
        'full_name' => 'Jordan Patel', 'state' => 'CA', 'city' => 'Santa Ana',
        'governance_level' => 'City', 'political_office' => 'Mayor', 'term_status' => 'seated',
        'is_active' => true, 'user_id' => null,
    ]);
    Politician::factory()->create([
        'full_name' => 'Santa Ana', 'state' => 'CA', 'city' => 'Santa Ana',
        'governance_level' => 'City', 'political_office' => 'City Council Member', 'term_status' => 'running',
        'is_active' => true, 'user_id' => null,
    ]);

    $names = collect(caPayload()['city_officials']['Santa Ana'] ?? [])
        ->flatMap(fn ($g) => collect($g['candidates'])->pluck('full_name'))->all();

    expect($names)->toBe(['Jordan Patel']);
});

it('uses the state calendar for the general-election date, whatever a scrape says', function () {
    StateElectionDate::create([
        'state' => 'CA', 'election_year' => 2026, 'stage_name' => 'General',
        'election_date' => now()->addMonths(2)->toDateString(),
    ]);
    ElectionCandidateRecord::create([
        'source' => 'ballotpedia', 'external_candidate_id' => 'dated',
        'full_name' => 'Dana Datecheck', 'political_office' => 'U.S. Representative',
        'state' => 'CA', 'governance_level' => 'federal', 'district' => 'CA-12',
        'election_date' => now()->addMonths(3)->toDateString(),
        'payload' => ['status' => 'running', 'general_date' => now()->addMonths(2)->subDay()->toDateString()],
    ]);

    $payload = caPayload();
    $official = now()->addMonths(2)->toDateString();

    expect($payload['general_election_date'])->toBe($official)
        ->and($payload['house_candidates']['CA-12'][0]['general_date'])->toBe($official)
        ->and($payload['quality']['date_conflicts'])->toBe(1);
});
