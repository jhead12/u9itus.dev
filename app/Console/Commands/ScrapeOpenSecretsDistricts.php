<?php

namespace App\Console\Commands;

use App\Models\Politician;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

/**
 * Scrape OpenSecrets district-level finance data for all congressional districts.
 *
 * Uses scripts/scrape-opensecrets.js --district=CA-31 which hits:
 *   https://www.opensecrets.org/elections/{state}/federal/{state}-district-{N}/candidates
 *
 * Returns all candidates in the race with raised/spent/cash on hand totals
 * and incumbent flags. Results are stored in politician_donor_snapshots.
 *
 * Usage:
 *   php artisan opensecrets:scrape-districts
 *   php artisan opensecrets:scrape-districts --states=CA,TX
 *   php artisan opensecrets:scrape-districts --year=2026 --dry-run
 */
class ScrapeOpenSecretsDistricts extends Command
{
    protected $signature = 'opensecrets:scrape-districts
        {--states=     : Comma-separated state codes to limit scraping (e.g. CA,TX)}
        {--year=2026   : Election cycle year}
        {--dry-run     : Report districts to be scraped without writing to DB}
        {--delay=1500  : Milliseconds to sleep between district requests}';

    protected $description = 'Scrape OpenSecrets district race pages for per-candidate fundraising data.';

    protected string $scraperScript = 'scripts/scrape-opensecrets.js';

    public function handle(): int
    {
        $statesOption = $this->option('states');
        $year         = $this->option('year') ?? '2026';
        $dryRun       = (bool) $this->option('dry-run');
        $delayMs      = max(500, (int) ($this->option('delay') ?? 1500));

        $states = $statesOption
            ? array_map('strtoupper', array_map('trim', explode(',', $statesOption)))
            : [];

        $scriptPath = base_path($this->scraperScript);
        if (! file_exists($scriptPath)) {
            $this->error("Scraper script not found: {$scriptPath}");
            return self::FAILURE;
        }

        // Build list of distinct district labels from federal House politicians
        $query = Politician::query()
            ->where('governance_level', 'Federal')
            ->whereRaw("LOWER(political_office) LIKE '%representative%'")
            ->whereNotNull('district')
            ->select('state', 'district')
            ->distinct();

        if (! empty($states)) {
            $query->whereIn('state', $states);
        }

        $districts = $query->get()
            ->map(fn ($r) => strtoupper(trim((string) $r->district)))
            ->filter(fn ($d) => preg_match('/^[A-Z]{2}-?\d+$/', $d))
            ->unique()
            ->sort()
            ->values();

        if ($districts->isEmpty()) {
            $this->info('No districts found to scrape.');
            return self::SUCCESS;
        }

        $this->info("Scraping {$districts->count()} district(s) from OpenSecrets (year={$year})...");
        if ($dryRun) {
            $this->warn('[dry-run] No DB writes will occur.');
            foreach ($districts as $d) {
                $this->line("  would scrape: {$d}");
            }
            return self::SUCCESS;
        }

        $scraped = 0;
        $failed  = 0;

        foreach ($districts as $district) {
            $this->line("  → {$district}");

            $cmd = ['node', $scriptPath, "--district={$district}", "--cycle={$year}"];
            $process = new Process($cmd, base_path(), null, null, 60);

            try {
                $process->run();
            } catch (\Throwable $e) {
                $this->warn("    ✗ Process error: {$e->getMessage()}");
                $failed++;
                continue;
            }

            if (! $process->isSuccessful()) {
                $this->warn("    ✗ Scraper failed: " . substr($process->getErrorOutput(), 0, 200));
                $failed++;
                continue;
            }

            $json = json_decode($process->getOutput(), true);
            if (! is_array($json) || empty($json['candidates'])) {
                $this->line("    – no data returned");
                $failed++;
                continue;
            }

            // Match each scraped candidate to a Politician and upsert their snapshot
            foreach ($json['candidates'] as $scraped_candidate) {
                $name = trim((string) ($scraped_candidate['name'] ?? ''));
                if ($name === '') continue;

                // Extract state from district label e.g. CA-31 → CA
                $stateAbbr = strtoupper(substr($district, 0, 2));

                $politician = Politician::query()
                    ->whereRaw('LOWER(full_name) = ?', [strtolower($name)])
                    ->where('state', $stateAbbr)
                    ->first();

                if (! $politician) {
                    $this->line("    – no DB match for: {$name}");
                    continue;
                }

                \App\Models\PoliticianDonorSnapshot::updateOrCreate(
                    ['politician_id' => $politician->id],
                    [
                        'district_raised'      => $scraped_candidate['raised']       ?? null,
                        'district_spent'       => $scraped_candidate['spent']        ?? null,
                        'district_cash_on_hand'=> $scraped_candidate['cash_on_hand'] ?? null,
                        'is_incumbent'         => (bool) ($scraped_candidate['is_incumbent'] ?? false),
                        'opensecrets_source_url' => $json['source_url'] ?? null,
                        'enriched_at'          => now(),
                    ]
                );

                $this->line("    ✓ {$name} — raised={$scraped_candidate['raised']}");
                $scraped++;
            }

            usleep($delayMs * 1000);
        }

        $this->info("Done. Districts scraped: {$scraped} candidates updated | {$failed} failed.");
        return self::SUCCESS;
    }
}
