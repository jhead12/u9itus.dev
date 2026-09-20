<?php

namespace App\Console\Commands;

use App\Support\PoliticianDataRules;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Single entry point tying together the data-quality cleanup pieces that
 * used to be a scattered set of manual-only commands: name repair, the
 * integrity auditor, lifecycle (retired/lost) reconciliation, dedup
 * detection, and ECR pruning. Safe, reversible normalizations apply
 * automatically; anything destructive (merges, deletes) only ever gets
 * queued to politician_cleanup_reviews for a human to approve — see
 * PoliticianCleanupReview / AdminController@dataQualityReviews.
 *
 * Runs daily via the scheduler (routes/console.php); safe to run by hand
 * too, including with --dry-run to preview without writing anything.
 *
 * Steps, in order:
 *   1. politicians:repair-names            — strip junk leading qualifiers, auto-apply
 *   2. politicians:audit-data-integrity     — normalize party/state/term_status, deactivate
 *                                              unfixable artifact names, auto-apply
 *   3. politicians:reconcile-status(+       — deactivate stale unclaimed rows, auto-apply
 *      -state-local)
 *   4. politicians:dedupe (both scopes)     — NEVER auto-applies; enqueues for review
 *   5. politicians:prune-junk-ecrs          — name/stale/cross_state/dup, plus (--wikipedia) news-
 *                                             discovered rows not on the race's Wikipedia field, auto-apply
 *      (already conservative: never touches identity-linked rows)
 *   6. politicians:flag-suspect-profiles    — profiles that duplicate a sitting official
 *                                              (cross-state impostors, "Greg Abbott's") are
 *                                              deactivated with an audit row; other headline
 *                                              names are queued for review
 *   7. politicians:dedupe-by-fec            — rows sharing an FEC candidate id. The one merge
 *                                              step that auto-applies, and only when the FEC's
 *                                              own record confirms both names (see its docblock);
 *                                              everything else is queued for review
 *
 * --state=NC,NY,TX targets those states only (steps 1, 2, 4-7); the national
 * lifecycle reconciliation in step 3 is skipped for a targeted run.
 *
 * Usage:
 *   php artisan politicians:cleanup-workflow                 # live run, all scopes
 *   php artisan politicians:cleanup-workflow --dry-run
 *   php artisan politicians:cleanup-workflow --scope=federal
 */
class PoliticiansCleanupWorkflow extends Command
{
    protected $signature = 'politicians:cleanup-workflow
        {--dry-run          : Preview every step without writing anything}
        {--scope=all        : all|federal|state-local — narrows lifecycle reconciliation + dedup}
        {--state=           : Comma-separated state codes to target (e.g. NC,NY,TX); default is every state}';

    protected $description = 'Run the full politician data-quality cleanup pipeline: name repair, integrity audit, lifecycle reconciliation, dedup detection, and ECR pruning.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $scope = (string) $this->option('scope');
        if (! in_array($scope, ['all', 'federal', 'state-local'], true)) {
            $this->error("Invalid --scope '{$scope}'. Must be 'all', 'federal', or 'state-local'.");

            return self::FAILURE;
        }

        $states = $this->parseStates((string) $this->option('state'));
        if ($states === null) {
            $this->error("Invalid --state '{$this->option('state')}'. Use two-letter codes such as NC,NY,TX.");

            return self::FAILURE;
        }
        if ($states !== []) {
            $this->info('Targeting states: '.implode(', ', $states));
        }

        $results = [];

        $this->section('1/7 · Repairing junk names');
        $results['repair-names'] = $this->callForStates('politicians:repair-names', $dryRun ? [] : ['--apply' => true, '--enqueue-review' => true], $states);

        // politicians:audit-data-integrity intentionally exits non-zero
        // whenever unresolved violations remain (it doubles as a CI gate) —
        // that's routine backlog, not a pipeline failure, so its exit code
        // is logged but doesn't count toward this command's own exit code.
        $this->section('2/7 · Auditing data integrity');
        $auditExitCode = $this->callForStates('politicians:audit-data-integrity', $dryRun ? [] : ['--fix' => true, '--deactivate' => true], $states);

        if ($states !== []) {
            // Lifecycle reconciliation compares every profile against the national
            // seated list, so it can't be narrowed to a state — a targeted run skips it.
            $this->section('3/7 · Lifecycle reconciliation skipped (runs nationally only; omit --state to include it)');
        }

        if ($states === [] && in_array($scope, ['all', 'federal'], true)) {
            $this->section('3/7 · Reconciling federal lifecycle status');
            $results['reconcile-status'] = $this->call('politicians:reconcile-status', $dryRun ? ['--dry-run' => true] : []);
        }

        if ($states === [] && in_array($scope, ['all', 'state-local'], true)) {
            $this->section('3/7 · Reconciling state/local lifecycle status');
            $results['reconcile-status-state-local'] = $this->call('politicians:reconcile-status-state-local', $dryRun ? ['--dry-run' => true] : []);
        }

        $this->section('4/7 · Detecting duplicates (queued for review, never auto-applied)');
        if (in_array($scope, ['all', 'federal'], true)) {
            $results['dedupe-federal'] = $this->callForStates('politicians:dedupe', $dryRun
                ? ['--scope' => 'federal']
                : ['--scope' => 'federal', '--enqueue-review' => true], $states);
        }
        if (in_array($scope, ['all', 'state-local'], true)) {
            $results['dedupe-unclaimed-all'] = $this->callForStates('politicians:dedupe', $dryRun
                ? ['--scope' => 'unclaimed-all']
                : ['--scope' => 'unclaimed-all', '--enqueue-review' => true], $states);
        }

        $this->section('5/7 · Pruning junk election candidate records');
        $results['clean-discovery-names'] = $this->callForStates('candidates:clean-discovery-names', $dryRun ? [] : ['--apply' => true], $states);
        $results['prune-junk-ecrs'] = $this->callForStates('politicians:prune-junk-ecrs', $dryRun ? ['--wikipedia' => true] : ['--apply' => true, '--wikipedia' => true], $states);
        $results['audit-discovery-records'] = $this->callForStates('candidates:audit-records', [], $states);

        $this->section('6/7 · Impostor and headline-text profiles (duplicates of a sitting official are deactivated; the rest queued)');
        $results['flag-suspect-profiles'] = $this->callForStates('politicians:flag-suspect-profiles', $dryRun ? [] : ['--apply' => true], $states);

        $this->section('7/7 · Merging duplicates confirmed by their FEC candidate id (unconfirmed ones queued for review)');
        $results['dedupe-by-fec'] = $this->callForStates('politicians:dedupe-by-fec', $dryRun ? [] : ['--apply' => true], $states);

        $failed = array_filter($results, fn (int $code) => $code !== self::SUCCESS);

        Log::info('politicians:cleanup-workflow summary', [
            'dry_run' => $dryRun,
            'scope' => $scope,
            'states' => $states,
            'results' => $results,
            'audit_data_integrity_exit_code' => $auditExitCode,
        ]);

        $this->newLine();
        if ($failed !== []) {
            $this->error('Cleanup workflow finished with failures in: '.implode(', ', array_keys($failed)));

            return self::FAILURE;
        }

        $this->info('Cleanup workflow complete — all steps succeeded.');

        return self::SUCCESS;
    }

    /**
     * Run a step once, or once per targeted state (worst exit code wins).
     *
     * @param  array<string, mixed>  $args
     * @param  array<int, string>  $states
     */
    private function callForStates(string $command, array $args, array $states): int
    {
        if ($states === []) {
            return $this->call($command, $args);
        }

        $worst = self::SUCCESS;
        foreach ($states as $state) {
            $worst = max($worst, $this->call($command, $args + ['--state' => $state]));
        }

        return $worst;
    }

    /**
     * @return array<int, string>|null  [] for "all states", null when a code is invalid
     */
    private function parseStates(string $raw): ?array
    {
        $codes = array_values(array_unique(array_filter(array_map(
            fn (string $code) => strtoupper(trim($code)),
            explode(',', $raw),
        ))));

        foreach ($codes as $code) {
            if (! in_array($code, PoliticianDataRules::ALLOWED_STATES, true)) {
                return null;
            }
        }

        return $codes;
    }

    private function section(string $title): void
    {
        $this->newLine();
        $this->line("<fg=cyan;options=bold>{$title}</>");
    }
}
