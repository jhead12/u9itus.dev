<?php

namespace App\Console\Commands;

use App\Models\CandidateIdentityLink;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Syncs primary election results into the election_candidate_records payload.
 *
 * For each state or federal candidate record where the election_date is in the
 * past and primary_result is not yet set, this command:
 *
 *  Tier 1 — Ballotpedia candidate page  (HTML scrape, no key required)
 *  Tier 2 — Wikipedia candidate page    (REST summary, free)
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
        {--force   : Re-check records that already have primary_result set.}';

    protected $description = 'Sync primary election results for state and federal candidates from Ballotpedia/Wikipedia.';

    /** Keywords that signal a candidate advanced */
    private const ADVANCED_SIGNALS = [
        'advanced to the general',
        'won the primary',
        'won primary',
        'advanced to general',
        'general election candidate',
        'qualified for the general',
        'top-two primary',
        'advance',
    ];

    /** Keywords that signal a candidate was eliminated */
    private const ELIMINATED_SIGNALS = [
        'lost the primary',
        'lost primary',
        'did not advance',
        'eliminated',
        'failed to advance',
        'defeated in the primary',
        'conceded',
    ];

    private const DELAY_MS = 500;

    public function handle(): int
    {
        $stateFilter = $this->option('state')
            ? strtoupper(trim((string) $this->option('state')))
            : null;
        $dryRun = (bool) $this->option('dry-run');
        $force  = (bool) $this->option('force');

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

        $records = $query->get(['id', 'external_candidate_id', 'full_name', 'political_office',
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
     * @param array<string,int> $stats
     */
    private function processRecord(object $rec, bool $force, bool $dryRun, array &$stats): void
    {
        $payload = json_decode($rec->payload ?? '{}', true) ?: [];

        // Skip records where primary_result is already set, unless --force
        if (isset($payload['primary_result']) && $payload['primary_result'] !== null && !$force) {
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

        $result = $this->resolvePrimaryResult($rec->full_name, $rec->state, $rec->political_office, $rec->election_date);

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
        if ($result === 'advanced_to_general') {
            // Estimate general election date (first Tuesday after first Monday in November)
            $year = (int) substr($rec->election_date, 0, 4);
            $payload['general_date'] = $this->generalElectionDate($year);
        } elseif ($result === 'eliminated') {
            $payload['elimination_note'] = "Did not advance from {$rec->election_date} primary";
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
     * Try Ballotpedia then Wikipedia to determine if a candidate advanced or was eliminated.
     */
    private function resolvePrimaryResult(
        string $name,
        string $state,
        string $office,
        ?string $electionDate
    ): ?string {
        // ── Tier 1: Ballotpedia candidate page ────────────────────────────────
        $bpSlug = str_replace(' ', '_', $name);
        $bpUrl  = "https://ballotpedia.org/{$bpSlug}";
        $result = $this->checkUrl($bpUrl, $name, $electionDate);
        if ($result !== null) {
            $this->line("  <fg=gray>[ballotpedia]</>");
            return $result;
        }

        usleep(self::DELAY_MS * 1000);

        // ── Tier 2: Wikipedia REST summary ────────────────────────────────────
        $wikiSlug = str_replace(' ', '_', $name);
        $wikiUrl  = "https://en.wikipedia.org/api/rest_v1/page/summary/{$wikiSlug}";
        try {
            $resp = Http::timeout(12)
                ->withHeaders(['User-Agent' => 'u9itus-sync/1.0 (primary-results)'])
                ->get($wikiUrl);
            if ($resp->ok()) {
                $text = strtolower((string) ($resp->json('extract') ?? ''));
                $result = $this->classify($text, $electionDate);
                if ($result !== null) {
                    $this->line("  <fg=gray>[wikipedia]</>");
                    return $result;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('SyncPrimaryResults: Wikipedia fetch failed', ['name' => $name, 'error' => $e->getMessage()]);
        }

        return null;
    }

    /**
     * Fetch a URL and classify its text content as advanced/eliminated/null.
     */
    private function checkUrl(string $url, string $name, ?string $electionDate): ?string
    {
        try {
            $resp = Http::timeout(15)
                ->withHeaders(['User-Agent' => 'u9itus-sync/1.0 (primary-results)'])
                ->get($url);
            if (!$resp->ok()) {
                return null;
            }
            // Strip HTML tags, lowercase
            $text = strtolower(strip_tags((string) $resp->body()));
            return $this->classify($text, $electionDate);
        } catch (\Throwable $e) {
            Log::warning('SyncPrimaryResults: HTTP fetch failed', ['url' => $url, 'error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Classify page text as advanced_to_general, eliminated, or null.
     */
    private function classify(string $text, ?string $electionDate): ?string
    {
        foreach (self::ELIMINATED_SIGNALS as $signal) {
            if (str_contains($text, $signal)) {
                return 'eliminated';
            }
        }
        foreach (self::ADVANCED_SIGNALS as $signal) {
            if (str_contains($text, $signal)) {
                return 'advanced_to_general';
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
