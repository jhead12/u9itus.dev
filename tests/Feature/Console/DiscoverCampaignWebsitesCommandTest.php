<?php

use App\Models\Politician;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('discovers and saves a campaign website for a running candidate missing one', function () {
    Http::fake([
        'ballotpedia.org/*' => Http::response(
            '<html><a href="https://janedoe2026.com" class="Website websiteedit">Website</a></html>',
            200
        ),
    ]);

    $politician = Politician::factory()->create([
        'full_name' => 'Jane Doe',
        'is_running_candidate' => true,
        'website_url' => null,
    ]);

    Artisan::call('politicians:discover-websites');

    $politician->refresh();
    expect($politician->website_url)->toBe('https://janedoe2026.com');
});

test('does not overwrite a politician who already has a website_url', function () {
    Http::fake([
        'ballotpedia.org/*' => Http::response(
            '<html><a href="https://someoneelse.com" class="Website websiteedit">Website</a></html>',
            200
        ),
    ]);

    $politician = Politician::factory()->create([
        'full_name' => 'Already Has Site',
        'is_running_candidate' => true,
        'website_url' => 'https://existing-site.com',
    ]);

    Artisan::call('politicians:discover-websites');

    $politician->refresh();
    expect($politician->website_url)->toBe('https://existing-site.com');
});

test('--dry-run does not write the discovered website', function () {
    Http::fake([
        'ballotpedia.org/*' => Http::response(
            '<html><a href="https://janedoe2026.com" class="Website websiteedit">Website</a></html>',
            200
        ),
    ]);

    $politician = Politician::factory()->create([
        'full_name' => 'Jane Dry',
        'is_running_candidate' => true,
        'website_url' => null,
    ]);

    Artisan::call('politicians:discover-websites', ['--dry-run' => true]);

    $politician->refresh();
    expect($politician->website_url)->toBeNull();
});

test('leaves website_url null when no external campaign link is found', function () {
    Http::fake([
        'ballotpedia.org/*' => Http::response('<html><p>No campaign links here.</p></html>', 200),
    ]);

    $politician = Politician::factory()->create([
        'full_name' => 'No Site Found',
        'is_running_candidate' => true,
        'website_url' => null,
    ]);

    Artisan::call('politicians:discover-websites');

    $politician->refresh();
    expect($politician->website_url)->toBeNull();
});

test('a non-running candidate is not targeted', function () {
    Http::fake([
        'ballotpedia.org/*' => Http::response(
            '<html><a href="https://janedoe2026.com" class="Website websiteedit">Website</a></html>',
            200
        ),
    ]);

    $politician = Politician::factory()->create([
        'full_name' => 'Not Running',
        'is_running_candidate' => false,
        'website_url' => null,
    ]);

    Artisan::call('politicians:discover-websites');

    $politician->refresh();
    expect($politician->website_url)->toBeNull();
});
