<?php

namespace App\Console\Commands;

use App\Models\StateElectionDate;
use App\Services\GoogleCivicService;
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
 * Vote Smart is the primary source (it also carries filing deadlines,
 * which Google Civic doesn't expose). If it's unconfigured or a state
 * comes back with no data, Google Civic's nationwide elections feed
 * (GoogleCivicService::listUpcomingElections()) fills the gap — it won't
 * overwrite a row Vote Smart already populated this run, so filing
 * deadlines are never clobbered by the less-detailed fallback.
 *
 * Usage:
 *   php artisan elections:sync-dates
 *   php artisan elections:sync-dates --year=2026 --state=CA
 *   php artisan elections:sync-dates --dry-run
 *   php artisan elections:sync-dates --skip-votesmart   (Civic-only, e.g. while VOTESMART_API_KEY is down)
 */
class SyncElectionDates extends Command
{
    protected $signature = 'elections:sync-dates
        {--year=2026 : Election year to sync}
        {--state=    : Single state to sync (two-letter code). Leave blank for all states + DC}
        {--skip-votesmart : Skip Vote Smart entirely and sync from Google Civic only}
        {--dry-run   : Report what would be synced without writing to the database}';

    protected $description = 'Sync real election dates and filing deadlines from Vote Smart, with Google Civic as a fallback/supplement.';

    // USPS abbreviations for all 50 states + DC.
    private const STATES = [
        'AL', 'AK', 'AZ', 'AR', 'CA', 'CO', 'CT', 'DE', 'FL', 'GA',
        'HI', 'ID', 'IL', 'IN', 'IA', 'KS', 'KY', 'LA', 'ME', 'MD',
        'MA', 'MI', 'MN', 'MS', 'MO', 'MT', 'NE', 'NV', 'NH', 'NJ',
        'NM', 'NY', 'NC', 'ND', 'OH', 'OK', 'OR', 'PA', 'RI', 'SC',
        'SD', 'TN', 'TX', 'UT', 'VT', 'VA', 'WA', 'WV', 'WI', 'WY',
        'DC',
    ];

    public function handle(VoteSmartService $voteSmart, GoogleCivicService $googleCivic): int
    {
        $year = (int) $this->option('year');
        $stateOption = $this->option('state');
        $dryRun = (bool) $this->option('dry-run');
        $skipVotesmart = (bool) $this->option('skip-votesmart');

        $states = $stateOption ? [strtoupper($stateOption)] : self::STATES;
        $useVotesmart = !$skipVotesmart && $voteSmart->isConfigured();

        if ($skipVotesmart) {
            $this->line('Skipping Vote Smart (--skip-votesmart) — syncing from Google Civic only.');
        } elseif (!$voteSmart->isConfigured()) {
            $this->warn('VOTESMART_API_KEY is not configured — falling back to Google Civic only.');
        }

        $this->info("Election-date sync — year={$year} states=" . count($states) . ($dryRun ? ' [DRY RUN]' : ''));

        $upserted = 0;
        $skipped = 0;
        /** @var array<string, true> $touched Keys of "STATE|stage_name" written by Vote Smart this run */
        $touched = [];

        if ($useVotesmart) {
            foreach ($states as $state) {
                $stages = $voteSmart->getElectionDates($state, $year);

                if ($stages === []) {
                    $this->line("  {$state}: no data returned from Vote Smart");
                    $skipped++;
                    continue;
                }

                foreach ($stages as $stage) {
                    $this->line("  {$state} — {$stage['stage_name']} [votesmart]: election={$stage['election_date']} filing_deadline={$stage['filing_deadline']}");

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
                    $touched[$state . '|' . strtolower($stage['stage_name'])] = true;
                    $upserted++;
                }
            }
        }

        if ($googleCivic->isConfigured()) {
            $civicStages = $googleCivic->listUpcomingElections();
            $statesFilter = $stateOption ? [strtoupper($stateOption)] : null;

            foreach ($civicStages as $stage) {
                if ($statesFilter !== null && !in_array($stage['state'], $statesFilter, true)) {
                    continue;
                }

                $touchedKey = $stage['state'] . '|' . strtolower($stage['stage_name']);
                if (isset($touched[$touchedKey])) {
                    continue; // Vote Smart already provided fresher data (with filing deadline) this run
                }

                $this->line("  {$stage['state']} — {$stage['stage_name']} [civic]: election={$stage['election_date']}");

                if (!$dryRun) {
                    StateElectionDate::updateOrCreate(
                        [
                            'state' => $stage['state'],
                            'election_year' => $year,
                            'stage_name' => $stage['stage_name'],
                        ],
                        [
                            'election_date' => $stage['election_date'],
                            'civic_election_id' => $stage['civic_election_id'],
                            'source' => 'google_civic',
                        ]
                    );
                }
                $upserted++;
            }
        } elseif (!$useVotesmart) {
            $this->error('Neither Vote Smart nor Google Civic (GOOGLE_CIVIC_API_KEY) is configured — nothing to sync.');
            return self::FAILURE;
        }

        $suffix = $dryRun ? ' (dry-run — no DB writes)' : '';
        $this->info("Done. {$upserted} stage(s) upserted, {$skipped} state(s) with no Vote Smart data{$suffix}.");

        return self::SUCCESS;
    }
}
