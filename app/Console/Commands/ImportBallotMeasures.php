<?php

namespace App\Console\Commands;

use App\Models\BallotMeasure;
use Illuminate\Console\Command;

class ImportBallotMeasures extends Command
{
    protected $signature = 'ballot-measures:import
        {file      : Path to a CSV file}
        {--dry-run : Report what would be imported without writing}';

    protected $description = 'Import ballot measures from a CSV (admin-entered/curated — no automated scraper source yet).';

    public function handle(): int
    {
        $path = (string) $this->argument('file');
        $dryRun = (bool) $this->option('dry-run');

        if (! is_readable($path)) {
            $this->error("Cannot read file: {$path}");

            return self::FAILURE;
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            $this->error("Failed to open file: {$path}");

            return self::FAILURE;
        }

        $header = fgetcsv($handle);
        if ($header === false) {
            $this->error('CSV file is empty.');
            fclose($handle);

            return self::FAILURE;
        }
        $header = array_map(fn ($h) => strtolower(trim((string) $h)), $header);

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $row = 1;

        while (($cols = fgetcsv($handle)) !== false) {
            $row++;
            $data = array_combine($header, array_pad($cols, count($header), null)) ?: [];

            $state = strtoupper(trim((string) ($data['state'] ?? '')));
            $title = trim((string) ($data['title'] ?? ''));
            $electionDate = trim((string) ($data['election_date'] ?? '')) ?: null;

            if ($state === '' || strlen($state) !== 2 || $title === '') {
                $this->warn("Row {$row}: skipped — missing state or title.");
                $skipped++;

                continue;
            }

            $attributes = [
                'county' => trim((string) ($data['county'] ?? '')) ?: null,
                'measure_number' => trim((string) ($data['measure_number'] ?? '')) ?: null,
                'summary' => trim((string) ($data['summary'] ?? '')) ?: null,
                'yes_meaning' => trim((string) ($data['yes_meaning'] ?? '')) ?: null,
                'no_meaning' => trim((string) ($data['no_meaning'] ?? '')) ?: null,
                'status' => trim((string) ($data['status'] ?? '')) ?: 'upcoming',
                'source' => trim((string) ($data['source'] ?? '')) ?: 'manual',
                'source_url' => trim((string) ($data['source_url'] ?? '')) ?: null,
            ];

            if ($dryRun) {
                $this->line("  · [{$state}] {$title}" . ($electionDate ? " ({$electionDate})" : ''));

                continue;
            }

            // Not a plain updateOrCreate() — the election_date column stores a
            // datetime ("2026-11-03 00:00:00"), so a raw string comparison against
            // the bare CSV date ("2026-11-03") never matches and silently
            // duplicates every row on re-import. whereDate() compares by calendar
            // day regardless of the stored time component.
            $existing = BallotMeasure::query()
                ->where('state', $state)
                ->where('title', $title)
                ->when(
                    $electionDate !== null,
                    fn ($q) => $q->whereDate('election_date', $electionDate),
                    fn ($q) => $q->whereNull('election_date'),
                )
                ->first();

            if ($existing) {
                $existing->update($attributes);
                $updated++;
            } else {
                BallotMeasure::create(array_merge($attributes, [
                    'state' => $state,
                    'title' => $title,
                    'election_date' => $electionDate,
                ]));
                $created++;
            }
        }

        fclose($handle);

        if ($dryRun) {
            $this->info('[dry-run] No changes written.');

            return self::SUCCESS;
        }

        $this->info("Done. Created: {$created} | Updated: {$updated} | Skipped: {$skipped}");

        return self::SUCCESS;
    }
}
