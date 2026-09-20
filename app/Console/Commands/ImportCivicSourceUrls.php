<?php

namespace App\Console\Commands;

use App\Models\ElectionDataSource;
use App\Support\CivicVendorClassifier;
use Illuminate\Console\Command;

/**
 * Load hand-curated voter-guide / ballot-measure links into the election_data_sources registry.
 *
 * The automated fill (civic:resolve-official-urls) is thin for cities, school districts and small
 * counties; this is the place to record the links a person has checked. Rows written here are marked
 * source_of_record = manual, which civic:seed-jurisdictions and civic:resolve-official-urls
 * treat as protected (they fill blanks but never overwrite a curated URL).
 *
 * CSV header (case-insensitive; any order; extra columns ignored):
 *   ocd_id, state, level, jurisdiction_name, ballot_measures_url, sample_ballot_url,
 *   elections_home_url, results_url, notes
 *
 * A row is matched on ocd_id, else on state + level + jurisdiction_name. A row that matches
 * nothing is only created when it carries ocd_id, state, level and jurisdiction_name — an
 * OCD id is never guessed. Blank cells never erase an existing value; non-blank cells replace it.
 * A changed URL resets scrape_status to "unverified" so civic:verify-sources re-checks it.
 *
 * Usage:
 *   php artisan civic:import-source-urls storage/app/guides.csv --dry-run
 *   php artisan civic:import-source-urls storage/app/guides.csv
 */
class ImportCivicSourceUrls extends Command
{
    protected $signature = 'civic:import-source-urls
        {file : CSV of curated links (see the command docblock for the header)}
        {--dry-run : Report what would change without writing}';

    protected $description = 'Load a curated CSV of voter-guide / ballot-measure links into election_data_sources as protected manual rows.';

    private const URL_COLUMNS = ['ballot_measures_url', 'sample_ballot_url', 'elections_home_url', 'results_url'];

    public function handle(): int
    {
        $path = (string) $this->argument('file');
        if (! is_readable($path)) {
            $this->error("Cannot read file: {$path}");

            return self::FAILURE;
        }

        $handle = fopen($path, 'r');
        $header = $handle ? fgetcsv($handle, 0, ',', '"', '') : false;
        if (! is_array($header)) {
            $this->error('The CSV is empty.');

            return self::FAILURE;
        }

        $header = array_map(fn ($h) => strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $h))), $header);
        $hasKey = in_array('ocd_id', $header, true) || (in_array('state', $header, true) && in_array('jurisdiction_name', $header, true));
        if (! $hasKey || ! array_intersect(self::URL_COLUMNS, $header)) {
            $this->error('The CSV needs ocd_id (or state + jurisdiction_name) and at least one of: '.implode(', ', self::URL_COLUMNS));

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $counts = ['created' => 0, 'updated' => 0, 'unchanged' => 0, 'skipped' => 0];
        $line = 1;

        while (($cells = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $line++;
            if ($cells === [null] || implode('', array_map('trim', $cells)) === '') {
                continue;
            }
            $row = array_combine($header, array_pad(array_slice($cells, 0, count($header)), count($header), ''));
            $row = array_map(fn ($v) => trim((string) $v), $row);

            $result = $this->applyRow($row, $line, $dryRun);
            $counts[$result]++;
        }
        fclose($handle);

        $this->info(sprintf('Done. created: %d | updated: %d | unchanged: %d | skipped: %d%s',
            $counts['created'], $counts['updated'], $counts['unchanged'], $counts['skipped'], $dryRun ? ' (dry-run — nothing saved)' : ''));

        return self::SUCCESS;
    }

    /** @param  array<string,string>  $row  @return 'created'|'updated'|'unchanged'|'skipped' */
    private function applyRow(array $row, int $line, bool $dryRun): string
    {
        $urls = [];
        foreach (self::URL_COLUMNS as $column) {
            $value = $row[$column] ?? '';
            if ($value === '') {
                continue;
            }
            if (! preg_match('#^https?://[^\s]+$#i', $value) || strlen($value) > 2048) {
                $this->warn("  line {$line}: {$column} is not a valid http(s) URL — row skipped");

                return 'skipped';
            }
            $urls[$column] = $value;
        }

        $state = strtoupper($row['state'] ?? '');
        $level = strtolower($row['level'] ?? '');
        $name = $row['jurisdiction_name'] ?? '';
        $ocdId = strtolower($row['ocd_id'] ?? '');

        $existing = $this->find($ocdId, $state, $level, $name);

        if ($existing === null) {
            if ($ocdId === '' || strlen($state) !== 2 || $name === '' || ! in_array($level, ElectionDataSource::LEVELS, true)) {
                $this->warn("  line {$line}: no matching registry row, and a new row needs ocd_id, state, level and jurisdiction_name — skipped");

                return 'skipped';
            }

            if (! $dryRun) {
                ElectionDataSource::create($urls + [
                    'ocd_id' => $ocdId,
                    'level' => $level,
                    'state' => $state,
                    'jurisdiction_name' => $name,
                    'vendor' => CivicVendorClassifier::fromUrls(...array_values($urls)),
                    'notes' => ($row['notes'] ?? '') ?: null,
                    'source_of_record' => 'manual',
                    'scrape_status' => 'unverified',
                ]);
            }
            $this->line("  [{$state}] {$name} (created)".($dryRun ? ' — dry run' : ''));

            return 'created';
        }

        $changes = [];
        foreach ($urls as $column => $value) {
            if ($existing->{$column} !== $value) {
                $changes[$column] = $value;
            }
        }
        if ($changes !== []) {
            $changes['scrape_status'] = 'unverified';
            $changes['last_verified_at'] = null;
            $vendor = CivicVendorClassifier::fromUrls(...array_values($urls));
            if ($vendor !== null) {
                $changes['vendor'] = $vendor;
            }
        }
        if (($row['notes'] ?? '') !== '' && $existing->notes !== $row['notes']) {
            $changes['notes'] = $row['notes'];
        }
        if ($changes !== [] && (string) $existing->source_of_record !== 'manual') {
            $changes['source_of_record'] = 'manual';
        }

        if ($changes === []) {
            return 'unchanged';
        }

        if (! $dryRun) {
            $existing->update($changes);
        }
        $this->line("  [{$existing->state}] {$existing->jurisdiction_name} (updated: ".implode(', ', array_diff(array_keys($changes), ['scrape_status', 'last_verified_at', 'source_of_record'])).')'.($dryRun ? ' — dry run' : ''));

        return 'updated';
    }

    private function find(string $ocdId, string $state, string $level, string $name): ?ElectionDataSource
    {
        if ($ocdId !== '') {
            return ElectionDataSource::query()->where('ocd_id', $ocdId)->first();
        }
        if ($state === '' || $name === '') {
            return null;
        }

        $matches = ElectionDataSource::query()
            ->where('state', $state)
            ->when($level !== '', fn ($q) => $q->where('level', $level))
            ->whereRaw('LOWER(jurisdiction_name) = ?', [strtolower($name)])
            ->limit(2)
            ->get();

        // Ambiguous (e.g. a city and a county of the same name with no level given): don't guess.
        return $matches->count() === 1 ? $matches->first() : null;
    }
}
