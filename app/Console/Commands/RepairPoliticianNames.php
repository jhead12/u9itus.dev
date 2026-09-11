<?php

namespace App\Console\Commands;

use App\Models\Politician;
use App\Models\PoliticianCleanupReview;
use App\Support\PoliticianNameRepairer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Backfill for junk full_name rows already sitting in the politicians table
 * from before PoliticianNameRepairer existed — "Former California",
 * "California Gavin Newsom", "Independent Michael Shellenberger", "Lt. Gov.
 * Eleni Kounalakis" and the like, leaked in by the RSS candidate-discovery
 * pipeline (RssCandidateDiscoverySource::extractCandidateName()).
 *
 * Every save already goes through the same repair via Politician::boot()'s
 * saving hook — this command is only needed for rows that predate it.
 *
 * Usage:
 *   php artisan politicians:repair-names --state=CA           # dry run
 *   php artisan politicians:repair-names --state=CA --apply
 *   php artisan politicians:repair-names --apply --enqueue-review
 */
class RepairPoliticianNames extends Command
{
    protected $signature = 'politicians:repair-names
        {--state=            : Restrict to a two-letter state code}
        {--apply             : Actually write repaired names (default is dry-run)}
        {--enqueue-review    : Write unrepairable names (nothing sensible left after stripping) to the cleanup review queue}
        {--limit=5000        : Max rows to scan}';

    protected $description = 'Strip leading qualifier/title/geography words from junk politician full_name rows, repairing them in place.';

    public function handle(): int
    {
        $state = $this->option('state') ? strtoupper(trim((string) $this->option('state'))) : null;
        $apply = (bool) $this->option('apply');
        $enqueueReview = (bool) $this->option('enqueue-review');
        $limit = max(1, (int) $this->option('limit'));

        if (! $apply) {
            $this->line('<fg=yellow>[dry-run] No rows will be changed. Pass --apply to write repaired names.</>');
        }

        $rows = Politician::query()
            ->when($state, fn ($q) => $q->whereRaw('UPPER(COALESCE(state, "")) = ?', [$state]))
            ->orderBy('id')
            ->limit($limit)
            ->get(['id', 'full_name', 'political_office', 'state']);

        $repaired = 0;
        $unrepairable = 0;
        $flagged = 0;

        foreach ($rows as $politician) {
            $result = PoliticianNameRepairer::repair($politician->full_name);

            if ($result['changed']) {
                $this->line(sprintf(
                    '  <fg=green>#%d</> "%s" → "%s"',
                    $politician->id,
                    $politician->full_name,
                    $result['name']
                ));

                if ($apply) {
                    $before = $politician->full_name;
                    $politician->full_name = $result['name'];
                    $politician->saveQuietly();
                    Log::info('politicians:repair-names repaired row', [
                        'id' => $politician->id,
                        'before' => $before,
                        'after' => $result['name'],
                    ]);
                }

                $repaired++;

                continue;
            }

            if ($result['unrepairable']) {
                $this->line(sprintf(
                    '  <fg=red>#%d</> "%s" — nothing sensible left after stripping, needs manual review',
                    $politician->id,
                    $politician->full_name
                ));
                $unrepairable++;

                if ($apply && $enqueueReview) {
                    PoliticianCleanupReview::enqueue(
                        PoliticianCleanupReview::TYPE_NAME_REJECT,
                        $politician->id,
                        null,
                        [
                            'full_name' => $politician->full_name,
                            'political_office' => $politician->political_office,
                            'state' => $politician->state,
                        ],
                        'Leading-qualifier strip left nothing sensible'
                    );
                    $flagged++;
                }
            }
        }

        $this->newLine();
        $verb = $apply ? 'repaired' : 'would repair';
        $this->info("{$rows->count()} scanned: {$repaired} {$verb}, {$unrepairable} unrepairable".($enqueueReview ? " ({$flagged} enqueued for review)" : '').'.');

        return self::SUCCESS;
    }
}
