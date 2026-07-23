<?php

namespace App\Console\Commands;

use App\Models\PoliticianDonorSnapshot;
use Illuminate\Console\Command;

/**
 * scripts/scrape-opensecrets.js used to fall back to a *guessed* profile URL
 * (name slugified, no `mpid`) whenever an OpenSecrets search turned up no
 * results, and persisted it even when the guess resolved to no real data.
 * Those guesses are indistinguishable from real links once stored — this
 * scans for the guessed URL shape and clears the ones with no backing data
 * to fetch, since the scraper is now fixed to never persist them going
 * forward (see enrichCandidate() in scrape-opensecrets.js).
 *
 * Usage:
 *   php artisan politicians:fix-opensecrets-links          # dry-run (report only)
 *   php artisan politicians:fix-opensecrets-links --fix    # write repairs to DB
 */
class FixGuessedOpenSecretsLinks extends Command
{
    protected $signature = 'politicians:fix-opensecrets-links
        {--fix : Actually write repairs to the database (default is dry-run)}';

    protected $description = 'Find and clear OpenSecrets profile links that were guessed (unverified) rather than confirmed by search.';

    /** Guessed URLs never carry ?mpid= — see enrichCandidate() in scrape-opensecrets.js. */
    private const GUESSED_URL_PATTERN = '#^https://www\.opensecrets\.org/profiles/[^/]+/us_congress/summary$#';

    public function handle(): int
    {
        $fix = (bool) $this->option('fix');

        $this->info('Scanning donor snapshots for guessed (unverified) OpenSecrets links…');

        $snapshots = PoliticianDonorSnapshot::whereNotNull('opensecrets_source_url')
            ->with('politician:id,slug,full_name')
            ->get();

        $broken = $snapshots->filter(function (PoliticianDonorSnapshot $snapshot) {
            // Scope to data that actually came from OpenSecrets — fec_summary /
            // outside_spending come from FEC and say nothing about whether this
            // particular link is real, so they're excluded from the check.
            $hasOpenSecretsData = $snapshot->hasContributors()
                || $snapshot->hasIndustries()
                || $snapshot->hasOpenSecretsSummary();

            return preg_match(self::GUESSED_URL_PATTERN, $snapshot->opensecrets_source_url) === 1
                && ! $hasOpenSecretsData;
        });

        if ($broken->isEmpty()) {
            $this->info('✓ No guessed/broken OpenSecrets links found.');
            return self::SUCCESS;
        }

        $this->warn($broken->count() . ' snapshot(s) with a guessed link and no backing data:');
        foreach ($broken as $snapshot) {
            $label = $snapshot->politician ? "/p/{$snapshot->politician->slug} ({$snapshot->politician->full_name})" : "politician_id={$snapshot->politician_id}";
            $this->line("  [{$snapshot->id}] {$label}");
            $this->line('    ' . $snapshot->opensecrets_source_url);
        }

        if (! $fix) {
            $this->comment('Re-run with --fix to clear these links (the nightly enrich job will re-attempt a real match).');
            return self::SUCCESS;
        }

        foreach ($broken as $snapshot) {
            $snapshot->update(['opensecrets_source_url' => null]);
        }

        $this->info("✓ Cleared {$broken->count()} guessed link(s).");
        return self::SUCCESS;
    }
}
