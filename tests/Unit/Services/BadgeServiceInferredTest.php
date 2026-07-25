<?php

use App\Models\Politician;
use App\Models\PoliticianTopic;
use App\Models\PoliticianTopicSignal;
use App\Services\BadgeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['u9itus.issues.signal_threshold' => 1.0]);

    $this->topic = PoliticianTopic::create([
        'name' => 'Healthcare', 'slug' => 'healthcare', 'is_active' => true, 'sort_order' => 1,
    ]);

    $this->politician = Politician::create([
        'uuid' => Str::uuid(),
        'full_name' => 'Jane Doe',
        'slug' => 'jane-doe',
        'state' => 'CA',
        'political_office' => 'Senator',
        'governance_level' => 'Federal',
        'party_affiliation' => 'Democratic',
        'is_active' => true,
        'page_published' => true,
    ]);

    $this->service = new BadgeService;
});

it('grants an inferred_discourse badge when the signal crosses the threshold', function () {
    $signals = collect([
        PoliticianTopicSignal::create([
            'politician_id' => $this->politician->id,
            'topic_id' => $this->topic->id,
            'total_score' => 2.5,
        ]),
    ]);

    $granted = $this->service->grantInferredBadges($this->politician, $signals);

    expect($granted)->toBe(1);

    $badge = $this->politician->badges()->where('topic_id', $this->topic->id)->first();
    expect($badge)->not->toBeNull()
        ->and($badge->badge_type)->toBe('inferred_discourse')
        ->and($badge->is_public)->toBeTrue();
});

it('does not grant a badge when the signal is below the threshold', function () {
    $signals = collect([
        PoliticianTopicSignal::create([
            'politician_id' => $this->politician->id,
            'topic_id' => $this->topic->id,
            'total_score' => 0.5,
        ]),
    ]);

    $granted = $this->service->grantInferredBadges($this->politician, $signals);

    expect($granted)->toBe(0)
        ->and($this->politician->badges()->count())->toBe(0);
});

it('does not overwrite a self-declared badge for the same topic', function () {
    // Pre-existing self-declared badge.
    $this->politician->addBadge($this->topic->id, 'self_declared');

    $signals = collect([
        PoliticianTopicSignal::create([
            'politician_id' => $this->politician->id,
            'topic_id' => $this->topic->id,
            'total_score' => 2.5,
        ]),
    ]);

    $granted = $this->service->grantInferredBadges($this->politician, $signals);

    // The self-declared badge already occupied the topic → no new grant.
    expect($granted)->toBe(0);

    $badges = $this->politician->badges()->where('topic_id', $this->topic->id)->get();
    expect($badges)->toHaveCount(1)
        ->and($badges->first()->badge_type)->toBe('self_declared');
});

it('is idempotent across repeated runs', function () {
    $signals = collect([
        PoliticianTopicSignal::create([
            'politician_id' => $this->politician->id,
            'topic_id' => $this->topic->id,
            'total_score' => 2.5,
        ]),
    ]);

    expect($this->service->grantInferredBadges($this->politician, $signals))->toBe(1)
        ->and($this->service->grantInferredBadges($this->politician, $signals))->toBe(0)
        ->and($this->politician->badges()->count())->toBe(1);
});
