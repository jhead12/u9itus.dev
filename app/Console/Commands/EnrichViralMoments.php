<?php

namespace App\Console\Commands;

use App\Models\Politician;
use App\Services\ViralMomentEnricherService;
use App\Services\YouTubeMomentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class EnrichViralMoments extends Command
{
    protected $signature = 'politicians:enrich-moments
        {--limit=200         : Max politicians to process per run}
        {--stale-hours=48    : Re-enrich moments older than N hours}
        {--politician=       : Process a single politician by ID or slug}
        {--force             : Re-enrich even if the last run is fresh}
        {--dry-run           : Report what would be fetched without writing}';

    protected $description = 'Fetch viral / C-SPAN / popular-moment clips (YouTube view counts) and score them for politician profiles + map.';

    public function handle(ViralMomentEnricherService $enricher, YouTubeMomentService $youtube): int
    {
        $limit      = (int) $this->option('limit');
        $staleHours = (int) $this->option('stale-hours');
        $force      = (bool) $this->option('force');
        $dryRun     = (bool) $this->option('dry-run');
        $singleId   = $this->option('politician');

        if (! $youtube->isConfigured()) {
            $this->error('YOUTUBE_API_KEY is not configured. Add it to .env / secrets and re-run.');
            return self::FAILURE;
        }

        if ($dryRun) {
            $this->info('[dry-run] No data will be written.');
        }

        $query = Politician::query()
            ->where('page_published', true)
            ->where('is_active', true)
            ->whereNotNull('full_name')
            ->where('full_name', '!=', '');

        if ($singleId) {
            $query->where(fn ($q) =>
                $q->where('id', $singleId)->orWhere('slug', $singleId)
            );
        } else {
            // Prioritise politicians with no moment run yet, then stale runs.
            $query->where(function ($q) use ($staleHours, $force) {
                $q->whereDoesntHave('viralMomentRuns');
                if (! $force) {
                    $q->orWhereHas('viralMomentRuns', fn ($sq) =>
                        $sq->where('enriched_at', '<', now()->subHours($staleHours))
                           ->orWhereNull('enriched_at')
                    );
                }
            })->limit($limit);
        }

        $politicians = $query->get();

        if ($politicians->isEmpty()) {
            $this->info('No politicians need viral-moment enrichment.');
            return self::SUCCESS;
        }

        $this->info("Enriching {$politicians->count()} politician moment(s)...");

        $enriched = 0;
        $skipped  = 0;
        $failed   = 0;

        foreach ($politicians as $politician) {
            if ($this->isFresh($politician, $staleHours, $force)) {
                $skipped++;
                $this->line("  ⏭ {$politician->full_name} (fresh)");
                continue;
            }

            // Quota gate: skip politicians with no recent news unless --force.
            // A quiet politician is unlikely to have a fresh viral clip, and
            // skipping them keeps a national roster within YouTube's quota.
            if (! $force && ! $enricher->hasRecentNews($politician)) {
                $skipped++;
                $this->line("  ⏭ {$politician->full_name} (no recent news)");
                continue;
            }

            $this->line("  → {$politician->full_name} (id={$politician->id})");

            if ($dryRun) {
                $this->reportDryRun($youtube, $politician);
                $enriched++;
                continue;
            }

            try {
                $result = $enricher->enrich($politician, $youtube);
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
                Log::warning('politicians:enrich-moments failed', [
                    'politician_id' => $politician->id,
                    'error'         => $e->getMessage(),
                ]);
            }
        }

        $this->info("Done. Enriched: {$enriched} | Skipped: {$skipped} | Failed: {$failed}");

        return self::SUCCESS;
    }

    /**
     * Freshness gate keyed on the latest viral-moment run's enriched_at.
     * `--force` or `--stale-hours=0` ⇒ not fresh (always run). No run ⇒ not
     * fresh (needs first enrichment).
     */
    private function isFresh(Politician $p, int $staleHours, bool $force = false): bool
    {
        if ($force || $staleHours <= 0) {
            return false;
        }

        $run = $p->latestViralMomentRun;
        if (! $run) {
            return false;
        }

        return $run->enriched_at !== null
            && $run->enriched_at->gt(now()->subHours($staleHours));
    }

    private function reportDryRun(YouTubeMomentService $youtube, Politician $politician): void
    {
        try {
            $result = $youtube->fetchMoments($politician);
            $this->line("    [dry-run] status={$result['status']}, query=\"{$result['query']}\", clips=" . count($result['clips']));
            foreach (array_slice($result['clips'], 0, 3) as $clip) {
                $views = $clip['view_count'] ?? 'n/a';
                $this->line("      • {$clip['title']} — views={$views}");
            }
        } catch (\Throwable $e) {
            $this->line("    [dry-run] error: {$e->getMessage()}");
        }
    }
}