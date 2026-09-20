<?php

use App\Models\Committee;
use App\Services\FECService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    Http::preventStrayRequests();
    $fec = Mockery::mock(FECService::class);
    $fec->shouldReceive('isConfigured')->andReturn(false);
    app()->instance(FECService::class, $fec);
});

test('discovers the verified EDF site from a PAC URL without replacing financial data', function () {
    $committee = Committee::create(['fec_committee_id' => 'C00707844', 'name' => 'EDF ACTION VOTES']);
    $committee->profile()->create(['fec_committee_id' => 'C00707844', 'total_receipts' => 123, 'enriched_at' => now()]);
    $this->artisan('committees:discover-websites', ['--committee' => 'https://www.u9itus.com/pacs/edf-action-votes-C00707844'])
        ->assertSuccessful();
    expect($committee->fresh()->website_url)->toBe('https://www.edfactionvotes.org/')
        ->and($committee->fresh()->website_source_url)->toBe('https://www.edfactionvotes.org/')
        ->and($committee->profile->total_receipts)->toBe('123.00');
    Http::assertNothingSent();
});

test('dry run writes nothing and existing discoveries are skipped', function () {
    $committee = Committee::create(['fec_committee_id' => 'C00707844']);
    $this->artisan('committees:discover-websites', ['--dry-run' => true])->assertSuccessful();
    expect($committee->fresh()->website_url)->toBeNull()
        ->and($committee->fresh()->website_discovered_at)->toBeNull();
    $committee->update(['website_url' => 'https://existing.org/']);
    $this->artisan('committees:discover-websites')->assertSuccessful();
    expect($committee->fresh()->website_url)->toBe('https://existing.org/');
    $this->artisan('committees:discover-websites', ['--force' => true])->assertSuccessful();
    expect($committee->fresh()->website_url)->toBe('https://www.edfactionvotes.org/');
});

test('uses filed website without external requests', function () {
    $committee = Committee::create(['fec_committee_id' => 'C00000001']);
    $committee->profile()->create(['fec_committee_id' => 'C00000001', 'fec_website_url' => 'example.org']);
    $this->artisan('committees:discover-websites')->assertSuccessful();
    expect($committee->fresh()->website_url)->toBe('https://example.org');
    Http::assertNothingSent();
});

test('only accepts explicitly labelled official links from reference pages', function () {
    $committee = Committee::create(['fec_committee_id' => 'C00000001', 'name' => 'Example PAC']);
    Http::fake(['ballotpedia.org/*' => Http::response('<a href="https://news.example.org">News article</a><a href="https://committee.example.org">Official website</a>')]);
    $this->artisan('committees:discover-websites')->assertSuccessful();
    expect($committee->fresh()->website_url)->toBe('https://committee.example.org')
        ->and($committee->fresh()->website_source_url)->toBe('https://ballotpedia.org/Example_PAC');
});

test('rejects unsafe and unrelated reference links', function (string $url) {
    $committee = Committee::create(['fec_committee_id' => 'C00000001', 'name' => 'Example PAC']);
    Http::fake(['ballotpedia.org/*' => Http::response('<a href="'.$url.'">Official website</a>')]);
    $this->artisan('committees:discover-websites')->assertSuccessful();
    expect($committee->fresh()->website_url)->toBeNull();
})->with(['javascript:alert(1)', 'https://facebook.com/example', 'http://127.0.0.1', 'https://user:password@example.org']);

test('reports lookup failures and continues with other committees', function () {
    Committee::create(['fec_committee_id' => 'C00000001', 'name' => 'Example PAC']);
    $edf = Committee::create(['fec_committee_id' => 'C00707844']);
    Http::fake(['ballotpedia.org/*' => Http::response('', 500)]);
    $this->artisan('committees:discover-websites')->assertFailed();
    expect($edf->fresh()->website_url)->toBe('https://www.edfactionvotes.org/');
});

test('validates input and unknown committee IDs', function () {
    $this->artisan('committees:discover-websites', ['--limit' => 0])->assertFailed();
    $this->artisan('committees:discover-websites', ['--committee' => 'invalid'])->assertFailed();
    $this->artisan('committees:discover-websites', ['--committee' => 'C99999999'])->assertFailed();
    Http::assertNothingSent();
});
