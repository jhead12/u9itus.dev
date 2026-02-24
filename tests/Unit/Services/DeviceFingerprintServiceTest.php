<?php

use App\Models\Voter;
use App\Services\DeviceFingerprintService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

uses(RefreshDatabase::class);

// ── Helpers ───────────────────────────────────────────────────────────────────

function makeFpRequest(array $headers = []): Request
{
    $request = Request::create('/test', 'GET');
    foreach ($headers as $key => $value) {
        $request->headers->set($key, $value);
    }
    return $request;
}

function fpVoter(array $attrs = []): Voter
{
    return Voter::factory()->create(array_merge(['device_fingerprint' => null], $attrs));
}

// ── generate() ───────────────────────────────────────────────────────────────

test('generate returns a 64-char hex string', function () {
    $fp  = app(DeviceFingerprintService::class)->generate(makeFpRequest());
    expect($fp)->toHaveLength(64)->toMatch('/^[a-f0-9]+$/');
});

test('same request headers produce the same fingerprint', function () {
    $svc     = app(DeviceFingerprintService::class);
    $headers = [
        'User-Agent'      => 'Mozilla/5.0 (Windows NT 10.0) Chrome/120',
        'Accept-Language' => 'en-US,en;q=0.9',
    ];

    $fp1 = $svc->generate(makeFpRequest($headers));
    $fp2 = $svc->generate(makeFpRequest($headers));

    expect($fp1)->toBe($fp2);
});

test('different X-Device-Fingerprint headers produce different fingerprints', function () {
    $svc = app(DeviceFingerprintService::class);
    $fp1 = $svc->generate(makeFpRequest(['X-Device-Fingerprint' => 'canvas-hash-aaa']));
    $fp2 = $svc->generate(makeFpRequest(['X-Device-Fingerprint' => 'canvas-hash-bbb']));

    expect($fp1)->not->toBe($fp2);
});

// ── compare() ────────────────────────────────────────────────────────────────

test('compare returns new when voter has no stored fingerprint', function () {
    $voter  = fpVoter();
    $result = app(DeviceFingerprintService::class)->compare('any-fp', $voter);

    expect($result)->toBe('new');
});

test('compare returns match when fingerprints are identical', function () {
    $fp    = hash('sha256', 'stable-fingerprint');
    $voter = fpVoter(['device_fingerprint' => $fp]);

    $result = app(DeviceFingerprintService::class)->compare($fp, $voter);

    expect($result)->toBe('match');
});

test('compare returns mismatch when fingerprints differ', function () {
    $voter  = fpVoter(['device_fingerprint' => hash('sha256', 'original')]);
    $result = app(DeviceFingerprintService::class)->compare(hash('sha256', 'different'), $voter);

    expect($result)->toBe('mismatch');
});

// ── storeIfNew() ─────────────────────────────────────────────────────────────

test('storeIfNew persists fingerprint when voter has none', function () {
    $voter = fpVoter(['device_fingerprint' => null]);
    $fp    = hash('sha256', 'new-device');

    app(DeviceFingerprintService::class)->storeIfNew($fp, $voter);

    expect($voter->fresh()->device_fingerprint)->toBe($fp);
});

test('storeIfNew does not overwrite an existing fingerprint', function () {
    $original = hash('sha256', 'existing-device');
    $voter    = fpVoter(['device_fingerprint' => $original]);

    app(DeviceFingerprintService::class)->storeIfNew(hash('sha256', 'attacker-fp'), $voter);

    expect($voter->fresh()->device_fingerprint)->toBe($original);
});

// ── analyseUserAgent() ────────────────────────────────────────────────────────

test('real chrome user-agent is not flagged as bot', function () {
    $result = app(DeviceFingerprintService::class)->analyseUserAgent(
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
    );

    expect($result['is_bot'])->toBeFalse();
});

test('empty user-agent is flagged as bot', function () {
    $result = app(DeviceFingerprintService::class)->analyseUserAgent('');
    expect($result['is_bot'])->toBeTrue()
        ->and($result['reason'])->toBe('empty_user_agent');
});

test('headless chrome is flagged as bot', function () {
    $result = app(DeviceFingerprintService::class)->analyseUserAgent(
        'Mozilla/5.0 HeadlessChrome/120.0.0.0'
    );
    expect($result['is_bot'])->toBeTrue();
});

test('python-requests is flagged as bot', function () {
    $result = app(DeviceFingerprintService::class)->analyseUserAgent('python-requests/2.31.0');
    expect($result['is_bot'])->toBeTrue();
});

test('curl is flagged as bot', function () {
    $result = app(DeviceFingerprintService::class)->analyseUserAgent('curl/7.88.1');
    expect($result['is_bot'])->toBeTrue();
});

test('UA with no browser marker is flagged as bot', function () {
    $result = app(DeviceFingerprintService::class)->analyseUserAgent('custom-sdk/1.0');
    expect($result['is_bot'])->toBeTrue()
        ->and($result['reason'])->toBe('no_browser_marker');
});
