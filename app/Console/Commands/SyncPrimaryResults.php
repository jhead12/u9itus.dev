<?php

namespace App\Console\Commands;

use App\Models\CandidateIdentityLink;
use App\Services\WikipediaPrimaryResultsService;
use App\Support\ElectionCycle;
use App\Support\MapCacheNotice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Syncs primary election results into the election_candidate_records payload.
 *
 * For each state or federal candidate record that primary_result has not yet
 * settled, this command reads the race's Wikipedia article (MediaWiki API; the
 * "Nominee", "Eliminated in primary" and "Withdrawn" headings). That is the only
 * source: a candidate's own page says "lost the primary" and "conceded" about
 * people they endorsed and about earlier cycles, so page text is never used and a
 * candidate the article does not settle stays unknown.
 *
 * Sets payload.primary_result to one of:
 *   advanced_to_general  — won or advanced from primary
 *   eliminated           — did not advance
 *   (null / missing)     — could not determine
 *
 * Usage:
 *   php artisan politicians:sync-primary-results
 *   php artisan politicians:sync-primary-results --state=CA
 *   php artisan politicians:sync-primary-results --dry-run
 *   php artisan politicians:sync-primary-results --force   # re-check already-set records
 */
class SyncPrimaryResults extends Command
{
    protected $signature = 'politicians:sync-primary-results
        {--state=  : Two-letter state code. Omit to process all states.}
        {--dry-run : Report only — no DB writes.}
        {--force   : Re-check records that already have primary_result set.}
        {--recheck-eliminated : Re-run the classifier on this cycle\'s news-discovered records stamped eliminated; clear a stamp it can no longer support.}
        {--limit=300 : With --recheck-eliminated: max records to re-check per run.}';

    protected $description = 'Sync primary election results for state and federal candidates from Ballotpedia/Wikipedia.';

    private const DELAY_MS = 500;

    /** Which tier produced the last resolvePrimaryResult() answer. */
    private ?string $lastSource = null;

    /** True when the last answer was "left the race" rather than "lost the primary". */
    private bool $lastWithdrew = false;

    private ?WikipediaPrimaryResultsService $wikipediaRaces = null;

    public function handle(): int
    {
        $stateFilter = $this->option('state')
            ? strtoupper(trim((string) $this->option('state')))
            : null;
        $dryRun = (bool) $this->option('dry-run');
        $force  = (bool) $this->option('force');

        if ($this->option('recheck-eliminated')) {
            return $this->recheckEliminated($stateFilter, $dryRun, max(1, (int) $this->option('limit')));
        }

        if ($dryRun) {
            $this->line('<fg=yellow>[dry-run] No DB writes will occur.</>');
        }

        // Fetch state and federal candidate records that are still relevant to primary
        // sync. We intentionally do NOT require election_date < today because Ballotpedia
        // imports often store the GENERAL election date on the row. If we filtered by
        // past election_date, eliminated primary candidates would never be processed.
        // The district filter remains broad; governance_level in (state, federal) is the
        // scope guard — local/city races aren't covered by the Ballotpedia/Wikipedia tiers.
        // Driver-branching JSON extraction so the SQLite test env doesn't choke on
        // MySQL's JSON_UNQUOTE(JSON_EXTRACT(...)). Both return the unquoted scalar.
        $driver = DB::connection()->getDriverName();
        $extract = fn (string $key): string => $driver === 'sqlite'
            ? "json_extract(payload,'{$key}')"
            : "JSON_UNQUOTE(JSON_EXTRACT(payload,'{$key}'))";

        $query = DB::table('election_candidate_records')
            ->whereRaw('LOWER(COALESCE(governance_level,\'\')) IN (?, ?)', ['state', 'federal'])
            // Ignore seated officeholder rows; they are not on the primary ballot.
            ->whereRaw('COALESCE(' . $extract('$.status') . ',\'\') != ?', ['seated'])
            // Exclude rows already stamped eliminated to avoid re-processing them
            ->whereRaw('COALESCE(' . $extract('$.primary_result') . ',\'\') != ?', ['eliminated']);

        if ($stateFilter) {
            $query->whereRaw('UPPER(COALESCE(state,\'\')) = ?', [$stateFilter]);
        }

        $records = $query->get(['id', 'external_candidate_id', 'full_name', 'political_office', 'district',
                                'party_affiliation', 'state', 'election_date', 'source', 'payload']);

        $this->info("Found {$records->count()} state/federal candidate record(s) eligible for primary-result sync.");

        $stats = ['advanced' => 0, 'eliminated' => 0, 'unknown' => 0, 'skipped' => 0, 'politician_updated' => 0];

        foreach ($records as $rec) {
            $this->processRecord($rec, $force, $dryRun, $stats);
        }

        $suffix = $dryRun ? ' (dry-run)' : '';
        $this->info(
            "\nSync complete{$suffix}: {$stats['advanced']} advanced | " .
            "{$stats['eliminated']} eliminated | {$stats['unknown']} unknown | {$stats['skipped']} skipped | " .
            "{$stats['politician_updated']} politician(s) updated"
        );

        return self::SUCCESS;
    }

    /**
     * The classifier used to read "conceded" / "lost the primary" from any year, so news-discovered
     * records for sitting senators and nominees were stamped eliminated — and the normal sync never
     * looks at a record already stamped. Re-run the fixed classifier over them:
     *
     *   still eliminated  → left alone
     *   now "advanced"    → left alone and reported (the advanced signals are too loose to overrule it)
     *   nothing supports it → the stamp is cleared (the record goes back to "unknown")
     *
     * A linked profile the old run set to "eliminated" goes back to "running". One a later step
     * marked "lost" is only reported — that status may be a real earlier loss. A stamp set by hand
     * (candidates:set-primary-result) is never touched.
     */
    private function recheckEliminated(?string $stateFilter, bool $dryRun, int $limit): int
    {
        $driver = DB::connection()->getDriverName();
        $extract = fn (string $key): string => $driver === 'sqlite'
            ? "json_extract(payload,'{$key}')"
            : "JSON_UNQUOTE(JSON_EXTRACT(payload,'{$key}'))";
        $cycleStart = now()->startOfYear()->toDateString();

        $records = DB::table('election_candidate_records')
            ->where('source', 'candidate_discovery')
            ->whereRaw($extract('$.primary_result').' = ?', ['eliminated'])
            ->whereRaw('COALESCE('.$extract('$.result_source').",'') != ?", ['manual'])
            ->where(fn ($q) => $q->whereNull('election_date')->orWhere('election_date', '>=', $cycleStart))
            ->when($stateFilter, fn ($q) => $q->whereRaw('UPPER(COALESCE(state, \'\')) = ?', [$stateFilter]))
            ->orderBy('id')
            ->limit($limit)
            ->get(['id', 'full_name', 'state', 'political_office', 'district', 'election_date', 'payload']);

        $this->line($dryRun ? '<fg=yellow>[dry-run] No DB writes will occur.</>' : '<comment>[LIVE — writing changes]</comment>');
        $this->info("Re-checking {$records->count()} record(s) stamped eliminated...");

        $stats = ['kept' => 0, 'conflicting' => 0, 'cleared' => 0, 'profiles' => 0];

        foreach ($records as $rec) {
            $payload = json_decode($rec->payload ?? '{}', true) ?: [];
            $date = $rec->election_date ?: ElectionCycle::generalElectionDate(ElectionCycle::year());
            $result = $this->resolvePrimaryResult($rec->full_name, $rec->state, (string) $rec->political_office, $date, $rec->district);

            if ($result === 'eliminated') {
                $stats['kept']++;
                usleep(self::DELAY_MS * 1000);

                continue;
            }

            // The "advanced" signals are loose (a page that merely says "advance" matches), so a
            // record the classifier now calls advanced is not flipped — it is reported to check by hand.
            if ($result === 'advanced_to_general') {
                $this->line(sprintf('  <fg=yellow>#%d</> %s (%s, %s) — conflicting signals, left eliminated; verify by hand', $rec->id, $rec->full_name, $rec->state, $rec->political_office));
                $stats['conflicting']++;
                usleep(self::DELAY_MS * 1000);

                continue;
            }

            $this->line(sprintf('  <fg=cyan>#%d</> %s (%s, %s) — eliminated → cleared (nothing supports it)', $rec->id, $rec->full_name, $rec->state, $rec->political_office));
            $stats['cleared']++;

            if (! $dryRun) {
                unset($payload['elimination_note'], $payload['primary_result'], $payload['primary_date']);
                $payload['result_rechecked_at'] = now()->toDateString();

                DB::table('election_candidate_records')->where('id', $rec->id)->update(['payload' => json_encode($payload), 'updated_at' => now()]);
            }

            foreach (CandidateIdentityLink::where('election_candidate_record_id', $rec->id)->with('politician')->get() as $link) {
                $politician = $link->politician;
                if ($politician === null) {
                    continue;
                }
                if ($politician->term_status === 'eliminated') {
                    $this->line("    profile #{$politician->id} eliminated → running");
                    $stats['profiles']++;
                    if (! $dryRun) {
                        $politician->update(['term_status' => 'running', 'is_running_candidate' => true, 'status_updated_at' => now()]);
                    }
                } elseif ($politician->term_status === 'lost') {
                    $this->line("    <fg=yellow>profile #{$politician->id} is marked lost — left alone, review by hand</>");
                }
            }

            usleep(self::DELAY_MS * 1000);
        }

        $this->info(sprintf("\nRe-check complete%s: %d still eliminated | %d conflicting (left as is) | %d stamps cleared | %d profile(s) restored", $dryRun ? ' (dry-run)' : '', $stats['kept'], $stats['conflicting'], $stats['cleared'], $stats['profiles']));

        if (! $dryRun) {
            MapCacheNotice::afterWrite($this);
        }

        return self::SUCCESS;
    }

    /**
     * @param array<string,int> $stats
     */
    private function processRecord(object $rec, bool $force, bool $dryRun, array &$stats): void
    {
        $payload = json_decode($rec->payload ?? '{}', true) ?: [];

        // Skip records already settled (advanced/eliminated), unless --force. Candidate discovery
        // stamps new rows "running", which is a placeholder, not a result — skipping on it meant a
        // plain run never looked at any discovery candidate, so losers and withdrawals stayed on the map.
        if (in_array($payload['primary_result'] ?? null, ['advanced_to_general', 'eliminated'], true) && !$force) {
            $stats['skipped']++;
            return;
        }

        // Skip seated officeholders — they are not candidates on the ballot
        if (($payload['status'] ?? null) === 'seated') {
            $stats['skipped']++;
            return;
        }

        if ($rec->election_date === null) {
            $this->line("\n<fg=yellow>[{$rec->state}]</> {$rec->full_name} — {$rec->political_office} — skipped (no election_date)");
            Log::warning('SyncPrimaryResults: skipping record with null election_date', ['id' => $rec->id, 'name' => $rec->full_name]);
            $stats['skipped']++;
            return;
        }

        $this->line("\n<fg=green>[{$rec->state}]</> {$rec->full_name} — {$rec->political_office}");

        $result = $this->resolvePrimaryResult($rec->full_name, $rec->state, $rec->political_office, $rec->election_date, $rec->district);

        if ($result === null) {
            $this->line("  <fg=yellow>✗ Could not determine primary result</>");
            $stats['unknown']++;
            return;
        }

        $this->line("  <fg=cyan>✓ primary_result:</> {$result}");

        if ($dryRun) {
            $this->reportDryRunElimination($rec, $result);
        } else {
            $this->persistResult($rec, $payload, $result, $stats);
        }

        $stats[$result === 'advanced_to_general' ? 'advanced' : 'eliminated']++;
        usleep(self::DELAY_MS * 1000);
    }

    /**
     * @param array<string,mixed> $payload
     * @param array<string,int> $stats
     */
    private function persistResult(object $rec, array $payload, string $result, array &$stats): void
    {
        $payload['primary_result'] = $result;
        $payload['primary_date']   = $rec->election_date;
        if ($this->lastSource !== null) {
            $payload['result_source'] = $this->lastSource;
        }
        if ($result === 'advanced_to_general') {
            // Estimate general election date (first Tuesday after first Monday in November)
            $year = (int) substr($rec->election_date, 0, 4);
            $payload['general_date'] = $this->generalElectionDate($year);
        } elseif ($result === 'eliminated') {
            $payload['elimination_note'] = $this->lastWithdrew
                ? 'Withdrew from the race (per Wikipedia)'
                : "Did not advance from {$rec->election_date} primary";
            if ($this->lastWithdrew) {
                $payload['withdrawn'] = true;
            }
        }

        DB::table('election_candidate_records')
            ->where('id', $rec->id)
            ->update([
                'payload'    => json_encode($payload),
                'updated_at' => now(),
            ]);

        if ($result === 'eliminated' && $this->markPoliticianEliminated($rec->id)) {
            $stats['politician_updated']++;
        }
    }

    /**
     * --dry-run: surface whether a linked politician would be updated, without writing.
     */
    private function reportDryRunElimination(object $rec, string $result): void
    {
        if ($result !== 'eliminated') {
            return;
        }

        $link = CandidateIdentityLink::where('election_candidate_record_id', $rec->id)->first();
        if ($link && $link->politician && !in_array($link->politician->term_status, ['seated', 'retired'], true)) {
            $this->line("  <fg=gray>[dry-run] would set politician #{$link->politician_id} term_status=eliminated</>");
        }
    }

    /**
     * Propagate a primary-loss result onto the linked Politician record (if any),
     * so voters see the "eliminated" status immediately rather than waiting for
     * the post-general reconciliation pass. Never clobbers a resolved status
     * (seated/retired) — those take precedence over a stale primary read.
     */
    private function markPoliticianEliminated(int $electionCandidateRecordId): bool
    {
        $link = CandidateIdentityLink::where('election_candidate_record_id', $electionCandidateRecordId)->first();
        $politician = $link?->politician;

        if (!$politician || in_array($politician->term_status, ['seated', 'retired'], true)) {
            return false;
        }

        $politician->update([
            'term_status'          => 'eliminated',
            'is_running_candidate' => false,
            'status_updated_at'    => now(),
        ]);

        $this->line("  <fg=cyan>✓ politician #{$politician->id} term_status → eliminated</>");

        return true;
    }

    /**
     * Read the candidate's result from the race's Wikipedia article. Page text (a Ballotpedia or
     * biography page) is deliberately not used: it says "lost the primary" and "conceded" about the
     * people a candidate endorsed and about earlier cycles, and stamped sitting members eliminated.
     */
    private function resolvePrimaryResult(
        string $name,
        string $state,
        string $office,
        ?string $electionDate,
        ?string $district = null
    ): ?string {
        $this->lastSource = null;
        $this->lastWithdrew = false;

        // ── Tier 0: Wikipedia race article ────────────────────────────────────
        // Structured headings beat keyword matching, and the MediaWiki API is
        // reachable from GitHub Actions when Ballotpedia is not.
        $year = $electionDate !== null ? (int) substr($electionDate, 0, 4) : 0;
        if ($year > 0) {
            $result = ($this->wikipediaRaces ??= new WikipediaPrimaryResultsService())
                ->resultFor($name, $state, $office, $district, $year);
            if ($result !== null) {
                $this->lastSource = 'wikipedia_race_page';
                $this->line('  <fg=gray>[wikipedia race page]</>');

                // A withdrawn candidate is off the ballot, so the map treats them like an
                // eliminated one; the payload records that they left rather than lost.
                if ($result === 'withdrawn') {
                    $this->lastWithdrew = true;

                    return 'eliminated';
                }

                return $result;
            }
        }

        return null;
    }

    /**
     * Returns the general election date string (first Tuesday after first Monday in November).
     */
    private function generalElectionDate(int $year): string
    {
        // First Monday in November
        $nov1    = new \DateTime("{$year}-11-01");
        $dayOfWeek = (int) $nov1->format('N'); // 1=Mon … 7=Sun
        $daysToMonday = ($dayOfWeek === 1) ? 0 : (8 - $dayOfWeek);
        $firstMonday = (clone $nov1)->modify("+{$daysToMonday} days");
        $electionDay = (clone $firstMonday)->modify('+1 day'); // Tuesday after first Monday
        return $electionDay->format('Y-m-d');
    }
}
