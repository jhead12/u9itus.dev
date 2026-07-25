<?php

namespace App\Console\Commands;

use App\Models\Politician;
use Illuminate\Console\Command;

/**
 * Upserts the curated roster from config/presidents.php as unclaimed
 * federal Politician profiles (governance_level=Federal, state=null —
 * the presidency is an at-large office with no home-state scoping).
 *
 * Safe to re-run: matches existing rows by full_name + political_office
 * and updates in place rather than duplicating.
 */
class ImportPresidents extends Command
{
    protected $signature = 'politicians:import-presidents {--dry-run : Report what would change without writing}';

    protected $description = 'Import/update the curated president roster (config/presidents.php) as unclaimed federal Politician profiles.';

    protected const OFFICE = 'President of the United States';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $roster = config('presidents.roster', []);

        if (empty($roster)) {
            $this->warn('config/presidents.php roster is empty — nothing to import.');
            return self::SUCCESS;
        }

        $created = 0;
        $updated = 0;

        foreach ($roster as $entry) {
            $fullName = trim((string) ($entry['full_name'] ?? ''));
            if ($fullName === '') {
                continue;
            }

            $attributes = [
                'political_office' => self::OFFICE,
                'governance_level' => 'Federal',
                'state' => null,
                'district' => null,
                'party_affiliation' => $entry['party_affiliation'] ?? null,
                'term_status' => $entry['term_status'] ?? null,
                'bio' => $entry['bio'] ?? null,
                'verified_official' => true,
                'is_active' => true,
                'page_published' => true,
            ];

            $existing = Politician::query()
                ->whereNull('user_id')
                ->whereRaw('LOWER(full_name) = ?', [strtolower($fullName)])
                ->where('political_office', self::OFFICE)
                ->first();

            if ($dryRun) {
                $this->line(($existing ? '  [update] ' : '  [create] ') . $fullName);
                continue;
            }

            if ($existing) {
                $existing->fill($attributes)->save();
                $updated++;
            } else {
                Politician::create(['full_name' => $fullName] + $attributes);
                $created++;
            }
        }

        if ($dryRun) {
            $this->info('Dry run complete — no changes written.');
            return self::SUCCESS;
        }

        $this->info("Presidents import complete: {$created} created, {$updated} updated.");
        return self::SUCCESS;
    }
}
