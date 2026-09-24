<?php

namespace App\Console\Commands;

use App\Models\PoliticianCleanupReview;
use App\Models\User;
use App\Notifications\PoliticiansCleanupHealthNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Analyze + Control pass over politician_cleanup_run_metrics (populated by
 * FlagSuspectProfiles, PruneJunkEcrs and RaceCountControl self-reporting at the end of their own
 * handle()). Runs daily as the last step of .github/workflows/politicians-cleanup.yml.
 *
 * Three checks, each independently simple — this is deliberately not a full
 * statistical-process-control system:
 *
 *  1. Control — silence: has each instrumented step run in the last --max-age-hours?
 *  2. Control — control-limit anomaly. For a filter step (it finds junk to remove), does
 *     today's findings_count fall to zero (or spike past 3x) against its trailing 14-day
 *     average, when that average was materially non-zero? This is exactly what would have
 *     surfaced "flag-suspect-profiles found 0 today" as an anomaly instead of a silent
 *     success. For race-count-control, whose count is races still out of control after the
 *     filters, zero is the goal: it alerts when the count rises above its baseline instead.
 *  3. Analyze — self-consistency: does the latest national flag-suspect-profiles run's
 *     claimed queued_count match its pending deactivate reviews actually in the queue? A
 *     mismatch means the pipeline's own reporting disagrees with the queue's real state.
 *
 * Also prints a 14-day Pareto breakdown (by step + reason) to the log for a human to
 * scan — the "Analyze" half of the DMAIC framing, kept as plain console output rather
 * than a dashboard.
 */
class CheckPoliticiansCleanupHealth extends Command
{
    protected $signature = 'politicians:check-cleanup-health
        {--max-age-hours=26 : Alert if an instrumented step has not run within this window}
        {--baseline-days=14 : Trailing window used to compute each step\'s average finding rate}';

    protected $description = 'Health check for the politicians:cleanup-workflow pipeline: silence detection, finding-rate anomalies, and a queue self-consistency check.';

    /**
     * Steps self-reporting to politician_cleanup_run_metrics, and whether a finding count of
     * zero is the goal (true) or a sign the step silently stopped finding anything (false).
     */
    private const STEPS = [
        'flag-suspect-profiles' => false,
        'prune-junk-ecrs' => false,
        'race-count-control' => true,
    ];

    public function handle(): int
    {
        $maxAgeHours = max(1, (int) $this->option('max-age-hours'));
        $baselineDays = max(1, (int) $this->option('baseline-days'));
        $healthy = true;

        foreach (self::STEPS as $step => $zeroIsGoal) {
            $healthy = $this->checkSilence($step, $maxAgeHours) && $healthy;
            $healthy = $this->checkFindingRate($step, $baselineDays, $zeroIsGoal) && $healthy;
        }

        $healthy = $this->checkSelfConsistency() && $healthy;

        $this->pareto($baselineDays);

        if ($healthy) {
            $this->info('Politicians cleanup pipeline: healthy.');
        }

        return self::SUCCESS;
    }

    private function checkSilence(string $step, int $maxAgeHours): bool
    {
        $latest = DB::table('politician_cleanup_run_metrics')
            ->where('step', $step)->max('started_at');

        if ($latest === null || Carbon::parse($latest)->diffInHours(now()) > $maxAgeHours) {
            $summary = "No {$step} run recorded in the last {$maxAgeHours}h.";
            $this->error($summary);
            $this->notifyAdmins('missing_or_stale', $summary, ['step' => $step, 'last_seen' => $latest ?? 'never']);

            return false;
        }

        return true;
    }

    private function checkFindingRate(string $step, int $baselineDays, bool $zeroIsGoal = false): bool
    {
        $today = (int) (DB::table('politician_cleanup_run_metrics')
            ->where('step', $step)
            ->where('started_at', '>=', now()->startOfDay())
            ->max('findings_count') ?? 0);

        $avg = (float) (DB::table('politician_cleanup_run_metrics')
            ->where('step', $step)
            ->where('started_at', '>=', now()->subDays($baselineDays))
            ->where('started_at', '<', now()->startOfDay())
            ->avg('findings_count') ?? 0.0);

        if ($zeroIsGoal) {
            // Out-of-control races should only fall. A rise of more than two above the
            // baseline means new junk is getting past the filters.
            if ($today > (int) ceil($avg) + 2) {
                $summary = "{$step} found {$today} today, up from a {$baselineDays}-day average of ".round($avg, 1).'.';
                $this->warn($summary);
                $this->notifyAdmins('finding_rate_spike', $summary, ['step' => $step, 'today' => $today, 'baseline_avg' => round($avg, 1)]);

                return false;
            }

            return true;
        }

        if ($avg >= 3.0 && $today === 0) {
            $summary = "{$step} found 0 today, vs a {$baselineDays}-day average of ".round($avg, 1).'.';
            $this->error($summary);
            $this->notifyAdmins('finding_rate_zero', $summary, ['step' => $step, 'today' => $today, 'baseline_avg' => round($avg, 1)]);

            return false;
        }

        if ($avg > 0.0 && $today > $avg * 3) {
            $summary = "{$step} found {$today} today, more than 3x its {$baselineDays}-day average of ".round($avg, 1).'.';
            $this->warn($summary);
            $this->notifyAdmins('finding_rate_spike', $summary, ['step' => $step, 'today' => $today, 'baseline_avg' => round($avg, 1)]);

            return false;
        }

        return true;
    }

    /**
     * Compares flag-suspect-profiles' self-reported queued_count against the deactivate
     * reviews actually pending. Its queued_count is every finding it left for a human —
     * including ones already pending from an earlier run — and a national run retires the
     * pending reviews that no longer apply, so afterwards the two should match. Other steps
     * queue merge and name reviews, which is why only deactivate reviews are counted.
     */
    private function checkSelfConsistency(): bool
    {
        $latest = DB::table('politician_cleanup_run_metrics')
            ->where('step', 'flag-suspect-profiles')
            ->whereNull('scope')
            ->where('started_at', '>=', now()->startOfDay())
            ->orderByDesc('started_at')->orderByDesc('id')
            ->first();

        if ($latest === null) {
            return true;
        }

        $claimed = (int) $latest->queued_count;
        // Same filter FlagSuspectProfiles::retireResolved() uses to recognise its own reviews.
        $actual = PoliticianCleanupReview::query()
            ->where('review_type', PoliticianCleanupReview::TYPE_DEACTIVATE)
            ->where('status', PoliticianCleanupReview::STATUS_PENDING)
            ->get(['payload'])
            ->filter(fn (PoliticianCleanupReview $r) => str_starts_with((string) ($r->payload['source'] ?? ''), 'flag-suspect-profiles'))
            ->count();

        if ($claimed !== $actual) {
            $summary = "flag-suspect-profiles claimed {$claimed} queued today, but {$actual} deactivate review(s) are pending.";
            $this->error($summary);
            $this->notifyAdmins('self_consistency_mismatch', $summary, ['claimed' => $claimed, 'actual' => $actual]);

            return false;
        }

        return true;
    }

    private function pareto(int $baselineDays): void
    {
        $rows = DB::table('politician_cleanup_run_metrics')
            ->where('started_at', '>=', now()->subDays($baselineDays))
            ->get(['step', 'breakdown']);

        $tally = [];
        foreach ($rows as $row) {
            $breakdown = json_decode((string) $row->breakdown, true) ?: [];
            foreach ($breakdown as $reason => $count) {
                $key = "{$row->step} · {$reason}";
                $tally[$key] = ($tally[$key] ?? 0) + (int) $count;
            }
        }

        arsort($tally);

        $this->newLine();
        $this->line("<fg=cyan;options=bold>{$baselineDays}-day Pareto (step · reason → count)</>");
        foreach (array_slice($tally, 0, 15, true) as $key => $count) {
            $this->line(sprintf('  %-50s %d', $key, $count));
        }
    }

    /** @param  array<string, mixed>  $details */
    private function notifyAdmins(string $eventType, string $summary, array $details): void
    {
        $admins = User::query()->where('user_type', 'admin')->get();
        Notification::send($admins, new PoliticiansCleanupHealthNotification($eventType, $summary, $details));
    }
}
