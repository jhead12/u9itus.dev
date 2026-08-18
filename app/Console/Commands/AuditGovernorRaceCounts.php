<?php

namespace App\Console\Commands;

use App\Jobs\DispatchHotStatesSyncWorkflow;
use App\Models\GovernorRaceCandidateCount;
use App\Models\Politician;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Cross-checks our own declared-candidate count per gubernatorial race
 * against governor_race_candidate_counts (hand-maintained from Ballotpedia,
 * see election:set-race-count). A state where we're under Ballotpedia's
 * count is a signal we're missing a declared challenger, not that
 * Ballotpedia is right — but it's a free correctness signal either way.
 *
 * With --dispatch, under-counted states are pushed through the same
 * GitHub Actions dispatch path map:sync-hot-states uses for trending states
 * (DispatchHotStatesSyncWorkflow), so a targeted re-sync runs ahead of the
 * next scheduled full pass instead of waiting on it.
 */
class AuditGovernorRaceCounts extends Command
{
    protected $signature = 'politicians:audit-race-counts
        {--state=            : Restrict to a two-letter state code}
        {--year=2026          : Election year}
        {--dispatch            : Trigger a targeted re-sync for under-counted states}
        {--cooldown-hours=20  : Do not re-dispatch a state dispatched within this many hours}
        {--json}';

    protected $description = 'Compare declared gubernatorial candidate counts against the Ballotpedia-sourced reference and optionally trigger a targeted re-sync.';

    public function handle(): int
    {
        $state = $this->option('state') ? strtoupper(trim((string) $this->option('state'))) : null;
        $year = (int) $this->option('year');
        $dispatch = (bool) $this->option('dispatch');
        $cooldownHours = max(0, (int) $this->option('cooldown-hours'));
        $asJson = (bool) $this->option('json');

        $rows = GovernorRaceCandidateCount::query()
            ->where('election_year', $year)
            ->when($state, fn ($q) => $q->where('state', $state))
            ->orderBy('state')
            ->get();

        if ($rows->isEmpty()) {
            $this->warn("No reference counts recorded for {$year}" . ($state ? " ({$state})" : '') . '. Seed with election:set-race-count.');

            return self::SUCCESS;
        }

        $results = [];
        $underCounted = [];
        $flagged = 0;

        foreach ($rows as $row) {
            $actual = Politician::query()
                ->where('state', $row->state)
                ->where('is_running_candidate', true)
                ->whereRaw('LOWER(political_office) LIKE ?', ['%governor%'])
                ->whereRaw('LOWER(political_office) NOT LIKE ?', ['%lieutenant%'])
                ->count();

            $diff = $actual - $row->expected_count;

            $results[] = [
                'state' => $row->state,
                'year' => $row->election_year,
                'expected' => $row->expected_count,
                'actual' => $actual,
                'diff' => $diff,
            ];

            if ($diff !== 0) {
                $flagged++;
            }

            if ($diff < 0) {
                $underCounted[] = $row->state;
            }
        }

        if ($asJson) {
            $this->line(json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->table(
                ['State', 'Year', 'Expected', 'Actual', 'Diff'],
                array_map(fn ($r) => [
                    $r['state'],
                    $r['year'],
                    $r['expected'],
                    $r['actual'],
                    $r['diff'] > 0 ? "+{$r['diff']}" : (string) $r['diff'],
                ], $results)
            );
            $this->info(sprintf('%d race(s) checked, %d mismatched, %d under-counted.', count($results), $flagged, count($underCounted)));
        }

        if ($dispatch && $underCounted !== []) {
            $toDispatch = [];
            foreach ($underCounted as $abbr) {
                $cacheKey = "race_count_dispatched:{$abbr}:{$year}";
                if (Cache::has($cacheKey)) {
                    continue;
                }
                $toDispatch[] = $abbr;
            }

            if ($toDispatch !== []) {
                DispatchHotStatesSyncWorkflow::dispatch($toDispatch);
                foreach ($toDispatch as $abbr) {
                    Cache::put("race_count_dispatched:{$abbr}:{$year}", true, now()->addHours($cooldownHours));
                }
                $this->info('Dispatched targeted re-sync for: ' . implode(', ', $toDispatch));
            } else {
                $this->line('All under-counted states are within their dispatch cooldown — nothing dispatched.');
            }
        }

        return $flagged > 0 ? self::FAILURE : self::SUCCESS;
    }
}
