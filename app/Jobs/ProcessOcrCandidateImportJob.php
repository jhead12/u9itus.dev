<?php

namespace App\Jobs;

use App\Exceptions\OcrCandidateImportException;
use App\Models\Politician;
use App\Services\OcrCandidateImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProcessOcrCandidateImportJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** OCR + import can be slow for large multi-page PDFs. */
    public int $timeout = 600;

    /** Do not retry — a failed OCR scan is unlikely to succeed on replay. */
    public int $tries = 1;

    /**
     * @param  array<string, string>  $defaults  Metadata defaults passed by the admin form.
     */
    public function __construct(
        public readonly string $storedPath,
        public readonly string $source,
        public readonly bool $dryRun,
        public readonly array $defaults,
    ) {
    }

    public function handle(OcrCandidateImportService $ocrService): void
    {
        Log::info('[OCR] Starting candidate import job', [
            'file' => $this->storedPath,
            'source' => $this->source,
            'dry_run' => $this->dryRun,
        ]);

        try {
            $records = $ocrService->extractCandidatesFromFile($this->storedPath, $this->defaults);
        } catch (OcrCandidateImportException $e) {
            Log::error('[OCR] Extraction failed', [
                'file' => $this->storedPath,
                'error' => $e->getMessage(),
            ]);
            $this->fail($e);

            return;
        }

        $count = count($records);

        $jsonPathRelative = 'imports/uploads/candidate-ocr-parsed-' . now()->format('Ymd-His') . '-' . uniqid('', true) . '.json';
        Storage::disk('local')->put(
            $jsonPathRelative,
            json_encode($records, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
        $jsonPathAbsolute = Storage::disk('local')->path($jsonPathRelative);

        $args = [
            '--source' => $this->source,
            '--file'   => $jsonPathAbsolute,
        ];

        if ($this->dryRun) {
            $args['--dry-run'] = true;
        }

        $exitCode = Artisan::call('elections:import-candidates', $args);
        $output   = trim((string) Artisan::output());

        if ($exitCode !== 0) {
            Log::error('[OCR] elections:import-candidates failed', [
                'file'      => $this->storedPath,
                'exit_code' => $exitCode,
                'output'    => $output,
            ]);
            $this->fail(new OcrCandidateImportException($output ?: 'elections:import-candidates returned non-zero exit code.'));

            return;
        }

        $profileStats = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
        ];

        if (! $this->dryRun) {
            $profileStats = $this->upsertUnverifiedProfiles($records);
        }

        Log::info('[OCR] Import job completed', [
            'source'         => $this->source,
            'records_parsed' => $count,
            'profiles_created' => $profileStats['created'],
            'profiles_updated' => $profileStats['updated'],
            'profiles_skipped' => $profileStats['skipped'],
            'output'         => $output,
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $records
     * @return array{created: int, updated: int, skipped: int}
     */
    protected function upsertUnverifiedProfiles(array $records): array
    {
        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($records as $record) {
            $fullName = trim((string) ($record['full_name'] ?? ''));
            if ($fullName === '') {
                $skipped++;
                continue;
            }

            $office = $this->nullableString($record['political_office'] ?? null);
            $state = strtoupper((string) ($record['state'] ?? ''));
            $state = $state !== '' ? $state : null;

            $query = Politician::query()
                ->whereNull('user_id')
                ->whereRaw('LOWER(full_name) = ?', [strtolower($fullName)]);

            if ($office !== null) {
                $query->whereRaw('LOWER(COALESCE(political_office, "")) = ?', [strtolower($office)]);
            }

            if ($state !== null) {
                $query->whereRaw('UPPER(COALESCE(state, "")) = ?', [$state]);
            }

            $existing = $query->first();

            $payload = [
                'full_name' => $fullName,
                'political_office' => $office,
                'governance_level' => $this->nullableString($record['governance_level'] ?? null),
                'district' => $this->nullableString($record['district'] ?? null),
                'party_affiliation' => $this->nullableString($record['party_affiliation'] ?? null),
                'state' => $state,
                'city' => $this->nullableString($record['city'] ?? null),
                'website_url' => $this->nullableUrl($record['website_url'] ?? ($record['website'] ?? null)),
                'bio' => $existing?->bio ?: $this->defaultBio(),
                'verified_official' => false,
                'verification_status' => 'unverified',
                'verified_at' => null,
                'is_active' => true,
                'page_published' => true,
            ];

            if ($existing) {
                $existing->fill($payload);
                $existing->save();
                $updated++;
                continue;
            }

            Politician::create($payload);
            $created++;
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
        ];
    }

    protected function defaultBio(): string
    {
        return 'Unverified profile imported from OCR candidate records and pending campaign ownership claim.';
    }

    protected function nullableString(mixed $value): ?string
    {
        $str = trim((string) $value);

        return $str === '' ? null : $str;
    }

    protected function nullableUrl(mixed $value): ?string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        if (! Str::startsWith($raw, ['http://', 'https://'])) {
            $raw = 'https://' . ltrim($raw, '/');
        }

        $validated = filter_var($raw, FILTER_VALIDATE_URL);

        return is_string($validated) ? $validated : null;
    }
}
