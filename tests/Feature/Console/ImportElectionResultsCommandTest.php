<?php

use App\Models\Politician;

function writeElectionResultsFixture(array $rows): string
{
    $path = storage_path('app/imports/test-election-results-'.uniqid().'.json');
    if (! is_dir(dirname($path))) {
        mkdir(dirname($path), recursive: true);
    }
    file_put_contents($path, json_encode($rows));

    return $path;
}

test('a won result stamps won_at, but incumbent and lost results do not', function () {
    $winner = Politician::factory()->create([
        'full_name' => 'Jane Winner',
        'state' => 'WY',
        'term_status' => 'running',
        'won_at' => null,
    ]);
    $incumbent = Politician::factory()->create([
        'full_name' => 'Ivan Incumbent',
        'state' => 'WY',
        'term_status' => 'seated',
        'won_at' => null,
    ]);
    $loser = Politician::factory()->create([
        'full_name' => 'Larry Loser',
        'state' => 'WY',
        'term_status' => 'running',
        'won_at' => null,
    ]);

    $path = writeElectionResultsFixture([
        ['full_name' => 'Jane Winner', 'state' => 'WY', 'result_status' => 'won'],
        ['full_name' => 'Ivan Incumbent', 'state' => 'WY', 'result_status' => 'incumbent'],
        ['full_name' => 'Larry Loser', 'state' => 'WY', 'result_status' => 'lost'],
    ]);

    $this->artisan('politicians:import-election-results', ['--file' => $path, '--skip-fresh-days' => 0])
        ->assertExitCode(0);

    expect($winner->refresh()->won_at)->not->toBeNull();
    expect($winner->term_status)->toBe('seated');

    expect($incumbent->refresh()->won_at)->toBeNull();
    expect($incumbent->term_status)->toBe('seated');

    expect($loser->refresh()->won_at)->toBeNull();
    expect($loser->term_status)->toBe('lost');

    unlink($path);
});

test('re-syncing an already-seated politician as incumbent does not overwrite a prior won_at', function () {
    $wonAt = now()->subDays(5);
    $politician = Politician::factory()->create([
        'full_name' => 'Pat Seated',
        'state' => 'WY',
        'term_status' => 'seated',
        'status_updated_at' => now()->subDays(10),
        'won_at' => $wonAt,
    ]);

    $path = writeElectionResultsFixture([
        ['full_name' => 'Pat Seated', 'state' => 'WY', 'result_status' => 'incumbent'],
    ]);

    $this->artisan('politicians:import-election-results', ['--file' => $path, '--skip-fresh-days' => 0])
        ->assertExitCode(0);

    expect($politician->refresh()->won_at->timestamp)->toBe($wonAt->timestamp);

    unlink($path);
});
