<?php

use App\Models\ImportRunLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

const CA_IMPORT_SOURCE_URL = 'https://example.test/source.json';

test('health check passes when latest california import success is recent', function () {
    ImportRunLog::query()->create([
        'command_name' => 'politicians:import-unclaimed-ca',
        'source_url' => CA_IMPORT_SOURCE_URL,
        'with_campaigns' => false,
        'dry_run' => false,
        'status' => 'success',
        'exit_code' => 0,
        'created_count' => 2,
        'updated_count' => 1,
        'skipped_count' => 0,
        'campaigns_created_count' => 0,
        'started_at' => now()->subHours(2),
        'finished_at' => now()->subHours(2)->addMinutes(1),
        'output' => 'ok',
    ]);

    $exitCode = Artisan::call('imports:check-california-health', [
        '--max-age-hours' => 30,
    ]);

    expect($exitCode)->toBe(0);
    expect(Artisan::output())->toContain('California import healthy.');
});

test('health check fails when latest california import success is stale', function () {
    ImportRunLog::query()->create([
        'command_name' => 'politicians:import-unclaimed-ca',
        'source_url' => CA_IMPORT_SOURCE_URL,
        'with_campaigns' => false,
        'dry_run' => false,
        'status' => 'success',
        'exit_code' => 0,
        'started_at' => now()->subHours(50),
        'finished_at' => now()->subHours(50)->addMinutes(1),
        'output' => 'ok',
    ]);

    $exitCode = Artisan::call('imports:check-california-health', [
        '--max-age-hours' => 30,
    ]);

    expect($exitCode)->toBe(1);
    expect(Artisan::output())->toContain('stale');
});

test('health check fails when latest california import run is failed', function () {
    ImportRunLog::query()->create([
        'command_name' => 'politicians:import-unclaimed-ca',
        'source_url' => CA_IMPORT_SOURCE_URL,
        'with_campaigns' => false,
        'dry_run' => false,
        'status' => 'failed',
        'exit_code' => 1,
        'started_at' => now()->subHours(1),
        'finished_at' => now()->subHours(1)->addMinutes(1),
        'error_message' => 'network timeout',
        'output' => 'error',
    ]);

    $exitCode = Artisan::call('imports:check-california-health', [
        '--max-age-hours' => 30,
    ]);

    expect($exitCode)->toBe(1);
    expect(Artisan::output())->toContain('failed');
});
