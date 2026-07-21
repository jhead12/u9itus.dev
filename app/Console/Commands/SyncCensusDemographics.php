<?php

namespace App\Console\Commands;

use App\Models\CityDemographic;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Fetch city-level socioeconomic data (poverty rate, educational attainment,
 * median household income) from the Census Bureau ACS 5-year estimates and
 * upsert into city_demographics — for the same curated city list already
 * shown on the map (resources/js/map/config/city-data.js TOP_CITIES).
 *
 * Data sources (ACS 5-year estimates, place geography):
 *   - B01001_001E        : total population (for allow-list matching + display)
 *   - B19013_001E        : median household income
 *   - S1701_C03_001E     : percent of population below poverty level
 *   - S1501_C02_015E     : percent age 25+ with bachelor's degree or higher
 *
 * Census Bureau API docs: https://www.census.gov/data/developers/data-sets.html
 * Requires an API key (free, https://api.census.gov/data/key_signup.html) —
 * set CENSUS_DATA_API in .env. As of 2026 the API rejects unauthenticated
 * requests entirely (this differs from geo:sync-census-population, written
 * when low-volume unauthenticated access was still allowed).
 *
 * Usage:
 *   php artisan geo:sync-census-demographics
 *   php artisan geo:sync-census-demographics --year=2022
 *   php artisan geo:sync-census-demographics --state=RI --state=VT
 *   php artisan geo:sync-census-demographics --dry-run
 */
class SyncCensusDemographics extends Command
{
    protected $signature = 'geo:sync-census-demographics
        {--year=2022  : ACS 5-year estimate vintage to pull}
        {--state=*    : Two-letter state filters (repeat option to include multiple states)}
        {--dry-run    : Fetch and report without writing to the database}';

    protected $description = 'Sync city-level poverty rate, educational attainment, and median household income from the Census ACS API.';

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

    /**
     * Allow-list of city names per state, mirroring TOP_CITIES in
     * resources/js/map/config/city-data.js — bounds this sync to the same
     * ~200 cities already shown on the map instead of ingesting every
     * Census-designated place.
     *
     * @var array<string, array<int, string>>
     */
    private const CITY_ALLOWLIST = [
        'AK' => ["Anchorage", "Fairbanks", "Juneau"],
        'AL' => ["Huntsville", "Birmingham", "Montgomery", "Mobile"],
        'AR' => ["Little Rock", "Fayetteville", "Fort Smith"],
        'AZ' => ["Phoenix", "Tucson", "Mesa", "Chandler", "Gilbert", "Scottsdale"],
        'CA' => ["Los Angeles", "San Diego", "San Jose", "San Francisco", "Fresno", "Sacramento", "Long Beach", "Oakland", "Bakersfield", "Anaheim"],
        'CO' => ["Denver", "Colorado Springs", "Aurora", "Fort Collins", "Lakewood"],
        'CT' => ["Bridgeport", "Stamford", "New Haven", "Hartford"],
        'DC' => ["Washington"],
        'DE' => ["Wilmington", "Dover"],
        'FL' => ["Jacksonville", "Miami", "Tampa", "Orlando", "St. Petersburg", "Hialeah"],
        'GA' => ["Atlanta", "Columbus", "Augusta", "Macon", "Savannah"],
        'HI' => ["Honolulu", "Pearl City", "Hilo"],
        'IA' => ["Des Moines", "Cedar Rapids", "Davenport"],
        'ID' => ["Boise", "Meridian", "Nampa"],
        'IL' => ["Chicago", "Aurora", "Joliet", "Naperville", "Rockford", "Springfield"],
        'IN' => ["Indianapolis", "Fort Wayne", "Evansville", "South Bend"],
        'KS' => ["Wichita", "Overland Park", "Kansas City"],
        'KY' => ["Louisville", "Lexington", "Bowling Green"],
        'LA' => ["New Orleans", "Baton Rouge", "Shreveport", "Lafayette"],
        'MA' => ["Boston", "Worcester", "Springfield", "Cambridge"],
        'MD' => ["Baltimore", "Frederick", "Rockville"],
        'ME' => ["Portland", "Lewiston"],
        'MI' => ["Detroit", "Grand Rapids", "Warren", "Sterling Heights", "Ann Arbor"],
        'MN' => ["Minneapolis", "Saint Paul", "Rochester", "Duluth"],
        'MO' => ["Kansas City", "St. Louis", "Springfield", "Columbia"],
        'MS' => ["Jackson", "Gulfport", "Southaven"],
        'MT' => ["Billings", "Missoula", "Great Falls"],
        'NC' => ["Charlotte", "Raleigh", "Greensboro", "Durham", "Winston-Salem"],
        'ND' => ["Fargo", "Bismarck"],
        'NE' => ["Omaha", "Lincoln", "Bellevue"],
        'NH' => ["Manchester", "Nashua", "Concord"],
        'NJ' => ["Newark", "Jersey City", "Paterson", "Elizabeth"],
        'NM' => ["Albuquerque", "Las Cruces", "Rio Rancho"],
        'NV' => ["Las Vegas", "Henderson", "Reno", "North Las Vegas"],
        'NY' => ["New York City", "Buffalo", "Rochester", "Yonkers", "Syracuse", "Albany"],
        'OH' => ["Columbus", "Cleveland", "Cincinnati", "Toledo", "Akron"],
        'OK' => ["Oklahoma City", "Tulsa", "Norman", "Broken Arrow"],
        'OR' => ["Portland", "Eugene", "Salem", "Gresham"],
        'PA' => ["Philadelphia", "Pittsburgh", "Allentown", "Erie", "Reading"],
        'RI' => ["Providence", "Cranston", "Woonsocket"],
        'SC' => ["Charleston", "Columbia", "North Charleston", "Mount Pleasant"],
        'SD' => ["Sioux Falls", "Rapid City"],
        'TN' => ["Nashville", "Memphis", "Knoxville", "Chattanooga", "Clarksville"],
        'TX' => ["Houston", "San Antonio", "Dallas", "Austin", "Fort Worth", "El Paso", "Arlington", "Corpus Christi"],
        'UT' => ["Salt Lake City", "West Valley City", "West Jordan", "Provo"],
        'VA' => ["Virginia Beach", "Chesapeake", "Norfolk", "Arlington", "Richmond"],
        'VT' => ["Burlington", "South Burlington"],
        'WA' => ["Seattle", "Spokane", "Tacoma", "Vancouver", "Bellevue"],
        'WI' => ["Milwaukee", "Madison", "Green Bay", "Kenosha"],
        'WV' => ["Charleston", "Huntington", "Morgantown"],
        'WY' => ["Cheyenne", "Casper"],
    ];

    public function handle(): int
    {
        $year = (int) $this->option('year');
        $dryRun = (bool) $this->option('dry-run');
        $stateFilter = collect((array) $this->option('state'))
            ->map(fn ($s) => strtoupper(trim((string) $s)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $states = $stateFilter !== []
            ? array_intersect_key(self::STATE_FIPS, array_flip($stateFilter))
            : self::STATE_FIPS;

        if ($states === []) {
            $this->error('No matching states for --state filter.');

            return self::FAILURE;
        }

        $this->info('Census demographics sync — year=' . $year . ' states=' . count($states) . ($dryRun ? ' [DRY RUN]' : ''));

        $upserted = 0;
        foreach ($states as $abbr => $fips) {
            $upserted += $this->syncState($abbr, $fips, $year, $dryRun);
        }

        $suffix = $dryRun ? ' (dry-run — no DB writes)' : '';
        $this->info("Done. {$upserted} rows upserted{$suffix}.");

        return self::SUCCESS;
    }

    private function syncState(string $abbr, string $fips, int $year, bool $dryRun): int
    {
        $allowlist = self::CITY_ALLOWLIST[$abbr] ?? [];
        if ($allowlist === []) {
            return 0;
        }

        $this->info("Fetching {$abbr} place-level demographics…");

        // Detail table: population + median household income.
        $detail = $this->fetchCensus(
            "https://api.census.gov/data/{$year}/acs/acs5"
            . '?get=NAME,B01001_001E,B19013_001E'
            . '&for=place:*'
            . "&in=state:{$fips}"
            . $this->apiKeyParam()
        );

        // Subject tables: pre-computed percentages (poverty rate, bachelor's+).
        $subject = $this->fetchCensus(
            "https://api.census.gov/data/{$year}/acs/acs5/subject"
            . '?get=NAME,S1701_C03_001E,S1501_C02_015E'
            . '&for=place:*'
            . "&in=state:{$fips}"
            . $this->apiKeyParam()
        );

        if ($detail === null || $subject === null) {
            return 0;
        }

        $detailByPlace = $this->indexByPlaceName($detail);
        $subjectByPlace = $this->indexByPlaceName($subject);

        $normalizedAllowlist = [];
        foreach ($allowlist as $name) {
            $normalizedAllowlist[$this->normalizeName($name)] = $name;
        }

        $count = 0;
        foreach ($detailByPlace as $rawName => $row) {
            $normalized = $this->normalizeName($rawName);
            if (! isset($normalizedAllowlist[$normalized])) {
                continue;
            }

            $cityName = $normalizedAllowlist[$normalized];
            $subjectRow = $subjectByPlace[$rawName] ?? [];

            $population = $this->censusValue($row['B01001_001E'] ?? null);
            $income = $this->censusValue($row['B19013_001E'] ?? null);
            $povertyRate = $this->censusValue($subjectRow['S1701_C03_001E'] ?? null);
            $bachelorsPlus = $this->censusValue($subjectRow['S1501_C02_015E'] ?? null);

            $this->line(sprintf(
                '  %s, %s: pop=%s income=$%s poverty=%s%% bachelors+=%s%%',
                $cityName, $abbr,
                $population !== null ? number_format($population) : 'n/a',
                $income !== null ? number_format($income) : 'n/a',
                $povertyRate !== null ? number_format($povertyRate, 1) : 'n/a',
                $bachelorsPlus !== null ? number_format($bachelorsPlus, 1) : 'n/a',
            ));

            if (! $dryRun) {
                CityDemographic::updateOrCreate(
                    ['state' => $abbr, 'city_name' => $cityName, 'census_year' => $year],
                    [
                        'population' => $population,
                        'poverty_rate' => $povertyRate,
                        'pct_bachelors_or_higher' => $bachelorsPlus,
                        'median_household_income' => $income,
                        'source' => 'acs5',
                    ],
                );
            }
            $count++;
        }

        $this->info("  → {$count}/" . count($allowlist) . ' cities matched.');

        return $count;
    }

    /**
     * Index Census API rows by their raw place NAME field.
     *
     * @return array<string, array<string, string>>
     */
    private function indexByPlaceName(array $rows): array
    {
        $headers = array_shift($rows) ?? [];
        $nameIdx = array_search('NAME', $headers, true);
        if ($nameIdx === false) {
            return [];
        }

        $indexed = [];
        foreach ($rows as $row) {
            $name = $row[$nameIdx] ?? null;
            if (! $name) {
                continue;
            }
            $byColumn = [];
            foreach ($headers as $i => $col) {
                $byColumn[$col] = $row[$i] ?? null;
            }
            $indexed[$name] = $byColumn;
        }

        return $indexed;
    }

    /**
     * Normalize a Census place NAME ("Los Angeles city, California") or an
     * allow-list entry ("Los Angeles", "New York City") down to a comparable
     * bare name, so both sides match regardless of place-type suffix.
     */
    private function normalizeName(string $name): string
    {
        // Strip the ", {State}" suffix Census appends to place names.
        $bare = preg_replace('/,.*/', '', $name);
        // Strip a trailing place-type word (city/town/village/borough/CDP).
        $bare = preg_replace('/\s+(city|town|village|borough|CDP)$/i', '', (string) $bare);

        return Str::lower(trim((string) $bare));
    }

    /**
     * ACS estimates use large-magnitude negative sentinels (e.g. -666666666)
     * to mean "not available" (insufficient sample size for that geography),
     * not a literal negative value — treat anything below -1,000,000 as null.
     */
    private function censusValue(mixed $raw): int|float|null
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        $value = is_numeric($raw) ? $raw + 0 : null;
        if ($value === null || $value <= -1_000_000) {
            return null;
        }

        return $value;
    }

    private function apiKeyParam(): string
    {
        $key = env('CENSUS_DATA_API');

        return $key ? '&key=' . rawurlencode($key) : '';
    }

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
}
