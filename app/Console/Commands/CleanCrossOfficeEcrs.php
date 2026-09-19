<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** Report ambiguous cross-office records without inferring election outcomes. */
class CleanCrossOfficeEcrs extends Command
{
    protected $signature = 'politicians:clean-cross-office-ecrs
        {--state=    : Restrict to a single two-letter state code}
        {--scope=state : Which governance_level to clean: state | all}
        {--dry-run   : Report only — no DB writes}';

    protected $description = 'Report cross-office candidate records requiring source verification; never infer a loss.';

    public function handle(): int
    {
        $stateOpt = strtoupper(trim((string) $this->option('state')));
        $scope   = strtolower(trim((string) $this->option('scope')));
        $today   = now()->toDateString();

        $this->line('[REPORT ONLY — no election outcomes will be changed]');
        $this->line("Scope: {$scope} | State filter: " . ($stateOpt ?: 'all states'));

        // ── Build the query ───────────────────────────────────────────────────
        // Find ECR rows that:
        //  1. Match a politician who is seated in a DIFFERENT office
        //  2. Are not already resolved (not 'eliminated' or 'advanced_to_general')
        //  3. Have an election_date in the past OR no election_date
        //
        // Driver-branching JSON extraction: SQLite (test env) lacks MySQL's
        // JSON_UNQUOTE(JSON_EXTRACT(...)); both forms return the unquoted scalar.
        $primaryResultExpr = DB::connection()->getDriverName() === 'sqlite'
            ? "json_extract(e.payload,'$.primary_result')"
            : "JSON_UNQUOTE(JSON_EXTRACT(e.payload,'$.primary_result'))";

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
              AND COALESCE({$primaryResultExpr},'') NOT IN ('eliminated','advanced_to_general')
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

        $this->line('Found ' . count($rows) . ' row(s) requiring source verification:');

        // ── Group summary by state for readable output ────────────────────────
        $byState = [];
        foreach ($rows as $row) {
            $byState[$row->state][] = $row;
        }

        $updated  = 0;

        foreach ($byState as $state => $stateRows) {
            foreach ($stateRows as $row) {
                $this->line(
                    "  [{$state}] {$row->full_name} — " .
                    "seated: {$row->seated_office} | ECR office: {$row->ecr_office} | " .
                    "ecr_id: #{$row->id}" .
                    ($row->election_date ? " | election: {$row->election_date}" : '')
                );

                $updated++;
            }
        }

        $this->warn("Review required: {$updated} cross-office record(s). No records changed.");

        return self::SUCCESS;
    }
}
