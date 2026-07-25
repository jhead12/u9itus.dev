<?php

use App\Models\Politician;
use App\Models\PoliticianViralMoment;
use App\Models\ViralMomentEnrichmentRun;
use App\Services\ViralMomentEnricherService;
use App\Services\YouTubeMomentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function seedMomentPolitician(array $extra = []): Politician
{
    return Politician::create(array_merge([
        'uuid' => Str::uuid(),
        'full_name' => 'Jane Sample',
        'state' => 'CA',
        'political_office' => 'Governor',
        'governance_level' => 'State',
        'party_affiliation' => 'Democratic',
        'is_running_candidate' => false,
        'term_status' => 'seated',
        'is_active' => true,
        'page_published' => true,
        'verified_official' => false,
        'user_id' => null,
        'slug' => 'jane-sample',
        'status_updated_at' => now()->subDays(3),
    ], $extra));
}

/** A canned YouTube fetch result: two recent high-view clips. */
function cannedClips(): array
{
    return [
        'status' => 'ok',
        'http_status' => 200,
        'query' => 'Jane Sample Governor CA',
        'clips' => [
            [
                'source' => 'youtube',
                'source_id' => 'vidA',
                'title' => 'Jane Sample delivers fiery floor speech',
                'url' => 'https://www.youtube.com/watch?v=vidA',
                'thumbnail_url' => 'https://img.example.com/vidA.jpg',
                'published_at' => Carbon::now()->subDays(2),
                'duration_seconds' => 90,
                'view_count' => 1_000_000,
                'like_count' => 50_000,
                'comment_count' => 1_200,
                'match_confidence' => 1.0,
            ],
            [
                'source' => 'youtube',
                'source_id' => 'vidB',
                'title' => 'Some unrelated recap',
                'url' => 'https://www.youtube.com/watch?v=vidB',
                'thumbnail_url' => null,
                'published_at' => Carbon::now()->subDays(5),
                'duration_seconds' => 45,
                'view_count' => 50_000,
                'like_count' => 800,
                'comment_count' => 60,
                'match_confidence' => 0.25,
            ],
        ],
    ];
}

// ── enrich: persistence + featured promotion ────────────────────────────────

it('upserts clips, records a run, and promotes the top clip to featured', function () {
    $politician = seedMomentPolitician();

    $this->mock(YouTubeMomentService::class, function ($m) {
        $m->shouldReceive('source')->andReturn('youtube');
        $m->shouldReceive('fetchMoments')->once()->andReturn(cannedClips());
    });

    $result = app(ViralMomentEnricherService::class)->enrich($politician);

    expect($result)->toBe(['status' => 'ok', 'kept' => 2, 'featured' => true]);

    // Run row recorded with provenance.
    expect(ViralMomentEnrichmentRun::count())->toBe(1)
        ->and($politician->viralMomentRuns()->first()->fetch_status)->toBe('ok');

    // Both clips persisted, scored.
    expect($politician->viralMoments)->toHaveCount(2)
        ->and($politician->viralMoments->firstWhere('source_id', 'vidA')->moment_score)->toBeGreaterThan(0);

    // vidA (1M views, full-name match) outranks vidB (50k, weak match).
    $vidA = $politician->viralMoments()->firstWhere('source_id', 'vidA');
    $vidB = $politician->viralMoments()->firstWhere('source_id', 'vidB');
    expect($vidA->is_featured)->toBeTrue()
        ->and($vidB->is_featured)->toBeFalse()
        ->and($vidA->moment_score)->toBeGreaterThan($vidB->moment_score);

    // Denormalized featured moment mirrored onto the politician for map-pin reads.
    expect($politician->fresh()->featured_moment)->toBe([
        'title' => 'Jane Sample delivers fiery floor speech',
        'url' => 'https://www.youtube.com/watch?v=vidA',
        'thumbnail_url' => 'https://img.example.com/vidA.jpg',
        'source' => 'youtube',
        'published_at' => $vidA->published_at->toIso8601String(),
        'view_count' => 1_000_000,
    ])->and($politician->fresh()->featured_moment_score)->toBeGreaterThan(0);
});

it('refreshes engagement on re-run instead of duplicating rows', function () {
    $politician = seedMomentPolitician();

    // Second run: vidA's view count jumped to 2.5M.
    $second = cannedClips();
    $second['clips'][0]['view_count'] = 2_500_000;

    $this->mock(YouTubeMomentService::class, function ($m) use ($second) {
        $m->shouldReceive('source')->andReturn('youtube');
        $m->shouldReceive('fetchMoments')->twice()->andReturn(cannedClips(), $second);
    });

    $enricher = app(ViralMomentEnricherService::class);
    $enricher->enrich($politician);
    $enricher->enrich($politician);

    expect($politician->fresh()->viralMoments)->toHaveCount(2); // not 4
    expect($politician->viralMoments()->firstWhere('source_id', 'vidA')->view_count)->toBe(2_500_000);
});

it('prunes to the configured max per politician, keeping the top-scored', function () {
    config(['u9itus.moments.max_per_politician' => 1]);

    $politician = seedMomentPolitician();
    $this->mock(YouTubeMomentService::class, function ($m) {
        $m->shouldReceive('source')->andReturn('youtube');
        $m->shouldReceive('fetchMoments')->once()->andReturn(cannedClips());
    });

    app(ViralMomentEnricherService::class)->enrich($politician);

    expect($politician->fresh()->viralMoments)->toHaveCount(1);
    expect($politician->viralMoments()->first()->source_id)->toBe('vidA'); // top-scored kept
});

it('records a run and does not crash when the fetch returns no clips', function () {
    $politician = seedMomentPolitician();

    $this->mock(YouTubeMomentService::class, function ($m) {
        $m->shouldReceive('source')->andReturn('youtube');
        $m->shouldReceive('fetchMoments')->once()->andReturn([
            'status' => 'empty', 'http_status' => null, 'query' => 'Jane Sample', 'clips' => [],
        ]);
    });

    $result = app(ViralMomentEnricherService::class)->enrich($politician);

    expect($result['status'])->toBe('empty')
        ->and($result['kept'])->toBe(0)
        ->and(ViralMomentEnrichmentRun::count())->toBe(1)
        ->and($politician->fresh()->viralMoments)->toBeEmpty()
        ->and($politician->fresh()->featured_moment)->toBeNull();
});

it('does not feature a clip below the min view count', function () {
    config(['u9itus.moments.min_view_count' => 5_000_000]); // higher than both clips

    $politician = seedMomentPolitician();
    $this->mock(YouTubeMomentService::class, function ($m) {
        $m->shouldReceive('source')->andReturn('youtube');
        $m->shouldReceive('fetchMoments')->once()->andReturn(cannedClips());
    });

    app(ViralMomentEnricherService::class)->enrich($politician);

    expect($politician->viralMoments()->where('is_featured', true)->exists())->toBeFalse()
        ->and($politician->fresh()->featured_moment)->toBeNull();
});

// ── getDisplayData (profile read path) ───────────────────────────────────────

it('getDisplayData returns ranked moments with the featured clip first', function () {
    $politician = seedMomentPolitician();
    $this->mock(YouTubeMomentService::class, function ($m) {
        $m->shouldReceive('source')->andReturn('youtube');
        $m->shouldReceive('fetchMoments')->once()->andReturn(cannedClips());
    });

    app(ViralMomentEnricherService::class)->enrich($politician);

    $data = app(ViralMomentEnricherService::class)->getDisplayData($politician);

    expect($data)->not->toBeNull()
        ->and($data['featured']['title'])->toBe('Jane Sample delivers fiery floor speech')
        ->and($data['moments'])->toHaveCount(2)
        ->and($data['moments'][0]['is_featured'])->toBeTrue()
        ->and($data['moments'][1]['is_featured'])->toBeFalse();
});

it('getDisplayData returns null when nothing has been enriched', function () {
    $politician = seedMomentPolitician();

    expect(app(ViralMomentEnricherService::class)->getDisplayData($politician))->toBeNull();
});

// ── news-freshness gate ──────────────────────────────────────────────────────

it('hasRecentNews returns false when no news rows exist for the politician', function () {
    $politician = seedMomentPolitician();

    expect(app(ViralMomentEnricherService::class)->hasRecentNews($politician))->toBeFalse();
});

it('hasRecentNews returns true when a recent news article exists', function () {
    $politician = seedMomentPolitician();

    \App\Models\CandidateNewsArticle::create([
        'politician_id' => $politician->id,
        'candidate_name' => $politician->full_name,
        'headline' => 'Jane Sample in the news',
        'source_name' => 'Test Daily',
        'source_url' => 'https://example.com/news/1',
        'provider' => 'test',
        'verification_status' => 'unverified',
        'source_hash' => sha1('https://example.com/news/1'),
    ]);

    expect(app(ViralMomentEnricherService::class)->hasRecentNews($politician))->toBeTrue();
});