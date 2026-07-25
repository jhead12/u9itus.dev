<?php

use App\Models\PoliticianTopic;
use App\Services\IssueClassifierService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    // The classifier caches the topic catalog under this key; flush so each
    // test's seeded topics are seen.
    Cache::flush();

    config(['u9itus.issues.enabled' => true]);
    config(['u9itus.issues.llm_fallback' => true]);

    $this->healthcare = PoliticianTopic::create([
        'name' => 'Healthcare',
        'slug' => 'healthcare',
        'is_active' => true,
        'sort_order' => 1,
    ]);
    PoliticianTopic::create([
        'name' => 'Climate Action',
        'slug' => 'climate-action',
        'is_active' => true,
        'sort_order' => 2,
    ]);
});

it('classifies a keyword hit above threshold and resolves the topic_id', function () {
    config(['services.anthropic.api_key' => null]); // no LLM path
    $service = new IssueClassifierService;

    $result = $service->classify('Senator pushes healthcare reform bill');

    expect($result['topic_id'])->toBe($this->healthcare->id)
        ->and($result['topic_slug'])->toBe('healthcare')
        ->and($result['method'])->toBe('keyword')
        ->and($result['confidence'])->toBeGreaterThanOrEqual(0.55);
});

it('falls back to the LLM when the keyword tier is unconfident', function () {
    config(['services.anthropic.api_key' => 'sk-test']);

    Http::fake([
        'api.anthropic.com/v1/messages' => Http::response([
            'content' => [
                ['text' => json_encode(['topic_id' => $this->healthcare->id, 'confidence' => 0.88])],
            ],
        ], 200),
    ]);

    $service = new IssueClassifierService;
    $result = $service->classify('Senator floor remarks on the pending measure');

    // No keyword in the snippet → keyword tier is unconfident → LLM is consulted.
    expect($result['topic_id'])->toBe($this->healthcare->id)
        ->and($result['method'])->toBe('llm')
        ->and($result['confidence'])->toBe(0.88);
});

it('discards an LLM topic_id that is not in the catalog', function () {
    config(['services.anthropic.api_key' => 'sk-test']);

    Http::fake([
        'api.anthropic.com/v1/messages' => Http::response([
            'content' => [['text' => json_encode(['topic_id' => 99999, 'confidence' => 0.9])]],
        ], 200),
    ]);

    $service = new IssueClassifierService;
    $result = $service->classify('Senator floor remarks on the pending measure');

    expect($result['topic_id'])->toBeNull()
        ->and($result['method'])->toBe('llm');
});

it('returns none for empty text without calling the LLM', function () {
    config(['services.anthropic.api_key' => 'sk-test']);
    Http::fake(fn () => throw new RuntimeException('LLM should not be called'));

    $service = new IssueClassifierService;
    $result = $service->classify('   ');

    expect($result['topic_id'])->toBeNull()
        ->and($result['method'])->toBe('none')
        ->and($result['confidence'])->toBe(0.0);
});

it('skips the LLM fallback when llm_fallback is disabled', function () {
    config(['u9itus.issues.llm_fallback' => false]);
    config(['services.anthropic.api_key' => 'sk-test']);
    Http::fake(fn () => throw new RuntimeException('LLM should not be called'));

    $service = new IssueClassifierService;
    expect($service->isLlmConfigured())->toBeFalse();

    $result = $service->classify('Senator floor remarks on the pending measure');
    expect($result['topic_id'])->toBeNull()
        ->and($result['method'])->toBe('keyword');
});

it('degrades to none on a non-200 LLM response', function () {
    config(['services.anthropic.api_key' => 'sk-test']);

    Http::fake([
        'api.anthropic.com/v1/messages' => Http::response(['error' => 'rate limited'], 429),
    ]);

    $service = new IssueClassifierService;
    $result = $service->classify('Senator floor remarks on the pending measure');

    expect($result['topic_id'])->toBeNull()
        ->and($result['method'])->toBe('llm');
});
