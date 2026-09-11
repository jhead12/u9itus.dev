<?php

namespace App\Console\Commands;

use App\Models\Politician;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * One-off remediation for damage done by the first production run of
 * politicians:cleanup-workflow (2026-09-11, GitHub Actions run 34616848637)
 * before PoliticianDataRules::LEADING_QUALIFIER_PATTERN and nameViolation()
 * were fixed to stop over-stripping real names — see the commit that added
 * this file.
 *
 * Two groups, both keyed by exact id + the exact corrupted value captured
 * from that run's log, so this is a no-op on any row that's since been
 * hand-edited:
 *  - RESTORE: "christian" was wrongly treated as a leading-qualifier word
 *    and stripped off 10 real given names (e.g. "Christian Ahmed" ->
 *    "Ahmed"). Restored verbatim.
 *  - DEACTIVATE: "Democratic Party"/"Republican Party" and "Mayor of X"
 *    were pre-existing garbage placeholder rows (not real candidate names
 *    either way) that collapsed into the single word "Party" or a dangling
 *    "of X" fragment. There's no real name to restore, so these come off
 *    the public map instead — reversible via the admin panel.
 *
 * Delete this command once it's been run once against production.
 */
class RemediateNameCorruption20260911 extends Command
{
    protected $signature = 'politicians:remediate-name-corruption-2026-09-11 {--apply}';

    protected $description = 'One-off: restore names wrongly stripped by the first cleanup-workflow run and deactivate unrecoverable placeholder rows.';

    /** @var array<int, string> */
    private const RESTORE = [
        733 => 'Christian D. Menefee',
        1352 => 'Christian Ahmed',
        1540 => 'Christian Hurd',
        1702 => 'Christian Schlaefer',
        1986 => 'Christian Bright',
        2472 => 'Christian Menefee',
        6548 => 'Christian Urrutia',
        8226 => 'Christian Johnson',
        8410 => 'Christian Maxwell',
        9424 => 'Christian Mendez',
        6668 => 'Mayor of Maury County, Tennessee',
        7856 => 'Mayor of Evanston',
    ];

    /** @var array<int, string> Current (corrupted) full_name expected on each id, for the safety check. */
    private const RESTORE_CURRENT = [
        733 => 'D. Menefee',
        1352 => 'Ahmed',
        1540 => 'Hurd',
        1702 => 'Schlaefer',
        1986 => 'Bright',
        2472 => 'Menefee',
        6548 => 'Urrutia',
        8226 => 'Johnson',
        8410 => 'Maxwell',
        9424 => 'Mendez',
        6668 => 'of Maury County, Tennessee',
        7856 => 'of Evanston',
    ];

    /** @var int[] */
    private const DEACTIVATE_IDS = [
        5403, 5404, 5701, 5702, 5712, 5713, 5729, 5730, 5741, 5742, 5767, 5768,
        5784, 5785, 5803, 5804, 5826, 5827, 5878, 5879, 5891, 5892, 5900, 5901,
        5914, 5915, 5929, 5930, 5946, 5947, 5981, 5982, 6003, 6004, 6029, 6030,
        6049, 6050, 6282, 6283, 6307, 6308, 6316, 6317, 6372, 6373, 6402, 6403,
        6420, 6421, 6474, 6475, 6488, 6489, 6500, 6501, 6522, 6523, 6555, 6556,
        6717, 6718, 7037, 7038, 7046, 7047, 7478, 7479, 7494, 7495, 7553, 7554,
        7906, 7907, 7977, 7978, 7989, 7990, 8253, 8254, 8498, 8499, 12442, 12443,
        12611, 12612,
    ];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        if (! $apply) {
            $this->line('<fg=yellow>[dry-run] No rows will be changed. Pass --apply to write.</>');
        }

        $restored = 0;
        foreach (self::RESTORE as $id => $originalName) {
            $politician = Politician::find($id);
            if (! $politician) {
                $this->line("  <fg=yellow>#{$id}</> not found, skipping");

                continue;
            }

            if ($politician->full_name !== self::RESTORE_CURRENT[$id]) {
                $this->line("  <fg=yellow>#{$id}</> full_name is \"{$politician->full_name}\", not the expected corrupted value — skipping (already edited)");

                continue;
            }

            $this->line("  <fg=green>#{$id}</> \"{$politician->full_name}\" → \"{$originalName}\"");

            if ($apply) {
                $before = $politician->full_name;
                $politician->full_name = $originalName;
                $politician->saveQuietly();
                Log::info('politicians:remediate-name-corruption-2026-09-11 restored row', [
                    'id' => $id,
                    'before' => $before,
                    'after' => $originalName,
                ]);
            }
            $restored++;
        }

        $deactivated = 0;
        foreach (self::DEACTIVATE_IDS as $id) {
            $politician = Politician::find($id);
            if (! $politician) {
                continue;
            }

            if ($politician->full_name !== 'Party' || ! $politician->is_active) {
                $this->line("  <fg=yellow>#{$id}</> full_name=\"{$politician->full_name}\" is_active=".($politician->is_active ? 'true' : 'false').' — skipping');

                continue;
            }

            $this->line("  <fg=green>#{$id}</> deactivating (full_name=\"Party\" placeholder junk)");

            if ($apply) {
                $politician->is_active = false;
                $politician->page_published = false;
                $politician->saveQuietly();
                Log::info('politicians:remediate-name-corruption-2026-09-11 deactivated row', ['id' => $id]);
            }
            $deactivated++;
        }

        $this->newLine();
        $verb = $apply ? '' : 'would be ';
        $this->info("{$restored} row(s) {$verb}restored, {$deactivated} row(s) {$verb}deactivated.");

        return self::SUCCESS;
    }
}
