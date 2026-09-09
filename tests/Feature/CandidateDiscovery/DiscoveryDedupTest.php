<?php

use App\Contracts\CandidateDiscoverySource;
use App\Models\CandidateLead;
use App\Models\ElectionCandidateRecord;
use App\Models\Politician;
use App\Services\CandidateDiscovery\CandidateLeadPromoter;
use App\Services\CandidateDiscovery\RssCandidateDiscoverySource;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function discoveryLead(string $name, array $overrides = []): CandidateLead
{
    return CandidateLead::create(array_merge([
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
    ], $overrides));
}

/**
 * Fake RSS source returning a fixed set of raw signals, so the command can be
 * exercised without hitting Google News.
 *
 * @param  array<int, array<string, mixed>>  $signals
 */
function fakeRssSource(array $signals): void
{
    $fake = new class($signals) implements CandidateDiscoverySource
    {
        public function __construct(private array $signals) {}

        public function key(): string
        {
            return 'rss_google_news';
        }

        public function discover(array $options = []): array
        {
            return array_map(fn ($s) => array_merge([
                'full_name' => 'Jane Doe',
                'state' => 'CA',
                'office_hint' => 'Governor',
                'source_url' => 'https://news.example/'.md5(json_encode($s)),
                'published_at' => now(),
                'raw' => ['headline' => 'Headline', 'snippet' => 'Snippet'],
            ], $s), $this->signals);
        }
    };

    app()->instance(RssCandidateDiscoverySource::class, $fake);
}

it('collapses many articles about one candidate into a single ECR', function () {
    $promoter = app(CandidateLeadPromoter::class);

    $a = $promoter->promote(discoveryLead('Steve Hilton'));
    $b = $promoter->promote(discoveryLead('Steve Hilton'));
    $c = $promoter->promote(discoveryLead('Steve Hilton'));

    expect($a->id)->toBe($b->id)->toBe($c->id);
    expect(ElectionCandidateRecord::where('full_name', 'Steve Hilton')->count())->toBe(1);
});

it('does not clobber a corrected office on re-discovery', function () {
    $promoter = app(CandidateLeadPromoter::class);
    $rec = $promoter->promote(discoveryLead('Katie Porter'));

    // A reconcile / manual step fixes the office.
    $rec->update(['political_office' => 'U.S. Senator']);

    $promoter->promote(discoveryLead('Katie Porter'));

    expect($rec->fresh()->political_office)->toBe('U.S. Senator');
});

it('skips an RSS lead when an active politician already exists', function () {
    Politician::factory()->create([
        'full_name' => 'Gavin Newsom',
        'state' => 'CA',
        'term_status' => 'seated',
    ]);
    fakeRssSource([['full_name' => 'Gavin Newsom']]);

    $this->artisan('candidates:discover-leads', ['--source' => ['rss_google_news']])
        ->expectsOutputToContain('already-tracked')
        ->assertExitCode(0);

    expect(CandidateLead::where('full_name', 'Gavin Newsom')->count())->toBe(0);
});

it('skips an RSS lead when an open lead for the same person already exists', function () {
    discoveryLead('Xavier Becerra', ['status' => CandidateLead::STATUS_PENDING]);
    fakeRssSource([['full_name' => 'Xavier Becerra']]);

    $this->artisan('candidates:discover-leads', ['--source' => ['rss_google_news']])
        ->assertExitCode(0);

    expect(CandidateLead::where('full_name', 'Xavier Becerra')->count())->toBe(1);
});

it('skips an RSS lead when a discovery ECR for the same person already exists', function () {
    ElectionCandidateRecord::factory()->create([
        'source' => 'candidate_discovery',
        'full_name' => 'Toni Atkins',
        'state' => 'CA',
        'political_office' => 'Governor',
        'governance_level' => 'state',
    ]);
    fakeRssSource([['full_name' => 'Toni Atkins']]);

    $this->artisan('candidates:discover-leads', ['--source' => ['rss_google_news']])
        ->assertExitCode(0);

    expect(CandidateLead::where('full_name', 'Toni Atkins')->count())->toBe(0);
});

it('still creates a lead for a genuinely new candidate', function () {
    fakeRssSource([['full_name' => 'Brand New Candidate']]);

    $this->artisan('candidates:discover-leads', ['--source' => ['rss_google_news']])
        ->assertExitCode(0);

    expect(CandidateLead::where('full_name', 'Brand New Candidate')->where('state', 'CA')->exists())->toBeTrue();
});
