<?php

use App\Models\CandidateLead;
use App\Models\ElectionCandidateRecord;
use App\Models\Politician;
use App\Models\PoliticianCleanupReview;
use App\Models\User;
use App\Services\CandidateDiscovery\CandidateLeadPromoter;
use App\Support\CrossStateImpostors;
use App\Support\MapCandidateHygiene;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(fn () => Cache::flush());

function abbottTexas(array $over = []): Politician
{
    return Politician::factory()->create(array_merge([
        'full_name' => 'Greg Abbott', 'state' => 'TX', 'political_office' => 'Governor',
        'governance_level' => 'state', 'term_status' => 'seated', 'is_active' => true,
        'slug' => 'abbott-tx-'.fake()->unique()->numerify('####'),
    ], $over));
}

function unclaimedAbbott(string $state, array $over = []): Politician
{
    $p = Politician::factory()->make(array_merge([
        'full_name' => 'Greg Abbott', 'state' => $state, 'political_office' => 'Governor',
        'governance_level' => 'state', 'term_status' => 'running', 'is_running_candidate' => true,
        'is_active' => true, 'user_id' => null, 'verified_official' => false,
        'slug' => 'abbott-'.strtolower($state).'-'.fake()->unique()->numerify('####'),
    ], $over));
    $p->saveQuietly();

    return $p;
}

function leadFor(string $name, string $state): CandidateLead
{
    return CandidateLead::create([
        'source_key' => 'rss_google_news', 'full_name' => $name, 'state' => $state,
        'office_hint' => 'Governor', 'source_url' => 'https://news.example/'.fake()->unique()->slug(),
        'source_hash' => hash('sha256', fake()->unique()->uuid()), 'discovered_at' => now(),
        'status' => CandidateLead::STATUS_VERIFIED, 'confidence' => 0.95,
        'verified_payload' => ['political_office' => 'Governor', 'governance_level' => 'state'],
    ]);
}

function discoveryEcr(string $name, string $state): ElectionCandidateRecord
{
    return ElectionCandidateRecord::create([
        'source' => ElectionCandidateRecord::DISCOVERY_SOURCE,
        'external_candidate_id' => 'disc:'.strtolower($state).':governor:'.str($name)->slug(),
        'full_name' => $name, 'political_office' => 'Governor', 'governance_level' => 'state',
        'state' => $state, 'party_affiliation' => 'Republican',
        'election_date' => now()->addMonths(2)->toDateString(),
        // Survives the map's "unverified discovery" gate, so only the impostor filter can hide it.
        'payload' => ['primary_result' => 'advanced_to_general', 'status' => 'running'],
    ]);
}

it('treats a trailing possessive as headline text and as the same person', function () {
    expect(MapCandidateHygiene::nameProblem("Greg Abbott's"))->not->toBeNull()
        ->and(MapCandidateHygiene::nameProblem('Greg Abbott'))->toBeNull()
        ->and(MapCandidateHygiene::identityKey("Greg Abbott's"))->toBe(MapCandidateHygiene::identityKey('Greg Abbott'))
        ->and(MapCandidateHygiene::nameProblem("Beto O'Rourke"))->toBeNull();
});

it('finds the seated holder of the same statewide office in another state only', function () {
    abbottTexas();
    $holders = CrossStateImpostors::seatedHolders();

    expect(CrossStateImpostors::holderElsewhere('Greg Abbott', 'Governor', 'NC', $holders)['state'])->toBe('TX')
        ->and(CrossStateImpostors::holderElsewhere('Greg Abbott', 'Governor', 'TX', $holders))->toBeNull()
        ->and(CrossStateImpostors::holderElsewhere('Greg Abbott', 'Attorney General', 'NC', $holders))->toBeNull()
        ->and(CrossStateImpostors::holderElsewhere('Greg Abbott', 'Mayor', 'NC', $holders))->toBeNull()
        ->and(CrossStateImpostors::holderElsewhere('Jane Nobody', 'Governor', 'NC', $holders))->toBeNull();
});

it('rejects a discovery lead that is really a sitting governor of another state', function () {
    abbottTexas();
    $lead = leadFor('Greg Abbott', 'NC');

    expect(app(CandidateLeadPromoter::class)->promote($lead))->toBeNull();

    $lead->refresh();
    expect($lead->status)->toBe(CandidateLead::STATUS_REJECTED)
        ->and($lead->reason)->toContain('cross-state: seated Governor in TX')
        ->and(ElectionCandidateRecord::count())->toBe(0);
});

it('still promotes a genuine candidate with an unrelated name', function () {
    abbottTexas();

    expect(app(CandidateLeadPromoter::class)->promote(leadFor('Katie Porter', 'CA')))->not->toBeNull();
});

it('does not create public profiles from impostor or headline-text discovery records', function () {
    abbottTexas();
    discoveryEcr('Greg Abbott', 'NC');
    ElectionCandidateRecord::query()->insert([
        'source' => 'candidate_discovery', 'external_candidate_id' => 'disc:tx:governor:greg-abbotts',
        'full_name' => "Greg Abbott's", 'political_office' => 'Governor', 'governance_level' => 'state',
        'state' => 'TX', 'created_at' => now(), 'updated_at' => now(),
    ]);
    discoveryEcr('Katie Porter', 'CA');

    $before = Politician::count();
    $this->artisan('politicians:reconcile-missing-profiles')->assertExitCode(0);

    expect(Politician::count())->toBe($before + 1)
        ->and(Politician::where('full_name', 'Katie Porter')->exists())->toBeTrue()
        ->and(Politician::where('state', 'NC')->exists())->toBeFalse();
});

it('keeps the impostor off the map governor list', function () {
    abbottTexas();
    discoveryEcr('Greg Abbott', 'NC');
    discoveryEcr('Katie Porter', 'NC');

    $json = $this->getJson('/api/v1/map/state-candidates?state=NC')->assertOk()->json();
    $names = collect($json['offices'])->firstWhere('office', 'Governor')['candidates'] ?? [];
    $names = collect($names)->pluck('full_name')->all();

    expect($names)->toContain('Katie Porter')->not->toContain('Greg Abbott')
        ->and($json['quality']['cross_state'])->toBe(1);
});

it('shows the same row when nobody seated holds that office elsewhere', function () {
    discoveryEcr('Greg Abbott', 'NC');

    $json = $this->getJson('/api/v1/map/state-candidates?state=NC')->assertOk()->json();
    $names = collect(collect($json['offices'])->firstWhere('office', 'Governor')['candidates'] ?? [])->pluck('full_name')->all();

    expect($names)->toContain('Greg Abbott')->and($json['quality']['cross_state'])->toBe(0);
});

it('queues impostors and headline-text profiles for review, and nothing else', function () {
    $real = abbottTexas();
    $nc = unclaimedAbbott('NC');
    $ny = unclaimedAbbott('NY');
    $possessive = unclaimedAbbott('TX', ['full_name' => "Greg Abbott's"]);
    $claimed = unclaimedAbbott('FL', ['user_id' => User::factory()->create()->id]);
    $verified = unclaimedAbbott('OH', ['verified_official' => true]);
    $honest = unclaimedAbbott('CA', ['full_name' => 'Katie Porter']);

    // Report-only by default.
    $this->artisan('politicians:flag-suspect-profiles')->assertExitCode(0);
    expect(PoliticianCleanupReview::count())->toBe(0);

    $this->artisan('politicians:flag-suspect-profiles', ['--apply' => true])->assertExitCode(0);
    $this->artisan('politicians:flag-suspect-profiles', ['--apply' => true])->assertExitCode(0); // idempotent

    $flagged = PoliticianCleanupReview::query()->pluck('politician_id')->all();
    expect($flagged)->toHaveCount(3)
        ->and($flagged)->toContain($nc->id, $ny->id, $possessive->id)
        ->and($flagged)->not->toContain($real->id, $claimed->id, $verified->id, $honest->id);

    $review = PoliticianCleanupReview::where('politician_id', $nc->id)->first();
    expect($review->review_type)->toBe(PoliticianCleanupReview::TYPE_DEACTIVATE)
        ->and($review->status)->toBe('pending')
        ->and($review->reason)->toContain('TX')
        ->and($nc->refresh()->is_active)->toBeTrue(); // nothing was deactivated
});
