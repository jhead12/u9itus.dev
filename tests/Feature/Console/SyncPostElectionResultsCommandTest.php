<?php

use App\Jobs\DispatchHotStatesSyncWorkflow;
use App\Models\StateElectionDate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

function seedElectionDate(string $state, string $stage, int $daysAgo, int $year = 2026): StateElectionDate
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

test('dispatches a re-sync for a state whose primary just passed', function () {
    Queue::fake();

    seedElectionDate('GA', 'Primary', daysAgo: 1);

    Artisan::call('politicians:sync-post-election');

    Queue::assertPushed(DispatchHotStatesSyncWorkflow::class, fn ($job) => $job->states === ['GA']);
});

test('ignores elections outside the lookback window', function () {
    Queue::fake();

    seedElectionDate('WY', 'Primary', daysAgo: 10);

    Artisan::call('politicians:sync-post-election');

    Queue::assertNotPushed(DispatchHotStatesSyncWorkflow::class);
});

test('ignores elections still in the future', function () {
    Queue::fake();

    StateElectionDate::create([
        'state' => 'TX',
        'election_year' => 2026,
        'stage_name' => 'General',
        'election_date' => now()->addDays(5)->toDateString(),
    ]);

    Artisan::call('politicians:sync-post-election');

    Queue::assertNotPushed(DispatchHotStatesSyncWorkflow::class);
});

test('--stage filter restricts to matching stage names', function () {
    Queue::fake();

    seedElectionDate('NY', 'General', daysAgo: 1);
    seedElectionDate('CA', 'Primary', daysAgo: 1);

    Artisan::call('politicians:sync-post-election', ['--stage' => 'primary']);

    Queue::assertPushed(DispatchHotStatesSyncWorkflow::class, fn ($job) => $job->states === ['CA']);
});

test('dry-run reports affected states without dispatching', function () {
    Queue::fake();

    seedElectionDate('FL', 'Primary', daysAgo: 2);

    Artisan::call('politicians:sync-post-election', ['--dry-run' => true]);

    Queue::assertNotPushed(DispatchHotStatesSyncWorkflow::class);
    expect(Artisan::output())->toContain('[DRY RUN]')
        ->toContain('FL');
});

test('skips a state/stage combo still within its dispatch cooldown', function () {
    Queue::fake();
    Cache::put('post_election_dispatched:OH:2026:primary', true, now()->addHours(12));

    seedElectionDate('OH', 'Primary', daysAgo: 1);

    Artisan::call('politicians:sync-post-election');

    Queue::assertNotPushed(DispatchHotStatesSyncWorkflow::class);
    expect(Artisan::output())->toContain('cooldown');
});

test('dedupes a state that has multiple qualifying stages in one run', function () {
    Queue::fake();

    seedElectionDate('AZ', 'Primary Runoff', daysAgo: 1);
    seedElectionDate('AZ', 'Special', daysAgo: 2);

    Artisan::call('politicians:sync-post-election');

    Queue::assertPushed(DispatchHotStatesSyncWorkflow::class, fn ($job) => $job->states === ['AZ']);
});
