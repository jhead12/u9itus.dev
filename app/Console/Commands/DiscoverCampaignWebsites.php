<?php

namespace App\Console\Commands;

use App\Models\Politician;
use App\Services\CampaignWebsiteDiscoveryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Finds an official campaign website for candidates missing website_url.
 * politicians:enrich-profiles requires website_url to already be set, so
 * without this step a candidate with no known site is silently skipped by
 * the entire contact/social/donation enrichment pipeline.
 */
class DiscoverCampaignWebsites extends Command
{
    protected $signature = 'politicians:discover-websites
        {--limit=200   : Max politicians to process per run}
        {--politician= : Process a single politician by ID or slug}
        {--dry-run     : Report what would be found without writing}';

    protected $description = 'Discover campaign websites (via Ballotpedia) for running candidates missing website_url.';

    public function handle(CampaignWebsiteDiscoveryService $discovery): int
    {
        $limit    = (int) $this->option('limit');
        $dryRun   = (bool) $this->option('dry-run');
        $singleId = $this->option('politician');

        if ($dryRun) {
            $this->info('[dry-run] No data will be written.');
        }

        $query = Politician::query()
            ->where('is_running_candidate', true)
            ->where(fn ($q) => $q->whereNull('website_url')->orWhere('website_url', ''));

        if ($singleId) {
            $query->where(fn ($q) => $q->where('id', $singleId)->orWhere('slug', $singleId));
        } else {
            $query->limit($limit);
        }

        $politicians = $query->get();

        if ($politicians->isEmpty()) {
            $this->info('No candidates need website discovery.');

            return self::SUCCESS;
        }

        $this->info("Discovering website(s) for {$politicians->count()} candidate(s)...");

        $found   = 0;
        $missed  = 0;
        $failed  = 0;

        foreach ($politicians as $politician) {
            $this->line("  → {$politician->full_name} (id={$politician->id})");

            try {
                $url = $discovery->discoverFor($politician);

                if ($url === null) {
                    $missed++;
                    $this->line('    – no website found');
                    continue;
                }

                $this->line("    ✓ {$url}");

                if (! $dryRun) {
                    $politician->update(['website_url' => $url]);
                }

                $found++;
            } catch (\Throwable $e) {
                $failed++;
                $this->warn('    ✗ Failed: ' . $e->getMessage());
                Log::warning('politicians:discover-websites failed for politician', [
                    'politician_id' => $politician->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Done. Found: {$found} | Not found: {$missed} | Failed: {$failed}");

        return self::SUCCESS;
    }
}
