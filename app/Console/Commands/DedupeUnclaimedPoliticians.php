<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Deprecated alias for `politicians:dedupe --scope=federal` — kept for one
 * release in case anything external still calls this name directly. See
 * DedupePoliticians / DuplicatePoliticianDetectionService for the
 * consolidated implementation.
 */
class DedupeUnclaimedPoliticians extends Command
{
    protected $signature = 'politicians:dedupe-unclaimed
        {--state=   : Two-letter state code — limit to one state}
        {--apply    : Actually delete safe-to-remove duplicate rows (default is dry-run)}';

    protected $description = 'Deprecated — use politicians:dedupe --scope=federal instead.';

    protected $hidden = true;

    public function handle(): int
    {
        $this->warn('politicians:dedupe-unclaimed is deprecated — use politicians:dedupe --scope=federal.');

        $args = ['--scope' => 'federal'];
        if ($this->option('state')) {
            $args['--state'] = $this->option('state');
        }
        if ($this->option('apply')) {
            $args['--apply'] = true;
        }

        return $this->call('politicians:dedupe', $args);
    }
}
