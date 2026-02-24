<?php

use App\Enums\ViewPaymentStatus;
use App\Enums\ViewSessionStatus;
use App\Models\FraudSignal;
use App\Models\ViewSession;
use App\Models\Voter;
use App\Services\FraudPreventionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

uses(RefreshDatabase::class);

// ── Helpers ──────────────────────────────────────────────────────────────────

function makeRequest(array $overrides = []): Request
{
    $request = Request::create('/test', 'GET', [], [], [], array_merge(
        ['REMOTE_ADDR' => '127.0.0.1'],
        $overrides['server'] ?? [],
    ));

    if (!empty($overrides['fingerprint'])) {
        $request->headers->set('X-Device-Fingerprint', $overrides['fingerprint']);
    }

    if (!empty($overrides['user_agent'])) {
        $request->headers->set('User-Agent', $overrides['user_agent']);
        $request->server->set('HTTP_USER_AGENT', $overrides['user_agent']);
    }

    return $request;
}

function freshVoter(array $attrs = []): Voter
{
    return Voter::factory()->create(array_merge([
        'is_verified'        => true,
        'is_active'          => true,
        'flagged_for_fraud'  => false,
        'trust_score'        => 100,
        'device_fingerprint' => null,
    ], $attrs));
}

// ── evaluate(): clean voter ───────────────────────────────────────────────────

test('clean voter passes fraud check', function () {
    config([
        'u9itus.fraud.device_fingerprint_required' => false,
        'u9itus.fraud.ip_reputation_enabled'       => false,
    ]);

    $voter   = freshVoter();
    $request = makeRequest([
        'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0.0.0 Safari/537.36',
    ]);

    $result = app(FraudPreventionService::class)->evaluate($voter, $request);

    expect($result['allowed'])->toBeTrue()
        ->and($result['score'])->toBe(0)
        ->and($result['flags'])->toBeEmpty();
});

// ── evaluate(): previously flagged voter ─────────────────────────────────────

test('previously flagged voter is blocked', function () {
    config([
        'u9itus.fraud.device_fingerprint_required' => false,
        'u9itus.fraud.ip_reputation_enabled'       => false,
    ]);

    $voter   = freshVoter(['flagged_for_fraud' => true]);
    $request = makeRequest();

    $result = app(FraudPreventionService::class)->evaluate($voter, $request);

    expect($result['allowed'])->toBeFalse()
        ->and($result['flags'])->toContain('previously_flagged')
        ->and($result['score'])->toBeGreaterThanOrEqual(60);
});

// ── evaluate(): daily view limit exceeded ────────────────────────────────────

test('voter exceeding daily view limit is blocked', function () {
    config([
        'u9itus.fraud.device_fingerprint_required'    => false,
        'u9itus.fraud.ip_reputation_enabled'          => false,
        'u9itus.fraud.max_views_per_voter_per_day'    => 0,   // threshold of 0 → always exceeded
    ]);

    $voter   = freshVoter();
    $request = makeRequest();

    $result = app(FraudPreventionService::class)->evaluate($voter, $request);

    expect($result['flags'])->toContain('daily_limit_exceeded')
        ->and($result['score'])->toBeGreaterThanOrEqual(50);
});

// ── evaluate(): new device fingerprint is stored without penalty ──────────────

test('new device fingerprint is stored without fraud penalty', function () {
    config([
        'u9itus.fraud.device_fingerprint_required' => true,
        'u9itus.fraud.ip_reputation_enabled'       => false,
    ]);

    $voter   = freshVoter(['device_fingerprint' => null]);
    $request = makeRequest([
        'fingerprint' => 'brand-new-fp-abc',
        'user_agent'  => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0.0.0 Safari/537.36',
    ]);

    $result = app(FraudPreventionService::class)->evaluate($voter, $request);

    // No penalty for first-time fingerprint
    expect($result['flags'])->not->toContain('missing_device_fingerprint')
        ->and($result['flags'])->not->toContain('device_fingerprint_mismatch');
    // Fingerprint should now be stored
    expect($voter->fresh()->device_fingerprint)->not->toBeNull();
});

// ── evaluate(): device fingerprint mismatch ──────────────────────────────────

test('mismatched device fingerprint adds to fraud score', function () {
    config([
        'u9itus.fraud.device_fingerprint_required' => true,
        'u9itus.fraud.ip_reputation_enabled'       => false,
    ]);

    // Generate a stored fingerprint by hashing a known fingerprint component
    $storedFingerprint = hash('sha256', 'known-fp-abc||unknown|');
    $voter    = freshVoter(['device_fingerprint' => $storedFingerprint]);
    $request  = makeRequest(['fingerprint' => 'completely-different-fp-xyz']);

    $result = app(FraudPreventionService::class)->evaluate($voter, $request);

    expect($result['flags'])->toContain('device_fingerprint_mismatch')
        ->and($result['score'])->toBeGreaterThanOrEqual(30);
});

// ── evaluate(): bot user-agent flagged ───────────────────────────────────────

test('headless browser user-agent triggers bot flag', function () {
    config([
        'u9itus.fraud.device_fingerprint_required' => true,
        'u9itus.fraud.ip_reputation_enabled'       => false,
    ]);

    $voter   = freshVoter(['device_fingerprint' => null]);
    $request = makeRequest(['user_agent' => 'HeadlessChrome/120.0.0.0']);

    $result = app(FraudPreventionService::class)->evaluate($voter, $request);

    expect($result['flags'])->toContain('bot_user_agent')
        ->and($result['score'])->toBeGreaterThanOrEqual(30);
});

test('curl user-agent triggers bot flag', function () {
    config([
        'u9itus.fraud.device_fingerprint_required' => true,
        'u9itus.fraud.ip_reputation_enabled'       => false,
    ]);

    $voter   = freshVoter(['device_fingerprint' => null]);
    $request = makeRequest(['user_agent' => 'curl/7.88.1']);

    $result = app(FraudPreventionService::class)->evaluate($voter, $request);

    expect($result['flags'])->toContain('bot_user_agent');
});

// ── evaluate(): score is capped at 100 ───────────────────────────────────────

test('fraud score is capped at 100', function () {
    config([
        'u9itus.fraud.device_fingerprint_required'    => true,
        'u9itus.fraud.ip_reputation_enabled'          => false,
        'u9itus.fraud.max_views_per_voter_per_day'    => 0,
        'u9itus.fraud.suspicious_activity_threshold'  => 0,
    ]);

    $voter   = freshVoter(['flagged_for_fraud' => true, 'device_fingerprint' => null]);
    $request = makeRequest(['user_agent' => 'HeadlessChrome/120']);

    $result = app(FraudPreventionService::class)->evaluate($voter, $request);

    expect($result['score'])->toBeLessThanOrEqual(100);
});

// ── evaluate(): fraud signals are recorded ───────────────────────────────────

test('fraud signals are persisted to the database', function () {
    config([
        'u9itus.fraud.device_fingerprint_required' => false,
        'u9itus.fraud.ip_reputation_enabled'       => false,
        'u9itus.fraud.max_views_per_voter_per_day' => 0,
    ]);

    $voter   = freshVoter();
    $request = makeRequest();

    app(FraudPreventionService::class)->evaluate($voter, $request);

    expect(FraudSignal::where('voter_id', $voter->id)->where('signal_type', 'daily_limit_exceeded')->count())
        ->toBeGreaterThanOrEqual(1);
});

// ── flagVoter() ───────────────────────────────────────────────────────────────

test('flagVoter marks voter as flagged and reduces trust score', function () {
    $voter = freshVoter(['trust_score' => 100, 'flagged_for_fraud' => false]);

    app(FraudPreventionService::class)->flagVoter($voter, ['test_reason']);

    $voter->refresh();

    expect($voter->flagged_for_fraud)->toBeTrue()
        ->and($voter->trust_score)->toBeLessThan(100);
});

// ── updateTrustScore() ────────────────────────────────────────────────────────

test('updateTrustScore clamps score between 0 and 100', function () {
    $voter = freshVoter(['trust_score' => 95]);

    $svc = app(FraudPreventionService::class);
    $svc->updateTrustScore($voter, 20);  // would be 115 — should clamp to 100
    expect($voter->fresh()->trust_score)->toBe('100.00');

    $svc->updateTrustScore($voter, -200); // clamp to 0
    expect($voter->fresh()->trust_score)->toBe('0.00');
});

// ── holdPayouts() / releasePayouts() ──────────────────────────────────────────

test('holdPayouts changes approved sessions to held status', function () {
    $voter = freshVoter();

    ViewSession::factory()->count(3)->create([
        'voter_id'       => $voter->id,
        'payment_status' => ViewPaymentStatus::Approved->value,
    ]);

    // One session already rejected — should NOT be affected
    ViewSession::factory()->create([
        'voter_id'       => $voter->id,
        'payment_status' => ViewPaymentStatus::Rejected->value,
    ]);

    app(FraudPreventionService::class)->holdPayouts($voter);

    expect(
        ViewSession::where('voter_id', $voter->id)
            ->where('payment_status', ViewPaymentStatus::Held->value)
            ->count()
    )->toBe(3);

    expect(
        ViewSession::where('voter_id', $voter->id)
            ->where('payment_status', ViewPaymentStatus::Rejected->value)
            ->count()
    )->toBe(1);
});

test('releasePayouts changes held sessions back to approved', function () {
    $voter = freshVoter();

    ViewSession::factory()->count(2)->create([
        'voter_id'       => $voter->id,
        'payment_status' => ViewPaymentStatus::Held->value,
    ]);

    app(FraudPreventionService::class)->releasePayouts($voter);

    expect(
        ViewSession::where('voter_id', $voter->id)
            ->where('payment_status', ViewPaymentStatus::Approved->value)
            ->count()
    )->toBe(2);
});

// ── clearFlag() ───────────────────────────────────────────────────────────────

test('clearFlag removes fraud flag and boosts trust score', function () {
    $voter = freshVoter(['flagged_for_fraud' => true, 'trust_score' => 50]);

    app(FraudPreventionService::class)->clearFlag($voter);

    $voter->refresh();
    expect($voter->flagged_for_fraud)->toBeFalse()
        ->and((float) $voter->trust_score)->toBeGreaterThan(50);
});
