<?php

namespace App\Console\Commands;

use App\Jobs\DispatchHotStatesSyncWorkflow;
use App\Models\MapInteractionEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Looks at recent 3-D map click volume (map_interaction_events) and triggers
 * state-scoped GitHub Actions sync workflows for whichever states are
 * trending right now, instead of waiting for the next scheduled full
 * 50-state sync. E.g. if New York is getting far more clicks than
 * California this afternoon, NY's candidate/results/demographics data
 * refreshes ahead of schedule.
 *
 * Usage:
 *   php artisan map:sync-hot-states
 *   php artisan map:sync-hot-states --window-hours=12 --top=3
 *   php artisan map:sync-hot-states --dry-run
 */
class SyncHotStates extends Command
{
    /** Click events that indicate genuine geographic interest in a state. */
    private const GEO_EVENT_TYPES = [
        'state_click',
        'region_click',
        'district_click',
        'city_marker_click',
        'gov_marker_click',
        'candidate_marker_click',
    ];

    protected $signature = 'map:sync-hot-states
        {--window-hours=6   : Trailing window (hours) of click activity to measure}
        {--top=5            : Maximum number of trending states to sync per run}
        {--min-clicks=20    : Minimum clicks within the window before a state counts as "hot"}
        {--cooldown-hours=4 : Do not re-dispatch a state that was dispatched within this many hours}
        {--dry-run          : Report the trending states without dispatching any workflow}';

    protected $description = 'Detect trending states from map click volume and trigger their sync workflows on demand.';

    public function handle(): int
    {
        $windowHours = max(1, (int) $this->option('window-hours'));
        $top = max(1, (int) $this->option('top'));
        $minClicks = max(1, (int) $this->option('min-clicks'));
        $cooldownHours = max(0, (int) $this->option('cooldown-hours'));
        $dryRun = (bool) $this->option('dry-run');

        $ranked = MapInteractionEvent::query()
            ->where('created_at', '>=', now()->subHours($windowHours))
            ->whereNotNull('state_abbr')
            ->whereIn('event_type', self::GEO_EVENT_TYPES)
            ->select('state_abbr', DB::raw('count(*) as clicks'))
            ->groupBy('state_abbr')
            ->having('clicks', '>=', $minClicks)
            ->orderByDesc('clicks')
            ->limit($top)
            ->get();

        if ($ranked->isEmpty()) {
            $this->info("No state cleared the {$minClicks}-click threshold in the last {$windowHours}h.");

            return self::SUCCESS;
        }

        $this->table(
            ['State', "Clicks (last {$windowHours}h)"],
            $ranked->map(fn ($row) => [$row->state_abbr, $row->clicks])->all()
        );

        $toDispatch = [];
        foreach ($ranked as $row) {
            $abbr = strtoupper((string) $row->state_abbr);
            $cacheKey = "hot_state_dispatched:{$abbr}";

            if (Cache::has($cacheKey)) {
                $this->line("  {$abbr}: skipped (dispatched within the last {$cooldownHours}h cooldown)");

                continue;
            }

            $toDispatch[] = $abbr;
        }

        if ($toDispatch === []) {
            $this->info('All trending states are within their dispatch cooldown — nothing to do.');

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->info('[DRY RUN] Would dispatch sync workflows for: ' . implode(', ', $toDispatch));

            return self::SUCCESS;
        }

        DispatchHotStatesSyncWorkflow::dispatch($toDispatch);

        foreach ($toDispatch as $abbr) {
            Cache::put("hot_state_dispatched:{$abbr}", true, now()->addHours($cooldownHours));
        }

        $this->info('Dispatched sync workflows for: ' . implode(', ', $toDispatch));

        return self::SUCCESS;
    }
}
