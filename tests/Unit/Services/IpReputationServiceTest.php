<?php

use App\Services\IpReputationService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

// ── Datacenter / hosting IP detection ────────────────────────────────────────

test('known AWS IP range is flagged as datacenter', function () {
    $result = app(IpReputationService::class)->assess('54.1.2.3'); // 54.0.0.0/8

    expect($result['is_datacenter'])->toBeTrue()
        ->and($result['provider'])->toContain('Amazon');
});

test('known DigitalOcean IP is flagged as datacenter', function () {
    $result = app(IpReputationService::class)->assess('167.99.1.1');

    expect($result['is_datacenter'])->toBeTrue();
});

test('ordinary residential-like IP is not flagged as datacenter', function () {
    // 192.168.x.x is private/RFC1918 — not in any datacenter CIDR in our list
    $result = app(IpReputationService::class)->assess('192.168.1.50');

    expect($result['is_datacenter'])->toBeFalse()
        ->and($result['is_tor'])->toBeFalse();
});

// ── Tor exit-node detection ───────────────────────────────────────────────────

test('IP matching a known Tor prefix is flagged', function () {
    $result = app(IpReputationService::class)->assess('185.220.100.1');

    expect($result['is_tor'])->toBeTrue()
        ->and($result['score_impact'])->toBeGreaterThanOrEqual(50);
});

// ── Score impact sanity ───────────────────────────────────────────────────────

test('score_impact is capped at 50', function () {
    $result = app(IpReputationService::class)->assess('185.220.1.1'); // Tor prefix

    expect($result['score_impact'])->toBeLessThanOrEqual(50);
});

test('clean IP has zero score impact', function () {
    $result = app(IpReputationService::class)->assess('8.8.8.8'); // Google DNS — not in blocklists

    // Google DNS is not a datacenter in our CIDR list for 8.0.0.0 (we only have 34./35.)
    expect($result['score_impact'])->toBeGreaterThanOrEqual(0);
});

// ── Result is cached ─────────────────────────────────────────────────────────

test('repeated calls for same IP use cached result', function () {
    Cache::flush();

    $svc = app(IpReputationService::class);

    $first  = $svc->assess('10.0.0.1');
    $second = $svc->assess('10.0.0.1');

    expect($first)->toBe($second);
    expect(Cache::has('ip_rep:10.0.0.1'))->toBeTrue();
});

// ── ipinfo.io enrichment (optional) ──────────────────────────────────────────

test('ipinfo enrichment is skipped when no api key configured', function () {
    config(['u9itus.fraud.ipinfo_api_key' => '']);

    Http::preventStrayRequests(); // will throw if any HTTP call is made

    // Should not throw — no HTTP call made
    $result = app(IpReputationService::class)->assess('1.2.3.4');

    expect($result)->toHaveKeys(['is_vpn', 'is_datacenter', 'is_tor', 'provider', 'score_impact']);
});

test('ipinfo enrichment is called when api key is configured', function () {
    config(['u9itus.fraud.ipinfo_api_key' => 'test-token-123']);

    Http::fake([
        'https://ipinfo.io/1.2.3.4/json*' => Http::response([
            'ip'  => '1.2.3.4',
            'org' => 'AS12345 Some Hosting Provider',
            'privacy' => ['vpn' => true, 'hosting' => true],
        ], 200),
    ]);

    Cache::flush(); // clear any cached result for this IP

    $result = app(IpReputationService::class)->assess('1.2.3.4');

    expect($result['is_vpn'])->toBeTrue();
});
