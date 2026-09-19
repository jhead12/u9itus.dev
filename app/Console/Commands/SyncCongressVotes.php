<?php

namespace App\Console\Commands;

use App\Services\CongressVoteImporter;
use Illuminate\Console\Command;

/**
 * Pulls roll-call votes from the House Clerk and Senate.gov (no API key needed).
 *
 * Usage:
 *   php artisan congress:sync-votes                       # current session, both chambers, only new votes
 *   php artisan congress:sync-votes --congress=119 --session=1 --chamber=senate --limit=50
 *   php artisan congress:sync-votes --refresh             # re-fetch votes already stored
 */
class SyncCongressVotes extends Command
{
    protected $signature = 'congress:sync-votes
        {--congress=119 : Congress number}
        {--session=     : Session 1 or 2 (default: every session of the Congress held so far)}
        {--chamber=both : house, senate or both}
        {--limit=       : Stop after importing this many new votes per chamber}
        {--refresh      : Re-import votes that are already stored}';

    protected $description = 'Import U.S. House and Senate roll-call votes (and how each member voted).';

    public function handle(CongressVoteImporter $importer): int
    {
        $congress = (int) $this->option('congress');
        $sessions = $this->option('session')
            ? [(int) $this->option('session')]
            : range(1, max(1, min(2, now()->year - (2 * $congress + 1787) + 1)));
        $chamber = strtolower((string) $this->option('chamber'));
        $limit = $this->option('limit') !== null && $this->option('limit') !== '' ? (int) $this->option('limit') : null;
        $refresh = (bool) $this->option('refresh');

        if (! in_array($chamber, ['house', 'senate', 'both'], true)) {
            $this->error('--chamber must be house, senate or both.');

            return self::FAILURE;
        }

        $failed = 0;
        foreach ($sessions as $session) {
            foreach (['senate', 'house'] as $which) {
                if ($chamber !== 'both' && $chamber !== $which) {
                    continue;
                }

                $stats = $which === 'senate'
                    ? $importer->importSenate($congress, $session, $refresh, $limit)
                    : $importer->importHouse($congress, $session, $refresh, $limit);

                $this->info(sprintf(
                    '%s (Congress %d, session %d): %d imported, %d already stored/skipped, %d failed.',
                    ucfirst($which), $congress, $session, $stats['imported'], $stats['skipped'], $stats['failed'],
                ));
                $failed += $stats['failed'];
            }
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
