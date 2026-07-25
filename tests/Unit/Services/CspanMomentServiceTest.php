<?php

use App\Models\Politician;
use App\Services\CspanMomentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/**
 * Subclass that swaps the Playwright/Node scrape for an in-memory stub, so the
 * fetchMoments → normalize → cache pipeline is testable without Chromium/Node.
 */
class StubCspanService extends CspanMomentService
{
    public ?array $scrape = null;
    public int $invoked = 0;

    protected function runScraper(Politician $politician, string $query): ?array
    {
        $this->invoked++;

        return $this->scrape;
    }
}

function seedCspanPolitician(array $extra = []): Politician
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

it('normalizes scraper output into cspan clips with null engagement and a source label', function () {
    $politician = seedCspanPolitician();
    $svc = new StubCspanService();
    $svc->scrape = [
        'clips' => [
            [
                'source_id' => '519764',
                'title' => 'Alex Padilla Senate Floor Speech',
                'url' => 'https://www.c-span.org/video/?519764',
                'thumbnail_url' => 'https://static.c-span.org/assets/img/519764.jpg',
                'published_at' => '2024-07-23',
                'duration_seconds' => 3600,
            ],
        ],
    ];

    $result = $svc->fetchMoments($politician);

    expect($result['status'])->toBe('ok')
        ->and($result['query'])->toBe('Alex Padilla Senator CA')
        ->and($result['clips'])->toHaveCount(1);

    $clip = $result['clips'][0];
    expect($clip['source'])->toBe('cspan')
        ->and($clip['source_id'])->toBe('519764')
        ->and($clip['url'])->toBe('https://www.c-span.org/video/?519764')
        ->and($clip['thumbnail_url'])->toBe('https://static.c-span.org/assets/img/519764.jpg')
        ->and($clip['published_at'])->toBeInstanceOf(Carbon::class)
        ->and($clip['duration_seconds'])->toBe(3600)
        ->and($clip['view_count'])->toBeNull()
        ->and($clip['like_count'])->toBeNull()
        ->and($clip['comment_count'])->toBeNull()
        // "alex" + "padilla" both present → full name match
        ->and($clip['match_confidence'])->toBe(1.0);
});

it('floors match_confidence at 0.1 when no name tokens hit the title', function () {
    $politician = seedCspanPolitician();
    $svc = new StubCspanService();
    $svc->scrape = [
        'clips' => [
            [
                'source_id' => '999',
                'title' => 'A totally unrelated hearing',
                'url' => 'https://www.c-span.org/video/?999',
                'thumbnail_url' => null,
                'published_at' => null,
                'duration_seconds' => null,
            ],
        ],
    ];

    $clip = $svc->fetchMoments($politician)['clips'][0];

    expect($clip['match_confidence'])->toBe(0.1)
        ->and($clip['thumbnail_url'])->toBeNull()
        ->and($clip['published_at'])->toBeNull();
});

it('skips clips missing a source_id or url', function () {
    $politician = seedCspanPolitician();
    $svc = new StubCspanService();
    $svc->scrape = [
        'clips' => [
            ['source_id' => '', 'title' => 'no id', 'url' => 'https://www.c-span.org/video/?1'],
            ['source_id' => '2', 'title' => 'no url', 'url' => ''],
            ['source_id' => '3', 'title' => 'real', 'url' => 'https://www.c-span.org/video/?3'],
        ],
    ];

    expect($svc->fetchMoments($politician)['clips'])->toHaveCount(1);
});

it('reports empty when the scraper finds no clips', function () {
    $politician = seedCspanPolitician();
    $svc = new StubCspanService();
    $svc->scrape = ['clips' => []];

    $result = $svc->fetchMoments($politician);

    expect($result['status'])->toBe('empty')
        ->and($result['clips'])->toBeEmpty()
        ->and($result['query'])->toBe('Alex Padilla Senator CA');
});

it('reports failed when the scraper errors (returns null)', function () {
    $politician = seedCspanPolitician();
    $svc = new StubCspanService();
    $svc->scrape = null;

    $result = $svc->fetchMoments($politician);

    expect($result['status'])->toBe('failed')
        ->and($result['clips'])->toBeEmpty();
});

it('reports failed and does not scrape when the source is disabled', function () {
    config(['u9itus.moments.cspan.enabled' => false]);

    $politician = seedCspanPolitician();
    $svc = new StubCspanService();
    $svc->scrape = ['clips' => [['source_id' => '1', 'title' => 'x', 'url' => 'https://www.c-span.org/video/?1']]];

    $result = $svc->fetchMoments($politician);

    expect($result['status'])->toBe('failed')
        ->and($svc->invoked)->toBe(0); // never reached the scraper
});

it('caches the scrape result so a second fetch does not re-invoke the scraper', function () {
    $politician = seedCspanPolitician();
    $svc = new StubCspanService();
    $svc->scrape = [
        'clips' => [['source_id' => '7', 'title' => 'Alex Padilla', 'url' => 'https://www.c-span.org/video/?7']],
    ];

    $svc->fetchMoments($politician);
    $svc->fetchMoments($politician);

    expect($svc->invoked)->toBe(1);

    // clearCache forces a fresh scrape.
    $svc->clearCache($politician);
    $svc->fetchMoments($politician);
    expect($svc->invoked)->toBe(2);
});