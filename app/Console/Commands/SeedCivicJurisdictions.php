<?php

namespace App\Console\Commands;

use App\Models\ElectionDataSource;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Seed / refresh the election_data_sources registry — one row per US
 * jurisdiction that can put a measure on a ballot (states, counties, and the
 * subset of cities/townships that run their own elections).
 *
 * This is step 1 of the civic source pipeline (see doc/CIVIC_SOURCE_REGISTRY.md):
 *
 *   1. civic:seed-jurisdictions   ← this command. Creates the rows + OCD keys.
 *   2. civic:resolve-official-urls   (TODO) fills authority_name + URLs via
 *                                    Google Civic voterInfoQuery / NASS list.
 *   3. civic:pull-measures           (TODO) ingests measures from VIP / Civic,
 *                                    HTML-scrapes only where there's no feed.
 *   4. civic:verify-sources          (TODO) weekly HEAD-check + robots.txt.
 *
 * What's implemented here:
 *   - `state`  rows: fully seeded from the built-in list below (offline), with
 *     a curated official-elections URL where we're confident of it.
 *   - `county` rows: filtered out of the OpenCivicData master division CSV
 *     (streamed over HTTP, or read from --file), with county_fips from its
 *     census_geoid column.
 *   - `municipal` rows: stub — see seedMunicipalities(). Most cities don't run
 *     their own measures, so this is an opt-in, curated pass.
 *   - EAC EAVS jurisdiction list: stub — see seedFromEac(). Use it to attach
 *     authority_name + county_fips and to catch New England townships.
 *
 * Idempotent: matches on ocd_id. Without --refresh it inserts new rows and
 * fills blank columns on existing ones; it never overwrites a non-empty
 * authority_name / URL that a later step or a human set.
 *
 * Usage:
 *   php artisan civic:seed-jurisdictions
 *   php artisan civic:seed-jurisdictions --source=states
 *   php artisan civic:seed-jurisdictions --source=counties --state=CA
 *   php artisan civic:seed-jurisdictions --source=counties --file=storage/app/ocd/country-us.csv
 *   php artisan civic:seed-jurisdictions --source=eac --file=storage/app/eac/2024_eavs_jurisdictions.csv
 *   php artisan civic:seed-jurisdictions --refresh --dry-run
 */
class SeedCivicJurisdictions extends Command
{
    protected $signature = 'civic:seed-jurisdictions
        {--source=all : Which seed source: states, counties, municipalities, eac, or all}
        {--state=     : Limit to a single state (two-letter USPS code)}
        {--file=      : Local CSV to read instead of fetching (counties = OCD id CSV, eac = EAVS jurisdiction export)}
        {--refresh    : Overwrite authority_name / URLs on existing rows (default: only fill blanks)}
        {--dry-run    : Report what would be written without touching the database}';

    protected $description = 'Seed the election_data_sources registry with state/county/municipal jurisdiction rows keyed by OCD division ID.';

    private bool $refresh = false;

    private bool $dryRun = false;

    /**
     * OpenCivicData's master CSV of every US division (id,name,census_geoid,…),
     * ~192k rows. Streamed, not buffered; we keep only the top-level
     * `.../county:<x>` rows. Mirror it to --file to avoid hitting GitHub from CI.
     */
    private const OCD_DIVISIONS_CSV = 'https://raw.githubusercontent.com/opencivicdata/ocd-division-ids/master/identifiers/country-us.csv';

    /** USPS abbreviation => full state name. 50 states + DC. */
    private const STATES = [
        'AL' => 'Alabama', 'AK' => 'Alaska', 'AZ' => 'Arizona', 'AR' => 'Arkansas',
        'CA' => 'California', 'CO' => 'Colorado', 'CT' => 'Connecticut', 'DE' => 'Delaware',
        'FL' => 'Florida', 'GA' => 'Georgia', 'HI' => 'Hawaii', 'ID' => 'Idaho',
        'IL' => 'Illinois', 'IN' => 'Indiana', 'IA' => 'Iowa', 'KS' => 'Kansas',
        'KY' => 'Kentucky', 'LA' => 'Louisiana', 'ME' => 'Maine', 'MD' => 'Maryland',
        'MA' => 'Massachusetts', 'MI' => 'Michigan', 'MN' => 'Minnesota', 'MS' => 'Mississippi',
        'MO' => 'Missouri', 'MT' => 'Montana', 'NE' => 'Nebraska', 'NV' => 'Nevada',
        'NH' => 'New Hampshire', 'NJ' => 'New Jersey', 'NM' => 'New Mexico', 'NY' => 'New York',
        'NC' => 'North Carolina', 'ND' => 'North Dakota', 'OH' => 'Ohio', 'OK' => 'Oklahoma',
        'OR' => 'Oregon', 'PA' => 'Pennsylvania', 'RI' => 'Rhode Island', 'SC' => 'South Carolina',
        'SD' => 'South Dakota', 'TN' => 'Tennessee', 'TX' => 'Texas', 'UT' => 'Utah',
        'VT' => 'Vermont', 'VA' => 'Virginia', 'WA' => 'Washington', 'WV' => 'West Virginia',
        'WI' => 'Wisconsin', 'WY' => 'Wyoming', 'DC' => 'District of Columbia',
    ];

    public function handle(): int
    {
        $source = strtolower((string) $this->option('source'));
        $stateFilter = $this->option('state') ? strtoupper((string) $this->option('state')) : null;
        $file = $this->option('file') ? (string) $this->option('file') : null;
        $this->refresh = (bool) $this->option('refresh');
        $this->dryRun = (bool) $this->option('dry-run');

        if ($stateFilter !== null && ! isset(self::STATES[$stateFilter])) {
            $this->error("Unknown state code: {$stateFilter}");

            return self::FAILURE;
        }

        $this->info('Civic jurisdiction seed'
            ." — source={$source}"
            .($stateFilter ? " state={$stateFilter}" : '')
            .($this->refresh ? ' [refresh]' : '')
            .($this->dryRun ? ' [DRY RUN]' : ''));

        $created = 0;
        $updated = 0;
        $unchanged = 0;

        if ($source === 'all' || $source === 'states') {
            [$c, $u, $s] = $this->seedStates($stateFilter);
            $created += $c;
            $updated += $u;
            $unchanged += $s;
        }

        if ($source === 'all' || $source === 'counties') {
            [$c, $u, $s] = $this->seedCounties($stateFilter, $file);
            $created += $c;
            $updated += $u;
            $unchanged += $s;
        }

        if ($source === 'municipalities') {
            $this->seedMunicipalities($stateFilter, $file);
        }

        if ($source === 'eac') {
            $this->seedFromEac($stateFilter, $file);
        }

        $suffix = $this->dryRun ? ' (dry-run — no DB writes)' : '';
        $this->info("Done. Created: {$created} | Updated: {$updated} | Unchanged: {$unchanged}{$suffix}");

        return self::SUCCESS;
    }

    // ── states ──────────────────────────────────────────────────────────────

    /** @return array{0:int,1:int,2:int} [created, updated, skipped] */
    private function seedStates(?string $stateFilter): array
    {
        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach (self::STATES as $usps => $name) {
            if ($stateFilter !== null && $usps !== $stateFilter) {
                continue;
            }

            $ocdId = 'ocd-division/country:us/state:'.strtolower($usps);

            $result = $this->upsert($ocdId, [
                'level' => 'state',
                'state' => $usps,
                'jurisdiction_name' => $name,
                'source_of_record' => 'nass',
            ], fillable: [
                'elections_home_url' => config("civic.state_election_sites.{$usps}"),
            ]);

            $this->line("  {$usps}  {$name}  [{$result}]");
            match ($result) {
                'created' => $created++,
                'updated' => $updated++,
                default => $skipped++,
            };
        }

        $this->info("  → states: {$created} created, {$updated} updated, {$skipped} unchanged.");

        return [$created, $updated, $skipped];
    }

    // ── counties ────────────────────────────────────────────────────────────

    /** @return array{0:int,1:int,2:int} [created, updated, unchanged] */
    private function seedCounties(?string $stateFilter, ?string $file): array
    {
        $rows = $this->csvRows($file, self::OCD_DIVISIONS_CSV, ['id', 'name']);

        if ($rows === null) {
            return [0, 0, 0];
        }

        $created = 0;
        $updated = 0;
        $unchanged = 0;
        $skipped = 0; // county rows outside the 50 states + DC (territories)

        foreach ($rows as $row) {
            $ocdId = trim((string) ($row['id'] ?? ''));
            $name = trim((string) ($row['name'] ?? ''));

            // Top-level county only — ignore every other division row
            // (states, cities, council districts, school districts, …) and
            // sub-divisions like .../county:los_angeles/council_district:1
            if (! preg_match('#^ocd-division/country:us/state:([a-z]{2})/county:[^/]+$#', $ocdId, $m)) {
                continue;
            }
            $usps = strtoupper($m[1]);

            if ($stateFilter !== null && $usps !== $stateFilter) {
                continue;
            }
            if (! isset(self::STATES[$usps])) {
                $skipped++;

                continue;
            }

            // The master CSV carries a census_geoid like "place-06037";
            // its 5 digits are the county FIPS (state 2 + county 3).
            $geoid = preg_replace('/\D/', '', (string) ($row['census_geoid'] ?? ''));
            $countyFips = strlen((string) $geoid) === 5 ? $geoid : null;

            $result = $this->upsert($ocdId, [
                'level' => 'county',
                'state' => $usps,
                'jurisdiction_name' => $name !== '' ? $name : $this->prettyDivisionName($ocdId),
                'source_of_record' => 'census',
            ], fillable: [
                'county_fips' => $countyFips,
            ]);

            match ($result) {
                'created' => $created++,
                'updated' => $updated++,
                default => $unchanged++,
            };
        }

        $this->info("  → counties: {$created} created, {$updated} updated, {$unchanged} unchanged, {$skipped} non-state skipped.");

        return [$created, $updated, $unchanged];
    }

    // ── municipalities (stub) ───────────────────────────────────────────────

    private function seedMunicipalities(?string $stateFilter, ?string $file): void
    {
        $scope = $stateFilter ?? 'all states';
        $this->line("  municipalities scope: {$scope}".($file ? " (file: {$file})" : ''));

        // TODO: seed `level = municipal` rows for cities that actually run
        // their own ballot measures (charter cities in CA, home-rule cities,
        // most large cities). Two viable inputs:
        //
        //   a) OpenCivicData us_places.csv — every incorporated place with an
        //      OCD ID. Broad but noisy: the vast majority never run a measure.
        //      Pair with a curated allow-list (mirror the ~200-city list that
        //      geo:sync-census-demographics already maintains) so we don't
        //      create 19,000 dead rows.
        //
        //   b) EAC EAVS jurisdiction list (--source=eac) — already scoped to
        //      bodies that administer elections, and the only clean source for
        //      New England township jurisdictions.
        //
        // For each place: derive `state` + `place_fips` from the OCD ID / a
        // Census gazetteer join, set level=municipal, source_of_record=census,
        // and leave URLs for civic:resolve-official-urls.
        $this->warn('  municipalities: not implemented yet — see seedMunicipalities() docblock.');
        $this->line('  (use --source=eac --file=... to seed local jurisdictions from the EAC EAVS list)');
    }

    // ── EAC EAVS jurisdiction list (stub) ───────────────────────────────────

    private function seedFromEac(?string $stateFilter, ?string $file): void
    {
        $this->line('  EAC scope: '.($stateFilter ?? 'all states'));

        if ($file === null) {
            $this->error('  --source=eac requires --file= pointing at an EAVS jurisdiction CSV.');
            $this->line('  Download from https://www.eac.gov/research-and-data/studies-and-reports (EAVS) or https://catalog.data.gov/dataset/eac-data');

            return;
        }

        // TODO: parse the EAVS "jurisdiction" export. Columns vary by year but
        // typically include: Jurisdiction_Name, State_Full/State_Abbr, FIPSCode
        // (5- or 10-digit), Jurisdiction_Type. For each row:
        //   - level  = county   when FIPS is 5-digit / type is county
        //             = township when 10-digit / New England town
        //             = municipal for independent cities (VA, MO, MD, NV)
        //   - county_fips = first 5 digits of FIPSCode
        //   - ocd_id = derive from FIPS via a FIPS→OCD map, or
        //              ocd-division/country:us/state:xx/place:<slug> for cities
        //   - authority_name = Jurisdiction_Name + " Elections Office"
        //   - source_of_record = 'eac'
        // Then upsert() the same way seedCounties() does. Match existing county
        // rows on county_fips so this pass enriches them with authority_name
        // instead of creating duplicates.
        $this->warn("  EAC seeding not implemented yet — see seedFromEac() docblock. File given: {$file}");
    }

    // ── helpers ─────────────────────────────────────────────────────────────

    /**
     * Insert or update one registry row, matched on ocd_id.
     *
     * @param  array<string,mixed>  $always  columns always written (identity + provenance)
     * @param  array<string,mixed>  $fillable  columns written only on insert, or on --refresh, or when currently blank
     * @return 'created'|'updated'|'unchanged'
     */
    private function upsert(string $ocdId, array $always, array $fillable = []): string
    {
        $existing = ElectionDataSource::query()->where('ocd_id', $ocdId)->first();

        if ($existing === null) {
            if (! $this->dryRun) {
                ElectionDataSource::create(array_merge(
                    ['ocd_id' => $ocdId],
                    $always,
                    array_filter($fillable, fn ($v) => $v !== null && $v !== ''),
                ));
            }

            return 'created';
        }

        $changes = [];
        foreach ($always as $key => $value) {
            if ($existing->{$key} !== $value) {
                $changes[$key] = $value;
            }
        }
        foreach ($fillable as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $blank = $existing->{$key} === null || $existing->{$key} === '';
            if ($this->refresh || $blank) {
                if ($existing->{$key} !== $value) {
                    $changes[$key] = $value;
                }
            }
        }

        if ($changes === []) {
            return 'unchanged';
        }

        if (! $this->dryRun) {
            $existing->update($changes);
        }

        return 'updated';
    }

    /**
     * Stream a CSV row-by-row as associative arrays keyed by lower-cased
     * header. Reads from --file when given, otherwise downloads $fallbackUrl to
     * a temp file first — the OCD divisions CSV expands to tens of MB, so it's
     * never held in memory as one string. Returns null on setup failure.
     *
     * @param  list<string>  $required  header names that must be present
     * @return \Generator<int,array<string,string|null>>|null
     */
    private function csvRows(?string $file, string $fallbackUrl, array $required): ?\Generator
    {
        $tmp = null;

        if ($file !== null) {
            if (! is_readable($file)) {
                $this->error("Cannot read file: {$file}");

                return null;
            }
            $path = $file;
        } else {
            $this->line("  GET {$fallbackUrl}");
            $tmp = tempnam(sys_get_temp_dir(), 'ocd_');
            $response = Http::timeout(120)->sink($tmp)->get($fallbackUrl);
            if (! $response->ok()) {
                $this->error("  HTTP {$response->status()} fetching {$fallbackUrl}");
                @unlink($tmp);

                return null;
            }
            $path = $tmp;
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            $this->error("Failed to open CSV: {$path}");
            if ($tmp !== null) {
                @unlink($tmp);
            }

            return null;
        }

        $header = fgetcsv($handle);
        if ($header === false) {
            $this->error('CSV is empty.');
            fclose($handle);
            if ($tmp !== null) {
                @unlink($tmp);
            }

            return null;
        }
        $header = array_map(fn ($h) => strtolower(trim((string) $h)), $header);

        $missing = array_diff($required, $header);
        if ($missing !== []) {
            $this->error('CSV missing required column(s): '.implode(', ', $missing));
            fclose($handle);
            if ($tmp !== null) {
                @unlink($tmp);
            }

            return null;
        }

        return $this->streamRows($handle, $header, $tmp);
    }

    /**
     * @param  resource  $handle  positioned just past the header row
     * @param  list<string>  $header
     * @return \Generator<int,array<string,string|null>>
     */
    private function streamRows($handle, array $header, ?string $tmp): \Generator
    {
        try {
            while (($cols = fgetcsv($handle)) !== false) {
                if ($cols === [null]) { // blank line
                    continue;
                }
                yield array_combine($header, array_pad($cols, count($header), null)) ?: [];
            }
        } finally {
            fclose($handle);
            if ($tmp !== null) {
                @unlink($tmp);
            }
        }
    }

    /** "ocd-division/country:us/state:ca/county:los_angeles" -> "Los Angeles" */
    private function prettyDivisionName(string $ocdId): string
    {
        $tail = substr($ocdId, strrpos($ocdId, ':') + 1);

        return ucwords(str_replace(['_', '-'], ' ', $tail));
    }
}
