<?php

namespace App\Console\Commands;

use App\Models\Politician;
use App\Services\WikipediaPrimaryResultsService;
use App\Support\ElectionCycle;
use App\Support\MapCacheNotice;
use Illuminate\Console\Command;

/**
 * Marks sitting members of Congress whose seat is on this cycle's ballot as
 * running (or not) from the race's Wikipedia article.
 *
 * politicians:sync-primary-results only reads candidate records, and the
 * Congress import creates incumbents as seated / not running, so no sitting
 * House member ever showed as running for re-election — the map's Running
 * Candidates panel listed one House candidate for all of California.
 *
 * Per incumbent, using the district's section of the state's House article
 * (or the state's Senate article for a senator whose term ends this cycle):
 *   advanced / nominee          → running
 *   declared (primary not held) → running
 *   eliminated or withdrew      → not running (still seated until the term ends)
 *   not on the article          → left alone and reported (retiring, running
 *                                 for another office, or a name mismatch)
 *
 * A House member missing from their own district is also looked up across the
 * state's whole House article, since redistricting moves incumbents.
 */
class SyncIncumbentRuns extends Command
{
    protected $signature = 'politicians:sync-incumbent-runs
        {--state=* : Two-letter state code(s). Omit for every state.}
        {--year=   : Election year. Defaults to the current cycle.}
        {--dry-run : Report only — no DB writes.}';

    protected $description = 'Mark sitting House members and up-for-election senators as running or not from Wikipedia race articles.';

    public function handle(WikipediaPrimaryResultsService $wikipedia): int
    {
        $year = (int) ($this->option('year') ?: ElectionCycle::year());
        $states = array_map(fn ($s) => strtoupper(trim((string) $s)), (array) $this->option('state'));
        $dryRun = (bool) $this->option('dry-run');
        $termEnds = ($year + 1).'-01-03';

        $incumbents = Politician::query()
            ->where('is_active', true)
            ->where('term_status', 'seated')
            ->whereRaw('LOWER(COALESCE(governance_level, \'\')) = ?', ['federal'])
            ->whereDate('term_ends_on', $termEnds)
            ->when($states !== [], fn ($q) => $q->whereIn('state', $states))
            ->orderBy('state')->orderBy('district')
            ->get(['id', 'full_name', 'state', 'district', 'political_office', 'is_running_candidate', 'term_ends_on']);

        $this->line($dryRun ? '<fg=yellow>[dry-run] No DB writes will occur.</>' : '<comment>[LIVE — writing changes]</comment>');
        $this->info("Checking {$incumbents->count()} incumbent(s) whose term ends {$termEnds}...");

        $stats = ['running' => 0, 'not_running' => 0, 'unlisted' => 0, 'no_article' => 0, 'changed' => 0];
        $unlisted = [];

        foreach ($incumbents as $pol) {
            $isSenator = str_contains(strtolower((string) $pol->political_office), 'senat');
            $office = $isSenator ? 'U.S. Senator' : 'U.S. Representative';
            $roster = $wikipedia->roster((string) $pol->state, $office, $year, $isSenator ? null : $pol->district);

            if ($roster === null) {
                $stats['no_article']++;

                continue;
            }

            $names = $this->nameVariants((string) $pol->full_name);
            $running = $this->runningFromAny($wikipedia, $names, $roster);

            // Redistricting moves incumbents: someone missing from (or who
            // withdrew in) their old district may be on the ballot in another.
            if ($running !== true && ! $isSenator && $pol->district) {
                $statewide = $wikipedia->roster((string) $pol->state, $office, $year, null);
                if ($statewide !== null && $this->runningFromAny($wikipedia, $names, $statewide) === true) {
                    $running = true;
                }
            }

            if ($running === null) {
                $stats['unlisted']++;
                $unlisted[] = [$pol->id, $pol->full_name, $pol->district ?: $pol->state, $roster['title']];

                continue;
            }

            $stats[$running ? 'running' : 'not_running']++;
            if ((bool) $pol->is_running_candidate === $running) {
                continue;
            }

            $stats['changed']++;
            $this->line(sprintf('  #%d %s (%s) → %s', $pol->id, $pol->full_name, $pol->district ?: $pol->state, $running ? 'running' : 'not running'));
            if (! $dryRun) {
                $pol->update(['is_running_candidate' => $running, 'status_updated_at' => now()]);
            }
        }

        if ($unlisted !== []) {
            $this->newLine();
            $this->warn('Not on the race article — left unchanged (retiring, another office, or a name mismatch):');
            $this->table(['id', 'name', 'seat', 'article'], $unlisted);
        }

        $this->info(sprintf(
            "\nDone%s: %d running | %d not running | %d unlisted | %d without an article | %d changed",
            $dryRun ? ' (dry-run)' : '',
            $stats['running'], $stats['not_running'], $stats['unlisted'], $stats['no_article'], $stats['changed'],
        ));

        if (! $dryRun && $stats['changed'] > 0) {
            MapCacheNotice::afterWrite($this);
        }

        return self::SUCCESS;
    }

    /**
     * Congress data writes nicknames in quotes (Eric A. "Rick" Crawford);
     * Wikipedia lists the nickname ("Rick Crawford").
     *
     * @return array<int, string>
     */
    private function nameVariants(string $name): array
    {
        $names = [$name];
        if (preg_match('/["“]([^"”]+)["”]/u', $name, $m)) {
            $surname = trim((string) preg_replace('/,?\s+(jr|sr|ii|iii|iv)\.?$/i', '', trim((string) preg_replace('/.*["”]/u', '', $name))));
            if ($surname !== '') {
                $names[] = trim($m[1]).' '.$surname;
            }
        }

        return $names;
    }

    /**
     * @param  array<int, string>  $names
     * @param  array{pending: bool, advanced: string[], eliminated: string[], withdrawn: string[], declared: string[]}  $roster
     */
    private function runningFromAny(WikipediaPrimaryResultsService $wikipedia, array $names, array $roster): ?bool
    {
        $verdict = null;
        foreach ($names as $name) {
            $verdict = $this->runningFrom($wikipedia, $name, $roster) ?? $verdict;
            if ($verdict === true) {
                return true;
            }
        }

        return $verdict;
    }

    /**
     * @param  array{pending: bool, advanced: string[], eliminated: string[], withdrawn: string[], declared: string[]}  $roster
     */
    private function runningFrom(WikipediaPrimaryResultsService $wikipedia, string $name, array $roster): ?bool
    {
        $advanced = $wikipedia->contains($roster['advanced'], $name);
        $out = $wikipedia->contains($roster['eliminated'], $name) || $wikipedia->contains($roster['withdrawn'], $name);

        // Before the primary, outcome headings can't be trusted (see the
        // service); a declared or listed candidate is simply running.
        if ($roster['pending']) {
            if ($wikipedia->contains($roster['withdrawn'], $name)) {
                return false;
            }

            return $advanced || $wikipedia->contains($roster['declared'], $name) ? true : null;
        }

        if ($advanced !== $out) {
            return $advanced;
        }

        return null;
    }
}
