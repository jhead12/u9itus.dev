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
        ['full_name' => 'Jane Winner', 'state' => 'WY', 'result_status' => 'won', 'election_stage' => 'general'],
        ['full_name' => 'Ivan Incumbent', 'state' => 'WY', 'result_status' => 'incumbent'],
        ['full_name' => 'Larry Loser', 'state' => 'WY', 'result_status' => 'lost'],
    ]);

    $this->artisan('politicians:import-election-results', ['--file' => $path, '--skip-fresh-days' => 0])
        ->assertExitCode(0);

    expect($winner->refresh()->won_at)->not->toBeNull();
    expect($winner->term_status)->toBe('active');

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

test('primary advancement does not seat a candidate or stamp won_at', function (array $outcome) {
    $candidate = Politician::factory()->create([
        'full_name' => 'Jane Carter', 'state' => 'CA', 'term_status' => 'running',
        'is_running_candidate' => true, 'won_at' => null,
    ]);
    $path = writeElectionResultsFixture([['full_name' => 'Jane Carter', 'state' => 'CA', ...$outcome]]);
    $this->artisan('politicians:import-election-results', ['--file' => $path, '--skip-fresh-days' => 0])->assertSuccessful();
    expect($candidate->refresh()->term_status)->toBe('running')
        ->and($candidate->is_running_candidate)->toBeTrue()
        ->and($candidate->won_at)->toBeNull();
    unlink($path);
})->with([
    [['result_status' => 'advanced_to_general', 'election_stage' => 'primary']],
    [['result_status' => 'won', 'election_stage' => 'primary']],
    [['result_status' => 'won']],
]);

test('a result in another office never changes an incumbent status', function () {
    $candidate = Politician::factory()->create([
        'full_name' => 'Jane Carter', 'state' => 'CA', 'political_office' => 'Mayor',
        'term_status' => 'seated', 'is_running_candidate' => false,
    ]);
    $path = writeElectionResultsFixture([[
        'full_name' => 'Jane Carter', 'state' => 'CA', 'political_office' => 'Governor', 'result_status' => 'lost',
    ]]);
    $this->artisan('politicians:import-election-results', ['--file' => $path, '--skip-fresh-days' => 0])->assertSuccessful();
    expect($candidate->refresh()->term_status)->toBe('seated')->and($candidate->political_office)->toBe('Mayor');
    unlink($path);
});

test('new result profiles remain unpublished with source provenance and are idempotent', function () {
    $path = writeElectionResultsFixture([[
        'full_name' => 'Jane Carter', 'state' => 'CA', 'political_office' => 'Governor',
        'result_status' => 'advanced_to_general', 'source_url' => 'https://ballotpedia.org/Jane_Carter',
    ]]);
    for ($i = 0; $i < 2; $i++) {
        $this->artisan('politicians:import-election-results', ['--file' => $path, '--create-missing' => true])->assertSuccessful();
    }
    $candidate = Politician::where('full_name', 'Jane Carter')->sole();
    expect($candidate->page_published)->toBeFalse()->and($candidate->is_active)->toBeFalse()
        ->and($candidate->is_running_candidate)->toBeTrue()
        ->and($candidate->page_settings['import_review']['status'])->toBe('pending')
        ->and($candidate->page_settings['import_review']['source_url'])->toBe('https://ballotpedia.org/Jane_Carter');
    unlink($path);
});

test('dry run cannot create or publish a result profile', function () {
    $path = writeElectionResultsFixture([[
        'full_name' => 'Jane Carter', 'state' => 'CA', 'political_office' => 'Governor',
        'result_status' => null, 'source_url' => 'https://ballotpedia.org/Jane_Carter',
    ]]);
    $this->artisan('politicians:import-election-results', ['--file' => $path, '--create-missing' => true, '--dry-run' => true])->assertSuccessful();
    expect(Politician::where('full_name', 'Jane Carter')->exists())->toBeFalse();
    unlink($path);
});

test('shared race URLs are not used as person identifiers', function () {
    $existing = Politician::factory()->create([
        'full_name' => 'Jane Carter', 'state' => 'CA', 'political_office' => 'Governor',
        'term_status' => 'running', 'ballotpedia_id' => 'California_gubernatorial_election,_2026',
    ]);
    $path = writeElectionResultsFixture([[
        'full_name' => 'John Nelson', 'state' => 'CA', 'political_office' => 'Governor',
        'result_status' => 'lost', 'ballotpedia_url' => 'https://ballotpedia.org/California_gubernatorial_election,_2026',
    ]]);
    $this->artisan('politicians:import-election-results', ['--file' => $path, '--skip-fresh-days' => 0])->assertSuccessful();
    expect($existing->refresh()->term_status)->toBe('running');
    unlink($path);
});
