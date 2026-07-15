<?php

namespace App\Http\Controllers\Standalone;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessOcrCandidateImportJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

/**
 * Admin import monitoring and one-off import triggers.
 *
 * Split out of AdminController. Shows the California unclaimed-politician
 * import run log dashboard, triggers a one-off unverified-profile seed via
 * the politicians:create-unverified-profile Artisan command, and queues an
 * OCR-assisted candidate import (scan upload processed asynchronously).
 */
class AdminImportController extends Controller
{
    /**
     * Show California import logs and monitoring dashboard.
     *
     * Displays all scheduled import runs with status, counts, and error details.
     */
    public function imports()
    {
        $imports = \App\Models\ImportRunLog::query()
            ->where('command_name', 'politicians:import-unclaimed-ca')
            ->latest('started_at')
            ->paginate(20);

        $latestRun = \App\Models\ImportRunLog::query()
            ->where('command_name', 'politicians:import-unclaimed-ca')
            ->latest('started_at')
            ->first();

        return view('standalone.admin.imports.index', compact('imports', 'latestRun'));
    }

    /**
     * Trigger a one-off unverified politician profile seed from an official website.
     */
    public function seedUnverifiedPoliticianProfile(Request $request)
    {
        $validated = $request->validate([
            'website' => ['required', 'url'],
            'name' => ['required', 'string', 'max:255'],
            'office' => ['required', 'string', 'max:255'],
            'level' => ['nullable', 'string', 'max:100'],
            'state' => ['required', 'string', 'size:2'],
            'district' => ['nullable', 'string', 'max:120'],
            'party' => ['nullable', 'string', 'max:120'],
            'city' => ['nullable', 'string', 'max:120'],
            'bio' => ['nullable', 'string', 'max:5000'],
            'photo_url' => ['nullable', 'url'],
            'source' => ['nullable', 'string', 'max:64'],
            'publish' => ['nullable', 'boolean'],
        ]);

        $arguments = [
            '--website' => (string) $validated['website'],
            '--name' => (string) $validated['name'],
            '--office' => (string) $validated['office'],
            '--level' => (string) ($validated['level'] ?? 'State'),
            '--state' => strtoupper((string) $validated['state']),
            '--district' => (string) ($validated['district'] ?? ''),
            '--party' => (string) ($validated['party'] ?? ''),
            '--city' => (string) ($validated['city'] ?? ''),
            '--bio' => (string) ($validated['bio'] ?? ''),
            '--photo-url' => (string) ($validated['photo_url'] ?? ''),
            '--source' => (string) ($validated['source'] ?? 'official_state_website'),
            '--publish' => ($request->boolean('publish', true) ? '1' : '0'),
        ];

        $exitCode = Artisan::call('politicians:create-unverified-profile', $arguments);
        $output = trim((string) Artisan::output());

        if ($exitCode !== 0) {
            return back()->withErrors([
                'unverified_profile' => $output !== ''
                    ? $output
                    : 'Unable to run one-off unverified profile import.',
            ])->withInput();
        }

        return back()->with('success', $output !== ''
            ? 'Unverified profile import completed. ' . $output
            : 'Unverified profile import completed.');
    }

    /**
     * OCR-assisted candidate import for scanned local election packages.
     *
     * Stores the upload then dispatches a queue job so the OCR + artisan import
     * run asynchronously — avoiding the Railway web-worker request timeout.
     */
    public function importCandidatesFromOcr(Request $request)
    {
        $validated = $request->validate([
            'source' => ['required', 'string', 'max:64'],
            'scan_upload' => ['required', 'file', 'mimes:pdf,png,jpg,jpeg,tif,tiff,bmp,webp,txt,json', 'max:20480'],
            'state' => ['nullable', 'string', 'size:2'],
            'political_office' => ['nullable', 'string', 'max:255'],
            'governance_level' => ['nullable', 'string', 'max:120'],
            'district' => ['nullable', 'string', 'max:120'],
            'county' => ['nullable', 'string', 'max:120'],
            'city' => ['nullable', 'string', 'max:120'],
            'party_affiliation' => ['nullable', 'string', 'max:120'],
            'election_date' => ['nullable', 'date'],
            'dry_run' => ['nullable', 'boolean'],
        ]);

        $upload = $request->file('scan_upload');
        $extension = strtolower((string) $upload?->getClientOriginalExtension());
        $safeName = 'candidate-ocr-' . now()->format('Ymd-His') . '-' . uniqid('', true) . '.' . $extension;
        $storedRelative = $upload?->storeAs('imports/uploads', $safeName, 'local');
        $storedPath = Storage::disk('local')->path((string) $storedRelative);

        $defaults = [
            'state' => isset($validated['state']) ? strtoupper((string) $validated['state']) : '',
            'political_office' => (string) ($validated['political_office'] ?? ''),
            'governance_level' => (string) ($validated['governance_level'] ?? ''),
            'district' => (string) ($validated['district'] ?? ''),
            'county' => (string) ($validated['county'] ?? ''),
            'city' => (string) ($validated['city'] ?? ''),
            'party_affiliation' => (string) ($validated['party_affiliation'] ?? ''),
            'election_date' => isset($validated['election_date']) ? (string) $validated['election_date'] : '',
        ];

        ProcessOcrCandidateImportJob::dispatch(
            storedPath: $storedPath,
            source: (string) $validated['source'],
            dryRun: $request->boolean('dry_run'),
            defaults: $defaults,
        );

        return back()->with(
            'success',
            'OCR import job queued. The scan is being processed in the background — '
            . 'check the Rails logs or candidate matches section in a few minutes.'
        );
    }
}
