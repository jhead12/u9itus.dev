<?php

namespace App\Console\Commands;

use App\Services\CongressMemberLinker;
use Illuminate\Console\Command;

/**
 * Sets politicians.bioguide_id for sitting U.S. senators and representatives so their
 * profiles can show a voting record.
 *
 *   php artisan congress:link-members --dry-run
 */
class LinkCongressMembers extends Command
{
    protected $signature = 'congress:link-members {--dry-run : Report matches without saving}';

    protected $description = 'Match U.S. senator and representative profiles to Bioguide IDs (used for voting records).';

    public function handle(CongressMemberLinker $linker): int
    {
        $stats = $linker->link((bool) $this->option('dry-run'));

        $this->info(sprintf(
            '%d linked%s, %d already linked, %d current members with no matching profile.',
            $stats['linked'], $this->option('dry-run') ? ' (dry-run)' : '', $stats['already'], count($stats['unmatched']),
        ));

        if ($stats['unmatched'] !== [] && $this->output->isVerbose()) {
            foreach ($stats['unmatched'] as $name) {
                $this->line("  no profile: {$name}");
            }
        }

        return self::SUCCESS;
    }
}
