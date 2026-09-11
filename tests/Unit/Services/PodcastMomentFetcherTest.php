<?php

use App\Models\Politician;
use App\Services\PodcastMomentFetcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function seedPodcastPolitician(array $extra = []): Politician
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

function configurePodcastIndex(): void
{
    config([
        'u9itus.moments.podcast.enabled' => true,
        'services.podcastindex.api_key' => 'PI_KEY',
        'services.podcastindex.api_secret' => 'PI_SECRET',
        'services.listennotes.api_key' => null,
    ]);
}

function configureListenNotes(): void
{
    config([
        'u9itus.moments.podcast.enabled' => true,
        'services.podcastindex.api_key' => null,
        'services.podcastindex.api_secret' => null,
        'services.listennotes.api_key' => 'LN_KEY',
    ]);
}

it('reports failed and makes no requests when the source is disabled', function () {
    config(['u9itus.moments.podcast.enabled' => false]);
    Http::fake();

    $politician = seedPodcastPolitician();
    $result = (new PodcastMomentFetcher())->fetchMoments($politician);

    expect($result['status'])->toBe('failed')
        ->and($result['clips'])->toBeEmpty();
    Http::assertNothingSent();
});

it('is not configured when enabled but no provider key is set', function () {
    config([
        'u9itus.moments.podcast.enabled' => true,
        'services.podcastindex.api_key' => null,
        'services.podcastindex.api_secret' => null,
        'services.listennotes.api_key' => null,
    ]);

    expect((new PodcastMomentFetcher())->isConfigured())->toBeFalse();
});

it('normalizes Podcast Index episodes, matching on title+description', function () {
    configurePodcastIndex();

    Http::fake([
        'api.podcastindex.org/api/1.0/search/byperson*' => Http::response([
            'status' => 'true',
            'items' => [
                [
                    'id' => 4242,
                    'title' => 'Episode 142',
                    'description' => 'A sit-down interview with Alex Padilla about the session.',
                    'link' => 'https://example-show.com/episodes/142',
                    'enclosureUrl' => 'https://example-show.com/episodes/142.mp3',
                    'image' => 'https://example-show.com/art.jpg',
                    'datePublished' => 1721692800,
                    'duration' => 1800,
                ],
            ],
        ], 200),
    ]);

    $politician = seedPodcastPolitician();
    $result = (new PodcastMomentFetcher())->fetchMoments($politician);

    expect($result['status'])->toBe('ok')
        ->and($result['query'])->toBe('Alex Padilla Senator CA')
        ->and($result['clips'])->toHaveCount(1);

    $clip = $result['clips'][0];
    expect($clip['source'])->toBe('podcast')
        ->and($clip['source_id'])->toBe('podcastindex:4242')
        ->and($clip['url'])->toBe('https://example-show.com/episodes/142')
        ->and($clip['thumbnail_url'])->toBe('https://example-show.com/art.jpg')
        ->and($clip['published_at'])->toBeInstanceOf(Carbon::class)
        ->and($clip['duration_seconds'])->toBe(1800)
        ->and($clip['view_count'])->toBeNull()
        // Title alone has no name tokens; description carries "Alex Padilla".
        ->and($clip['match_confidence'])->toBe(1.0);
});

it('normalizes ListenNotes episodes into the documented embed URL', function () {
    configureListenNotes();

    Http::fake([
        'listen-api.listennotes.com/api/v2/search*' => Http::response([
            'results' => [
                [
                    'id' => 'abc123',
                    'title_original' => 'Alex Padilla on the future of the Senate',
                    'description_original' => 'A conversation about policy.',
                    'thumbnail' => 'https://cdn.listennotes.com/thumb.jpg',
                    'pub_date_ms' => 1721692800000,
                    'audio_length_sec' => 2400,
                ],
            ],
        ], 200),
    ]);

    $politician = seedPodcastPolitician();
    $clip = (new PodcastMomentFetcher())->fetchMoments($politician)['clips'][0];

    expect($clip['source_id'])->toBe('listennotes:abc123')
        ->and($clip['url'])->toBe('https://www.listennotes.com/e/abc123/embed/')
        ->and($clip['duration_seconds'])->toBe(2400)
        ->and($clip['match_confidence'])->toBe(1.0);
});

it('fans out to both providers and merges their clips when both are configured', function () {
    config([
        'u9itus.moments.podcast.enabled' => true,
        'services.podcastindex.api_key' => 'PI_KEY',
        'services.podcastindex.api_secret' => 'PI_SECRET',
        'services.listennotes.api_key' => 'LN_KEY',
    ]);

    Http::fake([
        'api.podcastindex.org/api/1.0/search/byperson*' => Http::response([
            'items' => [[
                'id' => 1, 'title' => 'Alex Padilla interview', 'description' => '',
                'link' => 'https://a.example.com/1', 'datePublished' => 1721692800,
            ]],
        ], 200),
        'listen-api.listennotes.com/api/v2/search*' => Http::response([
            'results' => [[
                'id' => 'xyz', 'title_original' => 'Alex Padilla interview', 'pub_date_ms' => 1721692800000,
            ]],
        ], 200),
    ]);

    $politician = seedPodcastPolitician();
    $result = (new PodcastMomentFetcher())->fetchMoments($politician);

    expect($result['clips'])->toHaveCount(2);
    $sources = collect($result['clips'])->pluck('source_id')->all();
    expect($sources)->toContain('podcastindex:1')->toContain('listennotes:xyz');
});

it('reports empty when the provider returns no items', function () {
    configurePodcastIndex();

    Http::fake([
        'api.podcastindex.org/api/1.0/search/byperson*' => Http::response(['items' => []], 200),
    ]);

    $politician = seedPodcastPolitician();
    $result = (new PodcastMomentFetcher())->fetchMoments($politician);

    expect($result['status'])->toBe('empty')
        ->and($result['clips'])->toBeEmpty();
});

it('reports ok=false-equivalent status but keeps the other provider\'s clips when one provider errors', function () {
    config([
        'u9itus.moments.podcast.enabled' => true,
        'services.podcastindex.api_key' => 'PI_KEY',
        'services.podcastindex.api_secret' => 'PI_SECRET',
        'services.listennotes.api_key' => 'LN_KEY',
    ]);

    Http::fake([
        'api.podcastindex.org/api/1.0/search/byperson*' => Http::response([], 500),
        'listen-api.listennotes.com/api/v2/search*' => Http::response([
            'results' => [['id' => 'xyz', 'title_original' => 'Alex Padilla interview', 'pub_date_ms' => 1721692800000]],
        ], 200),
    ]);

    $politician = seedPodcastPolitician();
    $result = (new PodcastMomentFetcher())->fetchMoments($politician);

    expect($result['status'])->toBe('ok')
        ->and($result['clips'])->toHaveCount(1)
        ->and($result['clips'][0]['source_id'])->toBe('listennotes:xyz');
});

it('caches the fetch result so a second fetch does not re-request providers', function () {
    configurePodcastIndex();

    Http::fake([
        'api.podcastindex.org/api/1.0/search/byperson*' => Http::response([
            'items' => [['id' => 1, 'title' => 'Alex Padilla', 'datePublished' => 1721692800]],
        ], 200),
    ]);

    $politician = seedPodcastPolitician();
    $fetcher = new PodcastMomentFetcher();

    $fetcher->fetchMoments($politician);
    $fetcher->fetchMoments($politician);
    Http::assertSentCount(1);

    $fetcher->clearCache($politician);
    $fetcher->fetchMoments($politician);
    Http::assertSentCount(2);
});
