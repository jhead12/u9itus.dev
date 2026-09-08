<?php

use App\Enums\CivicEventStatus;
use App\Enums\PostStatus;
use App\Models\Citizen;
use App\Models\CivicEvent;
use App\Models\Committee;
use App\Models\NeighborhoodGroup;
use App\Models\Politician;
use App\Models\PoliticianTopic;
use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->withoutVite();
    Http::preventStrayRequests();
    Http::fake();
    Queue::fake();
});

function seoDocument(string $html): DOMXPath
{
    $dom = new DOMDocument;
    @$dom->loadHTML($html);

    return new DOMXPath($dom);
}

function seoValue(DOMXPath $document, string $selector, string $attribute = 'content'): string
{
    $nodes = $document->query($selector);
    expect($nodes->length)->toBe(1);

    return $nodes->item(0)->getAttribute($attribute);
}

test('public landing pages emit one set of metadata and a real fallback image', function (string $path) {
    $response = $this->get($path)->assertOk();
    $document = seoDocument($response->getContent());
    expect(seoValue($document, '//link[@rel="canonical"]', 'href'))->toBe(url($path));
    foreach (['og:title', 'og:description', 'og:image', 'og:url', 'og:type'] as $property) {
        expect(seoValue($document, '//meta[@property="'.$property.'"]'))->not->toBeEmpty();
    }
    expect(seoValue($document, '//meta[@name="description"]'))->not->toBeEmpty();
    expect(seoValue($document, '//meta[@name="twitter:title"]'))->not->toBeEmpty();
    expect(seoValue($document, '//meta[@property="og:image"]'))->toBe(asset('images/og-default.png'));
    expect(file_exists(public_path('images/og-default.png')))->toBeTrue();
})->with(['/', '/politicians', '/pacs', '/groups', '/events', '/blog', '/earn', '/map', '/district-lookup']);

test('pagination preserves its own canonical and ignores tracking', function (string $path) {
    $document = seoDocument($this->get($path.'?page=2&utm_source=test')->assertOk()->getContent());
    expect(seoValue($document, '//link[@rel="canonical"]', 'href'))->toBe(url($path).'?page=2');
    expect(seoValue($document, '//meta[@property="og:url"]'))->toBe(url($path).'?page=2');
    expect(seoValue($document, '//meta[@name="robots"]'))->toBe('index, follow');
})->with(['/politicians', '/pacs', '/groups', '/events', '/blog']);

test('filtered search pages are noindex and preserve their content parameters', function () {
    $document = seoDocument($this->get('/politicians?q=Jane&page=2&utm_source=test')->assertOk()->getContent());
    expect(seoValue($document, '//link[@rel="canonical"]', 'href'))->toBe(url('/politicians').'?page=2&q=Jane');
    expect(seoValue($document, '//meta[@name="robots"]'))->toBe('noindex, follow');
});

test('blog canonical override is unique and structured data safely round trips text', function () {
    $author = Citizen::factory()->create();
    $title = "O'Brien's \"Town Hall\" \\ recap\nSecond line";
    $post = Post::factory()->create([
        'author_type' => Citizen::class, 'author_id' => $author->id,
        'status' => PostStatus::Published, 'published_at' => now()->subHour(),
        'title' => $title, 'meta_title' => null,
        'canonical_url' => 'https://example.org/original',
        'featured_image_url' => 'https://example.org/image.jpg',
    ]);
    $document = seoDocument($this->get(route('blog.show', $post))->assertOk()->getContent());
    expect(seoValue($document, '//link[@rel="canonical"]', 'href'))->toBe($post->canonical_url);
    expect(seoValue($document, '//meta[@property="og:url"]'))->toBe($post->canonical_url);
    expect(seoValue($document, '//meta[@property="og:image"]'))->toBe($post->featured_image_url);
    $scripts = $document->query('//script[@type="application/ld+json"]');
    expect($scripts->length)->toBe(1);
    $schema = json_decode($scripts->item(0)->textContent, true, 512, JSON_THROW_ON_ERROR);
    expect($schema['headline'])->toBe($title)
        ->and($schema['author']['@type'])->toBe('Person')
        ->and($schema['dateModified'])->not->toBeEmpty();
});

test('structured data cannot terminate its script element', function () {
    $value = "O'Brien \"quoted\" \\ newline\n</script><script>alert(1)</script>";
    $html = view('standalone.partials.structured-data', ['schema' => ['name' => $value]])->render();
    $document = seoDocument($html);
    $scripts = $document->query('//script');
    expect($scripts->length)->toBe(1);
    expect(json_decode($scripts->item(0)->textContent, true, 512, JSON_THROW_ON_ERROR)['name'])->toBe($value);
});

test('profile slugs remain stable when descriptive fields change', function () {
    $politician = Politician::factory()->create(['page_published' => true]);
    $slug = $politician->slug;
    $politician->update(['full_name' => 'New Name', 'political_office' => 'Senator', 'city' => 'Sacramento']);
    expect($politician->fresh()->slug)->toBe($slug);
});

test('sitemap contains public canonical records and excludes unavailable content', function () {
    config(['app.url' => 'http://localhost']);
    $visible = Politician::factory()->create(['page_published' => true]);
    $inactive = Politician::factory()->create(['page_published' => true, 'is_active' => false]);
    $draft = Politician::factory()->create(['page_published' => false]);
    $post = Post::factory()->create(['status' => PostStatus::Published, 'published_at' => now()->subDay()]);
    $external = Post::factory()->create(['status' => PostStatus::Published, 'published_at' => now()->subDay(), 'canonical_url' => 'https://example.org/original']);
    $topic = PoliticianTopic::create(['name' => 'Housing', 'slug' => 'housing', 'is_active' => true]);
    $emptyTopic = PoliticianTopic::create(['name' => 'Empty', 'slug' => 'empty', 'is_active' => true]);
    $post->topics()->attach($topic);
    $committee = Committee::create(['fec_committee_id' => 'C00000001', 'name' => 'Public PAC']);
    $committee->profile()->create(['fec_committee_id' => $committee->fec_committee_id, 'enriched_at' => now(), 'cycle' => 2026]);
    $unenriched = Committee::create(['fec_committee_id' => 'C00000002', 'name' => 'Unavailable PAC']);
    $event = CivicEvent::factory()->create();
    $draftEvent = CivicEvent::factory()->create(['status' => CivicEventStatus::Draft]);
    $group = NeighborhoodGroup::create(['name' => 'Local Group', 'scope' => 'District', 'admin_user_id' => User::factory()->create()->id]);
    $response = $this->get('/sitemap.xml')->assertOk();
    $xml = simplexml_load_string($response->getContent());
    expect($xml)->not->toBeFalse();
    $locations = array_map(fn ($url) => (string) $url->loc, iterator_to_array($xml->url, false));
    foreach (['/p/'.$visible->slug, '/blog/'.$post->slug, '/blog/topic/housing', '/pacs/'.$committee->publicSlug(), '/events/'.$event->slug, '/groups/'.$group->slug.'/district', '/earn'] as $path) {
        expect($locations)->toContain(url($path));
    }
    foreach (['/p/'.$inactive->slug, '/p/'.$draft->slug, '/blog/'.$external->slug, '/blog/topic/empty', '/pacs/'.$unenriched->publicSlug(), '/events/'.$draftEvent->slug] as $path) {
        expect($locations)->not->toContain(url($path));
    }
    expect(isset($xml->url[0]->lastmod))->toBeFalse();
});

test('robots fallback serves the same policy as the public file', function () {
    $this->get('/robots.txt')->assertOk()->assertContent(file_get_contents(public_path('robots.txt')));
});

test('politician profile has unique metadata and valid person schema', function () {
    $politician = Politician::factory()->create([
        'page_published' => true,
        'full_name' => "Jane O'Brien",
        'bio' => "Local official\nFocused on housing & schools.",
        'profile_photo_url' => null,
    ]);
    $document = seoDocument($this->get('/p/'.$politician->slug)->assertOk()->getContent());
    expect(seoValue($document, '//link[@rel="canonical"]', 'href'))->toBe(url('/p/'.$politician->slug));
    expect(seoValue($document, '//meta[@property="og:title"]'))->toContain("Jane O'Brien");
    expect(seoValue($document, '//meta[@property="og:type"]'))->toBe('profile');
    expect(seoValue($document, '//meta[@name="description"]'))->toContain('housing');
    $scripts = $document->query('//script[@type="application/ld+json"]');
    expect($scripts->length)->toBe(1);
    $schema = json_decode($scripts->item(0)->textContent, true, 512, JSON_THROW_ON_ERROR);
    expect($schema['name'])->toBe("Jane O'Brien")
        ->and($schema['@type'])->toBe('Person')
        ->and($schema)->not->toHaveKey('worksFor');
});

test('invalid and first pagination values do not create canonical variants', function (string $query) {
    $document = seoDocument($this->get('/blog?'.$query)->assertOk()->getContent());
    expect(seoValue($document, '//link[@rel="canonical"]', 'href'))->toBe(url('/blog'));
})->with(['page=1', 'page=0', 'page=-1', 'page=invalid']);
