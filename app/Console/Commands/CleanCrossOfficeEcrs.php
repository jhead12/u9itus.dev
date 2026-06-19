<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Finds election_candidate_records rows where the matched politician is
 * already seated in a DIFFERENT office and stamps them as eliminated so
 * they never bleed into the wrong map panel.
 *
 * Examples of what this catches:
 *  - A Lt. Governor scraped under "Governor" (Eleni Kounalakis pattern)
 *  - A seated Senator whose old House ECR row still has no primary_result
 *  - Any stale Ballotpedia row for a race a politician didn't win
 *
 * Safe guards:
 *  - Never touches rows already marked 'advanced_to_general' (legitimate candidacy)
 *  - Never touches rows whose election_date is in the future (race still open)
 *  - Preserves all other payload fields; only stamps primary_result
 *  - --dry-run reports without writing
 *  - --scope=state (default) only touches state-level ECRs that affect the
 *    statewide map panel. Use --scope=all to also clean federal/house rows.
 *
 * Usage:
 *   php artisan politicians:clean-cross-office-ecrs
 *   php artisan politicians:clean-cross-office-ecrs --dry-run
 *   php artisan politicians:clean-cross-office-ecrs --scope=all
 *   php artisan politicians:clean-cross-office-ecrs --state=CA --dry-run
 */
class CleanCrossOfficeEcrs extends Command
{
    protected $signature = 'politicians:clean-cross-office-ecrs
        {--state=    : Restrict to a single two-letter state code}
        {--scope=state : Which governance_level to clean: state | all}
        {--dry-run   : Report only — no DB writes}';

    protected $description = 'Mark cross-office ECR rows as eliminated for already-seated politicians.';

    public function handle(): int
    {
        $dryRun  = (bool)   $this->option('dry-run');
        $stateOpt = strtoupper(trim((string) $this->option('state')));
        $scope   = strtolower(trim((string) $this->option('scope')));
        $today   = now()->toDateString();

        $this->line($dryRun ? '[DRY RUN — no writes]' : '[LIVE — writing to DB]');
        $this->line("Scope: {$scope} | State filter: " . ($stateOpt ?: 'all states'));

        // ── Build the query ───────────────────────────────────────────────────
        // Find ECR rows that:
        //  1. Match a politician who is seated in a DIFFERENT office
        //  2. Are not already resolved (not 'eliminated' or 'advanced_to_general')
        //  3. Have an election_date in the past OR no election_date
        $sql = "
            SELECT e.id,
                   e.full_name,
                   e.state,
                   e.political_office     AS ecr_office,
                   e.governance_level     AS ecr_level,
                   p.political_office     AS seated_office,
                   p.term_status,
                   e.election_date,
                   e.payload
            FROM election_candidate_records e
            JOIN politicians p
              ON LOWER(e.full_name)                        = LOWER(p.full_name)
             AND UPPER(COALESCE(e.state,''))               = UPPER(COALESCE(p.state,''))
             AND LOWER(COALESCE(e.political_office,''))    != LOWER(COALESCE(p.political_office,''))
            WHERE p.term_status IN ('seated','current')
              AND COALESCE(
                    JSON_UNQUOTE(JSON_EXTRACT(e.payload,'$.primary_result')),''
                  ) NOT IN ('eliminated','advanced_to_general')
              AND (e.election_date IS NULL OR e.election_date < :today)
        ";

        $bindings = ['today' => $today];

        // Governance-level scope filter
        if ($scope === 'state') {
            $sql .= " AND LOWER(COALESCE(e.governance_level,'')) = 'state'";
        }

        // Optional single-state filter
        if ($stateOpt !== '') {
            $sql .= ' AND UPPER(COALESCE(e.state,\'\')) = :state';
            $bindings['state'] = $stateOpt;
        }

        $sql .= ' ORDER BY e.state, e.full_name';

        $rows = DB::select($sql, $bindings);

        if (empty($rows)) {
            $this->info('No cross-office ECR conflicts found — nothing to do.');
            return self::SUCCESS;
        }

        $this->line('Found ' . count($rows) . ' row(s) to stamp as eliminated:');

        // ── Group summary by state for readable output ────────────────────────
        $byState = [];
        foreach ($rows as $row) {
            $byState[$row->state][] = $row;
        }

        $updated  = 0;
        $byStateCount = [];

        foreach ($byState as $state => $stateRows) {
            foreach ($stateRows as $row) {
                $this->line(
                    "  [{$state}] {$row->full_name} — " .
                    "seated: {$row->seated_office} | ECR office: {$row->ecr_office} | " .
                    "ecr_id: #{$row->id}" .
                    ($row->election_date ? " | election: {$row->election_date}" : '')
                );

                if (! $dryRun) {
                    $payload = json_decode((string) $row->payload, true) ?? [];
                    $payload['primary_result'] = 'eliminated';

                    DB::table('election_candidate_records')
                        ->where('id', $row->id)
                        ->update([
                            'payload'    => json_encode($payload),
                            'updated_at' => now(),
                        ]);
                }

                $updated++;
                $byStateCount[$state] = ($byStateCount[$state] ?? 0) + 1;
            }
        }

        // ── Summary ──────────────────────────────────────────────────────────
        $this->newLine();
        if ($dryRun) {
            $this->warn("[dry-run] Would have stamped {$updated} ECR row(s) across " . count($byStateCount) . ' state(s) as eliminated.');
        } else {
            $this->info("Stamped {$updated} ECR row(s) across " . count($byStateCount) . ' state(s) as eliminated.');
            foreach ($byStateCount as $state => $cnt) {
                $this->line("  {$state}: {$cnt} row(s)");
            }
        }

        return self::SUCCESS;
    }
}
