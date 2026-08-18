<?php

namespace App\Console\Commands;

use App\Models\GovernorRaceCandidateCount;
use Illuminate\Console\Command;

/**
 * Manual entry point for the Ballotpedia-sourced reference count that
 * politicians:audit-race-counts checks our data against. No scraper reads
 * Ballotpedia for this — run this command by hand after checking a race's
 * Ballotpedia page (a few times per cycle is enough; the number rarely
 * moves between filing deadline and election day).
 *
 * Usage:
 *   php artisan election:set-race-count CA --count=4 --url=https://ballotpedia.org/...
 *   php artisan election:set-race-count CA --year=2026 --count=4
 */
class SetGovernorRaceCandidateCount extends Command
{
    protected $signature = 'election:set-race-count
        {state : Two-letter state code, e.g. CA}
        {--year=2026        : Election year}
        {--count=            : Expected candidate count from Ballotpedia (required)}
        {--url=               : Ballotpedia race page URL, for quick reverification}
        {--source=ballotpedia_manual : Free-text source label}';

    protected $description = 'Record the hand-checked expected gubernatorial candidate count for one state/year.';

    public function handle(): int
    {
        $state = strtoupper(trim((string) $this->argument('state')));
        $year = (int) $this->option('year');
        $count = $this->option('count');
        $url = $this->option('url') ? trim((string) $this->option('url')) : null;
        $source = trim((string) $this->option('source')) ?: 'ballotpedia_manual';

        if (strlen($state) !== 2) {
            $this->error("State must be a two-letter code, got '{$state}'.");

            return self::FAILURE;
        }

        if ($count === null || $count === '' || ! ctype_digit((string) $count)) {
            $this->error('--count is required and must be a non-negative integer.');

            return self::FAILURE;
        }

        $row = GovernorRaceCandidateCount::updateOrCreate(
            ['state' => $state, 'election_year' => $year],
            [
                'expected_count' => (int) $count,
                'source' => $source,
                'source_url' => $url,
                'verified_at' => now(),
            ]
        );

        $this->info(sprintf(
            '%s %d: expected_count=%d (source=%s)%s',
            $row->state,
            $row->election_year,
            $row->expected_count,
            $row->source,
            $url ? " — {$url}" : ''
        ));

        return self::SUCCESS;
    }
}
