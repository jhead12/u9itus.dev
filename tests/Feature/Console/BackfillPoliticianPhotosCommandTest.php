<?php

use App\Models\Politician;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function photolessCandidate(string $name, array $overrides = []): Politician
{
    return Politician::create($overrides + [
        'uuid' => Str::uuid(),
        'full_name' => $name,
        'state' => 'CA',
        'political_office' => 'Governor',
        'governance_level' => 'State',
        'is_running_candidate' => true,
        'term_status' => 'candidate',
        'is_active' => true,
        'page_published' => true,
        'verified_official' => false,
        'user_id' => null,
        'slug' => Str::slug($name).'-'.Str::random(4),
    ]);
}

function fakeNoWikipediaPage(array $extra = []): void
{
    Http::fake($extra + [
        'en.wikipedia.org/*' => Http::response(['query' => ['pages' => [], 'search' => []]]),
    ]);
}

test('falls back to the candidate website social image when Wikipedia has no photo', function () {
    $pol = photolessCandidate('Jane Doe', ['website_url' => 'https://janedoe.example']);

    fakeNoWikipediaPage([
        'janedoe.example/photos/jane-portrait.jpg' => Http::response('', 200, ['Content-Type' => 'image/jpeg']),
        'janedoe.example' => Http::response('<html><head><meta property="og:image" content="/photos/jane-portrait.jpg"></head></html>'),
    ]);

    Artisan::call('politicians:backfill-photos');

    expect($pol->fresh()->profile_photo_url)->toBe('https://janedoe.example/photos/jane-portrait.jpg')
        ->and(Artisan::output())->toContain('candidate website');
});

test('rejects a logo image from the website', function () {
    $pol = photolessCandidate('Jane Doe', ['website_url' => 'https://janedoe.example']);

    fakeNoWikipediaPage([
        'janedoe.example' => Http::response('<meta property="og:image" content="https://janedoe.example/img/site-logo.png">'),
    ]);

    Artisan::call('politicians:backfill-photos');

    expect($pol->fresh()->profile_photo_url)->toBeNull();
});

test('does not fetch a website that points at the internal network', function () {
    $pol = photolessCandidate('Jane Doe', ['website_url' => 'http://169.254.169.254/latest']);

    fakeNoWikipediaPage();

    Artisan::call('politicians:backfill-photos');

    Http::assertNotSent(fn ($request) => str_contains($request->url(), '169.254.169.254'));
    expect($pol->fresh()->profile_photo_url)->toBeNull();
});

test('--skip-website leaves the website alone', function () {
    photolessCandidate('Jane Doe', ['website_url' => 'https://janedoe.example']);

    fakeNoWikipediaPage();

    Artisan::call('politicians:backfill-photos', ['--skip-website' => true]);

    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'janedoe.example'));
});

test('skips junk-named and inactive profiles without spending requests on them', function () {
    // Rows like these predate the model's name guard, so insert them past it.
    foreach (['Election results', 'Do I run for office?', 'SeGqNJTiYizIbfRrOcCvX XiuSHcgtCtQTePQQ'] as $junk) {
        $row = photolessCandidate('Placeholder Person');
        DB::table('politicians')->where('id', $row->id)->update(['full_name' => $junk]);
    }
    photolessCandidate('Real Person', ['is_active' => false]);

    fakeNoWikipediaPage();

    Artisan::call('politicians:backfill-photos');

    Http::assertNothingSent();
    expect(Artisan::output())->toContain('3 junk-named');
});
