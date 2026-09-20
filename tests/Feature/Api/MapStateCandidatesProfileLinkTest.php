<?php

use App\Models\ElectionCandidateRecord;
use App\Models\Politician;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(fn () => Cache::flush());

function profileLinkGovernorCards(string $state = 'TX'): array
{
    $offices = test()->getJson('/api/v1/map/state-candidates?state='.$state)->assertOk()->json('offices');

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

it('merges a glued-on leading word into the clean name even when the city is not in the state city list', function () {
    profileLinkTexasRecord('Gina Hinojosa');
    (new ElectionCandidateRecord([
        'source' => 'ballotpedia',
        'external_candidate_id' => 'ext-austin-gina-2',
        'full_name' => 'Austin Gina Hinojosa',
        'political_office' => 'Governor',
        'governance_level' => 'state',
        'state' => 'TX',
        'party_affiliation' => 'Democratic',
        'election_date' => now()->addMonths(3)->toDateString(),
        'payload' => ['status' => 'running'],
    ]))->saveQuietly();

    $gina = collect(profileLinkGovernorCards())->filter(fn ($c) => str_contains($c['full_name'], 'Hinojosa'));

    expect($gina)->toHaveCount(1)
        ->and($gina->first()['full_name'])->toBe('Gina Hinojosa')
        ->and($gina->first()['party'])->toBe('Democratic');
});

it('does not list a seated governor again under a headline-decorated name', function () {
    Politician::factory()->create([
        'full_name' => 'Gretchen Whitmer', 'state' => 'MI', 'user_id' => null, 'term_status' => 'seated', 'is_active' => true,
        'political_office' => 'Governor', 'governance_level' => 'State', 'slug' => 'gretchen-whitmer-governor',
    ]);
    (new ElectionCandidateRecord([
        'source' => 'candidate_discovery', 'external_candidate_id' => 'disc:mi:governor:michigan-gretchen-whitmer',
        'full_name' => 'Michigan Gretchen Whitmer', 'political_office' => 'Governor', 'governance_level' => 'State', 'state' => 'MI',
        'party_affiliation' => 'Democratic', 'election_date' => now()->addMonths(3)->toDateString(), 'payload' => ['primary_result' => 'running'],
    ]))->saveQuietly();

    $names = collect(profileLinkGovernorCards('MI'))->pluck('full_name');

    expect($names->filter(fn ($n) => str_contains($n, 'Whitmer')))->toHaveCount(1);
});

it('hides a discovery record whose name is a headline verb, not a person', function () {
    foreach (['Rowdy Kicks', 'Aric Nesbitt'] as $name) {
        (new ElectionCandidateRecord([
            'source' => 'candidate_discovery', 'external_candidate_id' => 'disc:mi:governor:'.Str::slug($name),
            'full_name' => $name, 'political_office' => 'Governor', 'governance_level' => 'State', 'state' => 'MI',
            'party_affiliation' => 'Republican', 'election_date' => now()->addMonths(3)->toDateString(), 'payload' => ['primary_result' => 'running'],
        ]))->saveQuietly();
    }

    $names = collect(profileLinkGovernorCards('MI'))->pluck('full_name');

    expect($names)->not->toContain('Rowdy Kicks')->and($names)->toContain('Aric Nesbitt');
});

it('keeps a sitting official of another state and a state-legislature row off the statewide panel', function () {
    Politician::factory()->create([
        'full_name' => 'Marsha Blackburn', 'state' => 'TN', 'user_id' => null, 'term_status' => 'seated', 'is_active' => true,
        'political_office' => 'U.S. Senator', 'governance_level' => 'Federal', 'slug' => 'marsha-blackburn-senator',
    ]);
    $make = fn (string $name, string $office, string $level) => (new ElectionCandidateRecord([
        'source' => 'candidate_discovery', 'external_candidate_id' => 'disc:mi:x:'.Str::slug($name.$office),
        'full_name' => $name, 'political_office' => $office, 'governance_level' => $level, 'state' => 'MI',
        'election_date' => now()->addMonths(3)->toDateString(), 'payload' => ['primary_result' => 'running'],
    ]))->saveQuietly();
    $make('Marsha Blackburn', 'Governor', 'State');
    $make('Matt Koleszar', 'Michigan State Senate', 'State');
    $make('Jocelyn Benson', 'Governor', 'State');

    $offices = test()->getJson('/api/v1/map/state-candidates?state=MI')->assertOk()->json('offices');
    $names = collect($offices)->pluck('candidates')->flatten(1)->pluck('full_name');

    expect($names)->toContain('Jocelyn Benson')->not->toContain('Marsha Blackburn')->not->toContain('Matt Koleszar');
});
