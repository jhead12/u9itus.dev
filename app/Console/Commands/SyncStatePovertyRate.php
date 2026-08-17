<?php

namespace App\Console\Commands;

use App\Models\ImportRunLog;
use App\Models\StateDemographic;
use App\Models\User;
use App\Notifications\ImportRunFailedNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Fetch state-level poverty rate from the Census Bureau ACS 5-year estimates
 * and upsert into state_demographics — powers the map's "Poverty Rate"
 * choropleth layer (colors all 50 states + DC at once, overview zoom).
 *
 * Unlike geo:sync-census-demographics (city-level, requires one API call per
 * state plus a curated city allow-list and district geocoding), state-level
 * ACS geography needs none of that: a single request with `&for=state:*`
 * returns every state in one response.
 *
 * Data source (ACS 5-year estimates, state geography):
 *   - S1701_C03_001E : percent of population below poverty level
 *
 * Census Bureau API docs: https://www.census.gov/data/developers/data-sets.html
 * Requires an API key (same one geo:sync-census-demographics uses) — set
 * CENSUS_DATA_API in .env.
 *
 * Usage:
 *   php artisan geo:sync-state-poverty-rate
 *   php artisan geo:sync-state-poverty-rate --year=2022
 *   php artisan geo:sync-state-poverty-rate --dry-run
 */
class SyncStatePovertyRate extends Command
{
    protected $signature = 'geo:sync-state-poverty-rate
        {--year=2022  : ACS 5-year estimate vintage to pull}
        {--dry-run    : Fetch and report without writing to the database}';

    protected $description = 'Sync state-level poverty rate from the Census ACS API.';

    // FIPS numeric state code → USPS abbreviation (inverse of the mapping
    // duplicated in SyncCensusPopulation/SyncCensusDemographics — kept as its
    // own copy here to match that existing convention rather than extracting
    // a shared const for three call sites).
    private const FIPS_TO_STATE = [
        '01' => 'AL', '02' => 'AK', '04' => 'AZ', '05' => 'AR', '06' => 'CA',
        '08' => 'CO', '09' => 'CT', '10' => 'DE', '12' => 'FL', '13' => 'GA',
        '15' => 'HI', '16' => 'ID', '17' => 'IL', '18' => 'IN', '19' => 'IA',
        '20' => 'KS', '21' => 'KY', '22' => 'LA', '23' => 'ME', '24' => 'MD',
        '25' => 'MA', '26' => 'MI', '27' => 'MN', '28' => 'MS', '29' => 'MO',
        '30' => 'MT', '31' => 'NE', '32' => 'NV', '33' => 'NH', '34' => 'NJ',
        '35' => 'NM', '36' => 'NY', '37' => 'NC', '38' => 'ND', '39' => 'OH',
        '40' => 'OK', '41' => 'OR', '42' => 'PA', '44' => 'RI', '45' => 'SC',
        '46' => 'SD', '47' => 'TN', '48' => 'TX', '49' => 'UT', '50' => 'VT',
        '51' => 'VA', '53' => 'WA', '54' => 'WV', '55' => 'WI', '56' => 'WY',
        '11' => 'DC',
    ];

    public function handle(): int
    {
        $year = (int) $this->option('year');
        $dryRun = (bool) $this->option('dry-run');

        $this->info('State poverty-rate sync — year=' . $year . ($dryRun ? ' [DRY RUN]' : ''));

        $runLog = ImportRunLog::create([
            'command_name' => 'geo:sync-state-poverty-rate',
            'source_url'   => "https://api.census.gov/data/{$year}/acs/acs5/subject",
            'with_campaigns' => false,
            'dry_run'      => $dryRun,
            'status'       => 'running',
            'started_at'   => now(),
        ]);

        try {
            $rows = $this->fetchCensus(
                "https://api.census.gov/data/{$year}/acs/acs5/subject"
                . '?get=NAME,S1701_C03_001E'
                . '&for=state:*'
                . $this->apiKeyParam()
            );

            if ($rows === null) {
                $summary = "year={$year} — Census API request failed";
                $runLog->markFailed(self::FAILURE, $summary, 'Census API request failed — see command output.');
                $this->alertAdmins($runLog);

                return self::FAILURE;
            }

            $headers = array_shift($rows) ?? [];
            $stateIdx = array_search('state', $headers, true);
            $povertyIdx = array_search('S1701_C03_001E', $headers, true);
            $nameIdx = array_search('NAME', $headers, true);

            $upserted = 0;
            $lines = [];

            foreach ($rows as $row) {
                $fips = $row[$stateIdx] ?? null;
                $abbr = self::FIPS_TO_STATE[$fips] ?? null;
                if (! $abbr) {
                    continue;
                }

                $povertyRate = $this->censusValue($row[$povertyIdx] ?? null);
                $name = $row[$nameIdx] ?? $abbr;

                $lines[] = sprintf('  %s (%s): poverty=%s%%', $name, $abbr,
                    $povertyRate !== null ? number_format($povertyRate, 1) : 'n/a');

                if (! $dryRun && $povertyRate !== null) {
                    StateDemographic::updateOrCreate(
                        ['state' => $abbr],
                        ['poverty_rate' => $povertyRate, 'census_year' => $year, 'source' => 'acs5'],
                    );
                    $upserted++;
                }
            }

            foreach ($lines as $line) {
                $this->line($line);
            }

            $suffix = $dryRun ? ' (dry-run — no DB writes)' : '';
            $this->info("Done. {$upserted} states upserted{$suffix}.");

            if (! $dryRun && $upserted === 0) {
                $summary = "year={$year} upserted=0";
                $runLog->markFailed(self::SUCCESS, $summary, 'State poverty-rate sync completed but upserted 0 rows — likely a Census API problem.');
                $this->alertAdmins($runLog);
                $this->error('0 rows upserted — possible Census API failure. Admins alerted.');

                return self::FAILURE;
            }

            $summary = "year={$year} upserted={$upserted}" . ($dryRun ? ' [DRY RUN]' : '');
            $runLog->markSuccess(self::SUCCESS, $summary, ['created' => $upserted, 'updated' => 0, 'skipped' => 0, 'campaigns_created' => 0]);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $runLog->markFailed(-1, "year={$year}", $e->getMessage());
            $this->alertAdmins($runLog);

            Log::error('State poverty-rate sync crashed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->error('Sync crashed: ' . $e->getMessage() . ' — admins alerted.');

            return self::FAILURE;
        }
    }

    protected function alertAdmins(ImportRunLog $runLog): void
    {
        try {
            $admins = User::where('user_type', 'admin')->get();
            if ($admins->isNotEmpty()) {
                Notification::send($admins, new ImportRunFailedNotification($runLog, 'State Poverty-Rate Sync'));
            }
        } catch (\Throwable $e) {
            Log::warning('State poverty-rate sync: failed to queue admin alert', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * ACS estimates use large-magnitude negative sentinels (e.g. -666666666)
     * to mean "not available," not a literal negative value.
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
        $this->line("GET {$url}");

        $response = Http::timeout(60)->get($url);

        if (! $response->ok()) {
            $this->error("Census API error: HTTP {$response->status()} — {$response->body()}");

            return null;
        }

        $data = $response->json();

        if (! is_array($data) || count($data) < 2) {
            $this->error('Unexpected Census API response shape.');

            return null;
        }

        return $data;
    }
}
