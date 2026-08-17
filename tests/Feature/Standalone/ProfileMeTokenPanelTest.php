<?php

use App\Models\Politician;
use App\Models\User;
use App\Services\BallotpediaService;
use App\Services\PlatformSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * Sprint 7 — MeToken panel visibility on /p/{slug}.
 *
 * The panel renders only when ALL of:
 *   - `web3_features_enabled` platform setting is on
 *   - politician is eligible (state Governor)
 *   - politician has wallet_address or metoken_address set
 *   - subgraph returns data
 */

// This file's politicians are unclaimed, so the profile page's "Dig Deeper"
// section always attempts a live Ballotpedia scrape too (BallotpediaService
// now fetches the candidate's real Ballotpedia article instead of an unused
// API — see "Rebuild Ballotpedia Dig Deeper card as a scrape" decision note).
// That's unrelated to what this file tests and would add unaccounted-for
// HTTP calls to the assertSentCount()/assertNothingSent() assertions below,
// so it's stubbed out here to keep this file scoped to MeToken behavior.
beforeEach(function () {
    $ballotpedia = Mockery::mock(BallotpediaService::class);
    $ballotpedia->shouldReceive('getDisplayData')->andReturn(null);
    app()->instance(BallotpediaService::class, $ballotpedia);
});

function governorPolitician(array $overrides = []): Politician
{
    return Politician::factory()->create(array_merge([
        'full_name'        => 'Test Governor',
        'slug'             => 'test-governor',
        'political_office' => 'Governor',
        'governance_level' => 'state',
        'state'            => 'CA',
        'page_published'   => true,
        'is_active'        => true,
    ], $overrides));
}

function subgraphSuccess(): void
{
    Http::fake([
        '*goldsky.com*' => Http::response([
            'data' => [
                'metokens' => [[
                    'id'            => '0xabcdef0123456789abcdef0123456789abcdef01',
                    'name'          => 'Governor Token',
                    'symbol'        => 'GOV',
                    'totalSupply'   => '1234500000000000000000',
                    'balancePooled' => '9876500000000000000000',
                    'balanceLocked' => '0',
                    'holdersCount'  => 42,
                    'lastMintAt'    => '1719792000',
                ]],
            ],
        ], 200),
    ]);
}

// ─────────────────────────────────────────────────────────────────────────────

it('hides the metoken panel by default (kill switch off)', function () {
    Http::fake();

    $politician = governorPolitician([
        'wallet_address' => '0x1111111111111111111111111111111111111111',
    ]);

    $this->get(route('politician.public.show', ['slug' => $politician->slug]))
        ->assertOk()
        ->assertDontSee('On-Chain Loyalty');

    Http::assertNothingSent();
});

it('hides the panel for non-eligible politicians even when the flag is on', function () {
    PlatformSettingsService::set('web3_features_enabled', true, ['category' => 'web3']);
    subgraphSuccess();

    $politician = Politician::factory()->create([
        'full_name'        => 'Ineligible Mayor',
        'slug'             => 'ineligible-mayor',
        'political_office' => 'Mayor',
        'governance_level' => 'Local',
        'wallet_address'   => '0x1111111111111111111111111111111111111111',
        'page_published'   => true,
        'is_active'        => true,
    ]);

    $this->get(route('politician.public.show', ['slug' => $politician->slug]))
        ->assertOk()
        ->assertDontSee('On-Chain Loyalty');

    Http::assertNothingSent();
});

it('hides the panel when eligible politician has no addresses', function () {
    PlatformSettingsService::set('web3_features_enabled', true, ['category' => 'web3']);
    Http::fake();

    $politician = governorPolitician([
        'wallet_address'  => null,
        'metoken_address' => null,
    ]);

    $this->get(route('politician.public.show', ['slug' => $politician->slug]))
        ->assertOk()
        ->assertDontSee('On-Chain Loyalty');

    Http::assertNothingSent();
});

it('renders the panel when flag on + eligible + address + subgraph returns data', function () {
    PlatformSettingsService::set('web3_features_enabled', true, ['category' => 'web3']);
    subgraphSuccess();

    $politician = governorPolitician([
        'wallet_address' => '0x1111111111111111111111111111111111111111',
    ]);

    $this->get(route('politician.public.show', ['slug' => $politician->slug]))
        ->assertOk()
        ->assertSee('On-Chain Loyalty')
        ->assertSee('Governor Token')
        ->assertSee('View on Basescan', false)
        ->assertSee('Base L2');
});

it('gracefully hides the panel when the subgraph returns no data', function () {
    PlatformSettingsService::set('web3_features_enabled', true, ['category' => 'web3']);
    Http::fake([
        '*goldsky.com*' => Http::response(['data' => ['metokens' => []]], 200),
    ]);

    $politician = governorPolitician([
        'wallet_address' => '0x1111111111111111111111111111111111111111',
    ]);

    $this->get(route('politician.public.show', ['slug' => $politician->slug]))
        ->assertOk()
        ->assertDontSee('On-Chain Loyalty');
});

it('gracefully hides the panel when the subgraph returns a 500', function () {
    PlatformSettingsService::set('web3_features_enabled', true, ['category' => 'web3']);
    Http::fake(['*goldsky.com*' => Http::response('boom', 500)]);

    $politician = governorPolitician([
        'wallet_address' => '0x1111111111111111111111111111111111111111',
    ]);

    $this->get(route('politician.public.show', ['slug' => $politician->slug]))
        ->assertOk()
        ->assertDontSee('On-Chain Loyalty');
});

it('ignores ?refresh=1 for non-admin viewers (cache is not busted)', function () {
    PlatformSettingsService::set('web3_features_enabled', true, ['category' => 'web3']);
    subgraphSuccess();

    $politician = governorPolitician([
        'wallet_address' => '0x1111111111111111111111111111111111111111',
    ]);

    // First call warms the cache.
    $this->get(route('politician.public.show', ['slug' => $politician->slug]))->assertOk();
    // Second call with ?refresh=1 by a guest must NOT bust.
    $this->get(route('politician.public.show', ['slug' => $politician->slug, 'refresh' => 1]))->assertOk();

    Http::assertSentCount(1);
});

it('honors ?refresh=1 for admin viewers (cache is busted)', function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $admin = User::factory()->create(['user_type' => 'admin']);
    $admin->assignRole('admin');

    PlatformSettingsService::set('web3_features_enabled', true, ['category' => 'web3']);
    subgraphSuccess();

    $politician = governorPolitician([
        'wallet_address' => '0x1111111111111111111111111111111111111111',
    ]);

    $this->actingAs($admin)
        ->get(route('politician.public.show', ['slug' => $politician->slug]))
        ->assertOk();

    $this->actingAs($admin)
        ->get(route('politician.public.show', ['slug' => $politician->slug, 'refresh' => 1]))
        ->assertOk();

    // Admin refresh busted the cache — two HTTP calls.
    Http::assertSentCount(2);
});
