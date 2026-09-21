<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Load the Census Bureau's ZCTA → congressional district relationship file
 * into zip_district_crosswalk — powers ZIP-only lookups on the map's "Find your
 * district" card and /district-lookup (Google Civic no longer returns
 * congressional districts for a bare ZIP).
 *
 * Source: https://www.census.gov/geographies/reference-files/time-series/geo/relationship-files.html
 * (2020 ZCTAs against the 119th Congress; free, no API key). ZCTAs are an
 * approximation of ZIP delivery areas, so results stay "ZIP precision".
 *
 * Usage:
 *   php artisan geo:sync-zip-districts
 *   php artisan geo:sync-zip-districts --dry-run
 */
class SyncZipDistrictCrosswalk extends Command
{
    protected $signature = 'geo:sync-zip-districts
        {--congress=119 : Congress whose district lines to load}
        {--dry-run      : Download and parse without writing to the database}';

    protected $description = 'Sync the Census ZIP → congressional district crosswalk.';

    // A healthy national file has ~40k rows (~33k ZIPs). Refuse to replace the
    // table with anything much smaller — that would be a truncated download.
    private const MIN_ROWS = 30000;

    // FIPS numeric state code → USPS abbreviation. Territories are left out:
    // the map has no district layer for them.
    private const FIPS_TO_STATE = [
        '01' => 'AL', '02' => 'AK', '04' => 'AZ', '05' => 'AR', '06' => 'CA',
        '08' => 'CO', '09' => 'CT', '10' => 'DE', '11' => 'DC', '12' => 'FL',
        '13' => 'GA', '15' => 'HI', '16' => 'ID', '17' => 'IL', '18' => 'IN',
        '19' => 'IA', '20' => 'KS', '21' => 'KY', '22' => 'LA', '23' => 'ME',
        '24' => 'MD', '25' => 'MA', '26' => 'MI', '27' => 'MN', '28' => 'MS',
        '29' => 'MO', '30' => 'MT', '31' => 'NE', '32' => 'NV', '33' => 'NH',
        '34' => 'NJ', '35' => 'NM', '36' => 'NY', '37' => 'NC', '38' => 'ND',
        '39' => 'OH', '40' => 'OK', '41' => 'OR', '42' => 'PA', '44' => 'RI',
        '45' => 'SC', '46' => 'SD', '47' => 'TN', '48' => 'TX', '49' => 'UT',
        '50' => 'VT', '51' => 'VA', '53' => 'WA', '54' => 'WV', '55' => 'WI',
        '56' => 'WY',
    ];

    public function handle(): int
    {
        $congress = (int) $this->option('congress');
        $dryRun = (bool) $this->option('dry-run');
        $url = "https://www2.census.gov/geo/docs/maps-data/data/rel2020/cd-sld/tab20_cd{$congress}20_zcta520_natl.txt";

        $this->info("ZIP → district crosswalk sync — congress={$congress}".($dryRun ? ' [DRY RUN]' : ''));

        $response = Http::timeout(120)->get($url);
        if (! $response->successful()) {
            $this->error("Census download failed (HTTP {$response->status()}): {$url}");

            return self::FAILURE;
        }

        $rows = $this->parse($response->body(), $congress);
        $this->info(count($rows).' ZIP/district pairs across '.count(array_unique(array_column($rows, 'zip'))).' ZIPs.');

        if (count($rows) < self::MIN_ROWS) {
            $this->error('Too few rows parsed — refusing to replace the existing crosswalk.');

            return self::FAILURE;
        }

        if ($dryRun) {
            return self::SUCCESS;
        }

        DB::transaction(function () use ($rows) {
            DB::table('zip_district_crosswalk')->delete();
            foreach (array_chunk($rows, 1000) as $chunk) {
                DB::table('zip_district_crosswalk')->insert($chunk);
            }
        });

        $this->info('Crosswalk replaced.');

        return self::SUCCESS;
    }

    /**
     * @return list<array{zip:string, state:string, district_number:string, land_area:int, congress:int}>
     */
    private function parse(string $body, int $congress): array
    {
        $lines = preg_split('/\r\n|\n|\r/', ltrim($body, "\xEF\xBB\xBF"), -1, PREG_SPLIT_NO_EMPTY);
        $header = array_flip(explode('|', (string) array_shift($lines)));
        $cdCol = $header["GEOID_CD{$congress}_20"] ?? null;
        $zipCol = $header['GEOID_ZCTA5_20'] ?? null;
        $areaCol = $header['AREALAND_PART'] ?? null;
        if ($cdCol === null || $zipCol === null || $areaCol === null) {
            return [];
        }

        $rows = [];
        foreach ($lines as $line) {
            $cols = explode('|', $line);
            $zip = $cols[$zipCol] ?? '';
            $geoid = $cols[$cdCol] ?? '';
            $land = (int) ($cols[$areaCol] ?? 0);
            // Rows with no ZCTA are the district's own totals; "ZZ" districts are
            // undefined; a part with no land is water only.
            if (! preg_match('/^\d{5}$/', $zip) || ! preg_match('/^\d{4}$/', $geoid) || $land <= 0) {
                continue;
            }
            $state = self::FIPS_TO_STATE[substr($geoid, 0, 2)] ?? null;
            if ($state === null) {
                continue;
            }
            // 00 = at-large state, 98 = DC's delegate seat.
            $number = in_array(substr($geoid, 2), ['00', '98'], true) ? 'AL' : (string) (int) substr($geoid, 2);
            $rows["{$zip}|{$state}|{$number}"] = [
                'zip' => $zip, 'state' => $state, 'district_number' => $number,
                'land_area' => $land, 'congress' => $congress,
            ];
        }

        return array_values($rows);
    }
}
