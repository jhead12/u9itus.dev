<?php

use App\Services\FECService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * A FECService whose backoff/throttle sleeps are no-ops, so retry tests don't
 * stall the suite. Production code routes every sleep through
 * sleepMicroseconds(); this subclass overrides it.
 */
function fecServiceNoSleep(): FECService
{
    return new class extends FECService {
        protected function sleepMicroseconds(int $microseconds): void
        {
            // no-op in tests
        }
    };
}

beforeEach(function () {
    FECService::resetTelemetry();
    Cache::flush();
});

it('returns empty array from getCommitteeContributions when service is not configured', function () {
    config(['services.fec.api_key' => null]);

    $service = new FECService();

    expect($service->getCommitteeContributions('C00123456'))->toBe([]);
});

it('maps FEC Schedule A committee contributions to name/total pairs', function () {
    config(['services.fec.api_key' => 'DEMO_KEY']);

    Http::fake([
        'api.open.fec.gov/v1/schedules/schedule_a/*' => Http::response([
            'results' => [
                ['contributor_name' => 'AIPAC', 'contribution_receipt_amount' => 5000],
                ['contributor_name' => 'National Association of Realtors', 'contribution_receipt_amount' => 2500],
            ],
        ], 200),
    ]);

    $service = fecServiceNoSleep();

    $result = $service->getCommitteeContributions('C00123456');

    expect($result)->toBe([
        ['name' => 'AIPAC', 'total' => '$5,000'],
        ['name' => 'National Association of Realtors', 'total' => '$2,500'],
    ]);

    // committee_id must be a plain string param, NOT the array form
    // (committee_id[]=) — the array form is what triggered the schedule_a 400s.
    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'schedules/schedule_a')
            && $request['contributor_type'] === 'committee'
            && $request['committee_id'] === 'C00123456';
    });
});

it('returns empty array when the Schedule A request fails', function () {
    config(['services.fec.api_key' => 'DEMO_KEY']);

    Http::fake([
        'api.open.fec.gov/v1/schedules/schedule_a/*' => Http::response([], 500),
    ]);

    $service = fecServiceNoSleep();

    expect($service->getCommitteeContributions('C00123456'))->toBe([]);
});

it('classifies FEC-sourced committee contributions through PacAffiliationClassifier', function () {
    config(['services.fec.api_key' => 'DEMO_KEY']);

    Http::fake([
        'api.open.fec.gov/v1/schedules/schedule_a/*' => Http::response([
            'results' => [
                ['contributor_name' => 'NORPAC', 'contribution_receipt_amount' => 10000],
            ],
        ], 200),
    ]);

    $service = fecServiceNoSleep();
    $classifier = new App\Services\PacAffiliationClassifier();

    $contributions = $service->getCommitteeContributions('C00123456');
    $matches = $classifier->classify($contributions);

    expect($matches)->toHaveCount(1);
    expect($matches[0]['group'])->toBe('aipac_pro_israel');
    expect($matches[0]['matched_name'])->toBe('NORPAC');
});

it('retries on 429 then succeeds and returns results', function () {
    config(['services.fec.api_key' => 'DEMO_KEY']);

    // getLatestCycle calls /candidate/{id}/ once (cached). Fake a 429 then a 200
    // so the request helper retries once and returns the parsed cycle.
    Http::fake([
        'api.open.fec.gov/v1/candidate/H8CA32123/*' => Http::sequence()
            ->push([], 429)
            ->push(['results' => [['cycles' => [2022, 2024]]]], 200),
    ]);

    $service = fecServiceNoSleep();

    $cycle = $service->getLatestCycle('H8CA32123');

    expect($cycle)->toBe(2024);
    expect(FECService::getRateLimitCount())->toBe(1);
});

it('retries on a connection timeout then succeeds and returns results', function () {
    config(['services.fec.api_key' => 'DEMO_KEY']);

    // Production logs showed schedule_a/schedule_e routinely hitting cURL
    // error 28 (timeout) — previously the request() helper had no retry path
    // for connection failures at all, unlike 429/5xx, and gave up instantly.
    Http::fake([
        'api.open.fec.gov/v1/candidate/H8CA32123/*' => Http::sequence()
            ->pushFailedConnection('cURL error 28: Operation timed out')
            ->push(['results' => [['cycles' => [2022, 2024]]]], 200),
    ]);

    $service = fecServiceNoSleep();

    $cycle = $service->getLatestCycle('H8CA32123');

    expect($cycle)->toBe(2024);
    expect(FECService::getHttpCallCount())->toBe(1); // only the successful attempt counts as a completed HTTP call
});

it('gives up on a connection timeout after exhausting retries', function () {
    config(['services.fec.api_key' => 'DEMO_KEY']);

    Http::fake([
        'api.open.fec.gov/v1/candidate/H8CA32123/*' => Http::failedConnection('cURL error 28: Operation timed out'),
    ]);

    $service = fecServiceNoSleep();

    expect($service->getLatestCycle('H8CA32123'))->toBeNull();
});

it('short-circuits after a storm of consecutive 429s and stops hitting the API', function () {
    config(['services.fec.api_key' => 'DEMO_KEY']);

    // Every call 429s. The short-circuit trips at the START of a call once
    // consecutiveRateLimits (incremented per 429 attempt) reaches the
    // threshold. Each call retries up to 3 attempts, so two calls push the
    // counter past the threshold; the third call must short-circuit and issue
    // NO HTTP request.
    Http::fake(function () {
        return Http::response([], 429);
    });

    $service = fecServiceNoSleep();

    $service->getCandidateCommittees('H8CA32123');  // 3 attempts → counter = 3
    $service->getCandidateCommittees('S0NY00000'); // 3 attempts → counter = 6
    $callsBeforeThird = FECService::getHttpCallCount();

    $service->getCandidateCommittees('P0CA00000'); // short-circuits: 0 new HTTP

    expect(FECService::wasShortCircuited())->toBeTrue();
    expect(FECService::getRateLimitCount())->toBeGreaterThanOrEqual(5);
    expect(FECService::getHttpCallCount())->toBe($callsBeforeThird);
});

it('serves repeated committee-contributions calls from cache without new HTTP requests', function () {
    config(['services.fec.api_key' => 'DEMO_KEY']);

    Http::fake([
        'api.open.fec.gov/v1/schedules/schedule_a/*' => Http::response([
            'results' => [['contributor_name' => 'AIPAC', 'contribution_receipt_amount' => 5000]],
        ], 200),
    ]);

    $service = fecServiceNoSleep();

    $first = $service->getCommitteeContributions('C00123456');
    $second = $service->getCommitteeContributions('C00123456');

    expect($first)->toBe($second);

    // Cached path: only one HTTP call across both invocations.
    Http::assertSentCount(1);
});

it('serves repeated outside-spending calls from cache without new HTTP requests', function () {
    config(['services.fec.api_key' => 'DEMO_KEY']);

    Http::fake([
        'api.open.fec.gov/v1/schedules/schedule_e/*' => Http::response([
            'results' => [],
            'pagination' => [],
        ], 200),
        'api.open.fec.gov/v1/committees/*' => Http::response(['results' => []], 200),
    ]);

    $service = fecServiceNoSleep();

    $service->getOutsideSpending('H8CA32123', 2024);
    $service->getOutsideSpending('H8CA32123', 2024);

    // Only the first invocation hits schedule_e; the second is served from cache.
    Http::assertSentCount(1);
});