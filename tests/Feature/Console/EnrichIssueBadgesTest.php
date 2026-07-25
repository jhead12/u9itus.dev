<?php

use App\Models\CandidateNewsArticle;
use App\Models\Politician;
use App\Models\PoliticianTopic;
use App\Models\PoliticianTopicSignal;
use App\Models\ProfileBadge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();

    config([
        'u9itus.issues.enabled' => true,
        'u9itus.issues.llm_fallback' => false,
        'u9itus.issues.signal_threshold' => 0.3,
        'u9itus.issues.recency_window_days' => 90,
        'u9itus.issues.recency_half_life_days' => 60,
        'u9itus.issues.source_weights' => [
            'news' => 1.0,
            'viral_moment' => 1.2,
            'votesmart' => 1.5,
        ],
        'services.anthropic.api_key' => null,
    ]);

    $this->topic = PoliticianTopic::create([
        'name' => 'Healthcare', 'slug' => 'healthcare', 'is_active' => true, 'sort_order' => 1,
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
        'show_votesmart_data' => false,
    ]);

    CandidateNewsArticle::factory()->create([
        'politician_id' => $this->politician->id,
        'topic_key' => 'healthcare',
        'topic_confidence' => 0.9,
        'verification_status' => 'verified',
        'published_at' => now()->subDays(2),
    ]);
});

it('computes signals and grants an inferred badge for a single politician', function () {
    $this->artisan('politicians:enrich-issue-badges', [
        '--politician' => 'jane-doe',
        '--force' => true,
    ])->assertSuccessful();

    $signal = PoliticianTopicSignal::where('politician_id', $this->politician->id)
        ->where('topic_id', $this->topic->id)
        ->first();

    expect($signal)->not->toBeNull()
        ->and($signal->news_count)->toBe(1)
        ->and((float) $signal->total_score)->toBeGreaterThan(0.0);

    $badge = ProfileBadge::where('badgeable_type', Politician::class)
        ->where('badgeable_id', $this->politician->id)
        ->where('topic_id', $this->topic->id)
        ->first();

    expect($badge)->not->toBeNull()
        ->and($badge->badge_type)->toBe('inferred_discourse')
        ->and($badge->is_public)->toBeTrue();
});

it('exits successfully and writes nothing when issue badges are disabled', function () {
    config(['u9itus.issues.enabled' => false]);

    $this->artisan('politicians:enrich-issue-badges', [
        '--politician' => 'jane-doe',
        '--force' => true,
    ])->assertSuccessful();

    expect(PoliticianTopicSignal::count())->toBe(0)
        ->and(ProfileBadge::count())->toBe(0);
});

it('dry-runs without writing signals or badges', function () {
    $this->artisan('politicians:enrich-issue-badges', [
        '--politician' => 'jane-doe',
        '--force' => true,
        '--dry-run' => true,
    ])->assertSuccessful();

    expect(PoliticianTopicSignal::count())->toBe(0)
        ->and(ProfileBadge::count())->toBe(0);
});
