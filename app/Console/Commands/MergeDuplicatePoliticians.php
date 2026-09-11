<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Deprecated alias for `politicians:dedupe --scope=unclaimed-all` — kept for
 * one release in case anything external (a doc, a cron, a runbook) still
 * calls this name directly. See DedupePoliticians / DuplicatePoliticianDetectionService
 * for the consolidated implementation; this class and DedupeUnclaimedPoliticians
 * used to each have their own copy of the same grouping/survivor/FK-reassignment
 * logic, which had drifted apart.
 */
class MergeDuplicatePoliticians extends Command
{
    protected $signature = 'politicians:merge-duplicates
                            {--dry-run : Report duplicate groups without changing anything}
                            {--force : Skip the confirmation prompt}';

    protected $description = 'Deprecated — use politicians:dedupe --scope=unclaimed-all instead.';

    protected $hidden = true;

    public function handle(): int
    {
        $this->warn('politicians:merge-duplicates is deprecated — use politicians:dedupe --scope=unclaimed-all.');

        $args = ['--scope' => 'unclaimed-all'];
        if (! $this->option('dry-run')) {
            $args['--apply'] = true;
        }
        if ($this->option('force')) {
            $args['--force'] = true;
        }

        return $this->call('politicians:dedupe', $args);
    }
}
