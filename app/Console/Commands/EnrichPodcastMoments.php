<?php

namespace App\Console\Commands;

use App\Models\Politician;
use App\Services\PodcastMomentFetcher;
use App\Services\ViralMomentEnricherService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Dedicated podcast/radio-interview viral-moment enrichment — mirrors
 * EnrichCspanMoments but drives PodcastMomentFetcher (Podcast Index +
 * ListenNotes) through the same ViralMomentEnricherService pipeline. Run
 * separately so a slow/rate-limited podcast provider doesn't block the
 * quota-bounded YouTube pass or the browser-driven C-SPAN scrape.
 */
class EnrichPodcastMoments extends Command
{
    protected $signature = 'politicians:enrich-podcast-moments
        {--limit=200         : Max politicians to process per run}
        {--stale-hours=48    : Re-enrich moments older than N hours}
        {--politician=       : Process a single politician by ID or slug}
        {--force             : Re-enrich even if the last run is fresh}
        {--upcoming-only     : Only target candidates currently running (is_running_candidate)}
        {--dry-run           : Report what would be fetched without writing}';

    protected $description = 'Fetch podcast/radio interview clips (Podcast Index + ListenNotes) and score them for politician profiles + map.';

    public function handle(ViralMomentEnricherService $enricher, PodcastMomentFetcher $podcast): int
    {
        $limit      = (int) $this->option('limit');
        $staleHours = (int) $this->option('stale-hours');
        $force      = (bool) $this->option('force');
        $dryRun     = (bool) $this->option('dry-run');
        $singleId   = $this->option('politician');
        $upcomingOnly = (bool) $this->option('upcoming-only');

        if (! $podcast->isConfigured()) {
            $this->error('Podcast moments are disabled (PODCAST_MOMENTS_ENABLED=false), or no provider key is configured (PODCASTINDEX_API_KEY/SECRET or LISTENNOTES_API_KEY).');
            return self::FAILURE;
        }

        if ($dryRun) {
            $this->info('[dry-run] No data will be written.');
        }

        $query = Politician::query()
            ->where('page_published', true)
            ->where('is_active', true)
            ->whereNotNull('full_name')
            ->where('full_name', '!=', '')
            ->when($upcomingOnly, fn ($q) => $q->where('is_running_candidate', true));

        if ($singleId) {
            $query->where(fn ($q) =>
                $q->where('id', $singleId)->orWhere('slug', $singleId)
            );
        } else {
            // Prioritise politicians with no podcast run yet, then stale runs.
            $query->where(function ($q) use ($staleHours, $force) {
                $q->whereDoesntHave('viralMomentRuns', fn ($sq) => $sq->where('source', 'podcast'));
                if (! $force) {
                    $q->orWhereHas('viralMomentRuns', fn ($sq) =>
                        $sq->where('source', 'podcast')
                           ->where('enriched_at', '<', now()->subHours($staleHours))
                           ->orWhereNull('enriched_at')
                    );
                }
            })->limit($limit);
        }

        $politicians = $query->get();

        if ($politicians->isEmpty()) {
            $this->info('No politicians need podcast moment enrichment.');
            return self::SUCCESS;
        }

        $this->info("Enriching {$politicians->count()} politician podcast moment(s)...");

        $enriched = 0;
        $skipped  = 0;
        $failed   = 0;

        foreach ($politicians as $politician) {
            if ($this->isFresh($politician, $staleHours, $force)) {
                $skipped++;
                $this->line("  ⏭ {$politician->full_name} (fresh)");
                continue;
            }

            // Keep the news-freshness gate: a quiet politician is unlikely to
            // have a fresh podcast appearance, and skipping them keeps
            // provider quota usage bounded. --force bypasses it.
            if (! $force && ! $enricher->hasRecentNews($politician)) {
                $skipped++;
                $this->line("  ⏭ {$politician->full_name} (no recent news)");
                continue;
            }

            $this->line("  → {$politician->full_name} (id={$politician->id})");

            if ($dryRun) {
                $this->reportDryRun($podcast, $politician);
                $enriched++;
                continue;
            }

            try {
                $result = $enricher->enrich($politician, $podcast);
                if ($result === null) {
                    $failed++;
                    $this->warn("  ✗ {$politician->full_name}: enrich returned null");
                    continue;
                }
                $enriched++;
                $this->line("  ✓ {$politician->full_name} (status={$result['status']}, kept={$result['kept']}, featured=" . ($result['featured'] ? 'yes' : 'no') . ')');
            } catch (\Throwable $e) {
                $failed++;
                $this->warn("  ✗ {$politician->full_name}: {$e->getMessage()}");
                Log::warning('politicians:enrich-podcast-moments failed', [
                    'politician_id' => $politician->id,
                    'error'         => $e->getMessage(),
                ]);
            }
        }

        $this->info("Done. Enriched: {$enriched} | Skipped: {$skipped} | Failed: {$failed}");

        return self::SUCCESS;
    }

    /**
     * Freshness gate keyed on the latest *podcast* run's enriched_at (not
     * YouTube/C-SPAN). `--force` or `--stale-hours=0` ⇒ not fresh (always run).
     */
    private function isFresh(Politician $p, int $staleHours, bool $force = false): bool
    {
        if ($force || $staleHours <= 0) {
            return false;
        }

        $run = $p->viralMomentRuns()->where('source', 'podcast')->orderByDesc('enriched_at')->first();
        if (! $run) {
            return false;
        }

        return $run->enriched_at !== null
            && $run->enriched_at->gt(now()->subHours($staleHours));
    }

    private function reportDryRun(PodcastMomentFetcher $podcast, Politician $politician): void
    {
        try {
            $result = $podcast->fetchMoments($politician);
            $this->line("    [dry-run] status={$result['status']}, query=\"{$result['query']}\", clips=" . count($result['clips']));
            foreach (array_slice($result['clips'], 0, 3) as $clip) {
                $this->line("      • {$clip['title']} — id={$clip['source_id']}");
            }
        } catch (\Throwable $e) {
            $this->line("    [dry-run] error: {$e->getMessage()}");
        }
    }
}
