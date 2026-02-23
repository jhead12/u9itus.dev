<?php

use App\Enums\ViewPaymentStatus;
use App\Enums\ViewSessionStatus;
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
    config(['u9itus.fraud.device_fingerprint_required' => false]);

    $voter   = freshVoter();
    $request = makeRequest();

    $result = app(FraudPreventionService::class)->evaluate($voter, $request);

    expect($result['allowed'])->toBeTrue()
        ->and($result['score'])->toBe(0)
        ->and($result['flags'])->toBeEmpty();
});

// ── evaluate(): previously flagged voter ─────────────────────────────────────

test('previously flagged voter is blocked', function () {
    config(['u9itus.fraud.device_fingerprint_required' => false]);

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
        'u9itus.fraud.max_views_per_voter_per_day'    => 0,   // threshold of 0 → always exceeded
    ]);

    $voter   = freshVoter();
    $request = makeRequest();

    $result = app(FraudPreventionService::class)->evaluate($voter, $request);

    expect($result['flags'])->toContain('daily_limit_exceeded')
        ->and($result['score'])->toBeGreaterThanOrEqual(50);
});

// ── evaluate(): missing device fingerprint ────────────────────────────────────

test('missing device fingerprint adds to fraud score', function () {
    config(['u9itus.fraud.device_fingerprint_required' => true]);

    $voter   = freshVoter(['device_fingerprint' => null]);
    $request = makeRequest();  // no fingerprint header

    $result = app(FraudPreventionService::class)->evaluate($voter, $request);

    expect($result['flags'])->toContain('missing_device_fingerprint')
        ->and($result['score'])->toBeGreaterThanOrEqual(20);
});

// ── evaluate(): device fingerprint mismatch ──────────────────────────────────

test('mismatched device fingerprint adds to fraud score', function () {
    config(['u9itus.fraud.device_fingerprint_required' => true]);

    $voter   = freshVoter(['device_fingerprint' => 'known-fp-abc']);
    $request = makeRequest(['fingerprint' => 'different-fp-xyz']);

    $result = app(FraudPreventionService::class)->evaluate($voter, $request);

    expect($result['flags'])->toContain('device_fingerprint_mismatch')
        ->and($result['score'])->toBeGreaterThanOrEqual(30);
});

// ── evaluate(): score is capped at 100 ───────────────────────────────────────

test('fraud score is capped at 100', function () {
    config([
        'u9itus.fraud.device_fingerprint_required'    => true,
        'u9itus.fraud.max_views_per_voter_per_day'    => 0,
        'u9itus.fraud.suspicious_activity_threshold'  => 0,
    ]);

    $voter   = freshVoter(['flagged_for_fraud' => true, 'device_fingerprint' => null]);
    $request = makeRequest();

    $result = app(FraudPreventionService::class)->evaluate($voter, $request);

    expect($result['score'])->toBeLessThanOrEqual(100);
});

// ── flagVoter() ───────────────────────────────────────────────────────────────

test('flagVoter marks voter as flagged and reduces trust score', function () {
    $voter = freshVoter(['trust_score' => 100, 'flagged_for_fraud' => false]);

    app(FraudPreventionService::class)->flagVoter($voter, ['test_reason']);

    $voter->refresh();

    expect($voter->flagged_for_fraud)->toBeTrue()
        ->and($voter->trust_score)->toBeLessThan(100);
});

// ── holdPayouts() ─────────────────────────────────────────────────────────────

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

    // Rejected session remains rejected
    expect(
        ViewSession::where('voter_id', $voter->id)
            ->where('payment_status', ViewPaymentStatus::Rejected->value)
            ->count()
    )->toBe(1);
});
