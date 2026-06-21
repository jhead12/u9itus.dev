<?php

namespace App\Console\Commands;

use App\Models\Politician;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Normalizes stale "FULLSTATENAME-XX" district values (e.g. "CALIFORNIA-29")
 * to the canonical "ST-XX" format (e.g. "CA-43").
 *
 * Critically, it cross-references the congress-legislators feeds (current +
 * historical) to get the CORRECT district NUMBER, not just reformat the prefix.
 * Without this, "CALIFORNIA-29" for Maxine Waters (who served in district 43)
 * would become "CA-29" instead of "CA-43".
 *
 * Usage:
 *   php artisan politicians:normalize-district-format
 *   php artisan politicians:normalize-district-format --dry-run
 */
class NormalizeDistrictFormat extends Command
{
    protected $signature = 'politicians:normalize-district-format
                            {--dry-run : Report without writing}';

    protected $description = 'Normalize FULLSTATENAME-XX district values to ST-XX format, correcting district numbers via congress-legislators feed';

    /** Full state name (uppercase) → 2-letter abbreviation */
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

    /**
     * name_lower|STATE → correct district code (e.g. "maxine waters|CA" → "CA-43")
     * Built from the congress-legislators current + historical feeds.
     *
     * @var array<string, string>
     */
    private array $feedDistrictMap = [];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $this->info('Loading congress-legislators feeds for district verification…');
        $this->loadFeedDistrictMap();
        $this->info('Feed loaded: ' . count($this->feedDistrictMap) . ' House members indexed.');

        $updated = 0;
        $skipped = 0;

        Politician::query()
            ->whereNotNull('district')
            ->chunkById(200, function ($politicians) use ($dryRun, &$updated, &$skipped) {
                foreach ($politicians as $politician) {
                    $district = (string) $politician->district;

                    // Match "FULLNAME-NN" or "FULLNAME-AL" (e.g. "CALIFORNIA-29")
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

                    // Try to get the authoritative district from the congress-legislators feed.
                    // This corrects the NUMBER (e.g. Waters was CALIFORNIA-29, feed says CA-43).
                    $feedKey = strtolower(trim((string) $politician->full_name)) . '|' . $abbr;
                    $feedDistrict = $this->feedDistrictMap[$feedKey] ?? null;

                    if ($feedDistrict !== null) {
                        // Feed has the authoritative value — use it regardless of what the DB has.
                        $normalized = $feedDistrict;
                        $source = 'feed';
                    } else {
                        // No feed record — just reformat the prefix, preserve the number.
                        $normalized = $abbr . '-' . (
                            $distSuffix === 'AL'
                                ? 'AL'
                                : str_pad((string) ((int) $distSuffix), 2, '0', STR_PAD_LEFT)
                        );
                        $source = 'format-only';
                    }

                    $tag = $dryRun ? 'DRY' : 'FIX';
                    $this->line("[{$tag}] #{$politician->id} {$politician->full_name}: {$district} → {$normalized} ({$source})");

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

    /**
     * Fetch the congress-legislators current + historical JSON feeds and build
     * a name → correct district code lookup map.
     *
     * Keys:   "lowercase full name|STATE_ABBR"
     * Values: "ST-NN" canonical district code
     */
    private function loadFeedDistrictMap(): void
    {
        $urls = [
            'https://unitedstates.github.io/congress-legislators/legislators-current.json',
            'https://unitedstates.github.io/congress-legislators/legislators-historical.json',
        ];

        foreach ($urls as $url) {
            try {
                $response = Http::timeout(30)->get($url);
                if (! $response->successful()) {
                    $this->warn("Could not fetch {$url} (HTTP {$response->status()}) — skipping.");
                    continue;
                }

                $rows = $response->json();
                if (! is_array($rows)) {
                    continue;
                }

                foreach ($rows as $row) {
                    if (! is_array($row)) {
                        continue;
                    }

                    $terms = $row['terms'] ?? [];
                    if (! is_array($terms) || empty($terms)) {
                        continue;
                    }

                    // Sort terms descending by start date → latest term first.
                    usort($terms, fn ($a, $b) => strcmp(
                        (string) ($b['start'] ?? ''),
                        (string) ($a['start'] ?? '')
                    ));

                    $latestTerm = $terms[0];

                    // Only index House representatives.
                    if (($latestTerm['type'] ?? '') !== 'rep') {
                        continue;
                    }

                    $state = strtoupper(trim((string) ($latestTerm['state'] ?? '')));
                    $districtNum = isset($latestTerm['district']) ? (int) $latestTerm['district'] : null;

                    if ($state === '' || $districtNum === null) {
                        continue;
                    }

                    $districtCode = $state . '-' . str_pad((string) $districtNum, 2, '0', STR_PAD_LEFT);

                    $fullName = trim((string) ($row['name']['official_full']
                        ?? (($row['name']['first'] ?? '') . ' ' . ($row['name']['last'] ?? ''))));

                    if ($fullName === '') {
                        continue;
                    }

                    $key = strtolower($fullName) . '|' . $state;

                    // Current feed wins over historical; historical fills gaps.
                    if (! isset($this->feedDistrictMap[$key])) {
                        $this->feedDistrictMap[$key] = $districtCode;
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('NormalizeDistrictFormat: feed fetch failed', [
                    'url' => $url,
                    'error' => $e->getMessage(),
                ]);
                $this->warn("Feed fetch error ({$url}): {$e->getMessage()}");
            }
        }
    }
}
