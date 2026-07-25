<?php

namespace App\Console\Commands;

use App\Models\CityDemographic;
use App\Models\ImportRunLog;
use App\Models\User;
use App\Notifications\ImportRunFailedNotification;
use App\Services\DistrictLookupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
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
     * Allow-list of cities per state (name + lat/lng), mirroring TOP_CITIES in
     * resources/js/map/config/city-data.js — bounds this sync to the same
     * ~200 cities already shown on the map instead of ingesting every
     * Census-designated place. Coordinates are used to resolve each city's
     * congressional district via the same geocoder the map's "Find My
     * District" and region-panel city clicks use, precomputed here instead
     * of on every click.
     *
     * @var array<string, array<int, array{0:string,1:float,2:float}>>
     */
    private const CITY_ALLOWLIST = [
        'AK' => [['Anchorage', 61.218, -149.9], ['Fairbanks', 64.838, -147.716], ['Juneau', 58.301, -134.42]],
        'AL' => [['Huntsville', 34.73, -86.586], ['Birmingham', 33.52, -86.8], ['Montgomery', 32.366, -86.3], ['Mobile', 30.694, -88.043]],
        'AR' => [['Little Rock', 34.746, -92.289], ['Fayetteville', 36.082, -94.157], ['Fort Smith', 35.385, -94.398]],
        'AZ' => [['Phoenix', 33.448, -112.074], ['Tucson', 32.221, -110.926], ['Mesa', 33.415, -111.831], ['Chandler', 33.308, -111.841], ['Gilbert', 33.353, -111.789], ['Scottsdale', 33.494, -111.926]],
        'CA' => [['Los Angeles', 34.052, -118.244], ['San Diego', 32.715, -117.157], ['San Jose', 37.338, -121.886], ['San Francisco', 37.774, -122.419], ['Fresno', 36.737, -119.787], ['Sacramento', 38.581, -121.494], ['Long Beach', 33.77, -118.194], ['Oakland', 37.804, -122.271], ['Bakersfield', 35.373, -119.019], ['Anaheim', 33.837, -117.914]],
        'CO' => [['Denver', 39.739, -104.984], ['Colorado Springs', 38.833, -104.821], ['Aurora', 39.729, -104.832], ['Fort Collins', 40.585, -105.084], ['Lakewood', 39.705, -105.081]],
        'CT' => [['Bridgeport', 41.179, -73.189], ['Stamford', 41.053, -73.538], ['New Haven', 41.308, -72.928], ['Hartford', 41.764, -72.685]],
        'DC' => [['Washington', 38.907, -77.037]],
        'DE' => [['Wilmington', 39.745, -75.547], ['Dover', 39.158, -75.524]],
        'FL' => [['Jacksonville', 30.332, -81.656], ['Miami', 25.774, -80.194], ['Tampa', 27.947, -82.459], ['Orlando', 28.538, -81.379], ['St. Petersburg', 27.771, -82.64], ['Hialeah', 25.858, -80.279]],
        'GA' => [['Atlanta', 33.749, -84.388], ['Columbus', 32.46, -84.987], ['Augusta', 33.47, -81.975], ['Macon', 32.84, -83.632], ['Savannah', 32.083, -81.099]],
        'HI' => [['Honolulu', 21.306, -157.858], ['Pearl City', 21.397, -157.975], ['Hilo', 19.702, -155.085]],
        'IA' => [['Des Moines', 41.619, -93.598], ['Cedar Rapids', 41.978, -91.665], ['Davenport', 41.524, -90.578]],
        'ID' => [['Boise', 43.615, -116.202], ['Meridian', 43.612, -116.392], ['Nampa', 43.54, -116.563]],
        'IL' => [['Chicago', 41.878, -87.63], ['Aurora', 41.757, -88.32], ['Joliet', 41.525, -88.082], ['Naperville', 41.786, -88.147], ['Rockford', 42.271, -89.094], ['Springfield', 39.801, -89.643]],
        'IN' => [['Indianapolis', 39.768, -86.158], ['Fort Wayne', 41.13, -85.129], ['Evansville', 37.975, -87.557], ['South Bend', 41.676, -86.252]],
        'KS' => [['Wichita', 37.687, -97.33], ['Overland Park', 38.982, -94.671], ['Kansas City', 39.114, -94.627]],
        'KY' => [['Louisville', 38.254, -85.759], ['Lexington', 38.049, -84.499], ['Bowling Green', 36.99, -86.444]],
        'LA' => [['New Orleans', 29.951, -90.071], ['Baton Rouge', 30.457, -91.154], ['Shreveport', 32.525, -93.75], ['Lafayette', 30.224, -92.02]],
        'MA' => [['Boston', 42.36, -71.059], ['Worcester', 42.263, -71.803], ['Springfield', 42.101, -72.59], ['Cambridge', 42.374, -71.106]],
        'MD' => [['Baltimore', 39.29, -76.612], ['Frederick', 39.414, -77.411], ['Rockville', 39.084, -77.153]],
        'ME' => [['Portland', 43.657, -70.259], ['Lewiston', 44.1, -70.215]],
        'MI' => [['Detroit', 42.331, -83.046], ['Grand Rapids', 42.963, -85.668], ['Warren', 42.469, -83.026], ['Sterling Heights', 42.58, -83.031], ['Ann Arbor', 42.281, -83.748]],
        'MN' => [['Minneapolis', 44.977, -93.265], ['Saint Paul', 44.954, -93.102], ['Rochester', 44.022, -92.47], ['Duluth', 46.786, -92.1]],
        'MO' => [['Kansas City', 39.099, -94.579], ['St. Louis', 38.627, -90.197], ['Springfield', 37.215, -93.298], ['Columbia', 38.952, -92.334]],
        'MS' => [['Jackson', 32.298, -90.185], ['Gulfport', 30.367, -89.093], ['Southaven', 34.989, -90.001]],
        'MT' => [['Billings', 45.783, -108.501], ['Missoula', 46.872, -113.994], ['Great Falls', 47.502, -111.301]],
        'NC' => [['Charlotte', 35.227, -80.843], ['Raleigh', 35.78, -78.639], ['Greensboro', 36.072, -79.791], ['Durham', 35.994, -78.899], ['Winston-Salem', 36.099, -80.244]],
        'ND' => [['Fargo', 46.878, -96.788], ['Bismarck', 46.809, -100.793]],
        'NE' => [['Omaha', 41.257, -95.935], ['Lincoln', 40.813, -96.703], ['Bellevue', 41.152, -95.899]],
        'NH' => [['Manchester', 42.99, -71.464], ['Nashua', 42.765, -71.468], ['Concord', 43.207, -71.537]],
        'NJ' => [['Newark', 40.735, -74.172], ['Jersey City', 40.719, -74.044], ['Paterson', 40.917, -74.172], ['Elizabeth', 40.665, -74.21]],
        'NM' => [['Albuquerque', 35.085, -106.651], ['Las Cruces', 32.32, -106.765], ['Rio Rancho', 35.233, -106.664]],
        'NV' => [['Las Vegas', 36.175, -115.137], ['Henderson', 36.039, -114.982], ['Reno', 39.529, -119.814], ['North Las Vegas', 36.199, -115.117]],
        'NY' => [['New York City', 40.713, -74.006], ['Buffalo', 42.886, -78.879], ['Rochester', 43.16, -77.611], ['Yonkers', 40.931, -73.899], ['Syracuse', 43.048, -76.147], ['Albany', 42.651, -73.755]],
        'OH' => [['Columbus', 39.961, -82.999], ['Cleveland', 41.505, -81.693], ['Cincinnati', 39.103, -84.512], ['Toledo', 41.664, -83.556], ['Akron', 41.081, -81.519]],
        'OK' => [['Oklahoma City', 35.468, -97.516], ['Tulsa', 36.154, -95.993], ['Norman', 35.222, -97.439], ['Broken Arrow', 36.06, -95.791]],
        'OR' => [['Portland', 45.523, -122.676], ['Eugene', 44.052, -123.087], ['Salem', 44.942, -123.029], ['Gresham', 45.499, -122.43]],
        'PA' => [['Philadelphia', 39.953, -75.165], ['Pittsburgh', 40.44, -79.996], ['Allentown', 40.607, -75.491], ['Erie', 42.129, -80.085], ['Reading', 40.336, -75.927]],
        'RI' => [['Providence', 41.824, -71.413], ['Cranston', 41.78, -71.437], ['Woonsocket', 42.002, -71.515]],
        'SC' => [['Charleston', 32.777, -79.931], ['Columbia', 34.0, -81.035], ['North Charleston', 32.854, -79.974], ['Mount Pleasant', 32.827, -79.828]],
        'SD' => [['Sioux Falls', 43.549, -96.7], ['Rapid City', 44.081, -103.231]],
        'TN' => [['Nashville', 36.165, -86.784], ['Memphis', 35.149, -90.049], ['Knoxville', 35.96, -83.921], ['Chattanooga', 35.047, -85.309], ['Clarksville', 36.53, -87.359]],
        'TX' => [['Houston', 29.763, -95.363], ['San Antonio', 29.425, -98.494], ['Dallas', 32.789, -96.8], ['Austin', 30.267, -97.743], ['Fort Worth', 32.755, -97.333], ['El Paso', 31.759, -106.487], ['Arlington', 32.7, -97.12], ['Corpus Christi', 27.8, -97.396]],
        'UT' => [['Salt Lake City', 40.76, -111.891], ['West Valley City', 40.688, -112.001], ['West Jordan', 40.602, -111.939], ['Provo', 40.233, -111.658]],
        'VA' => [['Virginia Beach', 36.853, -75.978], ['Chesapeake', 36.818, -76.275], ['Norfolk', 36.847, -76.286], ['Arlington', 38.88, -77.1], ['Richmond', 37.541, -77.434]],
        'VT' => [['Burlington', 44.476, -73.212], ['South Burlington', 44.467, -73.171]],
        'WA' => [['Seattle', 47.607, -122.332], ['Spokane', 47.659, -117.426], ['Tacoma', 47.252, -122.444], ['Vancouver', 45.638, -122.661], ['Bellevue', 47.614, -122.192]],
        'WI' => [['Milwaukee', 43.038, -87.906], ['Madison', 43.073, -89.401], ['Green Bay', 44.519, -88.02], ['Kenosha', 42.585, -87.821]],
        'WV' => [['Charleston', 38.349, -81.633], ['Huntington', 38.419, -82.445], ['Morgantown', 39.631, -79.957]],
        'WY' => [['Cheyenne', 41.14, -104.82], ['Casper', 42.867, -106.313]],
    ];

    public function handle(DistrictLookupService $districtLookup): int
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

        // Record this run for admin visibility (ImportRunLog is shared with the
        // politician import pipeline; command_name distinguishes them).
        $runLog = ImportRunLog::create([
            'command_name' => 'geo:sync-census-demographics',
            'source_url'   => "https://api.census.gov/data/{$year}/acs/acs5",
            'with_campaigns' => false,
            'dry_run'      => $dryRun,
            'status'       => 'running',
            'started_at'   => now(),
        ]);

        $perState = [];
        $upserted = 0;

        try {
            foreach ($states as $abbr => $fips) {
                $count = $this->syncState($abbr, $fips, $year, $dryRun, $districtLookup);
                $perState[$abbr] = $count;
                $upserted += $count;
            }

            $suffix = $dryRun ? ' (dry-run — no DB writes)' : '';
            $this->info("Done. {$upserted} rows upserted{$suffix}.");

            // A non-dry-run that touches states but upserts zero rows almost
            // always means a systemic Census API problem (bad key, outage,
            // schema change). Surface it as a failure so admins get alerted
            // instead of silently losing all city demographics.
            if (! $dryRun && $upserted === 0) {
                $summary = $this->buildRunSummary($year, $states, $perState, $upserted, $dryRun);
                $runLog->markFailed(self::SUCCESS, $summary, 'Census demographics sync completed but upserted 0 rows across ' . count($states) . ' states — likely a Census API problem.');
                $this->alertAdmins($runLog);
                $this->error('0 rows upserted — possible Census API failure. Admins alerted.');

                return self::FAILURE;
            }

            $summary = $this->buildRunSummary($year, $states, $perState, $upserted, $dryRun);
            $runLog->markSuccess(self::SUCCESS, $summary, ['created' => $upserted, 'updated' => 0, 'skipped' => 0, 'campaigns_created' => 0]);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $summary = $this->buildRunSummary($year, $states, $perState, $upserted, $dryRun);
            $runLog->markFailed(-1, $summary, $e->getMessage());
            $this->alertAdmins($runLog);

            Log::error('Census demographics sync crashed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->error('Sync crashed: ' . $e->getMessage() . ' — admins alerted.');

            return self::FAILURE;
        }
    }

    /**
     * Build a compact, admin-readable run summary for the ImportRunLog output.
     *
     * @param array<string, string> $states
     * @param array<string, int> $perState
     */
    protected function buildRunSummary(int $year, array $states, array $perState, int $upserted, bool $dryRun): string
    {
        $lines = ["year={$year} states=" . count($states) . " upserted={$upserted}" . ($dryRun ? ' [DRY RUN]' : '')];
        foreach ($perState as $abbr => $count) {
            $lines[] = "  {$abbr}: {$count}";
        }

        return implode("\n", $lines);
    }

    /**
     * Queue a failure notification to all admin users.
     */
    protected function alertAdmins(ImportRunLog $runLog): void
    {
        try {
            $admins = User::where('user_type', 'admin')->get();
            if ($admins->isNotEmpty()) {
                Notification::send($admins, new ImportRunFailedNotification($runLog, 'Census Demographics Sync'));
            }
        } catch (\Throwable $e) {
            Log::warning('Census demographics sync: failed to queue admin alert', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function syncState(string $abbr, string $fips, int $year, bool $dryRun, DistrictLookupService $districtLookup): int
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

        // Keyed by normalized name -> [cityName, lat, lng].
        $normalizedAllowlist = [];
        foreach ($allowlist as [$name, $lat, $lng]) {
            $normalizedAllowlist[$this->normalizeName($name)] = [$name, $lat, $lng];
        }

        $count = 0;
        foreach ($detailByPlace as $rawName => $row) {
            $normalized = $this->normalizeName($rawName);
            if (! isset($normalizedAllowlist[$normalized])) {
                continue;
            }

            [$cityName, $lat, $lng] = $normalizedAllowlist[$normalized];
            $subjectRow = $subjectByPlace[$rawName] ?? [];

            $population = $this->censusValue($row['B01001_001E'] ?? null);
            $income = $this->censusValue($row['B19013_001E'] ?? null);
            $povertyRate = $this->censusValue($subjectRow['S1701_C03_001E'] ?? null);
            $bachelorsPlus = $this->censusValue($subjectRow['S1501_C02_015E'] ?? null);
            $district = $this->resolveDistrict($lat, $lng, $districtLookup);

            $this->line(sprintf(
                '  %s, %s (%s): pop=%s income=$%s poverty=%s%% bachelors+=%s%%',
                $cityName, $abbr, $district['code'] ?? 'district unknown',
                $population !== null ? number_format($population) : 'n/a',
                $income !== null ? number_format($income) : 'n/a',
                $povertyRate !== null ? number_format($povertyRate, 1) : 'n/a',
                $bachelorsPlus !== null ? number_format($bachelorsPlus, 1) : 'n/a',
            ));

            if (! $dryRun) {
                CityDemographic::updateOrCreate(
                    ['state' => $abbr, 'city_name' => $cityName, 'census_year' => $year],
                    [
                        'district_number' => $district['number'],
                        'district_code' => $district['code'],
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

    /**
     * Resolve a city's congressional district via the same service the map's
     * "Find My District" button and region-panel city clicks use (in-process,
     * not an HTTP call to our own app — that's fragile from a console command,
     * which has no guarantee the app is reachable at its configured APP_URL).
     * Precomputed here once per sync instead of live on every click.
     *
     * @return array{number: ?string, code: ?string}
     */
    private function resolveDistrict(float $lat, float $lng, DistrictLookupService $districtLookup): array
    {
        $result = $districtLookup->lookupByCoordinates($lat, $lng);

        return [
            'number' => $result['district_number'] ?? null,
            'code' => $result['district_code'] ?? null,
        ];
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
