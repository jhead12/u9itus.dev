<?php

namespace App\Console\Commands;

use App\Jobs\DispatchHotStatesSyncWorkflow;
use App\Models\StateElectionDate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Closes the gap between state_election_dates (synced monthly from Vote
 * Smart, see elections:sync-dates) and the candidate/results import
 * pipeline: nothing previously read election_date to decide *when* a state
 * needs a results refresh, so winners only surfaced once the blind daily
 * sync-candidates/refresh-map-candidates crons happened to scrape an updated
 * Ballotpedia page.
 *
 * This command instead looks directly at state_election_dates for stages
 * (primary, general, runoff, ...) whose election_date fell within the
 * trailing --lookback-days window and dispatches a targeted re-sync for
 * those states via the same GitHub Actions path map:sync-hot-states and
 * politicians:audit-race-counts use (DispatchHotStatesSyncWorkflow), so
 * results land within hours of an election instead of waiting on the next
 * scheduled full pass.
 *
 * Usage:
 *   php artisan politicians:sync-post-election
 *   php artisan politicians:sync-post-election --stage=primary --lookback-days=2
 *   php artisan politicians:sync-post-election --dry-run
 */
class SyncPostElectionResults extends Command
{
    protected $signature = 'politicians:sync-post-election
        {--lookback-days=3   : Dispatch a re-sync for stages whose election_date fell within this many trailing days}
        {--cooldown-hours=24 : Do not re-dispatch the same state+stage combo within this many hours}
        {--stage=            : Restrict to stage_name values containing this substring, e.g. "primary" (case-insensitive)}
        {--dry-run           : Report the affected states without dispatching any workflow}';

    protected $description = 'Dispatch a targeted results re-sync for states whose primary/general election date just passed.';

    public function handle(): int
    {
        $lookbackDays = max(0, (int) $this->option('lookback-days'));
        $cooldownHours = max(0, (int) $this->option('cooldown-hours'));
        $stageFilter = trim((string) $this->option('stage'));
        $dryRun = (bool) $this->option('dry-run');

        $rows = StateElectionDate::query()
            ->whereNotNull('election_date')
            ->whereBetween('election_date', [
                now()->subDays($lookbackDays)->toDateString(),
                now()->toDateString(),
            ])
            ->when($stageFilter !== '', fn ($q) => $q->whereRaw('LOWER(stage_name) LIKE ?', ['%' . strtolower($stageFilter) . '%']))
            ->orderBy('election_date')
            ->get();

        if ($rows->isEmpty()) {
            $this->info("No election stage fell within the last {$lookbackDays} day(s).");

            return self::SUCCESS;
        }

        $this->table(
            ['State', 'Stage', 'Election Date'],
            $rows->map(fn (StateElectionDate $row) => [
                $row->state,
                $row->stage_name,
                optional($row->election_date)?->toDateString(),
            ])->all()
        );

        $toDispatch = [];
        $skipped = [];

        foreach ($rows as $row) {
            $state = strtoupper((string) $row->state);
            $cacheKey = sprintf(
                'post_election_dispatched:%s:%d:%s',
                $state,
                $row->election_year,
                Str::slug((string) $row->stage_name)
            );

            if (Cache::has($cacheKey)) {
                $skipped[] = "{$state} ({$row->stage_name})";

                continue;
            }

            $toDispatch[$state] = $cacheKey;
        }

        if ($skipped !== []) {
            $this->line('Skipped (within cooldown): ' . implode(', ', $skipped));
        }

        if ($toDispatch === []) {
            $this->info('All affected state/stage combos are within their dispatch cooldown — nothing to do.');

            return self::SUCCESS;
        }

        $states = array_keys($toDispatch);

        if ($dryRun) {
            $this->info('[DRY RUN] Would dispatch results re-sync for: ' . implode(', ', $states));

            return self::SUCCESS;
        }

        DispatchHotStatesSyncWorkflow::dispatch($states);

        foreach ($toDispatch as $cacheKey) {
            Cache::put($cacheKey, true, now()->addHours($cooldownHours));
        }

        $this->info('Dispatched post-election results re-sync for: ' . implode(', ', $states));

        return self::SUCCESS;
    }
}
