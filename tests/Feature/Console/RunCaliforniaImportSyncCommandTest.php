<?php

use App\Mail\CaliforniaImportSyncFailedMail;
use App\Models\ImportRunLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

test('sync wrapper logs successful california import run', function () {
    Http::fake([
        'https://example.test/legislators-current.json' => Http::response([
            [
                'id' => ['bioguide' => 'P000197'],
                'name' => [
                    'official_full' => 'Nancy Pelosi',
                ],
                'terms' => [
                    [
                        'type' => 'rep',
                        'state' => 'CA',
                        'district' => 11,
                        'party' => 'Democrat',
                        'start' => '2023-01-03',
                        'end' => '2027-01-03',
                    ],
                ],
            ],
        ], 200),
    ]);

    $exitCode = Artisan::call('imports:sync-california', [
        '--source-url' => 'https://example.test/legislators-current.json',
        '--state' => 'CA',
    ]);

    expect($exitCode)->toBe(0);

    $runLog = ImportRunLog::query()->latest('id')->first();

    expect($runLog)->not->toBeNull();
    expect($runLog->command_name)->toBe('politicians:import-unclaimed-ca');
    expect($runLog->status)->toBe('success');
    expect($runLog->exit_code)->toBe(0);
    expect($runLog->created_count)->toBe(1);
    expect($runLog->updated_count)->toBe(0);
    expect($runLog->skipped_count)->toBe(0);
    expect($runLog->campaigns_created_count)->toBe(0);
    expect($runLog->started_at)->not->toBeNull();
    expect($runLog->finished_at)->not->toBeNull();
    expect((string) $runLog->output)->toContain('[state=CA]');
    expect((string) $runLog->output)->toContain('U.S. import complete');
});

test('sync wrapper logs failure and emails admins when california import fails', function () {
    Mail::fake();

    User::factory()->create([
        'email' => 'admin@example.test',
        'user_type' => 'admin',
    ]);

    Http::fake([
        'https://example.test/legislators-current.json' => Http::response([], 503),
    ]);

    $exitCode = Artisan::call('imports:sync-california', [
        '--source-url' => 'https://example.test/legislators-current.json',
        '--state' => 'CA',
    ]);

    expect($exitCode)->toBe(1);

    $runLog = ImportRunLog::query()->latest('id')->first();

    expect($runLog)->not->toBeNull();
    expect($runLog->status)->toBe('failed');
    expect($runLog->exit_code)->toBe(1);
    expect($runLog->error_message)->toContain('Unable to fetch current legislators feed');
    expect($runLog->finished_at)->not->toBeNull();

    Mail::assertQueued(CaliforniaImportSyncFailedMail::class, function (CaliforniaImportSyncFailedMail $mail) use ($runLog) {
        return $mail->runLog->is($runLog);
    });
});
