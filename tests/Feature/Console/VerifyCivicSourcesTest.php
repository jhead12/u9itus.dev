<?php

use App\Models\ElectionDataSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function civicVerifyRow(array $overrides = []): ElectionDataSource
{
    return ElectionDataSource::create(array_merge([
        'ocd_id' => 'ocd-division/country:us/state:ca/county:alameda',
        'level' => 'county',
        'state' => 'CA',
        'jurisdiction_name' => 'Alameda County',
        'source_of_record' => 'google_civic',
        'elections_home_url' => 'https://acvote.example/elections',
    ], $overrides));
}

it('marks a live URL ok and records robots as allowed', function () {
    civicVerifyRow();

    Http::fake([
        'acvote.example/robots.txt' => Http::response("User-agent: *\nDisallow: /admin\n", 200),
        'acvote.example/*' => Http::response('', 200),
    ]);

    $this->artisan('civic:verify-sources', ['--state' => 'CA', '--stale-days' => 0])
        ->expectsOutputToContain('ok=1')
        ->assertExitCode(0);

    $row = ElectionDataSource::first();
    expect($row->scrape_status)->toBe('ok')
        ->and($row->robots_ok)->toBeTrue()
        ->and($row->last_verified_at)->not->toBeNull();
});

it('marks a 404 dead and a 403 blocked', function () {
    civicVerifyRow(['ocd_id' => 'ocd-division/country:us/state:ca/county:a', 'elections_home_url' => 'https://gone.example/x']);
    civicVerifyRow(['ocd_id' => 'ocd-division/country:us/state:ca/county:b', 'jurisdiction_name' => 'B County', 'elections_home_url' => 'https://wall.example/x']);

    Http::fake([
        '*/robots.txt' => Http::response('', 404),
        'gone.example/*' => Http::response('', 404),
        'wall.example/*' => Http::response('', 403),
    ]);

    $this->artisan('civic:verify-sources', ['--state' => 'CA', '--stale-days' => 0])->assertExitCode(0);

    expect(ElectionDataSource::where('elections_home_url', 'https://gone.example/x')->value('scrape_status'))->toBe('dead')
        ->and(ElectionDataSource::where('elections_home_url', 'https://wall.example/x')->value('scrape_status'))->toBe('blocked');
});

it('detects a redirect, and --rewrite-redirects persists the final URL', function () {
    civicVerifyRow(['elections_home_url' => 'https://old.example/vote']);

    Http::fake([
        '*/robots.txt' => Http::response('', 404),
        'old.example/*' => Http::response('', 200, ['X-Guzzle-Redirect-History' => 'https://voteinfo.net/alameda']),
        'voteinfo.net/*' => Http::response('', 200),
    ]);

    // Without the flag: status flips, URL stays.
    $this->artisan('civic:verify-sources', ['--state' => 'CA', '--stale-days' => 0])->assertExitCode(0);
    $row = ElectionDataSource::first();
    expect($row->scrape_status)->toBe('redirected')
        ->and($row->elections_home_url)->toBe('https://old.example/vote')
        ->and($row->vendor)->toBe('voteinfo_net'); // re-classified from the redirect target

    // With the flag: URL is rewritten to the resolved target.
    $this->artisan('civic:verify-sources', ['--state' => 'CA', '--stale-days' => 0, '--rewrite-redirects' => true])->assertExitCode(0);
    expect(ElectionDataSource::first()->elections_home_url)->toBe('https://voteinfo.net/alameda');
});

it('sets robots_ok false when robots.txt disallows our agent', function () {
    civicVerifyRow(['elections_home_url' => 'https://strict.example/ballot']);

    Http::fake([
        'strict.example/robots.txt' => Http::response("User-agent: *\nDisallow: /\n", 200),
        'strict.example/*' => Http::response('', 200),
    ]);

    $this->artisan('civic:verify-sources', ['--state' => 'CA', '--stale-days' => 0])
        ->expectsOutputToContain('1 with a robots.txt disallow')
        ->assertExitCode(0);

    expect(ElectionDataSource::first()->robots_ok)->toBeFalse();
});

it('treats a trailing-slash-only change as ok, not redirected', function () {
    civicVerifyRow(['elections_home_url' => 'https://acvote.example/elections']);

    Http::fake([
        '*/robots.txt' => Http::response('', 404),
        'acvote.example/*' => Http::response('', 200, ['X-Guzzle-Redirect-History' => 'https://acvote.example/elections/']),
    ]);

    $this->artisan('civic:verify-sources', ['--state' => 'CA', '--stale-days' => 0])->assertExitCode(0);

    expect(ElectionDataSource::first()->scrape_status)->toBe('ok');
});

it('skips rows with no URLs and honours --dry-run', function () {
    civicVerifyRow(['ocd_id' => 'ocd-division/country:us/state:ca', 'level' => 'state', 'jurisdiction_name' => 'California', 'elections_home_url' => null]);
    civicVerifyRow(); // has a URL

    Http::fake([
        '*/robots.txt' => Http::response('', 404),
        '*' => Http::response('', 200),
    ]);

    $this->artisan('civic:verify-sources', ['--state' => 'CA', '--stale-days' => 0, '--dry-run' => true])
        ->expectsOutputToContain('Verifying 1 row(s)')
        ->assertExitCode(0);

    expect(ElectionDataSource::whereNotNull('scrape_status')->where('scrape_status', '!=', 'unverified')->count())->toBe(0);
});
