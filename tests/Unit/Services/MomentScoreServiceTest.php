<?php

use App\Services\MomentScoreService;
use Illuminate\Support\Carbon;

// ── Basic scoring ────────────────────────────────────────────────────────────

test('a viral recent clip scores higher than a stale high-view clip', function () {
    $svc = app(MomentScoreService::class);
    $asOf = Carbon::parse('2026-07-21T12:00:00Z');

    // 1M views, 2 days old, YouTube (authority 0.60)
    $recent = $svc->score(1_000_000, Carbon::parse('2026-07-19T12:00:00Z'), 0.60, 1.0, $asOf);
    // 5M views, 200 days old, YouTube
    $stale = $svc->score(5_000_000, Carbon::parse('2026-01-02T12:00:00Z'), 0.60, 1.0, $asOf);

    expect($recent['moment_score'])->toBeGreaterThan($stale['moment_score']);
});

test('score scales with view count for clips of equal age', function () {
    $svc = app(MomentScoreService::class);
    $asOf = Carbon::parse('2026-07-21T12:00:00Z');
    $published = Carbon::parse('2026-07-19T12:00:00Z');

    $small = $svc->score(10_000, $published, 0.60, 1.0, $asOf);
    $big = $svc->score(1_000_000, $published, 0.60, 1.0, $asOf);

    expect($big['moment_score'])->toBeGreaterThan($small['moment_score']);
});

// ── Authority + confidence weighting ──────────────────────────────────────────

test('C-SPAN authority outranks a YouTube clip with identical engagement', function () {
    $svc = app(MomentScoreService::class);
    $asOf = Carbon::parse('2026-07-21T12:00:00Z');
    $published = Carbon::parse('2026-07-19T12:00:00Z');

    $cspan = $svc->score(500_000, $published, $svc->authorityWeightFor('cspan'), 1.0, $asOf);
    $youtube = $svc->score(500_000, $published, $svc->authorityWeightFor('youtube'), 1.0, $asOf);

    expect($cspan['moment_score'])->toBeGreaterThan($youtube['moment_score']);
});

test('authorityWeightFor returns configured weights and a safe default', function () {
    $svc = app(MomentScoreService::class);

    expect($svc->authorityWeightFor('cspan'))->toBe(1.00)
        ->and($svc->authorityWeightFor('youtube'))->toBe(0.60)
        ->and($svc->authorityWeightFor('news'))->toBe(0.80)
        ->and($svc->authorityWeightFor('unknown_source'))->toBe(0.50);
});

test('low match confidence drags the score down proportionally', function () {
    $svc = app(MomentScoreService::class);
    $asOf = Carbon::parse('2026-07-21T12:00:00Z');
    $published = Carbon::parse('2026-07-19T12:00:00Z');

    $strong = $svc->score(500_000, $published, 0.60, 1.0, $asOf);
    $weak = $svc->score(500_000, $published, 0.60, 0.25, $asOf);

    // 0.25 confidence ≈ quarter of the strong-match score.
    expect($weak['moment_score'])->toBeGreaterThan(0.0)
        ->and($weak['moment_score'])->toBeLessThan($strong['moment_score'])
        ->and($weak['moment_score'])->toBeGreaterThan($strong['moment_score'] * 0.24)
        ->and($weak['moment_score'])->toBeLessThan($strong['moment_score'] * 0.26);
});

// ── Recency decay ─────────────────────────────────────────────────────────────

test('recency decay halves the velocity contribution around the half-life', function () {
    $svc = app(MomentScoreService::class);
    $asOf = Carbon::parse('2026-07-21T12:00:00Z');

    $fresh = $svc->score(100_000, Carbon::parse('2026-07-21T12:00:00Z'), 0.60, 1.0, $asOf);
    $aged = $svc->score(100_000, Carbon::parse('2026-06-21T12:00:00Z'), 0.60, 1.0, $asOf); // ~30 days

    // decay factor for ~30 days at half-life 30 ≈ exp(-1) ≈ 0.368
    expect($aged['score_components']['recency_decay'])->toBeGreaterThan(0.36)
        ->and($aged['score_components']['recency_decay'])->toBeLessThan(0.38)
        ->and($aged['moment_score'])->toBeLessThan($fresh['moment_score']);
});

// ── Edge cases ────────────────────────────────────────────────────────────────

test('null or zero views produce a zero score', function () {
    $svc = app(MomentScoreService::class);
    $asOf = Carbon::parse('2026-07-21T12:00:00Z');

    expect($svc->score(null, Carbon::parse('2026-07-19T12:00:00Z'), 0.60, 1.0, $asOf)['moment_score'])->toBe(0.0)
        ->and($svc->score(0, Carbon::parse('2026-07-19T12:00:00Z'), 0.60, 1.0, $asOf)['moment_score'])->toBe(0.0);
});

test('missing published_at does not crash and treats age as zero', function () {
    $svc = app(MomentScoreService::class);

    $result = $svc->score(100_000, null, 0.60, 1.0);

    expect($result['score_components']['age_days'])->toBe(0.0)
        ->and($result['moment_score'])->toBeGreaterThan(0.0);
});

test('future-dated clip is floored to zero age (no negative age)', function () {
    $svc = app(MomentScoreService::class);
    $asOf = Carbon::parse('2026-07-21T12:00:00Z');

    $result = $svc->score(100_000, Carbon::parse('2026-07-23T12:00:00Z'), 0.60, 1.0, $asOf);

    expect($result['score_components']['age_days'])->toBe(0.0);
});

test('match confidence is clamped to the 0–1 range', function () {
    $svc = app(MomentScoreService::class);
    $asOf = Carbon::parse('2026-07-21T12:00:00Z');
    $published = Carbon::parse('2026-07-19T12:00:00Z');

    $over = $svc->score(100_000, $published, 0.60, 2.0, $asOf);
    $one = $svc->score(100_000, $published, 0.60, 1.0, $asOf);

    expect($over['moment_score'])->toBe($one['moment_score'])
        ->and($over['score_components']['match_confidence'])->toBe(1.0);
});

test('score_components are returned for audit and retuning', function () {
    $svc = app(MomentScoreService::class);
    $asOf = Carbon::parse('2026-07-21T12:00:00Z');

    $result = $svc->score(1_000_000, Carbon::parse('2026-07-19T12:00:00Z'), 0.60, 1.0, $asOf);

    expect($result['score_components'])->toHaveKeys([
        'log_views', 'view_velocity', 'age_days', 'recency_decay', 'authority_weight', 'match_confidence',
    ])
        ->and($result['score_components']['log_views'])->toBe(6.0) // log10(1_000_000)
        ->and($result['view_velocity'])->toBe(500000.0); // 1M views / 2 days
});