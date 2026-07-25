<?php

use App\Models\CandidateNewsArticle;
use App\Models\Politician;
use App\Models\ProfileAddress;
use App\Models\ProfileDonationLink;
use App\Models\ProfileEnrichmentRun;
use App\Services\ProfileEnricherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function seedEnrichPolitician(array $extra = []): Politician
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
        'website_url' => 'https://example.gov/',
        'status_updated_at' => now()->subDays(3),
    ], $extra));
}

// ── isConfigured ─────────────────────────────────────────────────────────

it('isConfigured reflects the anthropic key presence', function () {
    config(['services.profile_enricher.anthropic_key' => null]);
    expect((new ProfileEnricherService())->isConfigured())->toBeFalse();

    config(['services.profile_enricher.anthropic_key' => 'sk-test']);
    expect((new ProfileEnricherService())->isConfigured())->toBeTrue();
});

// ── extractFromHtml (native DOM) ─────────────────────────────────────────

it('extracts tel: and mailto: anchors into contact methods', function () {
    $html = '<html><body>'
        . '<h2>District Office</h2><a href="tel:+1-555-0100">555-0100</a>'
        . '<a href="mailto:office@example.gov">Email us</a>'
        . '</body></html>';

    $facts = (new ProfileEnricherService())->extractFromHtml($html, 'https://example.gov/');

    expect($facts['contact_methods'])->toHaveCount(2);
    expect($facts['contact_methods'][0]['kind'])->toBe('phone');
    expect($facts['contact_methods'][0]['value'])->toBe('+1-555-0100');
    expect($facts['contact_methods'][0]['label'])->toBe('District Office');
    expect($facts['contact_methods'][1]['kind'])->toBe('email');
    expect($facts['contact_methods'][1]['value'])->toBe('office@example.gov');
});

it('detects donation links by known host and by anchor text', function () {
    $html = '<html><body>'
        . '<a href="https://secure.actblue.com/donate/sample">Donate</a>'
        . '<a href="https://example.gov/contribute">Contribute to the campaign</a>'
        . '</body></html>';

    $facts = (new ProfileEnricherService())->extractFromHtml($html, 'https://example.gov/');

    expect($facts['donation_links'])->toHaveCount(2);
    expect($facts['donation_links'][0]['platform'])->toBe('actblue');
    expect($facts['donation_links'][1]['platform'])->toBe('candidate_site');
});

it('detects social links by host and resolves relative URLs', function () {
    $html = '<html><body>'
        . '<a href="https://twitter.com/janesample">Twitter</a>'
        . '<a href="https://jane.substack.com/about">Newsletter</a>'
        . '</body></html>';

    $facts = (new ProfileEnricherService())->extractFromHtml($html, 'https://example.gov/');

    $platforms = array_column($facts['social_links'], 'platform');
    expect($platforms)->toContain('x_twitter');
    expect($platforms)->toContain('substack');
    // Generic same-host page links are NOT treated as social links.
    expect($facts['social_links'])->toHaveCount(2);
});

it('extracts JSON-LD PostalAddress into addresses', function () {
    $html = '<html><head><script type="application/ld+json">'
        . json_encode([
            '@type' => 'Place',
            'name' => 'Capitol Office',
            'address' => [
                'streetAddress' => '123 Capitol Mall, Ste 400',
                'addressLocality' => 'Sacramento',
                'addressRegion' => 'CA',
                'postalCode' => '95814',
            ],
        ])
        . '</script></head><body></body></html>';

    $facts = (new ProfileEnricherService())->extractFromHtml($html, 'https://example.gov/');

    expect($facts['addresses'])->toHaveCount(1);
    expect($facts['addresses'][0]['line1'])->toContain('123 Capitol Mall');
    expect($facts['addresses'][0]['city'])->toBe('Sacramento');
    expect($facts['addresses'][0]['label'])->toBe('Capitol Office');
});

// ── normalizeAddress: residential rejection ──────────────────────────────

it('normalizeAddress rejects residential labels and accepts office labels', function () {
    $service = new ProfileEnricherService();

    $rejected = $service->normalizeAddress([
        'label' => 'Home Address',
        'line1' => '123 Maple St',
        'city' => 'Sacramento',
    ], 'https://example.gov/');
    expect($rejected)->toBeNull();

    $accepted = $service->normalizeAddress([
        'label' => 'Office of the Governor',
        'line1' => '123 Capitol Mall',
        'city' => 'Sacramento',
        'state' => 'CA',
        'postal_code' => '95814',
    ], 'https://example.gov/');
    expect($accepted)->not->toBeNull()
        ->and($accepted['address_kind'])->toBe('office')
        ->and($accepted['line1'])->toBe('123 Capitol Mall');
});

it('normalizeAddress defaults address_kind to office when no label', function () {
    $service = new ProfileEnricherService();

    $r = $service->normalizeAddress([
        'line1' => '1 Main St',
        'city' => 'Sacramento',
    ], 'https://example.gov/');

    expect($r['address_kind'])->toBe('office');
});

// ── normalizers ──────────────────────────────────────────────────────────

it('normalizeDonation maps actblue host and stores URL only', function () {
    $service = new ProfileEnricherService();

    $r = $service->normalizeDonation(['url' => 'https://secure.actblue.com/donate/sample'], 'https://example.gov/');

    expect($r['platform'])->toBe('actblue')
        ->and($r['url'])->toBe('https://secure.actblue.com/donate/sample')
        ->and($r)->not->toHaveKey('body_html');
});

it('normalizeSocial parses handles from x.com and substack URLs', function () {
    $service = new ProfileEnricherService();

    $r1 = $service->normalizeSocial(['url' => 'https://x.com/janesample'], 'https://example.gov/');
    expect($r1['platform'])->toBe('x_twitter')
        ->and($r1['handle'])->toBe('janesample');

    $r2 = $service->normalizeSocial(['url' => 'https://jane.substack.com'], 'https://example.gov/');
    expect($r2['platform'])->toBe('substack')
        ->and($r2['is_official'])->toBeTrue();
});

// ── extractWithClaude ────────────────────────────────────────────────────

it('extractWithClaude returns null when the key is missing', function () {
    config(['services.profile_enricher.anthropic_key' => null]);
    $service = new ProfileEnricherService();

    expect($service->extractWithClaude('<html></html>', 'https://example.gov/'))->toBeNull();
});

it('extractWithClaude parses a strict JSON response into the facts shape', function () {
    config(['services.profile_enricher.anthropic_key' => 'sk-test']);
    Http::fake([
        'api.anthropic.com/v1/messages' => Http::response([
            'content' => [
                ['text' => json_encode([
                    'contact_methods' => [['kind' => 'phone', 'value' => '555-0200', 'label' => 'Office']],
                    'addresses' => [['address_kind' => 'office', 'line1' => '1 Capitol', 'city' => 'Sacramento']],
                    'social_links' => [],
                    'donation_links' => [['url' => 'https://secure.actblue.com/x', 'platform' => 'actblue']],
                    'newsletter_url' => null,
                ])],
            ],
        ], 200),
    ]);

    $service = new ProfileEnricherService();
    $facts = $service->extractWithClaude('<html></html>', 'https://example.gov/');

    expect($facts['contact_methods'][0]['value'])->toBe('555-0200')
        ->and($facts['donation_links'][0]['platform'])->toBe('actblue');
});

it('extractWithClaude returns null on malformed JSON without throwing', function () {
    config(['services.profile_enricher.anthropic_key' => 'sk-test']);
    Http::fake([
        'api.anthropic.com/v1/messages' => Http::response([
            'content' => [['text' => 'not json at all']],
        ], 200),
    ]);

    $service = new ProfileEnricherService();
    expect($service->extractWithClaude('<html></html>', 'https://example.gov/'))->toBeNull();
});

// ── robots.txt ───────────────────────────────────────────────────────────

it('fetchPageHtml respects a robots.txt disallow', function () {
    Http::fake([
        'https://example.gov/robots.txt' => Http::response("User-agent: *\nDisallow: /", 200),
    ]);

    config(['services.profile_enricher.anthropic_key' => null]);
    $service = new ProfileEnricherService();

    expect($service->robotsAllowed('https://example.gov/private'))->toBeFalse();
});

// ── fetchNewsletterPosts ──────────────────────────────────────────────────

it('fetchNewsletterPosts pulls Substack JSON and stores titled links only', function () {
    $p = seedEnrichPolitician(['website_url' => 'https://example.gov/']);

    Http::fake([
        'https://jane.substack.com/api/v1/posts?*' => Http::response([
            ['title' => 'Post A', 'canonical_url' => 'https://jane.substack.com/p/a', 'post_date' => '2026-07-19T10:00:00Z', 'audience' => 'everyone'],
            ['title' => 'Post B', 'canonical_url' => 'https://jane.substack.com/p/b', 'post_date' => '2026-07-18T10:00:00Z', 'audience' => 'only_paid'],
        ], 200),
    ]);

    $service = new ProfileEnricherService();
    $stored = $service->fetchNewsletterPosts('https://jane.substack.com/p/a', $p);

    expect($stored)->toHaveCount(2);
    expect(CandidateNewsArticle::where('provider', 'substack_json')->count())->toBe(2);

    $rows = CandidateNewsArticle::where('provider', 'substack_json')->get();
    // Titled links only — never body text or images.
    expect($rows->pluck('snippet')->filter()->all())->toBe([])
        ->and($rows->pluck('image_url')->filter()->all())->toBe([]);

    $paywalled = $rows->firstWhere('source_url', 'https://jane.substack.com/p/b');
    expect($paywalled->verification_reason)->toBe('newsletter_paywalled_titled_link_only');
});

it('fetchNewsletterPosts falls back to RSS when the JSON endpoint 404s', function () {
    $p = seedEnrichPolitician();

    $rss = '<?xml version="1.0"?><rss><channel>'
        . '<item><title>RSS Post</title><link>https://jane.substack.com/p/rss</link><pubDate>Mon, 14 Jul 2026 10:00:00 +0000</pubDate></item>'
        . '</channel></rss>';

    Http::fake([
        'https://jane.substack.com/api/v1/posts?*' => Http::response('', 404),
        'https://jane.substack.com/feed' => Http::response($rss, 200),
    ]);

    $stored = (new ProfileEnricherService())->fetchNewsletterPosts('https://jane.substack.com/', $p);

    expect($stored)->toHaveCount(1);
    expect(CandidateNewsArticle::where('provider', 'substack_rss')->count())->toBe(1);
});

// ── enrich orchestrator ───────────────────────────────────────────────────

it('enrich persists facts and returns the display shape', function () {
    config(['services.profile_enricher.anthropic_key' => null]);
    $p = seedEnrichPolitician();

    $html = '<html><head><script type="application/ld+json">'
        . json_encode(['@type' => 'Place', 'name' => 'Capitol Office', 'address' => [
            'streetAddress' => '123 Capitol Mall', 'addressLocality' => 'Sacramento',
            'addressRegion' => 'CA', 'postalCode' => '95814',
        ]]) . '</script></head><body>'
        . '<h2>Office</h2><a href="tel:+1-555-0100">Call</a>'
        . '<a href="https://secure.actblue.com/donate/sample">Donate</a>'
        . '<a href="https://jane.substack.com">Newsletter</a>'
        . '</body></html>';

    Http::fake([
        'https://example.gov/robots.txt' => Http::response("User-agent: *\nDisallow:", 200),
        'https://example.gov/' => Http::response($html, 200),
        'https://jane.substack.com/api/v1/posts?*' => Http::response([], 200),
    ]);

    $service = new ProfileEnricherService();
    $result = $service->enrich($p);

    expect($result)->not->toBeNull()
        ->and($result['source'])->toBe('Official campaign website')
        ->and($result['sections'])->toHaveKeys(['contact_methods','addresses','social_links','donation_links','newsletter_posts']);

    expect(ProfileEnrichmentRun::where('profilable_id', $p->id)->count())->toBe(1);
    expect(ProfileDonationLink::where('profilable_id', $p->id)->count())->toBe(1);
    expect(ProfileAddress::where('profilable_id', $p->id)->value('line1'))->toBe('123 Capitol Mall');
});

it('enrich drops a residential address end-to-end and keeps the office address', function () {
    config(['services.profile_enricher.anthropic_key' => null]);
    $p = seedEnrichPolitician();

    $html = '<html><head><script type="application/ld+json">'
        . json_encode(['@type' => 'Place', 'name' => 'Home Address', 'address' => [
            'streetAddress' => '123 Maple St', 'addressLocality' => 'Sacramento',
            'addressRegion' => 'CA', 'postalCode' => '95814',
        ]]) . '</script></head><body>'
        . '<h2>Capitol Office</h2><address>123 Capitol Mall, Sacramento, CA 95814</address>'
        . '</body></html>';

    Http::fake([
        'https://example.gov/robots.txt' => Http::response("User-agent: *\nDisallow:", 200),
        'https://example.gov/' => Http::response($html, 200),
    ]);

    $service = new ProfileEnricherService();
    $service->enrich($p);

    $addresses = ProfileAddress::where('profilable_id', $p->id)->get();
    expect($addresses)->toHaveCount(1);
    expect($addresses->first()->line1)->toBe('123 Capitol Mall, Sacramento, CA 95814');
    expect($addresses->pluck('line1'))->not->toContain('123 Maple St');
});

// ── getDisplayData / clearCache ──────────────────────────────────────────

it('getDisplayData returns null when no run exists', function () {
    $p = seedEnrichPolitician();
    config(['services.profile_enricher.anthropic_key' => null]);
    expect((new ProfileEnricherService())->getDisplayData($p))->toBeNull();
});

it('clearCache forgets the namespaced cache keys', function () {
    $p = seedEnrichPolitician();
    Cache::put('profile_enricher.runs.' . $p->id, ['cached' => true]);
    Cache::put('profile_enricher.page.' . sha1($p->website_url), ['cached' => true]);

    (new ProfileEnricherService())->clearCache($p);

    expect(Cache::has('profile_enricher.runs.' . $p->id))->toBeFalse()
        ->and(Cache::has('profile_enricher.page.' . sha1($p->website_url)))->toBeFalse();
});