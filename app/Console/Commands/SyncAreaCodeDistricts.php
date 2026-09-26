<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Build area_code_districts: every city an area code serves, and the
 * congressional district(s) each city falls in. Powers area-code searches on
 * /district-lookup, where the voter picks their city from the list.
 *
 * Sources (both free, no API key):
 *  - Cities: libphonenumber's prefix geocoding data (Apache-2.0), which names
 *    the town each block of phone numbers is assigned to.
 *    https://github.com/google/libphonenumber/tree/master/resources/geocoding
 *  - Districts: Census place and county-subdivision → 119th Congress
 *    relationship files. County subdivisions cover the New England towns and
 *    NYC boroughs ("Brooklyn") that aren't Census places.
 *    https://www.census.gov/geographies/reference-files/time-series/geo/relationship-files.html
 *
 * The phone data only names the main town for each block of numbers, so small
 * towns and unincorporated communities are often missing — the lookup page
 * tells voters this and points them to ZIP or address search.
 *
 * Usage:
 *   php artisan geo:sync-area-code-districts
 *   php artisan geo:sync-area-code-districts --dry-run
 */
class SyncAreaCodeDistricts extends Command
{
    protected $signature = 'geo:sync-area-code-districts
        {--congress=119 : Congress whose district lines to load}
        {--dry-run      : Download and parse without writing to the database}';

    protected $description = 'Sync the area code → city → congressional district table.';

    private const PHONE_GEOCODING_URL = 'https://raw.githubusercontent.com/google/libphonenumber/master/resources/geocoding/en/1.txt';

    // A healthy build has ~10k rows. Refuse to replace the table with anything
    // much smaller — that would be a truncated download.
    private const MIN_ROWS = 8000;

    // A district must hold at least this share of a city's land to be listed.
    private const MIN_DISTRICT_SHARE = 0.001;

    // Census legal/statistical area suffixes, stripped so "Austin city" matches "Austin".
    private const LSAD_SUFFIX = '/\s+(?:city and borough|(?:consolidated|metropolitan|unified) government.*|urban county|city|town|village|borough|CDP|charter township|township|municipality|plantation|comunidad|zona urbana|CCD|UT|gore|grant|location|purchase)(?:\s*\(balance\))?$/i';

    // Abbreviations the phone data uses in place names.
    private const ABBREVIATIONS = [
        'st' => 'saint', 'ste' => 'sainte', 'ft' => 'fort', 'mt' => 'mount', 'pt' => 'point',
        'spgs' => 'springs', 'spg' => 'spring', 'jct' => 'junction', 'mls' => 'mills',
        'hts' => 'heights', 'vlg' => 'village', 'twp' => 'township', 'bch' => 'beach',
        'ctr' => 'center', 'crk' => 'creek', 'is' => 'island', 'lk' => 'lake', 'pk' => 'park',
    ];

    public function handle(): int
    {
        $congress = (int) $this->option('congress');
        $dryRun = (bool) $this->option('dry-run');
        $base = 'https://www2.census.gov/geo/docs/maps-data/data/rel2020/cd-sld';

        $this->info("Area code → district sync — congress={$congress}".($dryRun ? ' [DRY RUN]' : ''));

        $bodies = [];
        foreach ([
            'phone' => self::PHONE_GEOCODING_URL,
            'place' => "{$base}/tab20_cd{$congress}20_place20_natl.txt",
            'cousub' => "{$base}/tab20_cd{$congress}20_cousub20_natl.txt",
        ] as $key => $url) {
            $response = Http::timeout(120)->get($url);
            if (! $response->successful()) {
                $this->error("Download failed (HTTP {$response->status()}): {$url}");

                return self::FAILURE;
            }
            $bodies[$key] = $response->body();
        }

        $places = $this->parseCensus($bodies['place'], 'PLACE', $congress);
        $cousubs = $this->parseCensus($bodies['cousub'], 'COUSUB', $congress);
        [$rows, $matched, $total] = $this->buildRows($bodies['phone'], $places, $cousubs, $congress);

        $this->info(count($rows).' rows across '.count(array_unique(array_column($rows, 'area_code')))
            ." area codes; matched {$matched} of {$total} area code/city pairs to Census geography.");

        if (count($rows) < self::MIN_ROWS) {
            $this->error('Too few rows built — refusing to replace the existing table.');

            return self::FAILURE;
        }

        if ($dryRun) {
            return self::SUCCESS;
        }

        DB::transaction(function () use ($rows) {
            DB::table('area_code_districts')->delete();
            foreach (array_chunk($rows, 1000) as $chunk) {
                DB::table('area_code_districts')->insert($chunk);
            }
        });

        $this->info('Area code table replaced.');

        return self::SUCCESS;
    }

    /**
     * Index a Census relationship file by state and normalized name.
     *
     * @return array<string, array<string, array{name:string, districts:array<string,int>}>>
     */
    private function parseCensus(string $body, string $layer, int $congress): array
    {
        $lines = preg_split('/\r\n|\n|\r/', ltrim($body, "\xEF\xBB\xBF"), -1, PREG_SPLIT_NO_EMPTY);
        $header = array_flip(explode('|', (string) array_shift($lines)));
        $cdCol = $header["GEOID_CD{$congress}_20"] ?? null;
        $nameCol = $header["NAMELSAD_{$layer}_20"] ?? null;
        $areaCol = $header['AREALAND_PART'] ?? null;
        if ($cdCol === null || $nameCol === null || $areaCol === null) {
            return [];
        }

        $index = [];
        foreach ($lines as $line) {
            $cols = explode('|', $line);
            $geoid = $cols[$cdCol] ?? '';
            $name = trim($cols[$nameCol] ?? '');
            $land = (int) ($cols[$areaCol] ?? 0);
            // Rows with no name are the district's own totals; "ZZ" districts are
            // undefined; a part with no land is water only.
            if ($name === '' || ! preg_match('/^\d{4}$/', $geoid) || $land <= 0) {
                continue;
            }
            $state = SyncZipDistrictCrosswalk::FIPS_TO_STATE[substr($geoid, 0, 2)] ?? null;
            if ($state === null) {
                continue;
            }
            // 00 = at-large state, 98 = DC's delegate seat.
            $number = in_array(substr($geoid, 2), ['00', '98'], true) ? 'AL' : (string) (int) substr($geoid, 2);
            $display = trim(preg_replace(self::LSAD_SUFFIX, '', $name));
            $key = $this->normalize($display);
            $index[$state][$key]['name'] ??= $display;
            $index[$state][$key]['districts'][$number] = ($index[$state][$key]['districts'][$number] ?? 0) + $land;
        }

        // Boundary slivers (a few thousand m² of a city in a neighbouring
        // district) would list districts nobody in that city lives in.
        foreach ($index as &$cities) {
            foreach ($cities as &$city) {
                $total = array_sum($city['districts']);
                $largest = max($city['districts']);
                $city['districts'] = array_filter($city['districts'],
                    fn (int $land) => $land === $largest || $land / $total >= self::MIN_DISTRICT_SHARE);
            }
        }
        unset($cities, $city);

        return $index;
    }

    /**
     * @return array{0: list<array<string, mixed>>, 1: int, 2: int}
     */
    private function buildRows(string $phoneBody, array $places, array $cousubs, int $congress): array
    {
        $seen = [];
        $rows = [];
        $matched = 0;
        foreach (preg_split('/\r\n|\n|\r/', $phoneBody) as $line) {
            // "1760324|Palm Desert, CA" — a bare state name ("California") or a
            // Canadian province carries no city, so it never matches.
            if (! preg_match('/^1([2-9]\d{2})\d*\|(.+),\s*([A-Z]{2})$/', trim($line), $m)) {
                continue;
            }
            [, $areaCode, $city, $state] = $m;
            if (! in_array($state, SyncZipDistrictCrosswalk::FIPS_TO_STATE, true) || isset($seen["{$areaCode}|{$state}|{$city}"])) {
                continue;
            }
            $seen["{$areaCode}|{$state}|{$city}"] = true;

            $match = $this->match($city, $state, $places, $cousubs);
            if ($match === null) {
                continue;
            }
            $matched++;
            foreach ($match['districts'] as $number => $land) {
                $rows["{$areaCode}|{$state}|{$match['name']}|{$number}"] = [
                    'area_code' => $areaCode, 'state' => $state, 'city' => mb_substr($match['name'], 0, 100),
                    'district_number' => (string) $number, 'land_area' => $land, 'congress' => $congress,
                ];
            }
        }

        return [array_values($rows), $matched, count($seen)];
    }

    /**
     * Census places first (incorporated cities and CDPs), then county
     * subdivisions. "East Bloomington, Bloomington" falls back to its last part.
     *
     * @return array{name:string, districts:array<string,int>}|null
     */
    private function match(string $city, string $state, array $places, array $cousubs): ?array
    {
        $parts = array_map('trim', explode(',', $city));
        $candidates = array_unique([$city, end($parts)]);
        foreach ($candidates as $candidate) {
            $key = $this->normalize($candidate);
            $bare = $this->normalize(preg_replace('/\s+(?:township|twp\.?)$/i', '', $candidate));
            foreach ([$places, $cousubs] as $index) {
                if (isset($index[$state][$key])) {
                    return $index[$state][$key];
                }
                if (isset($index[$state][$bare])) {
                    return $index[$state][$bare];
                }
            }
        }

        return null;
    }

    private function normalize(string $name): string
    {
        $words = preg_split('/[^a-z0-9]+/', strtolower($name), -1, PREG_SPLIT_NO_EMPTY);

        return implode(' ', array_map(fn ($w) => self::ABBREVIATIONS[$w] ?? $w, $words));
    }
}
