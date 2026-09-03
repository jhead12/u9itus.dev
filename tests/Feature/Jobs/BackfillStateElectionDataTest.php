<?php

use App\Jobs\BackfillStateElectionData;
use App\Mail\StateBallotMeasuresReadyMail;
use App\Models\BallotMeasure;
use App\Models\ElectionDataBackfill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    // The pipeline commands the job shells out to may hit Google Civic — stub
    // every outbound call so the test stays offline and deterministic.
    Http::fake(['*' => Http::response([], 200)]);
    config(['services.google.civic_api_key' => 'DEMO_KEY']);
});

it('records unavailable when the pipeline finds no measures', function () {
    (new BackfillStateElectionData('TX'))->handle();

    $row = ElectionDataBackfill::firstWhere('state', 'TX');
    expect($row->status)->toBe(ElectionDataBackfill::STATUS_UNAVAILABLE)
        ->and($row->measures_found)->toBe(0)
        ->and($row->elections_found)->toBe(1)   // statutory General seeded by elections:sync-dates
        ->and($row->attempts)->toBe(1)
        ->and($row->last_attempted_at)->not->toBeNull();
});

it('records ready and emails watchers when the state has measures', function () {
    Mail::fake();

    BallotMeasure::create(['state' => 'TX', 'title' => 'Proposition 1', 'status' => 'upcoming']);

    $row = ElectionDataBackfill::create(['state' => 'TX', 'status' => ElectionDataBackfill::STATUS_QUEUED]);
    $row->addWatcher('voter@example.com');
    $row->save();

    (new BackfillStateElectionData('TX'))->handle();

    $row->refresh();
    expect($row->status)->toBe(ElectionDataBackfill::STATUS_READY)
        ->and($row->measures_found)->toBe(1)
        ->and($row->watch_emails)->toBeNull(); // cleared after notifying

    Mail::assertQueued(StateBallotMeasuresReadyMail::class, function ($mail) {
        return $mail->state === 'TX' && $mail->count === 1 && $mail->hasTo('voter@example.com');
    });
});

it('does not re-run while another worker holds it', function () {
    ElectionDataBackfill::create([
        'state' => 'TX',
        'status' => ElectionDataBackfill::STATUS_RUNNING,
        'attempts' => 1,
        'last_attempted_at' => now()->subMinute(),
    ]);

    (new BackfillStateElectionData('TX'))->handle();

    expect(ElectionDataBackfill::firstWhere('state', 'TX')->attempts)->toBe(1);
});
