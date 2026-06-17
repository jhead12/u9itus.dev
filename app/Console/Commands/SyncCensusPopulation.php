<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Fetch population figures from the US Census Bureau API and upsert
 * them into the district_populations table.
 *
 * Data sources used:
 *   - State populations:  2020 Decennial Census P.L. 94-171 redistricting file
 *   - District populations: same product, broken out by congressional district
 *
 * Census Bureau API docs: https://www.census.gov/data/developers/data-sets.html
 * No API key required for low-volume usage.
 *
 * Usage:
 *   php artisan geo:sync-census-population
 *   php artisan geo:sync-census-population --year=2020
 *   php artisan geo:sync-census-population --level=state
 *   php artisan geo:sync-census-population --level=district
 *   php artisan geo:sync-census-population --dry-run
 */
class SyncCensusPopulation extends Command
{
    protected $signature = 'geo:sync-census-population
        {--year=2020 : Decennial census year to pull (2020 is the latest redistricting file)}
        {--level=all : Which level to sync: state, district, or all}
        {--dry-run   : Fetch and report without writing to the database}';

    protected $description = 'Sync US state and congressional district populations from the Census Bureau API.';

    // USPS abbreviation → FIPS numeric state code
    private const STATE_FIPS = [
        'AL' => '01', 'AK' => '02', 'AZ' => '04', 'AR' => '05', 'CA' => '06',
        'CO' => '08', 'CT' => '09', 'DE' => '10', 'FL' => '12', 'GA' => '13',
        'HI' => '15', 'ID' => '16', 'IL' => '17', 'IN' => '18', 'IA' => '19',
        'KS' => '20', 'KY' => '21', 'LA' => '22', 'ME' => '23', 'MD' => '24',
        'MA' => '25', 'MI' => '26', 'MN' => '27', 'MS' => '28', 'MO' => '29',
        'MT' => '30', 'NE' => '31', 'NV' => '32', 'NH' => '33', 'NJ' => '34',
        'NM' => '35', 'NY' => '36', 'NC' => '37', 'ND' => '38', 'OH' => '39',
        'OK' => '40', 'OR' => '41', 'PA' => '42', 'RI' => '44', 'SC' => '45',
        'SD' => '46', 'TN' => '47', 'TX' => '48', 'UT' => '49', 'VT' => '50',
        'VA' => '51', 'WA' => '53', 'WV' => '54', 'WI' => '55', 'WY' => '56',
        'DC' => '11',
    ];

    // FIPS code → USPS abbreviation (reverse map, built at runtime)
    private array $fipsToAbbr = [];

    // Full state name → USPS abbreviation
    private const STATE_NAMES = [
        'Alabama' => 'AL', 'Alaska' => 'AK', 'Arizona' => 'AZ', 'Arkansas' => 'AR',
        'California' => 'CA', 'Colorado' => 'CO', 'Connecticut' => 'CT', 'Delaware' => 'DE',
        'Florida' => 'FL', 'Georgia' => 'GA', 'Hawaii' => 'HI', 'Idaho' => 'ID',
        'Illinois' => 'IL', 'Indiana' => 'IN', 'Iowa' => 'IA', 'Kansas' => 'KS',
        'Kentucky' => 'KY', 'Louisiana' => 'LA', 'Maine' => 'ME', 'Maryland' => 'MD',
        'Massachusetts' => 'MA', 'Michigan' => 'MI', 'Minnesota' => 'MN', 'Mississippi' => 'MS',
        'Missouri' => 'MO', 'Montana' => 'MT', 'Nebraska' => 'NE', 'Nevada' => 'NV',
        'New Hampshire' => 'NH', 'New Jersey' => 'NJ', 'New Mexico' => 'NM', 'New York' => 'NY',
        'North Carolina' => 'NC', 'North Dakota' => 'ND', 'Ohio' => 'OH', 'Oklahoma' => 'OK',
        'Oregon' => 'OR', 'Pennsylvania' => 'PA', 'Rhode Island' => 'RI',
        'South Carolina' => 'SC', 'South Dakota' => 'SD', 'Tennessee' => 'TN',
        'Texas' => 'TX', 'Utah' => 'UT', 'Vermont' => 'VT', 'Virginia' => 'VA',
        'Washington' => 'WA', 'West Virginia' => 'WV', 'Wisconsin' => 'WI', 'Wyoming' => 'WY',
        'District of Columbia' => 'DC',
    ];

    public function handle(): int
    {
        $year   = (int) $this->option('year');
        $level  = (string) $this->option('level');
        $dryRun = (bool) $this->option('dry-run');

        $this->fipsToAbbr = array_flip(self::STATE_FIPS);

        $this->info("Census population sync — year={$year} level={$level}" . ($dryRun ? ' [DRY RUN]' : ''));

        $upserted = 0;

        if ($level === 'all' || $level === 'state') {
            $upserted += $this->syncStates($year, $dryRun);
        }

        if ($level === 'all' || $level === 'district') {
            $upserted += $this->syncDistricts($year, $dryRun);
        }

        $suffix = $dryRun ? ' (dry-run — no DB writes)' : '';
        $this->info("Done. {$upserted} rows upserted{$suffix}.");

        return self::SUCCESS;
    }

    // ── State-level population ───────────────────────────────────────────────

    private function syncStates(int $year, bool $dryRun): int
    {
        $this->info('Fetching state populations from Census Bureau…');

        $url = "https://api.census.gov/data/{$year}/dec/pl"
             . '?get=NAME,P1_001N'
             . '&for=state:*';

        $rows = $this->fetchCensus($url);
        if ($rows === null) {
            return 0;
        }

        // First row is header: [NAME, P1_001N, state]
        $headers = array_shift($rows);
        $nameIdx  = array_search('NAME', $headers);
        $popIdx   = array_search('P1_001N', $headers);
        $fipsIdx  = array_search('state', $headers);

        $count = 0;
        foreach ($rows as $row) {
            $stateName = $row[$nameIdx] ?? null;
            $pop       = (int) ($row[$popIdx] ?? 0);
            $fips      = $row[$fipsIdx] ?? null;

            if (! $stateName || ! $pop || ! $fips) {
                continue;
            }

            $abbr = $this->fipsToAbbr[$fips] ?? $this->nameToAbbr($stateName);
            if (! $abbr) {
                $this->warn("  Unknown state FIPS {$fips} / name {$stateName} — skipped");
                continue;
            }

            $this->line("  {$abbr} ({$stateName}): " . number_format($pop));

            if (! $dryRun) {
                DB::table('district_populations')->upsert([
                    'state'              => $abbr,
                    'district_number'    => null,
                    'label'              => $stateName,
                    'total_population'   => $pop,
                    'census_year'        => $year,
                    'census_product'     => 'dec/pl',
                    'updated_at'         => now(),
                    'created_at'         => now(),
                ], ['state', 'district_number', 'census_year'], ['total_population', 'label', 'updated_at']);
            }
            $count++;
        }

        $this->info("  → {$count} states processed.");
        return $count;
    }

    // ── Congressional-district population ────────────────────────────────────

    private function syncDistricts(int $year, bool $dryRun): int
    {
        $this->info('Fetching congressional district populations from Census Bureau…');

        $url = "https://api.census.gov/data/{$year}/dec/pl"
             . '?get=NAME,P1_001N'
             . '&for=congressional%20district:*'
             . '&in=state:*';

        $rows = $this->fetchCensus($url);
        if ($rows === null) {
            return 0;
        }

        $headers    = array_shift($rows);
        $nameIdx    = array_search('NAME', $headers);
        $popIdx     = array_search('P1_001N', $headers);
        $stateFips  = array_search('state', $headers);
        $distIdx    = array_search('congressional district', $headers);

        $count = 0;
        foreach ($rows as $row) {
            $name     = $row[$nameIdx]   ?? '';
            $pop      = (int) ($row[$popIdx] ?? 0);
            $fips     = $row[$stateFips]  ?? null;
            $distNum  = (int) ($row[$distIdx] ?? 0);

            if (! $pop || ! $fips) {
                continue;
            }

            $abbr = $this->fipsToAbbr[$fips] ?? null;
            if (! $abbr) {
                continue;
            }

            // At-large districts are returned as district 0 or 98/99 in some products
            $label = $distNum === 0 || $distNum >= 98
                ? "{$abbr}-AL"
                : sprintf('%s-%02d', $abbr, $distNum);

            $this->line("  {$label}: " . number_format($pop));

            if (! $dryRun) {
                DB::table('district_populations')->upsert([
                    'state'              => $abbr,
                    'district_number'    => $distNum,
                    'label'              => $label,
                    'total_population'   => $pop,
                    'census_year'        => $year,
                    'census_product'     => 'dec/pl',
                    'updated_at'         => now(),
                    'created_at'         => now(),
                ], ['state', 'district_number', 'census_year'], ['total_population', 'label', 'updated_at']);
            }
            $count++;
        }

        $this->info("  → {$count} districts processed.");
        return $count;
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function fetchCensus(string $url): ?array
    {
        $this->line("  GET {$url}");

        $response = Http::timeout(60)->get($url);

        if (! $response->ok()) {
            $this->error("  Census API error: HTTP {$response->status()} — {$response->body()}");
            return null;
        }

        $data = $response->json();

        if (! is_array($data) || count($data) < 2) {
            $this->error('  Unexpected Census API response shape.');
            return null;
        }

        return $data;
    }

    private function nameToAbbr(string $name): ?string
    {
        // Strip suffixes like " Congressional District (at Large), 2020"
        $bare = preg_replace('/\s*(Congressional District.*|,.*)/i', '', $name);
        return self::STATE_NAMES[trim((string) $bare)] ?? null;
    }
}
