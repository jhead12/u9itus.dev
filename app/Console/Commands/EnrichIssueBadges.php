<?php

namespace App\Console\Commands;

use App\Models\Politician;
use App\Services\BadgeService;
use App\Services\PoliticianTopicSignalService;
use App\Services\ViralMomentEnricherService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Nightly inferred issue-badge enrichment.
 *
 * For each politician: PoliticianTopicSignalService::compute() rolls up news +
 * viral-moment + Vote Smart evidence into politician_topic_signals, then
 * BadgeService::grantInferredBadges() grants an `inferred_discourse` badge for
 * any topic whose total_score crosses the configured threshold.
 *
 * Mirrors EnrichCspanMoments / EnrichViralMoments (same option surface, the
 * news-freshness gate so quiet politicians are skipped unless --force). Dry-run
 * reports the would-be signals + grants without writing. Scheduled at 06:30 —
 * after the 05:00 YouTube, 05:30 C-SPAN, and 06:00 marketing passes so all
 * upstream evidence (news, moments, votesmart) is fresh.
 */
class EnrichIssueBadges extends Command
{
    protected $signature = 'politicians:enrich-issue-badges
        {--limit=200         : Max politicians to process per run}
        {--stale-hours=48    : Re-compute signals older than N hours}
        {--politician=       : Process a single politician by ID or slug}
        {--force             : Re-compute even if signals are fresh / no recent news}
        {--dry-run           : Report what would be computed/granted without writing}';

    protected $description = 'Roll up news/viral-moment/Vote Smart signals into topic scores and grant inferred issue badges.';

    public function handle(
        PoliticianTopicSignalService $signals,
        BadgeService $badges,
        ViralMomentEnricherService $enricher,
    ): int {
        if (! (bool) config('u9itus.issues.enabled', true)) {
            $this->info('Issue badges are disabled (ISSUE_BADGES_ENABLED=false).');

            return self::SUCCESS;
        }

        $limit = (int) $this->option('limit');
        $staleHours = (int) $this->option('stale-hours');
        $force = (bool) $this->option('force');
        $dryRun = (bool) $this->option('dry-run');
        $singleId = $this->option('politician');

        if ($dryRun) {
            $this->info('[dry-run] No data will be written.');
        }

        $query = Politician::query()
            ->where('page_published', true)
            ->where('is_active', true)
            ->whereNotNull('full_name')
            ->where('full_name', '!=', '');

        if ($singleId) {
            $query->where(fn ($q) => $q->where('id', $singleId)->orWhere('slug', $singleId));
        } else {
            // Prioritise politicians never scored, then stale signals.
            $query->where(function ($q) use ($staleHours, $force) {
                $q->whereDoesntHave('topicSignals');
                if (! $force) {
                    $q->orWhereHas('topicSignals', fn ($sq) => $sq->where('last_seen_at', '<', now()->subHours($staleHours))
                        ->orWhereNull('last_seen_at')
                    );
                }
            })->limit($limit);
        }

        $politicians = $query->get();

        if ($politicians->isEmpty()) {
            $this->info('No politicians need issue-badge enrichment.');

            return self::SUCCESS;
        }

        $this->info("Enriching issue badges for {$politicians->count()} politician(s)...");

        $computed = 0;
        $grantedTotal = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($politicians as $politician) {
            if ($this->isFresh($politician, $staleHours, $force)) {
                $skipped++;
                $this->line("  ⏭ {$politician->full_name} (fresh)");

                continue;
            }

            // News-freshness gate: a politician with no recent coverage is
            // unlikely to have fresh discourse signals. --force bypasses it.
            if (! $force && ! $enricher->hasRecentNews($politician)) {
                $skipped++;
                $this->line("  ⏭ {$politician->full_name} (no recent news)");

                continue;
            }

            $this->line("  → {$politician->full_name} (id={$politician->id})");

            try {
                $rows = $signals->compute($politician, persist: ! $dryRun);
                $computed++;

                if ($dryRun) {
                    $this->reportDryRun($politician, $rows);

                    continue;
                }

                $granted = $badges->grantInferredBadges($politician, $rows);
                $grantedTotal += $granted;
                $this->line("  ✓ {$politician->full_name} (signals={$rows->count()}, granted={$granted})");
            } catch (\Throwable $e) {
                $failed++;
                $this->warn("  ✗ {$politician->full_name}: {$e->getMessage()}");
                Log::warning('politicians:enrich-issue-badges failed', [
                    'politician_id' => $politician->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Done. Computed: {$computed} | Badges granted: {$grantedTotal} | Skipped: {$skipped} | Failed: {$failed}");

        return self::SUCCESS;
    }

    /**
     * Freshness gate keyed on the most recent topic signal's last_seen_at.
     * `--force` or `--stale-hours=0` ⇒ not fresh (always run).
     */
    private function isFresh(Politician $p, int $staleHours, bool $force = false): bool
    {
        if ($force || $staleHours <= 0) {
            return false;
        }

        $last = $p->topicSignals()->max('last_seen_at');

        return $last !== null && $last > now()->subHours($staleHours)->toDateTimeString();
    }

    private function reportDryRun(Politician $politician, $rows): void
    {
        $threshold = (float) config('u9itus.issues.signal_threshold', 1.0);
        if ($rows->isEmpty()) {
            $this->line('    [dry-run] no topic signals');

            return;
        }
        foreach ($rows as $signal) {
            $topicName = $signal->topic?->name ?? "#{$signal->topic_id}";
            $wouldGrant = (float) $signal->total_score >= $threshold ? ' ★ would grant' : '';
            $this->line(sprintf(
                '    [dry-run] %s — score=%.4f (news=%d, viral=%d, votesmart=%d)%s',
                $topicName,
                (float) $signal->total_score,
                $signal->news_count,
                $signal->viral_moment_count,
                $signal->votesmart_count,
                $wouldGrant,
            ));
        }
    }
}
