<?php

namespace App\Console\Commands;

use App\Models\ElectionCandidateRecord;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ImportElectionCandidates extends Command
{
    protected $signature = 'elections:import-candidates
        {--source=local_feed : Source label for imported records}
        {--file=imports/local-elections.json : JSON file path under storage/app or absolute path}
        {--dry-run : Parse and validate without writing changes}';

    protected $description = 'Import local election candidate records from JSON into election_candidate_records.';

    public function handle(): int
    {
        $source = (string) $this->option('source');
        $fileOption = (string) $this->option('file');
        $dryRun = (bool) $this->option('dry-run');

        $path = $this->resolvePath($fileOption);

        if (! file_exists($path)) {
            $this->error('Import file not found: ' . $path);

            return self::FAILURE;
        }

        $decoded = $this->decodeJsonFile($path);

        if ($decoded === null) {
            $this->error('Invalid JSON format. Expected an array of candidate records.');

            return self::FAILURE;
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $dryRunReport = [];

        foreach ($decoded as $idx => $row) {
            $result = $this->processRow($source, $idx, $row, $dryRun);
            if ($dryRun) {
                $dryRunReport[] = $result;
            }

            if (($result['status'] ?? 'skipped') === 'created') {
                $created++;
            } elseif (($result['status'] ?? 'skipped') === 'updated') {
                $updated++;
            } else {
                $skipped++;
            }
        }

        if ($dryRun) {
            $this->renderDryRunReport($dryRunReport);
        }

        $summary = sprintf(
            'Import complete%s: %d created, %d updated, %d skipped from %s.',
            $dryRun ? ' (dry-run)' : '',
            $created,
            $updated,
            $skipped,
            $path
        );

        $this->info($summary);

        return self::SUCCESS;
    }

    protected function nullableString(mixed $value): ?string
    {
        $str = trim((string) $value);

        return $str === '' ? null : $str;
    }

    protected function resolvePath(string $fileOption): string
    {
        return str_starts_with($fileOption, '/')
            ? $fileOption
            : storage_path('app/' . ltrim($fileOption, '/'));
    }

    /**
     * @return array<int, mixed>|null
     */
    protected function decodeJsonFile(string $path): ?array
    {
        $raw = file_get_contents($path);
        $decoded = json_decode((string) $raw, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function processRow(string $source, int $idx, mixed $row, bool $dryRun): array
    {
        if (! is_array($row)) {
            $this->warn("Row {$idx}: skipped (not an object).");

            return [
                'status' => 'skipped',
                'row' => $idx,
                'reason' => 'not an object',
            ];
        }

        $fullName = trim((string) ($row['full_name'] ?? ''));
        if ($fullName === '') {
            $this->warn("Row {$idx}: skipped (missing full_name).");

            return [
                'status' => 'skipped',
                'row' => $idx,
                'reason' => 'missing full_name',
            ];
        }

        $hasExplicitId = trim((string) ($row['external_candidate_id'] ?? '')) !== '';
        $externalId = $this->buildExternalId($fullName, $row);
        $payload = $this->buildPayload($fullName, $row);

        // Without an explicit id, a row that was imported before under a different
        // derived id (the old one hashed in the row's position) is still the same
        // candidate — find it by who and where, and keep its id.
        $existing = ElectionCandidateRecord::where('source', $source)
            ->where('external_candidate_id', $externalId)
            ->first()
            ?? ($hasExplicitId ? null : $this->findByIdentity($source, $payload));
        $externalId = $existing?->external_candidate_id ?? $externalId;

        $changes = $this->buildFieldDiff($existing, $payload);

        if (! $dryRun) {
            ElectionCandidateRecord::updateOrCreate(
                [
                    'source' => $source,
                    'external_candidate_id' => $externalId,
                ],
                $payload
            );
        }

        return [
            'status' => $existing ? 'updated' : 'created',
            'row' => $idx,
            'source' => $source,
            'external_candidate_id' => $externalId,
            'full_name' => $fullName,
            'changes' => $changes,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $report
     */
    protected function renderDryRunReport(array $report): void
    {
        foreach ($report as $entry) {
            $status = strtoupper((string) ($entry['status'] ?? 'SKIPPED'));
            $row = (int) ($entry['row'] ?? -1);

            if ($status === 'SKIPPED') {
                $reason = (string) ($entry['reason'] ?? 'unknown');
                $this->line("[DRY-RUN][SKIP] row={$row} reason={$reason}");
                continue;
            }

            $key = (string) ($entry['source'] ?? '') . ':' . (string) ($entry['external_candidate_id'] ?? '');
            $name = (string) ($entry['full_name'] ?? '');
            $changes = $this->formatChanges($entry['changes'] ?? []);

            if ($status === 'UPDATED') {
                $this->line("[DRY-RUN][UPDATE] row={$row} key={$key} name=\"{$name}\" changes={$changes}");
                continue;
            }

            $this->line("[DRY-RUN][CREATE] row={$row} key={$key} name=\"{$name}\"");
        }
    }

    /**
     * @param  array<string, mixed>|mixed  $changes
     */
    protected function formatChanges(mixed $changes): string
    {
        if (! is_array($changes) || $changes === []) {
            return 'none';
        }

        $parts = [];
        foreach ($changes as $field => $delta) {
            if (! is_array($delta)) {
                continue;
            }

            $from = $this->formatScalar($delta['from'] ?? null);
            $to = $this->formatScalar($delta['to'] ?? null);
            $parts[] = $field . ':' . $from . '=>'. $to;
        }

        return $parts === [] ? 'none' : implode(';', $parts);
    }

    protected function formatScalar(mixed $value): string
    {
        if ($value === null || $value === '') {
            return 'null';
        }

        return str_replace(['\n', '\r', ';'], [' ', ' ', ','], (string) $value);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, array{from: mixed, to: mixed}>
     */
    protected function buildFieldDiff(?ElectionCandidateRecord $existing, array $payload): array
    {
        if (! $existing) {
            return [];
        }

        $fields = [
            'full_name',
            'political_office',
            'governance_level',
            'state',
            'county',
            'city',
            'district',
            'party_affiliation',
            'election_date',
        ];

        $changes = [];
        foreach ($fields as $field) {
            $from = $existing->getAttribute($field);
            $to = $payload[$field] ?? null;

            if ((string) ($from ?? '') === (string) ($to ?? '')) {
                continue;
            }

            $changes[$field] = ['from' => $from, 'to' => $to];
        }

        return $changes;
    }

    /**
     * @param array<string, mixed> $row
     */
    protected function buildExternalId(string $fullName, array $row): string
    {
        $externalId = trim((string) ($row['external_candidate_id'] ?? ''));

        if ($externalId !== '') {
            return $externalId;
        }

        // Derived from who the candidate is and which race, never the row's position,
        // so re-importing a reordered or re-exported file finds the same record.
        $year = substr(trim((string) ($row['election_date'] ?? '')), 0, 4);

        return implode(':', [
            'auto',
            strtolower(trim((string) ($row['state'] ?? ''))) ?: 'xx',
            Str::slug((string) ($row['political_office'] ?? '')) ?: 'office',
            Str::slug((string) ($row['city'] ?? $row['county'] ?? '')) ?: 'na',
            Str::slug((string) ($row['district'] ?? '')) ?: 'na',
            Str::slug($fullName) ?: 'name',
            preg_match('/^\d{4}$/', $year) ? $year : 'na',
        ]);
    }

    /**
     * The same candidate in the same race, matched on name, office and place.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function findByIdentity(string $source, array $payload): ?ElectionCandidateRecord
    {
        $query = ElectionCandidateRecord::where('source', $source)
            ->whereRaw('LOWER(full_name) = ?', [mb_strtolower($payload['full_name'])]);

        foreach (['political_office', 'state', 'city', 'district'] as $field) {
            $payload[$field] === null
                ? $query->whereNull($field)
                : $query->where($field, $payload[$field]);
        }

        if ($payload['election_date'] !== null) {
            $query->whereYear('election_date', substr($payload['election_date'], 0, 4));
        }

        // Several matches mean the source itself lists this person more than once;
        // guessing between them would merge distinct rows, so treat it as new.
        $matches = $query->limit(2)->get();

        return $matches->count() === 1 ? $matches->first() : null;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    protected function buildPayload(string $fullName, array $row): array
    {
        return [
            'full_name' => $fullName,
            'political_office' => $this->nullableString($row['political_office'] ?? null),
            'governance_level' => $this->nullableString($row['governance_level'] ?? null),
            'state' => $this->nullableString($row['state'] ?? null),
            'county' => $this->nullableString($row['county'] ?? null),
            'city' => $this->nullableString($row['city'] ?? null),
            'district' => $this->nullableString($row['district'] ?? null),
            'party_affiliation' => $this->nullableString($row['party_affiliation'] ?? null),
            'election_date' => $this->nullableString($row['election_date'] ?? null),
            'payload' => $row,
            'last_seen_at' => now(),
        ];
    }
}
