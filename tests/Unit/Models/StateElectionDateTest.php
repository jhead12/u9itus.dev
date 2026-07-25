<?php

use App\Models\StateElectionDate;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns upcoming election stages for a state, ordered by date', function () {
    StateElectionDate::create([
        'state' => 'CA', 'election_year' => 2026, 'stage_name' => 'General',
        'election_date' => now()->addMonths(6)->toDateString(),
        'filing_deadline' => now()->addMonths(2)->toDateString(),
    ]);
    StateElectionDate::create([
        'state' => 'CA', 'election_year' => 2026, 'stage_name' => 'Primary',
        'election_date' => now()->addMonth()->toDateString(),
        'filing_deadline' => now()->addDays(10)->toDateString(),
    ]);
    // Different state — must not leak into CA's results.
    StateElectionDate::create([
        'state' => 'TX', 'election_year' => 2026, 'stage_name' => 'Primary',
        'election_date' => now()->addWeeks(2)->toDateString(),
    ]);
    // Past election — must be excluded.
    StateElectionDate::create([
        'state' => 'CA', 'election_year' => 2024, 'stage_name' => 'General',
        'election_date' => now()->subYear()->toDateString(),
    ]);

    $result = StateElectionDate::upcomingForState('CA');

    expect($result)->toHaveCount(2);
    expect($result[0]['stage_name'])->toBe('Primary');
    expect($result[1]['stage_name'])->toBe('General');
    expect($result[0])->toHaveKeys(['stage_name', 'election_date', 'election_date_formatted', 'filing_deadline', 'filing_deadline_formatted']);
    expect($result[0]['filing_deadline_formatted'])->not->toBeNull();
});

it('is case-insensitive on state code', function () {
    StateElectionDate::create([
        'state' => 'CA', 'election_year' => 2026, 'stage_name' => 'General',
        'election_date' => now()->addMonths(3)->toDateString(),
    ]);

    expect(StateElectionDate::upcomingForState('ca'))->toHaveCount(1);
});

it('returns an empty array for a state with no synced data', function () {
    expect(StateElectionDate::upcomingForState('WY'))->toBe([]);
});
