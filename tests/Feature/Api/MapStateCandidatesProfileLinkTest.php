<?php

use App\Models\ElectionCandidateRecord;
use App\Models\Politician;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(fn () => Cache::flush());

function profileLinkGovernorCards(): array
{
    $offices = test()->getJson('/api/v1/map/state-candidates?state=TX')->assertOk()->json('offices');

    return collect($offices)->firstWhere('office', 'Governor')['candidates'];
}

function profileLinkTexasRecord(string $name): void
{
    (new ElectionCandidateRecord([
        'source' => 'ballotpedia',
        'external_candidate_id' => 'ext-'.$name,
        'full_name' => $name,
        'political_office' => 'Governor',
        'governance_level' => 'state',
        'state' => 'TX',
        'election_date' => now()->addMonths(3)->toDateString(),
        'payload' => ['status' => 'running'],
    ]))->saveQuietly();
}

it('links a scraped statewide candidate card to the running candidate profile so /map?slug= can open it', function () {
    profileLinkTexasRecord('Gina Hinojosa');
    Politician::factory()->create([
        'slug' => '758c8-governor-austin-gina-hinojosa',
        'full_name' => 'Gina Hinojosa',
        'political_office' => 'Governor',
        'governance_level' => 'state',
        'state' => 'TX',
        'term_status' => 'running',
        'is_running_candidate' => true,
        'is_active' => true,
    ]);

    $card = collect(profileLinkGovernorCards())->firstWhere('full_name', 'Gina Hinojosa');

    expect($card['slug'])->toBe('758c8-governor-austin-gina-hinojosa')
        ->and($card['profile_url'])->toEndWith('/p/758c8-governor-austin-gina-hinojosa');
});

it('leaves a scraped card unlinked when no running candidate profile exists', function () {
    profileLinkTexasRecord('Nobody Profiled');

    $card = collect(profileLinkGovernorCards())->firstWhere('full_name', 'Nobody Profiled');

    expect($card['slug'])->toBeNull()->and($card['profile_url'])->toBeNull();
});

it('merges a news-discovery name with a glued-on city into the clean candidate card and keeps its party', function () {
    DB::table('city_demographics')->insert([
        'state' => 'TX', 'city_name' => 'Austin', 'census_year' => 2023, 'created_at' => now(), 'updated_at' => now(),
    ]);
    profileLinkTexasRecord('Gina Hinojosa');
    (new ElectionCandidateRecord([
        'source' => 'ballotpedia',
        'external_candidate_id' => 'ext-austin-gina',
        'full_name' => 'Austin Gina Hinojosa',
        'political_office' => 'Governor',
        'governance_level' => 'state',
        'state' => 'TX',
        'party_affiliation' => 'Democratic',
        'election_date' => now()->addMonths(3)->toDateString(),
        'payload' => ['status' => 'running'],
    ]))->saveQuietly();

    $names = collect(profileLinkGovernorCards())->where('full_name', '!=', null);
    $gina = $names->filter(fn ($c) => str_contains($c['full_name'], 'Hinojosa'));

    expect($gina)->toHaveCount(1)
        ->and($gina->first()['full_name'])->toBe('Gina Hinojosa')
        ->and($gina->first()['party'])->toBe('Democratic');
});
