<?php

namespace App\Console\Commands;

use App\Services\CongressCommitteeImporter;
use Illuminate\Console\Command;

/**
 * Refreshes committee and subcommittee seats for every sitting member of Congress.
 *
 * Usage:
 *   php artisan congress:sync-committees
 *   php artisan congress:sync-committees --dry-run
 */
class SyncCongressCommittees extends Command
{
    protected $signature = 'congress:sync-committees {--dry-run : Report counts without writing}';

    protected $description = 'Import current House, Senate and joint committee assignments (congress-legislators dataset).';

    public function handle(CongressCommitteeImporter $importer): int
    {
        try {
            $stats = $importer->import((bool) $this->option('dry-run'));
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            '%s%d seats on %d committees/subcommittees for %d members.',
            $this->option('dry-run') ? '[dry-run] ' : '',
            $stats['seats'], $stats['committees'], $stats['members'],
        ));

        return self::SUCCESS;
    }
}
