<?php

use App\Models\CandidateNewsArticle;
use App\Models\Politician;
use App\Models\ProfileAddress;
use App\Models\ProfileDonationLink;
use App\Models\ProfileEnrichmentRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function seedProfilePolitician(array $extra = []): Politician
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

function seedProfileRun(Politician $p, ?string $enrichedAgo): void
{
    ProfileEnrichmentRun::create([
        'profilable_type' => Politician::class,
        'profilable_id' => $p->id,
        'source_url' => $p->website_url,
        'fetch_status' => 'ok',
        'robots_allowed' => true,
        'used_claude_fallback' => false,
        'enriched_at' => $enrichedAgo === null ? null : now()->sub($enrichedAgo),
    ]);
}

function fakeSite(string $html = ''): void
{
    $html = $html !== '' ? $html
        : '<html><body>'
        . '<h2>Office</h2><a href="tel:+1-555-0100">Call</a>'
        . '<a href="https://secure.actblue.com/donate/sample">Donate</a>'
        . '</body></html>';

    Http::fake([
        'https://example.gov/robots.txt' => Http::response("User-agent: *\nDisallow:", 200),
        'https://example.gov/' => Http::response($html, 200),
    ]);
}

test('--dry-run reports discovered facts and writes nothing', function () {
    $p = seedProfilePolitician();
    config(['services.profile_enricher.anthropic_key' => null]);
    fakeSite();

    Artisan::call('politicians:enrich-profiles', ['--dry-run' => true, '--politician' => $p->slug]);

    expect(Artisan::output())->toContain('[dry-run]')
        ->and(ProfileEnrichmentRun::count())->toBe(0)
        ->and(ProfileDonationLink::count())->toBe(0);
});

test('skips a fresh profile without making any network calls', function () {
    $p = seedProfilePolitician();
    seedProfileRun($p, '1 hour');
    Http::fake();

    Artisan::call('politicians:enrich-profiles', ['--stale-hours' => 48, '--politician' => $p->slug]);

    expect(Artisan::output())->toContain('fresh')
        ->and(ProfileEnrichmentRun::count())->toBe(1); // the seeded run only
    Http::assertNothingSent();
});

test('enriches a stale profile', function () {
    $p = seedProfilePolitician();
    seedProfileRun($p, '3 days');
    config(['services.profile_enricher.anthropic_key' => null]);
    fakeSite();

    Artisan::call('politicians:enrich-profiles', ['--stale-hours' => 48, '--politician' => $p->slug]);

    // The stale seed run + a new run from this invocation.
    expect(ProfileEnrichmentRun::where('profilable_id', $p->id)->count())->toBe(2);
    expect(ProfileDonationLink::where('profilable_id', $p->id)->count())->toBe(1);
});

test('--force re-enriches a fresh profile', function () {
    $p = seedProfilePolitician();
    seedProfileRun($p, '1 hour');
    config(['services.profile_enricher.anthropic_key' => null]);
    fakeSite();

    Artisan::call('politicians:enrich-profiles', ['--force' => true, '--politician' => $p->slug]);

    expect(ProfileEnrichmentRun::where('profilable_id', $p->id)->count())->toBe(2);
});

test('--stale-hours=0 disables the freshness check', function () {
    $p = seedProfilePolitician();
    seedProfileRun($p, '1 hour');
    config(['services.profile_enricher.anthropic_key' => null]);
    fakeSite();

    Artisan::call('politicians:enrich-profiles', ['--stale-hours' => 0, '--politician' => $p->slug]);

    expect(ProfileEnrichmentRun::where('profilable_id', $p->id)->count())->toBe(2);
});

test('--politician= filters by slug and only that one is processed', function () {
    $a = seedProfilePolitician(['slug' => 'jane-sample', 'website_url' => 'https://example.gov/']);
    $b = seedProfilePolitician([
        'uuid' => Str::uuid(),
        'slug' => 'other-pol',
        'full_name' => 'Other Pol',
        'website_url' => 'https://other.gov/',
    ]);
    config(['services.profile_enricher.anthropic_key' => null]);

    Http::fake([
        'https://example.gov/robots.txt' => Http::response("User-agent: *\nDisallow:", 200),
        'https://example.gov/' => Http::response('<a href="tel:+1-555-0100">x</a>', 200),
        'https://other.gov/*' => Http::response('', 200),
    ]);

    Artisan::call('politicians:enrich-profiles', ['--politician' => 'jane-sample']);

    expect(ProfileEnrichmentRun::where('profilable_id', $a->id)->exists())->toBeTrue();
    expect(ProfileEnrichmentRun::where('profilable_id', $b->id)->exists())->toBeFalse();
});

test('politicians with a null website_url are never queued', function () {
    $with = seedProfilePolitician(['slug' => 'has-site']);
    $without = seedProfilePolitician([
        'uuid' => Str::uuid(),
        'slug' => 'no-site',
        'full_name' => 'No Site Pol',
        'website_url' => null,
    ]);
    config(['services.profile_enricher.anthropic_key' => null]);

    Http::fake([
        'https://example.gov/robots.txt' => Http::response("User-agent: *\nDisallow:", 200),
        'https://example.gov/' => Http::response('<a href="tel:+1-555-0100">x</a>', 200),
    ]);

    Artisan::call('politicians:enrich-profiles', ['--force' => true]);

    expect(ProfileEnrichmentRun::where('profilable_id', $with->id)->exists())->toBeTrue();
    expect(ProfileEnrichmentRun::where('profilable_id', $without->id)->exists())->toBeFalse();
});

test('a failed fetch increments the failed counter and writes no run', function () {
    $p = seedProfilePolitician();
    config(['services.profile_enricher.anthropic_key' => null]);
    Http::fake([
        'https://example.gov/robots.txt' => Http::response("User-agent: *\nDisallow:", 200),
        'https://example.gov/' => Http::response('', 500),
    ]);

    Artisan::call('politicians:enrich-profiles', ['--force' => true, '--politician' => $p->slug]);

    expect(Artisan::output())->toContain('null');
    expect(ProfileEnrichmentRun::where('profilable_id', $p->id)->count())->toBe(0);
});

test('a residential address is dropped end-to-end while the office address is kept', function () {
    $p = seedProfilePolitician();
    config(['services.profile_enricher.anthropic_key' => null]);
    fakeSite(
        '<html><body>'
        . '<h2>Home</h2><address>123 Maple St, Sacramento, CA 95814</address>'
        . '<h2>Capitol Office</h2><address>123 Capitol Mall, Sacramento, CA 95814</address>'
        . '</body></html>'
    );

    Artisan::call('politicians:enrich-profiles', ['--force' => true, '--politician' => $p->slug]);

    $rows = ProfileAddress::where('profilable_id', $p->id)->get();
    expect($rows)->toHaveCount(1);
    expect($rows->first()->line1)->toContain('123 Capitol Mall');
    expect($rows->pluck('line1'))->not->toContain('123 Maple St, Sacramento, CA 95814');
});

test('donation links are stored as URL only', function () {
    $p = seedProfilePolitician();
    config(['services.profile_enricher.anthropic_key' => null]);
    fakeSite(
        '<html><body><a href="https://secure.actblue.com/donate/sample">Donate</a></body></html>'
    );

    Artisan::call('politicians:enrich-profiles', ['--force' => true, '--politician' => $p->slug]);

    $row = ProfileDonationLink::where('profilable_id', $p->id)->first();
    expect($row)->not->toBeNull()
        ->and($row->platform)->toBe('actblue')
        ->and($row->url)->toBe('https://secure.actblue.com/donate/sample');
});

test('newsletter posts are persisted to candidate_news_articles as substack_json', function () {
    $p = seedProfilePolitician();
    config(['services.profile_enricher.anthropic_key' => null]);

    $html = '<html><body>'
        . '<a href="https://jane.substack.com">Newsletter</a>'
        . '<h2>Office</h2><a href="tel:+1-555-0100">Call</a>'
        . '</body></html>';

    Http::fake([
        'https://example.gov/robots.txt' => Http::response("User-agent: *\nDisallow:", 200),
        'https://example.gov/' => Http::response($html, 200),
        'https://jane.substack.com/api/v1/posts?*' => Http::response([
            ['title' => 'Post A', 'canonical_url' => 'https://jane.substack.com/p/a', 'post_date' => '2026-07-19T10:00:00Z', 'audience' => 'everyone'],
        ], 200),
    ]);

    Artisan::call('politicians:enrich-profiles', ['--force' => true, '--politician' => $p->slug]);

    expect(CandidateNewsArticle::where('provider', 'substack_json')->count())->toBe(1);
});

test('robots.txt disallow blocks enrichment and writes no run', function () {
    $p = seedProfilePolitician();
    config(['services.profile_enricher.anthropic_key' => null]);
    Http::fake([
        'https://example.gov/robots.txt' => Http::response("User-agent: *\nDisallow: /", 200),
        'https://example.gov/' => Http::response('<html></html>', 200),
    ]);

    Artisan::call('politicians:enrich-profiles', ['--force' => true, '--politician' => $p->slug]);

    expect(ProfileEnrichmentRun::where('profilable_id', $p->id)->count())->toBe(0);
});