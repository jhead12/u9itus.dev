<?php

namespace App\Console\Commands;

use App\Models\Politician;
use Illuminate\Console\Command;

/**
 * Normalizes stale "FULLSTATENAME-XX" district values (e.g. "CALIFORNIA-29")
 * to the canonical "ST-XX" format used everywhere else (e.g. "CA-29").
 *
 * Usage:
 *   php artisan politicians:normalize-district-format
 *   php artisan politicians:normalize-district-format --dry-run
 */
class NormalizeDistrictFormat extends Command
{
    protected $signature = 'politicians:normalize-district-format
                            {--dry-run : Report without writing}';

    protected $description = 'Normalize FULLSTATENAME-XX district values to ST-XX format';

    /** Full state name → 2-letter abbreviation */
    private const STATE_MAP = [
        'ALABAMA' => 'AL', 'ALASKA' => 'AK', 'ARIZONA' => 'AZ', 'ARKANSAS' => 'AR',
        'CALIFORNIA' => 'CA', 'COLORADO' => 'CO', 'CONNECTICUT' => 'CT',
        'DELAWARE' => 'DE', 'FLORIDA' => 'FL', 'GEORGIA' => 'GA', 'HAWAII' => 'HI',
        'IDAHO' => 'ID', 'ILLINOIS' => 'IL', 'INDIANA' => 'IN', 'IOWA' => 'IA',
        'KANSAS' => 'KS', 'KENTUCKY' => 'KY', 'LOUISIANA' => 'LA', 'MAINE' => 'ME',
        'MARYLAND' => 'MD', 'MASSACHUSETTS' => 'MA', 'MICHIGAN' => 'MI',
        'MINNESOTA' => 'MN', 'MISSISSIPPI' => 'MS', 'MISSOURI' => 'MO',
        'MONTANA' => 'MT', 'NEBRASKA' => 'NE', 'NEVADA' => 'NV',
        'NEW HAMPSHIRE' => 'NH', 'NEW JERSEY' => 'NJ', 'NEW MEXICO' => 'NM',
        'NEW YORK' => 'NY', 'NORTH CAROLINA' => 'NC', 'NORTH DAKOTA' => 'ND',
        'OHIO' => 'OH', 'OKLAHOMA' => 'OK', 'OREGON' => 'OR', 'PENNSYLVANIA' => 'PA',
        'RHODE ISLAND' => 'RI', 'SOUTH CAROLINA' => 'SC', 'SOUTH DAKOTA' => 'SD',
        'TENNESSEE' => 'TN', 'TEXAS' => 'TX', 'UTAH' => 'UT', 'VERMONT' => 'VT',
        'VIRGINIA' => 'VA', 'WASHINGTON' => 'WA', 'WEST VIRGINIA' => 'WV',
        'WISCONSIN' => 'WI', 'WYOMING' => 'WY', 'DISTRICT OF COLUMBIA' => 'DC',
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $updated = 0;
        $skipped = 0;

        Politician::query()
            ->whereNotNull('district')
            ->chunkById(200, function ($politicians) use ($dryRun, &$updated, &$skipped) {
                foreach ($politicians as $politician) {
                    $district = (string) $politician->district;

                    // Match "FULLNAME-NN" or "FULLNAME-AL"
                    if (! preg_match('/^([A-Z ]+)-(\d{1,2}|AL)$/i', $district, $m)) {
                        $skipped++;
                        continue;
                    }

                    $stateRaw = strtoupper(trim($m[1]));
                    $distSuffix = strtoupper(trim($m[2]));

                    // Already a 2-letter abbreviation — skip
                    if (strlen($stateRaw) === 2) {
                        $skipped++;
                        continue;
                    }

                    $abbr = self::STATE_MAP[$stateRaw] ?? null;
                    if ($abbr === null) {
                        $this->warn("Unknown state name '{$stateRaw}' on politician #{$politician->id} — skipped");
                        $skipped++;
                        continue;
                    }

                    $normalized = $abbr . '-' . (
                        $distSuffix === 'AL'
                            ? 'AL'
                            : str_pad((string) ((int) $distSuffix), 2, '0', STR_PAD_LEFT)
                    );

                    $this->line("[" . ($dryRun ? 'DRY' : 'FIX') . "] #{$politician->id} {$politician->full_name}: {$district} → {$normalized}");

                    if (! $dryRun) {
                        $politician->update(['district' => $normalized]);
                    }

                    $updated++;
                }
            });

        $suffix = $dryRun ? ' (dry-run)' : '';
        $this->info("Done{$suffix}: {$updated} normalized, {$skipped} skipped");

        return self::SUCCESS;
    }
}
