<?php

use App\Models\CongressCommitteeAssignment;
use App\Models\CongressMemberLegislation;
use App\Models\Politician;
use App\Models\PoliticianTopic;
use App\Services\CongressCommitteeImporter;
use App\Services\CongressLegislationImporter;
use App\Services\IssueClassifierService;
use App\Services\PoliticianTopicSignalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
    config([
        'services.anthropic.api_key' => null,
        'services.congress.api_key' => 'test-key',
        'u9itus.issues.llm_fallback' => false,
    ]);

    $this->healthcare = PoliticianTopic::create([
        'name' => 'Healthcare', 'slug' => 'healthcare', 'is_active' => true, 'sort_order' => 1,
        'keywords' => ['health insurance', 'hospital'], 'policy_areas' => ['Health'],
    ]);
    $this->guns = PoliticianTopic::create([
        'name' => 'Gun Control', 'slug' => 'gun-control', 'is_active' => true, 'sort_order' => 2,
        'keywords' => ['firearm', 'firearms', 'assault weapon'], 'policy_areas' => [],
    ]);
    $this->safety = PoliticianTopic::create([
        'name' => 'Public Safety', 'slug' => 'public-safety', 'is_active' => true, 'sort_order' => 3,
        'keywords' => ['police'], 'policy_areas' => ['Crime and Law Enforcement'],
    ]);
});

function committeeFeeds(): void
{
    Http::fake([
        CongressCommitteeImporter::COMMITTEES_URL => Http::response([
            ['type' => 'house', 'name' => 'House Committee on Energy and Commerce', 'thomas_id' => 'HSIF', 'url' => 'https://energycommerce.house.gov/',
                'subcommittees' => [['name' => 'Health', 'thomas_id' => '14']]],
            ['type' => 'senate', 'name' => 'Senate Committee on Finance', 'thomas_id' => 'SSFI'],
        ]),
        CongressCommitteeImporter::MEMBERSHIP_URL => Http::response([
            'HSIF' => [['name' => 'A', 'party' => 'majority', 'rank' => 1, 'title' => 'Chair', 'bioguide' => 'A000001'], ['name' => 'B', 'party' => 'minority', 'rank' => 2, 'bioguide' => 'B000002']],
            'HSIF14' => [['name' => 'B', 'party' => 'minority', 'rank' => 1, 'title' => 'Ranking Member', 'bioguide' => 'B000002']],
            'SSFI' => [['name' => 'C', 'party' => 'majority', 'rank' => 1, 'bioguide' => 'C000003']],
            'XXXX' => [['name' => 'Unknown committee', 'bioguide' => 'D000004']],
        ]),
    ]);
}

it('imports committee seats with subcommittees and replaces stale ones', function () {
    CongressCommitteeAssignment::create(['bioguide_id' => 'Z000009', 'committee_code' => 'OLD', 'name' => 'Gone', 'chamber' => 'house']);
    committeeFeeds();

    $stats = app(CongressCommitteeImporter::class)->import();

    expect($stats)->toBe(['committees' => 3, 'seats' => 4, 'members' => 3])
        ->and(CongressCommitteeAssignment::where('bioguide_id', 'Z000009')->exists())->toBeFalse();
    $sub = CongressCommitteeAssignment::where(['bioguide_id' => 'B000002', 'committee_code' => 'HSIF14'])->sole();
    expect($sub->parent_code)->toBe('HSIF')
        ->and($sub->name)->toBe('Health')
        ->and($sub->title)->toBe('Ranking Member')
        ->and($sub->side)->toBe('minority');
});

it('keeps existing seats when the membership feed is empty', function () {
    CongressCommitteeAssignment::create(['bioguide_id' => 'Z000009', 'committee_code' => 'HSIF', 'name' => 'Kept', 'chamber' => 'house']);
    Http::fake([CongressCommitteeImporter::COMMITTEES_URL => Http::response([]), CongressCommitteeImporter::MEMBERSHIP_URL => Http::response([])]);

    $this->artisan('congress:sync-committees')->assertFailed();
    expect(CongressCommitteeAssignment::count())->toBe(1);
});

it('shows committee seats and legislative focus on the profile', function () {
    committeeFeeds();
    app(CongressCommitteeImporter::class)->import();
    CongressMemberLegislation::create([
        'bioguide_id' => 'B000002', 'since_congress' => 118, 'sponsored_total' => 12, 'cosponsored_total' => 80,
        'policy_areas' => ['Health' => ['sponsored' => 5, 'cosponsored' => 30]], 'topics' => [],
    ]);
    $politician = Politician::factory()->create(['bioguide_id' => 'B000002', 'page_published' => true, 'is_active' => true]);

    $this->get(route('politician.public.show', $politician->slug))
        ->assertOk()
        ->assertSee('id="profile-committees"', false)
        ->assertSee('House Committee on Energy and Commerce')
        ->assertSee('Ranking Member')
        ->assertSee('Legislative focus')
        ->assertSee('5 sponsored · 30 cosponsored')
        ->assertSee('since the 118th Congress');

    $other = Politician::factory()->create(['bioguide_id' => null, 'page_published' => true, 'is_active' => true]);
    $this->get(route('politician.public.show', $other->slug))->assertOk()->assertDontSee('id="profile-committees"', false);
});

it('counts bills by policy area and by title keywords, skipping simple resolutions and old Congresses', function () {
    Http::fake([
        'api.congress.gov/v3/member/P000001/sponsored-legislation*' => Http::response([
            'sponsoredLegislation' => [
                ['congress' => 119, 'type' => 'HR', 'title' => 'Lower Hospital Costs Act', 'policyArea' => ['name' => 'Health']],
                ['congress' => 119, 'type' => 'HR', 'title' => 'Assault Weapon Ban Act', 'policyArea' => ['name' => 'Crime and Law Enforcement']],
                ['congress' => 119, 'type' => 'HRES', 'title' => 'Honoring nurses', 'policyArea' => ['name' => 'Health']],
                ['congress' => 116, 'type' => 'HR', 'title' => 'An old health bill', 'policyArea' => ['name' => 'Health']],
            ],
            'pagination' => ['count' => 4],
        ]),
        'api.congress.gov/v3/member/P000001/cosponsored-legislation*' => Http::response([
            'cosponsoredLegislation' => [
                ['congress' => 118, 'type' => 'S', 'title' => 'Health insurance for kids', 'policyArea' => ['name' => 'Health']],
            ],
            'pagination' => ['count' => 1],
        ]),
    ]);

    $row = app(CongressLegislationImporter::class)->import('P000001', 118);

    expect($row->sponsored_total)->toBe(2)
        ->and($row->cosponsored_total)->toBe(1)
        ->and($row->policy_areas['Health'])->toBe(['sponsored' => 1, 'cosponsored' => 1])
        ->and($row->topics['healthcare'])->toBe(['sponsored' => 1, 'cosponsored' => 1])
        // Filed under Crime and Law Enforcement, but the title says what it is about.
        ->and($row->topics['gun-control'])->toBe(['sponsored' => 1, 'cosponsored' => 0])
        ->and($row->topics['public-safety'])->toBe(['sponsored' => 1, 'cosponsored' => 0]);
});

it('badges only the topics that make up a real share of a member\'s bills', function () {
    config(['u9itus.issues.signal_threshold' => 1.0, 'u9itus.issues.legislation_share_per_point' => 0.08, 'u9itus.issues.legislation_min_bills' => 3]);
    $politician = Politician::factory()->create(['bioguide_id' => 'P000001', 'page_published' => true, 'is_active' => true, 'show_votesmart_data' => false]);
    CongressMemberLegislation::create([
        'bioguide_id' => 'P000001', 'since_congress' => 118, 'sponsored_total' => 20, 'cosponsored_total' => 400,
        'policy_areas' => [],
        'topics' => [
            'healthcare' => ['sponsored' => 6, 'cosponsored' => 60], // 18 of 100 weighted bills
            'gun-control' => ['sponsored' => 1, 'cosponsored' => 10], // 3 of 100: under the share bar
            'public-safety' => ['sponsored' => 0, 'cosponsored' => 5], // 1: under the minimum
        ],
    ]);

    $signals = app(PoliticianTopicSignalService::class)->compute($politician)->keyBy('topic_id');

    expect((float) $signals[$this->healthcare->id]->total_score)->toBeGreaterThanOrEqual(1.0)
        ->and($signals[$this->healthcare->id]->legislation_count)->toBe(66)
        ->and((float) $signals[$this->guns->id]->total_score)->toBeLessThan(1.0)
        ->and($signals->has($this->safety->id))->toBeFalse();
});

it('matches keywords as whole words', function () {
    $classifier = app(IssueClassifierService::class);

    expect($classifier->confidentKeywordMatch('Senate votes on firearms background checks')['topic_slug'])->toBe('gun-control')
        ->and($classifier->confidentKeywordMatch('Gunther Firearmsmith visits the capitol'))->toBeNull()
        ->and($classifier->confidentKeywordMatch('Plan to fund police and hospital upgrades')['topic_slug'])->not->toBeNull();
});

it('filters the directory by topic name in prose and counts badged profiles per chip', function () {
    $prose = Politician::factory()->create(['bio' => 'Focused on public safety and schools.', 'page_published' => true, 'is_active' => true]);
    $badged = Politician::factory()->create(['bio' => 'Local official.', 'page_published' => true, 'is_active' => true]);
    $badged->addBadge($this->healthcare->id, 'inferred_discourse', ['is_public' => true, 'earned_at' => now()]);

    $this->get('/politicians?topic=public-safety')->assertOk()->assertSee($prose->full_name)->assertDontSee($badged->full_name);

    $html = $this->get('/politicians')->assertOk()->getContent();
    expect($html)->toContain('aria-label="1 profile"')
        ->and($html)->toContain('the Healthcare filter')
        // A bio mention counts too: the chip leads to the same results as the filter.
        ->and($html)->toContain('the Public Safety filter')
        // No profile carries Gun Control yet, so its chip is hidden.
        ->and($html)->not->toContain('the Gun Control filter');

    // Even when selected, an issue with no profiles is hidden and dropped from chip links.
    $html = $this->get('/politicians?topic=gun-control,healthcare')->assertOk()->getContent();
    expect($html)->not->toContain('the Gun Control filter')
        ->and($html)->toContain('Remove the Healthcare filter')
        // Adding Public Safety keeps Healthcare and leaves the empty Gun Control out.
        ->and($html)->toContain('<input type="hidden" name="topic" value="healthcare,public-safety">');
});
