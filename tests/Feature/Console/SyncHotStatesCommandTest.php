<?php

use App\Jobs\DispatchHotStatesSyncWorkflow;
use App\Models\MapInteractionEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

function seedClicks(string $stateAbbr, int $count, int $minutesAgo = 5): void
{
    $timestamp = now()->subMinutes($minutesAgo);

    for ($i = 0; $i < $count; $i++) {
        $event = new MapInteractionEvent([
            'session_id' => 'sess-' . uniqid(),
            'event_type' => 'state_click',
            'state' => $stateAbbr,
            'state_abbr' => $stateAbbr,
        ]);
        // created_at/updated_at are not fillable, so they must be set via
        // forceFill to backdate events outside the command's click window.
        $event->forceFill(['created_at' => $timestamp, 'updated_at' => $timestamp])->save();
    }
}

test('dispatches sync workflow for a state above the click threshold', function () {
    Queue::fake();

    seedClicks('NY', 25);
    seedClicks('CA', 5); // below default --min-clicks=20

    Artisan::call('map:sync-hot-states');

    Queue::assertPushed(DispatchHotStatesSyncWorkflow::class, fn ($job) => $job->states === ['NY']);
});

test('does not dispatch when no state clears the threshold', function () {
    Queue::fake();

    seedClicks('WY', 3);

    Artisan::call('map:sync-hot-states');

    Queue::assertNotPushed(DispatchHotStatesSyncWorkflow::class);
    expect(Artisan::output())->toContain('No state cleared');
});

test('dry-run reports trending states without dispatching', function () {
    Queue::fake();

    seedClicks('TX', 30);

    Artisan::call('map:sync-hot-states', ['--dry-run' => true]);

    Queue::assertNotPushed(DispatchHotStatesSyncWorkflow::class);
    expect(Artisan::output())->toContain('[DRY RUN]')
        ->toContain('TX');
});

test('ignores clicks outside the trailing window', function () {
    Queue::fake();

    seedClicks('FL', 30, minutesAgo: 60 * 20); // 20h ago, outside default 6h window

    Artisan::call('map:sync-hot-states');

    Queue::assertNotPushed(DispatchHotStatesSyncWorkflow::class);
});

test('skips a state that is still within its dispatch cooldown', function () {
    Queue::fake();
    Cache::put('hot_state_dispatched:GA', true, now()->addHours(4));

    seedClicks('GA', 30);

    Artisan::call('map:sync-hot-states');

    Queue::assertNotPushed(DispatchHotStatesSyncWorkflow::class);
    expect(Artisan::output())->toContain('cooldown');
});
