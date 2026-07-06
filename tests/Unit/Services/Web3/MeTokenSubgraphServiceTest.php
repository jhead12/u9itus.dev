<?php

use App\Models\Politician;
use App\Services\PlatformSettingsService;
use App\Services\Web3\MeTokenSubgraphService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/**
 * Sprint 7 — MeTokenSubgraphService unit tests.
 *
 * All HTTP is faked with Http::fake(). Nothing hits the live Goldsky endpoint.
 */

function enableWeb3(): void
{
    PlatformSettingsService::set('web3_features_enabled', true, ['category' => 'web3']);
}

function fakeOwnerHit(): void
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

it('short-circuits and returns null when kill-switch is off', function () {
    Http::fake();

    $result = (new MeTokenSubgraphService())
        ->getMeTokenByOwner('0x1111111111111111111111111111111111111111');

    expect($result)->toBeNull();
    Http::assertNothingSent();
});

it('returns null for malformed wallet address without hitting the network', function () {
    enableWeb3();
    Http::fake();

    $svc = new MeTokenSubgraphService();
    expect($svc->getMeTokenByOwner('not-an-address'))->toBeNull();
    expect($svc->getMeTokenByOwner(''))->toBeNull();
    expect($svc->getMeTokenByOwner('0x123'))->toBeNull();
    Http::assertNothingSent();
});

it('returns a normalized array on a successful owner query', function () {
    enableWeb3();
    fakeOwnerHit();

    $result = (new MeTokenSubgraphService())
        ->getMeTokenByOwner('0x1111111111111111111111111111111111111111');

    expect($result)->toBeArray();
    expect($result['address'])->toBe('0xabcdef0123456789abcdef0123456789abcdef01');
    expect($result['name'])->toBe('Governor Token');
    expect($result['symbol'])->toBe('GOV');
    expect($result['total_supply'])->toBe(1234.5);
    expect($result['collateral_pooled_dai'])->toBe(9876.5);
    expect($result['holders_count'])->toBe(42);
    expect($result['last_mint_at'])->not->toBeNull();
    expect($result['basescan_url'])->toStartWith('https://basescan.org/token/0xabcdef');
    expect($result['fetched_at'])->not->toBeNull();
});

it('caches subsequent calls for the same owner', function () {
    enableWeb3();
    fakeOwnerHit();

    $svc = new MeTokenSubgraphService();
    $svc->getMeTokenByOwner('0x1111111111111111111111111111111111111111');
    $svc->getMeTokenByOwner('0x1111111111111111111111111111111111111111');
    $svc->getMeTokenByOwner('0x1111111111111111111111111111111111111111');

    Http::assertSentCount(1);
});

it('busts the cache when forceRefresh=true', function () {
    enableWeb3();
    fakeOwnerHit();

    $svc = new MeTokenSubgraphService();
    $svc->getMeTokenByOwner('0x1111111111111111111111111111111111111111');
    $svc->getMeTokenByOwner('0x1111111111111111111111111111111111111111', forceRefresh: true);

    Http::assertSentCount(2);
});

it('returns null on a 429 rate-limit response', function () {
    enableWeb3();
    Http::fake(['*goldsky.com*' => Http::response('', 429)]);

    expect((new MeTokenSubgraphService())
        ->getMeTokenByOwner('0x1111111111111111111111111111111111111111'))
        ->toBeNull();
});

it('returns null on 500 server errors', function () {
    enableWeb3();
    Http::fake(['*goldsky.com*' => Http::response('boom', 500)]);

    expect((new MeTokenSubgraphService())
        ->getMeTokenByOwner('0x1111111111111111111111111111111111111111'))
        ->toBeNull();
});

it('returns null on malformed JSON / missing data envelope', function () {
    enableWeb3();
    Http::fake(['*goldsky.com*' => Http::response(['unexpected' => 'shape'], 200)]);

    expect((new MeTokenSubgraphService())
        ->getMeTokenByOwner('0x1111111111111111111111111111111111111111'))
        ->toBeNull();
});

it('returns null when subgraph reports GraphQL errors', function () {
    enableWeb3();
    Http::fake([
        '*goldsky.com*' => Http::response([
            'errors' => [['message' => 'schema drift']],
            'data'   => null,
        ], 200),
    ]);

    expect((new MeTokenSubgraphService())
        ->getMeTokenByOwner('0x1111111111111111111111111111111111111111'))
        ->toBeNull();
});

it('returns null when the owner has no meToken', function () {
    enableWeb3();
    Http::fake(['*goldsky.com*' => Http::response(['data' => ['metokens' => []]], 200)]);

    expect((new MeTokenSubgraphService())
        ->getMeTokenByOwner('0x1111111111111111111111111111111111111111'))
        ->toBeNull();
});

it('prefers metoken_address direct lookup for politicians', function () {
    enableWeb3();
    Http::fake([
        '*goldsky.com*' => Http::response([
            'data' => [
                'metoken' => [
                    'id'            => '0xdeadbeefdeadbeefdeadbeefdeadbeefdeadbeef',
                    'name'          => 'Direct Lookup',
                    'symbol'        => 'DL',
                    'totalSupply'   => '1000000000000000000',
                    'balancePooled' => '0',
                    'balanceLocked' => '0',
                    'holdersCount'  => 1,
                    'lastMintAt'    => null,
                ],
            ],
        ], 200),
    ]);

    $politician = Politician::factory()->create([
        'wallet_address'  => '0x1111111111111111111111111111111111111111',
        'metoken_address' => '0xdeadbeefdeadbeefdeadbeefdeadbeefdeadbeef',
    ]);

    $result = (new MeTokenSubgraphService())->fetchForPolitician($politician);

    expect($result)->toBeArray();
    expect($result['name'])->toBe('Direct Lookup');
    expect($result['address'])->toBe('0xdeadbeefdeadbeefdeadbeefdeadbeefdeadbeef');
    Http::assertSentCount(1);
});

it('falls back to wallet lookup when metoken_address is missing', function () {
    enableWeb3();
    fakeOwnerHit();

    $politician = Politician::factory()->create([
        'wallet_address'  => '0x1111111111111111111111111111111111111111',
        'metoken_address' => null,
    ]);

    $result = (new MeTokenSubgraphService())->fetchForPolitician($politician);

    expect($result)->toBeArray();
    expect($result['name'])->toBe('Governor Token');
});

it('returns null when politician has no addresses', function () {
    enableWeb3();
    Http::fake();

    $politician = Politician::factory()->create([
        'wallet_address'  => null,
        'metoken_address' => null,
    ]);

    expect((new MeTokenSubgraphService())->fetchForPolitician($politician))->toBeNull();
    Http::assertNothingSent();
});
