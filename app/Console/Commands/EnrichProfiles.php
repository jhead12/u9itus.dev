<?php

namespace App\Console\Commands;

use App\Models\Politician;
use App\Services\ProfileEnricherService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class EnrichProfiles extends Command
{
    protected $signature = 'politicians:enrich-profiles
        {--limit=200         : Max politicians to process per run}
        {--stale-hours=48    : Re-enrich profiles older than N hours}
        {--politician=       : Process a single politician by ID or slug}
        {--force             : Re-enrich even if the last run is fresh}
        {--dry-run           : Report what would be fetched without writing}';

    protected $description = 'Fetch official/campaign website contact, social, donation, and newsletter facts for politician profiles.';

    public function handle(ProfileEnricherService $enricher): int
    {
        $limit      = (int) $this->option('limit');
        $staleHours = (int) $this->option('stale-hours');
        $force      = (bool) $this->option('force');
        $dryRun     = (bool) $this->option('dry-run');
        $singleId   = $this->option('politician');

        if ($dryRun) {
            $this->info('[dry-run] No data will be written.');
        }

        $query = Politician::query()
            ->where('page_published', true)
            ->where('is_active', true)
            ->whereNotNull('website_url')
            ->where('website_url', '!=', '');

        if ($singleId) {
            $query->where(fn ($q) =>
                $q->where('id', $singleId)->orWhere('slug', $singleId)
            );
        } else {
            // Prioritise politicians with no run yet, then stale runs.
            $query->where(function ($q) use ($staleHours, $force) {
                $q->whereDoesntHave('enrichmentRuns');
                if (! $force) {
                    $q->orWhereHas('enrichmentRuns', fn ($sq) =>
                        $sq->where('enriched_at', '<', now()->subHours($staleHours))
                           ->orWhereNull('enriched_at')
                    );
                }
            })->limit($limit);
        }

        $politicians = $query->get();

        if ($politicians->isEmpty()) {
            $this->info('No politicians need profile enrichment.');
            return self::SUCCESS;
        }

        $this->info("Enriching {$politicians->count()} politician profile(s)...");

        $enriched = 0;
        $skipped  = 0;
        $failed   = 0;

        foreach ($politicians as $politician) {
            // Freshness gate uses the latest enrichment run's enriched_at (the
            // politician row itself is not bumped by enrichment), so a
            // claimed profile (user_id set) is still enriched — official
            // contact info is universal directory data, not claim-gated.
            if ($this->isFresh($politician, $staleHours, $force)) {
                $skipped++;
                $this->line("  ⏭ {$politician->full_name} (fresh)");
                continue;
            }

            $this->line("  → {$politician->full_name} (id={$politician->id})");

            if ($dryRun) {
                $this->reportDryRun($enricher, $politician);
                $enriched++;
                continue;
            }

            try {
                $result = $enricher->enrich($politician);
                if ($result === null) {
                    $failed++;
                    $this->warn("  ✗ {$politician->full_name}: enrich returned null");
                    continue;
                }
                $enriched++;
                $this->line("  ✓ {$politician->full_name}");
            } catch (\Throwable $e) {
                $failed++;
                $this->warn("  ✗ {$politician->full_name}: {$e->getMessage()}");
                Log::warning('politicians:enrich-profiles failed', [
                    'politician_id' => $politician->id,
                    'error'         => $e->getMessage(),
                ]);
            }
        }

        $this->info("Done. Enriched: {$enriched} | Skipped: {$skipped} | Failed: {$failed}");

        return self::SUCCESS;
    }

    /**
     * Adapted from EnrichStatewideOfficeholders::isFresh, but keyed on the
     * latest enrichment run's enriched_at (enrichment does not bump the
     * politician row). `--force` or `--stale-hours=0` ⇒ not fresh (always run).
     * No run ⇒ not fresh (needs first enrichment). Claimed profiles are NOT
     * skipped here — see comment in handle().
     */
    private function isFresh(Politician $p, int $staleHours, bool $force = false): bool
    {
        if ($force || $staleHours <= 0) {
            return false;
        }

        $run = $p->latestEnrichmentRun;
        if (! $run) {
            return false;
        }

        return $run->enriched_at !== null
            && $run->enriched_at->gt(now()->subHours($staleHours));
    }

    private function reportDryRun(ProfileEnricherService $enricher, Politician $politician): void
    {
        $url = trim((string) $politician->website_url);
        if ($url === '') {
            $this->line("    [dry-run] no website_url");
            return;
        }

        try {
            $page = $enricher->fetchPageHtml($url);
            if ($page === null) {
                $this->line("    [dry-run] fetch failed");
                return;
            }
            $facts = $enricher->extractFromHtml($page['html'], $page['final_url']);
            $counts = [
                'contact' => count($facts['contact_methods']),
                'addresses' => count($facts['addresses']),
                'social'  => count($facts['social_links']),
                'donate'  => count($facts['donation_links']),
            ];
            $this->line("    [dry-run] discovered: " . json_encode($counts));
        } catch (\Throwable $e) {
            $this->line("    [dry-run] error: {$e->getMessage()}");
        }
    }
}