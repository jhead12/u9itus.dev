<?php

use App\Models\Politician;
use App\Models\ViralMomentEnrichmentRun;
use App\Services\CspanMomentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function seedCspanCommandPolitician(array $extra = []): Politician
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

it('enriches a politician via the C-SPAN fetcher and records a cspan run row', function () {
    $politician = seedCspanCommandPolitician();

    $this->mock(CspanMomentService::class, function ($m) {
        $m->shouldReceive('isConfigured')->andReturn(true);
        $m->shouldReceive('source')->andReturn('cspan');
        $m->shouldReceive('fetchMoments')->once()->andReturn([
            'status' => 'ok',
            'http_status' => 200,
            'query' => 'Alex Padilla Senator CA',
            'clips' => [
                [
                    'source' => 'cspan',
                    'source_id' => '519764',
                    'title' => 'Alex Padilla Senate Floor Speech',
                    'url' => 'https://www.c-span.org/video/?519764',
                    'thumbnail_url' => null,
                    'published_at' => now()->subDays(2),
                    'duration_seconds' => 3600,
                    'view_count' => null,
                    'like_count' => null,
                    'comment_count' => null,
                    'match_confidence' => 1.0,
                ],
            ],
        ]);
    });

    $this->artisan('politicians:enrich-cspan-moments', [
        '--politician' => 'alex-padilla',
        '--force' => true,
    ])->assertSuccessful();

    // Run row carries the cspan source, not the legacy youtube default.
    $run = ViralMomentEnrichmentRun::first();
    expect($run)->not->toBeNull()
        ->and($run->source)->toBe('cspan')
        ->and($run->fetch_status)->toBe('ok');

    // Clip persisted; no view counts → moment_score is 0 (list-only by design).
    expect($politician->fresh()->viralMoments)->toHaveCount(1)
        ->and($politician->viralMoments()->first()->source)->toBe('cspan')
        ->and((float) $politician->viralMoments()->first()->moment_score)->toBe(0.0)
        ->and($politician->viralMoments()->first()->is_featured)->toBeFalse();
});

it('fails fast when C-SPAN moments are disabled', function () {
    seedCspanCommandPolitician();

    $this->mock(CspanMomentService::class, function ($m) {
        $m->shouldReceive('isConfigured')->andReturn(false);
    });

    $this->artisan('politicians:enrich-cspan-moments', ['--politician' => 'alex-padilla'])
        ->assertFailed();

    expect(ViralMomentEnrichmentRun::count())->toBe(0);
});

it('dry-runs without writing any rows', function () {
    $politician = seedCspanCommandPolitician();

    $this->mock(CspanMomentService::class, function ($m) {
        $m->shouldReceive('isConfigured')->andReturn(true);
        $m->shouldReceive('source')->andReturn('cspan');
        $m->shouldReceive('fetchMoments')->once()->andReturn([
            'status' => 'ok',
            'http_status' => 200,
            'query' => 'Alex Padilla Senator CA',
            'clips' => [
                ['source' => 'cspan', 'source_id' => '1', 'title' => 'x', 'url' => 'https://www.c-span.org/video/?1',
                 'thumbnail_url' => null, 'published_at' => null, 'duration_seconds' => null,
                 'view_count' => null, 'like_count' => null, 'comment_count' => null, 'match_confidence' => 0.1],
            ],
        ]);
    });

    $this->artisan('politicians:enrich-cspan-moments', [
        '--politician' => 'alex-padilla',
        '--force' => true,
        '--dry-run' => true,
    ])->assertSuccessful();

    expect(ViralMomentEnrichmentRun::count())->toBe(0)
        ->and($politician->fresh()->viralMoments)->toBeEmpty();
});