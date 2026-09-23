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
 * FlagSuspectProfiles/PruneJunkEcrs self-reporting at the end of their own handle()).
 * Run daily, after politicians:cleanup-workflow (see .github/workflows/politicians-cleanup.yml).
 *
 * Three checks, each independently simple — this is deliberately not a full
 * statistical-process-control system:
 *
 *  1. Control — silence: has each instrumented step run in the last --max-age-hours?
 *  2. Control — control-limit anomaly: does today's national findings_count for a step
 *     fall to zero (or spike past 3x) against its trailing 14-day average, when that
 *     average was materially non-zero? This is exactly what would have surfaced
 *     "flag-suspect-profiles found 0 today" as an anomaly instead of a silent success.
 *  3. Analyze — self-consistency: does today's claimed queued_count sum match the
 *     actual number of PoliticianCleanupReview rows created today? A mismatch means the
 *     pipeline's own reporting disagrees with the queue's real state.
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

    /** Steps self-reporting to politician_cleanup_run_metrics. National scope only (scope IS NULL) is what a full daily run produces. */
    private const STEPS = ['flag-suspect-profiles', 'prune-junk-ecrs'];

    public function handle(): int
    {
        $maxAgeHours = max(1, (int) $this->option('max-age-hours'));
        $baselineDays = max(1, (int) $this->option('baseline-days'));
        $healthy = true;

        foreach (self::STEPS as $step) {
            $healthy = $this->checkSilence($step, $maxAgeHours) && $healthy;
            $healthy = $this->checkFindingRate($step, $baselineDays) && $healthy;
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

    private function checkFindingRate(string $step, int $baselineDays): bool
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
     * Compares this step's self-reported queued_count against the actual number of
     * PoliticianCleanupReview rows created today. Only flag-suspect-profiles queues to
     * that table today (prune-junk-ecrs deletes directly, queued_count is always 0).
     */
    private function checkSelfConsistency(): bool
    {
        $claimed = (int) (DB::table('politician_cleanup_run_metrics')
            ->where('step', 'flag-suspect-profiles')
            ->where('started_at', '>=', now()->startOfDay())
            ->sum('queued_count'));

        $actual = PoliticianCleanupReview::query()
            ->where('created_at', '>=', now()->startOfDay())
            ->count();

        if ($claimed !== $actual) {
            $summary = "flag-suspect-profiles claimed {$claimed} queued today, but {$actual} politician_cleanup_reviews row(s) were actually created.";
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
