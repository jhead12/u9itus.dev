<?php

use App\Models\Politician;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function seedMayor(string $updatedAgo): Politician
{
    return Politician::create([
        'uuid' => Str::uuid(),
        'full_name' => 'Karen Bass',
        'state' => 'CA',
        'city' => 'Los Angeles',
        'political_office' => 'Mayor',
        'governance_level' => 'City',
        'party_affiliation' => 'Democratic',
        'is_running_candidate' => false,
        'term_status' => 'seated',
        'is_active' => true,
        'page_published' => true,
        'verified_official' => false,
        'user_id' => null,
        'slug' => 'karen-bass',
        'status_updated_at' => now()->sub($updatedAgo),
    ]);
}

test('skips a fresh officeholder without making any network calls', function () {
    seedMayor('1 hour');
    Http::fake();

    Artisan::call('politicians:enrich-statewide', ['--scope' => 'mayors', '--state' => 'CA']);

    expect(Artisan::output())->toContain('fresh')->toContain('skipped');
    Http::assertNothingSent();
});

test('re-enriches a stale officeholder', function () {
    seedMayor('25 hours');
    Http::fake();

    Artisan::call('politicians:enrich-statewide', ['--scope' => 'mayors', '--state' => 'CA']);

    Http::assertSent(fn () => true);
});

test('--force bypasses the freshness check', function () {
    seedMayor('1 hour');
    Http::fake();

    Artisan::call('politicians:enrich-statewide', ['--scope' => 'mayors', '--state' => 'CA', '--force' => true]);

    Http::assertSent(fn () => true);
});

test('--stale-hours=0 disables the freshness check', function () {
    seedMayor('1 hour');
    Http::fake();

    Artisan::call('politicians:enrich-statewide', ['--scope' => 'mayors', '--state' => 'CA', '--stale-hours' => 0]);

    Http::assertSent(fn () => true);
});
