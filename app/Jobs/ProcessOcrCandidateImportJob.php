<?php

namespace App\Jobs;

use App\Exceptions\OcrCandidateImportException;
use App\Services\OcrCandidateImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

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

        Log::info('[OCR] Import job completed', [
            'source'         => $this->source,
            'records_parsed' => $count,
            'output'         => $output,
        ]);
    }
}
