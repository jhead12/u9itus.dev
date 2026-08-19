<?php

use App\Models\CandidateNewsArticle;
use App\Models\Politician;
use App\Models\StateElectionDate;
use Illuminate\Support\Facades\Http;

function seedWinDetectionElectionDate(string $state, string $stage, int $daysAgo, int $year = 2026): StateElectionDate
{
    return StateElectionDate::create([
        'state' => $state,
        'election_year' => $year,
        'stage_name' => $stage,
        'election_date' => now()->subDays($daysAgo)->toDateString(),
        'filing_deadline' => null,
        'source' => 'votesmart',
    ]);
}

function fakeAnthropicWinResponse(bool $isWin, float $confidence, string $reason = 'confirmed'): void
{
    Http::fake([
        'api.anthropic.com/v1/messages' => Http::response([
            'content' => [
                ['text' => json_encode(['is_win' => $isWin, 'confidence' => $confidence, 'reason' => $reason])],
            ],
        ], 200),
    ]);
}

beforeEach(function () {
    config(['services.anthropic.api_key' => 'test-key']);
});

test('confirms and records a win when AI classifies the news coverage above the confidence threshold', function () {
    seedWinDetectionElectionDate('WY', 'Primary', daysAgo: 2);

    $politician = Politician::factory()->create([
        'full_name' => 'Jane Winner',
        'state' => 'WY',
        'term_status' => 'running',
        'is_running_candidate' => true,
        'is_active' => true,
        'won_at' => null,
    ]);

    CandidateNewsArticle::factory()->create([
        'politician_id' => $politician->id,
        'headline' => 'Jane Winner wins Wyoming primary',
        'snippet' => 'Jane Winner declared winner of the Republican primary.',
        'published_at' => now()->subDay(),
    ]);

    fakeAnthropicWinResponse(true, 0.92);

    $this->artisan('politicians:detect-election-wins')->assertExitCode(0);

    $politician->refresh();
    expect($politician->term_status)->toBe('seated');
    expect($politician->is_running_candidate)->toBeFalse();
    expect($politician->won_at)->not->toBeNull();
});

test('does not record a win when AI confidence is below the threshold', function () {
    seedWinDetectionElectionDate('WY', 'Primary', daysAgo: 2);

    $politician = Politician::factory()->create([
        'full_name' => 'Jane Winner',
        'state' => 'WY',
        'term_status' => 'running',
        'is_running_candidate' => true,
        'won_at' => null,
    ]);

    CandidateNewsArticle::factory()->create([
        'politician_id' => $politician->id,
        'headline' => 'Jane Winner wins straw poll ahead of primary',
        'snippet' => 'An early indicator, not the final result.',
        'published_at' => now()->subDay(),
    ]);

    fakeAnthropicWinResponse(true, 0.4, 'ambiguous — could be a straw poll, not the real election');

    $this->artisan('politicians:detect-election-wins')->assertExitCode(0);

    $politician->refresh();
    expect($politician->term_status)->toBe('running');
    expect($politician->won_at)->toBeNull();
});

test('does not call the AI when no article mentions the candidate with win-signal language', function () {
    seedWinDetectionElectionDate('WY', 'Primary', daysAgo: 2);

    $politician = Politician::factory()->create([
        'full_name' => 'Jane Winner',
        'state' => 'WY',
        'term_status' => 'running',
        'is_running_candidate' => true,
    ]);

    CandidateNewsArticle::factory()->create([
        'politician_id' => $politician->id,
        'headline' => 'Jane Winner discusses campaign priorities',
        'snippet' => 'An interview about policy positions.',
        'published_at' => now()->subDay(),
    ]);

    Http::fake();

    $this->artisan('politicians:detect-election-wins')->assertExitCode(0);

    Http::assertNothingSent();
    expect($politician->refresh()->term_status)->toBe('running');
});

test('skips politicians in states with no recent election date', function () {
    $politician = Politician::factory()->create([
        'full_name' => 'Jane Winner',
        'state' => 'CA',
        'term_status' => 'running',
        'is_running_candidate' => true,
    ]);

    CandidateNewsArticle::factory()->create([
        'politician_id' => $politician->id,
        'headline' => 'Jane Winner wins primary',
        'snippet' => 'Declared winner.',
        'published_at' => now()->subDay(),
    ]);

    Http::fake();

    $this->artisan('politicians:detect-election-wins')->assertExitCode(0);

    Http::assertNothingSent();
});

test('respects the per-politician cooldown across runs', function () {
    seedWinDetectionElectionDate('WY', 'Primary', daysAgo: 2);

    $politician = Politician::factory()->create([
        'full_name' => 'Jane Winner',
        'state' => 'WY',
        'term_status' => 'running',
        'is_running_candidate' => true,
        'won_at' => null,
    ]);

    CandidateNewsArticle::factory()->create([
        'politician_id' => $politician->id,
        'headline' => 'Jane Winner wins Wyoming primary',
        'snippet' => 'Declared winner of the primary.',
        'published_at' => now()->subDay(),
    ]);

    fakeAnthropicWinResponse(true, 0.4);

    $this->artisan('politicians:detect-election-wins')->assertExitCode(0);
    $this->artisan('politicians:detect-election-wins')->assertExitCode(0);

    Http::assertSentCount(1);
});

test('dry-run does not persist a confirmed win', function () {
    seedWinDetectionElectionDate('WY', 'Primary', daysAgo: 2);

    $politician = Politician::factory()->create([
        'full_name' => 'Jane Winner',
        'state' => 'WY',
        'term_status' => 'running',
        'is_running_candidate' => true,
        'won_at' => null,
    ]);

    CandidateNewsArticle::factory()->create([
        'politician_id' => $politician->id,
        'headline' => 'Jane Winner wins Wyoming primary',
        'snippet' => 'Declared winner of the primary.',
        'published_at' => now()->subDay(),
    ]);

    fakeAnthropicWinResponse(true, 0.95);

    $this->artisan('politicians:detect-election-wins', ['--dry-run' => true])->assertExitCode(0);

    $politician->refresh();
    expect($politician->term_status)->toBe('running');
    expect($politician->won_at)->toBeNull();
});

test('skips politicians who are already seated', function () {
    seedWinDetectionElectionDate('WY', 'Primary', daysAgo: 2);

    $politician = Politician::factory()->create([
        'full_name' => 'Already Seated',
        'state' => 'WY',
        'term_status' => 'seated',
        'is_running_candidate' => false,
    ]);

    CandidateNewsArticle::factory()->create([
        'politician_id' => $politician->id,
        'headline' => 'Already Seated wins re-election',
        'snippet' => 'Declared winner.',
        'published_at' => now()->subDay(),
    ]);

    Http::fake();

    $this->artisan('politicians:detect-election-wins')->assertExitCode(0);

    Http::assertNothingSent();
});

test('returns success and makes no calls when ANTHROPIC_API_KEY is missing', function () {
    config(['services.anthropic.api_key' => null]);

    seedWinDetectionElectionDate('WY', 'Primary', daysAgo: 2);
    Politician::factory()->create([
        'full_name' => 'Jane Winner',
        'state' => 'WY',
        'term_status' => 'running',
        'is_running_candidate' => true,
    ]);

    Http::fake();

    $this->artisan('politicians:detect-election-wins')->assertExitCode(0);

    Http::assertNothingSent();
});
