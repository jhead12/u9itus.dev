<?php

namespace App\Console\Commands;

use App\Models\BallotMeasureCommittee;
use App\Support\MeasureCommitteePriority;
use App\Support\MeasureCommitteeRules;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Nightly integrity audit for ballot measure committee links, run in
 * .github/workflows/politicians-cleanup.yml ahead of politicians:check-cleanup-health.
 *
 *  1. Re-checks every non-rejected link against MeasureCommitteeRules::flags(). The data
 *     a link was checked against can drift (a measure's state or number is corrected, the
 *     state's registered finance site changes), so a verified link that picks up a flag its
 *     reviewer didn't see goes back to pending — and off the public page — until reviewed.
 *  2. Rescores the pending queue (MeasureCommitteePriority).
 *  3. Self-reports to politician_cleanup_run_metrics as 'measure-committee-links', so the
 *     health check alerts if the audit stops running or open flags start climbing.
 */
class AuditMeasureCommitteeLinks extends Command
{
    protected $signature = 'ballot-measures:audit-committee-links {--dry-run : Report only, no DB writes}';

    protected $description = 'Re-check ballot measure committee links for mis-assignment, return drifted verified links to review, and rescore the review queue.';

    public function handle(): int
    {
        $startedAt = now();
        $dryRun = (bool) $this->option('dry-run');
        $findings = 0;
        $demoted = 0;
        $breakdown = [];

        $links = BallotMeasureCommittee::query()
            ->where('status', '!=', BallotMeasureCommittee::STATUS_REJECTED)
            ->with('ballotMeasure')
            ->get();

        foreach ($links as $link) {
            $flags = MeasureCommitteeRules::flags($link);
            $open = MeasureCommitteeRules::unacknowledged($flags, $link->acknowledged_flags);

            if ($open !== []) {
                $findings++;
                foreach ($open as $flag) {
                    $breakdown[$flag] = ($breakdown[$flag] ?? 0) + 1;
                }
            }

            if ($dryRun) {
                continue;
            }

            $link->integrity_flags = $flags;

            if ($link->status === BallotMeasureCommittee::STATUS_VERIFIED && $open !== []) {
                $link->status = BallotMeasureCommittee::STATUS_PENDING;
                $link->verified_at = null;
                $link->verified_by_user_id = null;
                $link->review_note = trim(($link->review_note ? $link->review_note."\n" : '')
                    .'Returned to review by audit on '.now()->toDateString().': '.implode(', ', $open));
                $demoted++;
                $this->warn("Returned to review: #{$link->id} {$link->committee_name} ({$link->state} {$link->committee_id}) — ".implode(', ', $open));
            }

            if ($link->isDirty()) {
                $link->save();
            }
        }

        $scored = $dryRun ? 0 : MeasureCommitteePriority::scorePending();
        $pending = BallotMeasureCommittee::query()->where('status', BallotMeasureCommittee::STATUS_PENDING)->count();

        if (! $dryRun) {
            DB::table('politician_cleanup_run_metrics')->insert([
                'step' => 'measure-committee-links',
                'scope' => null,
                'exit_code' => self::SUCCESS,
                'findings_count' => $findings,
                'auto_applied_count' => $demoted,
                'queued_count' => $pending,
                'breakdown' => json_encode($breakdown),
                'started_at' => $startedAt,
                'finished_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->info(($dryRun ? '[dry run] ' : '')."Checked {$links->count()} link(s): {$findings} with open flags, {$demoted} returned to review, {$pending} pending ({$scored} scored).");

        return self::SUCCESS;
    }
}
