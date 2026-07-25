<?php

use App\Models\CandidateNewsArticle;
use App\Models\Politician;
use App\Models\PoliticianTopic;
use App\Models\PoliticianTopicSignal;
use App\Models\PoliticianViralMoment;
use App\Services\IssueClassifierService;
use App\Services\PoliticianTopicSignalService;
use App\Services\VoteSmartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();

    // Keyword-only deployment: no Anthropic key so the LLM tier is skipped.
    config([
        'services.anthropic.api_key' => null,
        'u9itus.issues.enabled' => true,
        'u9itus.issues.llm_fallback' => false,
        'u9itus.issues.recency_window_days' => 90,
        'u9itus.issues.recency_half_life_days' => 60,
        'u9itus.issues.signal_threshold' => 1.0,
        'u9itus.issues.source_weights' => [
            'news' => 1.0,
            'viral_moment' => 1.2,
            'votesmart' => 1.5,
        ],
    ]);

    $this->healthcare = PoliticianTopic::create([
        'name' => 'Healthcare', 'slug' => 'healthcare', 'is_active' => true, 'sort_order' => 1,
    ]);
    $this->climate = PoliticianTopic::create([
        'name' => 'Climate Action', 'slug' => 'climate-action', 'is_active' => true, 'sort_order' => 2,
    ]);

    $this->politician = Politician::create([
        'uuid' => Str::uuid(),
        'full_name' => 'Jane Doe',
        'slug' => 'jane-doe',
        'state' => 'CA',
        'political_office' => 'Senator',
        'governance_level' => 'Federal',
        'party_affiliation' => 'Democratic',
        'is_active' => true,
        'page_published' => true,
        'show_votesmart_data' => true,
    ]);
});

it('rolls up news + viral moments + Vote Smart positions into per-topic signals', function () {
    // Verified news article already tagged healthcare.
    CandidateNewsArticle::factory()->create([
        'politician_id' => $this->politician->id,
        'topic_key' => 'healthcare',
        'topic_confidence' => 0.7,
        'verification_status' => 'verified',
        'verification_confidence' => 0.9,
        'published_at' => now()->subDays(5),
    ]);

    // Viral moment with a stored topic_key (no classification needed).
    PoliticianViralMoment::factory()->create([
        'politician_id' => $this->politician->id,
        'title' => 'Senator floor speech',
        'topic_key' => 'healthcare',
        'topic_confidence' => 0.8,
        'published_at' => now()->subDays(3),
        'captured_at' => now()->subDays(3),
    ]);

    // Viral moment WITHOUT a stored topic_key → classified by keyword tier.
    PoliticianViralMoment::factory()->create([
        'politician_id' => $this->politician->id,
        'title' => 'Jane Doe healthcare reform remarks',
        'topic_key' => null,
        'topic_confidence' => null,
        'published_at' => now()->subDays(2),
        'captured_at' => now()->subDays(2),
    ]);

    // Vote Smart NPAT position (mocked) → classified by keyword tier.
    $this->mock(VoteSmartService::class, function ($m) {
        $m->shouldReceive('fetchPoliticianRatings')
            ->once()
            ->andReturn([
                'candidate' => 'Jane Doe',
                'ratings' => [],
                'issue_positions' => [['issue' => 'Healthcare', 'position' => 'Support universal care']],
                'key_votes' => [],
            ]);
    });

    $service = new PoliticianTopicSignalService(
        new IssueClassifierService,
        app(VoteSmartService::class),
    );

    $rows = $service->compute($this->politician);

    expect($rows)->not->toBeEmpty();

    $health = PoliticianTopicSignal::where('politician_id', $this->politician->id)
        ->where('topic_id', $this->healthcare->id)
        ->first();

    expect($health)->not->toBeNull()
        ->and($health->news_count)->toBe(1)
        ->and($health->viral_moment_count)->toBe(2)
        ->and($health->votesmart_count)->toBe(1)
        ->and((float) $health->total_score)->toBeGreaterThan(0.0);

    // The untagged moment was classified + back-filled with a topic_key.
    $untagged = $this->politician->viralMoments()
        ->where('title', 'Jane Doe healthcare reform remarks')->first();
    expect($untagged->topic_key)->toBe('healthcare')
        ->and((float) $untagged->topic_confidence)->toBeGreaterThan(0.0);

    // No evidence for Climate → no signal row.
    expect(PoliticianTopicSignal::where('topic_id', $this->climate->id)->exists())->toBeFalse();
});

it('skips the Vote Smart path when the politician hides that data', function () {
    $this->politician->update(['show_votesmart_data' => false]);

    CandidateNewsArticle::factory()->create([
        'politician_id' => $this->politician->id,
        'topic_key' => 'healthcare',
        'topic_confidence' => 0.7,
        'verification_status' => 'verified',
        'published_at' => now()->subDays(5),
    ]);

    // VoteSmart must NOT be called.
    $this->mock(VoteSmartService::class, function ($m) {
        $m->shouldNotReceive('fetchPoliticianRatings');
    });

    $service = new PoliticianTopicSignalService(
        new IssueClassifierService,
        app(VoteSmartService::class),
    );

    $service->compute($this->politician);

    $health = PoliticianTopicSignal::where('topic_id', $this->healthcare->id)->first();
    expect($health)->not->toBeNull()
        ->and($health->votesmart_count)->toBe(0)
        ->and($health->news_count)->toBe(1);
});

it('drops stale signals for topics with no evidence on recompute', function () {
    // Pre-existing signal for climate (no evidence this run).
    PoliticianTopicSignal::create([
        'politician_id' => $this->politician->id,
        'topic_id' => $this->climate->id,
        'news_count' => 5,
        'viral_moment_count' => 0,
        'votesmart_count' => 0,
        'total_score' => 3.0,
        'last_seen_at' => now()->subDays(10),
    ]);

    $this->mock(VoteSmartService::class, fn ($m) => $m->shouldReceive('fetchPoliticianRatings')->andReturn(['issue_positions' => []]));

    $service = new PoliticianTopicSignalService(
        new IssueClassifierService,
        app(VoteSmartService::class),
    );

    $service->compute($this->politician);

    // Stale climate signal removed; nothing replaced it.
    expect(PoliticianTopicSignal::where('topic_id', $this->climate->id)->exists())->toBeFalse();
});
