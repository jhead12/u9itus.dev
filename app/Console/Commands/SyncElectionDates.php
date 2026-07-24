<?php

namespace App\Console\Commands;

use App\Models\StateElectionDate;
use App\Services\VoteSmartService;
use Illuminate\Console\Command;

/**
 * Sync real per-state election dates (primary, general, etc.) and candidate
 * filing deadlines from Vote Smart's Election class.
 *
 * This is deliberately separate from the candidate-import pipeline: unlike
 * candidates (thousands of rows, changing daily), the election calendar is
 * ~51 rows that only change a handful of times per cycle — so a small
 * monthly sync is sufficient, no scraping required.
 *
 * Usage:
 *   php artisan elections:sync-dates
 *   php artisan elections:sync-dates --year=2026 --state=CA
 *   php artisan elections:sync-dates --dry-run
 */
class SyncElectionDates extends Command
{
    protected $signature = 'elections:sync-dates
        {--year=2026 : Election year to sync}
        {--state=    : Single state to sync (two-letter code). Leave blank for all states + DC}
        {--dry-run   : Report what would be synced without writing to the database}';

    protected $description = 'Sync real election dates and filing deadlines from Vote Smart.';

    // USPS abbreviations for all 50 states + DC.
    private const STATES = [
        'AL', 'AK', 'AZ', 'AR', 'CA', 'CO', 'CT', 'DE', 'FL', 'GA',
        'HI', 'ID', 'IL', 'IN', 'IA', 'KS', 'KY', 'LA', 'ME', 'MD',
        'MA', 'MI', 'MN', 'MS', 'MO', 'MT', 'NE', 'NV', 'NH', 'NJ',
        'NM', 'NY', 'NC', 'ND', 'OH', 'OK', 'OR', 'PA', 'RI', 'SC',
        'SD', 'TN', 'TX', 'UT', 'VT', 'VA', 'WA', 'WV', 'WI', 'WY',
        'DC',
    ];

    public function handle(VoteSmartService $voteSmart): int
    {
        $year = (int) $this->option('year');
        $stateOption = $this->option('state');
        $dryRun = (bool) $this->option('dry-run');

        if (!$voteSmart->isConfigured()) {
            $this->error('VOTESMART_API_KEY is not configured.');
            return self::FAILURE;
        }

        $states = $stateOption ? [strtoupper($stateOption)] : self::STATES;

        $this->info("Election-date sync — year={$year} states=" . count($states) . ($dryRun ? ' [DRY RUN]' : ''));

        $upserted = 0;
        $skipped = 0;

        foreach ($states as $state) {
            $stages = $voteSmart->getElectionDates($state, $year);

            if ($stages === []) {
                $this->line("  {$state}: no data returned");
                $skipped++;
                continue;
            }

            foreach ($stages as $stage) {
                $this->line("  {$state} — {$stage['stage_name']}: election={$stage['election_date']} filing_deadline={$stage['filing_deadline']}");

                if (!$dryRun) {
                    StateElectionDate::updateOrCreate(
                        [
                            'state' => $state,
                            'election_year' => $year,
                            'stage_name' => $stage['stage_name'],
                        ],
                        [
                            'election_date' => $stage['election_date'],
                            'filing_deadline' => $stage['filing_deadline'],
                            'votesmart_election_id' => $stage['votesmart_election_id'],
                            'source' => 'votesmart',
                        ]
                    );
                }
                $upserted++;
            }
        }

        $suffix = $dryRun ? ' (dry-run — no DB writes)' : '';
        $this->info("Done. {$upserted} stage(s) upserted, {$skipped} state(s) with no data{$suffix}.");

        return self::SUCCESS;
    }
}
