<?php

namespace App\Services;

use App\Models\Politician;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

/**
 * OpenSecrets (Center for Responsive Politics) — Web Scraper Integration
 *
 * OpenSecrets retired their free public API in 2025. This service now scrapes
 * data directly from their public profile pages using the Playwright-based
 * scripts/scrape-opensecrets.js script.
 *
 * Data scraped per candidate:
 *  - Total raised / spent / cash on hand (current cycle)
 *  - Top contributors (organization, total, individuals, PACs)
 *  - Top industries (industry name, total, individuals, PACs)
 *  - Cycle history (raised/spent per election year)
 *  - Profile URL (for linking back)
 *
 * No API key required — all data is publicly available on opensecrets.org.
 *
 * Requirements:
 *   npm install playwright
 *   npx playwright install chromium
 */
class OpenSecretsService
{
    protected int $cacheDuration = 86400 * 3; // 72 hours — scraping is slow; cache aggressively

    /** Path to the Node.js scraper script, relative to base_path() */
    protected string $scraperScript = 'scripts/scrape-opensecrets.js';

    /**
     * OpenSecrets sits behind a Cloudflare bot-check. Each politician is a
     * separate Node process (up to a minute when blocked), so a batch of 200
     * against a blocked IP can eat hours for nothing. The scraper reports a
     * bot-check as `error: "blocked"`; after this many in a row every remaining
     * politician in the run is skipped. A real answer (data or "not found")
     * resets the count. Counters are per-process, like FECService's.
     */
    protected const WAF_BLOCK_SHORT_CIRCUIT_THRESHOLD = 10;
    protected static int $consecutiveBlocks = 0;
    protected static int $blockedCount = 0;
    protected static int $skippedCount = 0;

    public function __construct() {}

    /**
     * Service is always "configured" — no API key needed.
     * Returns false only if Node.js / Playwright is not available.
     */
    public function isConfigured(): bool
    {
        return true;
    }

    /**
     * Fetch campaign finance data by running the Playwright scraper.
     * Results are cached for 72 hours to avoid hammering opensecrets.org.
     */
    public function fetchCampaignFinanceData(Politician $politician): ?array
    {
        $cacheKey = "opensecrets.scrape.{$politician->id}";

        return Cache::remember($cacheKey, $this->cacheDuration, function () use ($politician) {
            return $this->runScraper($politician);
        });
    }

    /**
     * Invoke scripts/scrape-opensecrets.js via Node.js and return parsed JSON.
     */
    protected function runScraper(Politician $politician): ?array
    {
        $name  = trim((string) $politician->full_name);
        $state = trim((string) ($politician->state ?? ''));
        $mpid  = trim((string) ($politician->opensecrets_id ?? '')); // reuse field for mpid

        if ($name === '') {
            return null;
        }

        $scriptPath = base_path($this->scraperScript);
        if (! file_exists($scriptPath)) {
            Log::warning('OpenSecretsService: scraper script not found', ['path' => $scriptPath]);
            return null;
        }

        // Cloudflare has blocked us repeatedly this run — every further scrape
        // would be blocked too, so don't spawn a browser for it.
        if (self::wasShortCircuited()) {
            self::$skippedCount++;
            return null;
        }

        $cmd = ['node', $scriptPath, "--name={$name}"];
        if ($state !== '') $cmd[] = "--state={$state}";
        if ($mpid  !== '') $cmd[] = "--mpid={$mpid}";

        $process = $this->newProcess($cmd);

        try {
            $process->run();
        } catch (\Throwable $e) {
            Log::warning('OpenSecretsService: scraper process error', [
                'politician_id' => $politician->id,
                'error'         => $e->getMessage(),
            ]);
            return null;
        }

        if (! $process->isSuccessful()) {
            Log::info('OpenSecretsService: scraper returned non-zero', [
                'politician_id' => $politician->id,
                'stderr'        => substr($process->getErrorOutput(), 0, 2000),
            ]);
            return null;
        }

        $json = json_decode($process->getOutput(), true);

        if (is_array($json) && ($json['error'] ?? null) === 'blocked') {
            self::$consecutiveBlocks++;
            self::$blockedCount++;
            Log::info('OpenSecretsService: scraper blocked by bot-check', [
                'politician_id'      => $politician->id,
                'consecutive_blocks' => self::$consecutiveBlocks,
                'stderr'             => substr($process->getErrorOutput(), 0, 2000),
            ]);
            return null;
        }
        // Anything else means we got past the bot-check (found, or genuinely not found).
        self::$consecutiveBlocks = 0;

        if (! is_array($json) || isset($json['error'])) {
            // Surface the scraper's stderr (it carries the [search]/[scrape]
            // diagnostic lines) so a systemic "no data returned" isn't silent.
            Log::info('OpenSecretsService: scraper returned no usable JSON', [
                'politician_id' => $politician->id,
                'stderr'        => substr($process->getErrorOutput(), 0, 2000),
            ]);
            return null;
        }

        // The scraper can succeed (valid JSON) but parse nothing — the
        // "0 contributors / 0 industries" symptom. Log the stderr trail so the
        // workflow log shows whether it died at search (0 results) or at the
        // profile scrape (stale table selectors), instead of just "0 contributors".
        $contributors = $json['top_contributors'] ?? [];
        $industries   = $json['top_industries']   ?? [];
        if ($contributors === [] && $industries === []) {
            Log::info('OpenSecretsService: scraper succeeded but parsed no contributors/industries', [
                'politician_id' => $politician->id,
                'stderr'        => substr($process->getErrorOutput(), 0, 2000),
            ]);
        }

        // Persist the mpid back to the politician for faster future lookups
        if (! empty($json['mpid']) && $politician->opensecrets_id !== $json['mpid']) {
            $politician->updateQuietly(['opensecrets_id' => $json['mpid']]);
        }

        return [
            'candidate_summary' => $this->normaliseSummary($json['summary'] ?? []),
            'top_contributors'  => $json['top_contributors'] ?? [],
            'top_industries'    => $json['top_industries']   ?? [],
            'cycle_history'     => $json['cycle_history']    ?? [],
            'profile_url'       => $json['profile_url']      ?? null,
            'mpid'              => $json['mpid']              ?? null,
        ];
    }

    /** Seam so tests can stand in for the Node process. */
    protected function newProcess(array $cmd): Process
    {
        return new Process($cmd, base_path(), null, null, 60);
    }

    /**
     * Per-run telemetry for the enricher's summary line.
     */
    public static function resetTelemetry(): void
    {
        self::$consecutiveBlocks = 0;
        self::$blockedCount = 0;
        self::$skippedCount = 0;
    }

    /** True once enough consecutive bot-check blocks have accumulated to stop scraping. */
    public static function wasShortCircuited(): bool
    {
        return self::$consecutiveBlocks >= self::WAF_BLOCK_SHORT_CIRCUIT_THRESHOLD;
    }

    public static function getBlockedCount(): int
    {
        return self::$blockedCount;
    }

    public static function getSkippedCount(): int
    {
        return self::$skippedCount;
    }

    /**
     * Normalise the scraped summary hash into a consistent shape.
     */
    protected function normaliseSummary(array $raw): array
    {
        // Keys come from the table's "Category" column, lowercased + underscored
        $summary = [
            'total_raised' => $raw['raised']       ?? $raw['total_raised'] ?? null,
            'total_spent'  => $raw['spent']        ?? $raw['total_spent']  ?? null,
            'cash_on_hand' => $raw['cash_on_hand'] ?? null,
            'debt'         => $raw['debts']        ?? $raw['debt']         ?? null,
        ];

        // A summary table that didn't parse (or wasn't there) shouldn't be
        // persisted as a non-empty, all-null shape — that reads as "has data"
        // to !empty() checks elsewhere. Collapse it to a real empty array.
        $hasValue = array_filter($summary, fn ($v) => $v !== null) !== [];

        return $hasValue ? $summary : [];
    }

    /**
     * Clear cached scrape result for a politician.
     */
    public function clearCache(Politician $politician): void
    {
        Cache::forget("opensecrets.scrape.{$politician->id}");
    }

    protected function logProviderException(string $operation, \Throwable $exception, array $context = []): void
    {
        Log::warning('OpenSecretsService: provider exception', array_merge($context, [
            'operation' => $operation,
            'error'     => $exception->getMessage(),
        ]));
    }

    /**
     * Get display-ready data for frontend
     * 
     * @param Politician $politician
     * @return array|null
     */
    /**
     * Return pre-fetched OpenSecrets data for display on a politician's public profile.
     *
     * TWO-TIER PATTERN — this method is called on every page request:
     *
     *  Tier 1 (fast, always used):  Read from politician_donor_snapshots, written
     *                               by the nightly `politicians:enrich-donors` command
     *                               (GitHub Actions: enrich-donor-snapshots.yml).
     *                               Zero external HTTP — sub-millisecond.
     *
     *  Tier 2 (never on page load): The Playwright scraper (fetchCampaignFinanceData)
     *                               is ONLY called by the nightly workflow. It is never
     *                               invoked inline because launching a headless browser
     *                               on a live HTTP request would take 20-60 seconds.
     *
     * If the snapshot doesn't exist yet (new politician, not yet enriched), this
     * returns null and the Dig Deeper panel shows a "data not yet available" state.
     * It will populate automatically after the next nightly run.
     */
    public function getDisplayData(Politician $politician): ?array
    {
        $snapshot = \App\Models\PoliticianDonorSnapshot::where('politician_id', $politician->id)->first();

        if (! $snapshot || ! $snapshot->enriched_at) {
            return null; // Not yet enriched — nightly job hasn't run for this politician yet
        }

        $topContributors = $snapshot->top_contributors ?? [];
        $topIndustries   = $snapshot->top_industries   ?? [];
        // Legacy rows may still have the old all-null-values summary shape
        // (see normaliseSummary()) — hasOpenSecretsSummary() knows how to
        // tell that apart from a real summary, raw !empty() doesn't.
        $openSecretsSummary = $snapshot->hasOpenSecretsSummary() ? $snapshot->opensecrets_summary : [];
        $pacAffiliations = $snapshot->pac_affiliations ?? [];

        if (empty($topContributors) && empty($topIndustries) && empty($openSecretsSummary) && empty($pacAffiliations)) {
            return null; // Enriched but no data found on OpenSecrets
        }

        $sourceUrl = $snapshot->opensecrets_source_url
            ?? ($politician->opensecrets_id
                ? 'https://www.opensecrets.org/profiles/' . $this->nameSlug($politician->full_name) . "/us_congress/summary?mpid={$politician->opensecrets_id}"
                : null);

        $sections = [];
        if (! empty($topContributors)) {
            $sections['top_contributors'] = ['items' => $topContributors];
        }
        if (! empty($topIndustries)) {
            $sections['top_industries'] = ['items' => $topIndustries];
        }
        if (! empty($openSecretsSummary)) {
            $sections['summary'] = $openSecretsSummary;
        }

        return [
            'source'           => 'OpenSecrets',
            'source_url'       => $sourceUrl,
            'sections'         => $sections,
            'pac_affiliations'  => $snapshot->pac_affiliations,
            'election_cycle'    => $snapshot->election_cycle,
        ];
    }

    /**
     * Build the URL slug OpenSecrets uses for profile pages.
     * e.g. "Adam B. Schiff" → "adam-b-schiff"
     */
    protected function nameSlug(string $name): string
    {
        return preg_replace('/[^a-z0-9]+/', '-', strtolower(trim($name)));
    }
}
