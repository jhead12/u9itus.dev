<?php

use App\Models\Politician;
use App\Models\ViralMomentEnrichmentRun;
use App\Services\PodcastMomentFetcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function seedPodcastCommandPolitician(array $extra = []): Politician
{
    return Politician::create(array_merge([
        'uuid' => Str::uuid(),
        'full_name' => 'Alex Padilla',
        'state' => 'CA',
        'political_office' => 'Senator',
        'governance_level' => 'Federal',
        'party_affiliation' => 'Democratic',
        'is_running_candidate' => false,
        'term_status' => 'seated',
        'is_active' => true,
        'page_published' => true,
        'verified_official' => false,
        'user_id' => null,
        'slug' => 'alex-padilla',
        'status_updated_at' => now()->subDays(3),
    ], $extra));
}

it('enriches a politician via the podcast fetcher and records a podcast run row', function () {
    $politician = seedPodcastCommandPolitician();

    $this->mock(PodcastMomentFetcher::class, function ($m) {
        $m->shouldReceive('isConfigured')->andReturn(true);
        $m->shouldReceive('source')->andReturn('podcast');
        $m->shouldReceive('fetchMoments')->once()->andReturn([
            'status' => 'ok',
            'http_status' => 200,
            'query' => 'Alex Padilla Senator CA',
            'clips' => [
                [
                    'source' => 'podcast',
                    'source_id' => 'listennotes:abc123',
                    'title' => 'Alex Padilla on the future of the Senate',
                    'url' => 'https://embeddable-player.listennotes.com/audio/?e=abc123',
                    'thumbnail_url' => null,
                    'published_at' => now()->subDays(2),
                    'duration_seconds' => 2400,
                    'view_count' => null,
                    'like_count' => null,
                    'comment_count' => null,
                    'match_confidence' => 1.0,
                ],
            ],
        ]);
    });

    $this->artisan('politicians:enrich-podcast-moments', [
        '--politician' => 'alex-padilla',
        '--force' => true,
    ])->assertSuccessful();

    $run = ViralMomentEnrichmentRun::first();
    expect($run)->not->toBeNull()
        ->and($run->source)->toBe('podcast')
        ->and($run->fetch_status)->toBe('ok');

    expect($politician->fresh()->viralMoments)->toHaveCount(1)
        ->and($politician->viralMoments()->first()->source)->toBe('podcast')
        // No engagement counts → moment_score is 0 (list-only by design, same as C-SPAN).
        ->and((float) $politician->viralMoments()->first()->moment_score)->toBe(0.0)
        ->and($politician->viralMoments()->first()->is_featured)->toBeFalse();
});

it('fails fast when podcast moments are disabled or unconfigured', function () {
    seedPodcastCommandPolitician();

    $this->mock(PodcastMomentFetcher::class, function ($m) {
        $m->shouldReceive('isConfigured')->andReturn(false);
    });

    $this->artisan('politicians:enrich-podcast-moments', ['--politician' => 'alex-padilla'])
        ->assertFailed();

    expect(ViralMomentEnrichmentRun::count())->toBe(0);
});

it('--upcoming-only only targets currently-running candidates', function () {
    $running = seedPodcastCommandPolitician([
        'full_name' => 'Running Candidate',
        'slug' => 'running-candidate',
        'is_running_candidate' => true,
        'term_status' => 'running',
    ]);
    $seated = seedPodcastCommandPolitician([
        'full_name' => 'Seated Incumbent',
        'slug' => 'seated-incumbent',
        'is_running_candidate' => false,
        'term_status' => 'seated',
    ]);

    $this->mock(PodcastMomentFetcher::class, function ($m) {
        $m->shouldReceive('isConfigured')->andReturn(true);
        $m->shouldReceive('source')->andReturn('podcast');
        $m->shouldReceive('fetchMoments')->once()->andReturn([
            'status' => 'empty', 'http_status' => 200, 'query' => 'q', 'clips' => [],
        ]);
    });

    $this->artisan('politicians:enrich-podcast-moments', ['--upcoming-only' => true, '--force' => true])
        ->assertSuccessful();

    expect($running->viralMomentRuns()->where('source', 'podcast')->exists())->toBeTrue();
    expect($seated->viralMomentRuns()->where('source', 'podcast')->exists())->toBeFalse();
});

it('dry-runs without writing any rows', function () {
    $politician = seedPodcastCommandPolitician();

    $this->mock(PodcastMomentFetcher::class, function ($m) {
        $m->shouldReceive('isConfigured')->andReturn(true);
        $m->shouldReceive('source')->andReturn('podcast');
        $m->shouldReceive('fetchMoments')->once()->andReturn([
            'status' => 'ok',
            'http_status' => 200,
            'query' => 'Alex Padilla Senator CA',
            'clips' => [
                ['source' => 'podcast', 'source_id' => 'podcastindex:1', 'title' => 'x', 'url' => 'https://a.example.com/1',
                 'thumbnail_url' => null, 'published_at' => null, 'duration_seconds' => null,
                 'view_count' => null, 'like_count' => null, 'comment_count' => null, 'match_confidence' => 0.1],
            ],
        ]);
    });

    $this->artisan('politicians:enrich-podcast-moments', [
        '--politician' => 'alex-padilla',
        '--force' => true,
        '--dry-run' => true,
    ])->assertSuccessful();

    expect(ViralMomentEnrichmentRun::count())->toBe(0)
        ->and($politician->fresh()->viralMoments)->toBeEmpty();
});
