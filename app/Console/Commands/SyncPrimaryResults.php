<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Syncs primary election results into the election_candidate_records payload.
 *
 * For each state-level candidate record where the election_date is in the past
 * and primary_result is not yet set, this command:
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

    protected $description = 'Sync primary election results for statewide candidates from Ballotpedia/Wikipedia.';

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

        // Fetch statewide candidate records that are still relevant to primary sync.
        // We intentionally do NOT require election_date < today because Ballotpedia
        // imports often store the GENERAL election date on the row. If we filtered by
        // past election_date, eliminated primary candidates would never be processed.
        // The district filter remains broad; governance_level=State is the scope guard.
        $query = DB::table('election_candidate_records')
            ->whereRaw('LOWER(COALESCE(governance_level,\'\')) = ?', ['state'])
            // Ignore seated officeholder rows; they are not on the primary ballot.
            ->whereRaw("COALESCE(JSON_UNQUOTE(JSON_EXTRACT(payload,'$.status')),'') != 'seated'")
            // Exclude rows already stamped eliminated to avoid re-processing them
            ->whereRaw("COALESCE(JSON_UNQUOTE(JSON_EXTRACT(payload,'$.primary_result')),'') != 'eliminated'");

        if ($stateFilter) {
            $query->whereRaw('UPPER(COALESCE(state,\'\')) = ?', [$stateFilter]);
        }

        $records = $query->get(['id', 'external_candidate_id', 'full_name', 'political_office',
                                'party_affiliation', 'state', 'election_date', 'source', 'payload']);

        $this->info("Found {$records->count()} statewide candidate record(s) eligible for primary-result sync.");

        $stats = ['advanced' => 0, 'eliminated' => 0, 'unknown' => 0, 'skipped' => 0];

        foreach ($records as $rec) {
            $payload = json_decode($rec->payload ?? '{}', true) ?: [];

            // Skip records where primary_result is already set, unless --force
            if (isset($payload['primary_result']) && $payload['primary_result'] !== null && !$force) {
                $stats['skipped']++;
                continue;
            }

            // Skip seated officeholders — they are not candidates on the ballot
            if (($payload['status'] ?? null) === 'seated') {
                $stats['skipped']++;
                continue;
            }

            $this->line("\n<fg=green>[{$rec->state}]</> {$rec->full_name} — {$rec->political_office}");

            $result = $this->resolvePrimaryResult($rec->full_name, $rec->state, $rec->political_office, $rec->election_date);

            if ($result === null) {
                $this->line("  <fg=yellow>✗ Could not determine primary result</>");
                $stats['unknown']++;
                continue;
            }

            $this->line("  <fg=cyan>✓ primary_result:</> {$result}");

            if (!$dryRun) {
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
            }

            $stats[$result === 'advanced_to_general' ? 'advanced' : 'eliminated']++;
            usleep(self::DELAY_MS * 1000);
        }

        $suffix = $dryRun ? ' (dry-run)' : '';
        $this->info(
            "\nSync complete{$suffix}: {$stats['advanced']} advanced | " .
            "{$stats['eliminated']} eliminated | {$stats['unknown']} unknown | {$stats['skipped']} skipped"
        );

        return self::SUCCESS;
    }

    /**
     * Try Ballotpedia then Wikipedia to determine if a candidate advanced or was eliminated.
     */
    private function resolvePrimaryResult(
        string $name,
        string $state,
        string $office,
        string $electionDate
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
    private function checkUrl(string $url, string $name, string $electionDate): ?string
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
    private function classify(string $text, string $electionDate): ?string
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
