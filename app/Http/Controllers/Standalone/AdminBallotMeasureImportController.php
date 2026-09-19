<?php

namespace App\Http\Controllers\Standalone;

use App\Exceptions\OcrCandidateImportException;
use App\Http\Controllers\Controller;
use App\Models\BallotMeasure;
use App\Services\VoterGuideMeasureExtractor;
use App\Support\BallotMeasureWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Admin: pull ballot measures out of a voter guide (uploaded PDF/scan, or a link) using the
 * OCR pipeline. Two steps — extract and show for review, then save what the admin kept —
 * because guide layouts vary and the parser is heuristic.
 */
class AdminBallotMeasureImportController extends Controller
{
    public function create(): View
    {
        return view('standalone.admin.ballot-measures.import', ['measures' => null, 'context' => []]);
    }

    public function preview(Request $request, VoterGuideMeasureExtractor $extractor): View|RedirectResponse
    {
        $context = $this->validatedContext($request);
        $request->validate([
            'guide_file' => ['nullable', 'file', 'mimes:pdf,png,jpg,jpeg,tif,tiff,webp,txt', 'max:20480'],
            'guide_url' => ['nullable', 'url', 'max:2048'],
        ]);

        $file = $request->file('guide_file');
        $url = trim((string) $request->input('guide_url'));
        if ($file === null && $url === '') {
            return back()->withInput()->withErrors(['guide_file' => 'Upload a guide file or paste a link to one.']);
        }

        try {
            if ($file !== null) {
                $path = $file->storeAs('imports/uploads', 'guide-'.uniqid('', true).'.'.strtolower($file->getClientOriginalExtension()), 'local');
                $absolute = Storage::disk('local')->path($path);

                try {
                    $measures = $extractor->fromFile($absolute);
                } finally {
                    @unlink($absolute);
                }
            } else {
                $measures = $extractor->fromUrl($url);
                $context['source_url'] = $context['source_url'] ?? $url;
            }
        } catch (OcrCandidateImportException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            Log::error('AdminBallotMeasureImportController: guide extraction failed', ['error' => $e->getMessage()]);

            return back()->withInput()->with('error', 'Something went wrong reading that guide. Please try again or use a different file.');
        }

        if ($measures === []) {
            return back()->withInput()->with('error', 'No ballot measures were detected. The parser looks for headings like "Measure A" or "Proposition 3" — try a text version of the guide, or add the measures by hand.');
        }

        return view('standalone.admin.ballot-measures.import', compact('measures', 'context'));
    }

    public function store(Request $request): RedirectResponse
    {
        $context = $this->validatedContext($request);
        $rows = $request->validate([
            'measures' => ['required', 'array', 'max:200'],
            'measures.*.include' => ['nullable', 'boolean'],
            'measures.*.title' => ['required_with:measures.*.include', 'nullable', 'string', 'max:255'],
            'measures.*.measure_number' => ['nullable', 'string', 'max:20'],
            'measures.*.summary' => ['nullable', 'string', 'max:5000'],
            'measures.*.yes_meaning' => ['nullable', 'string', 'max:5000'],
            'measures.*.no_meaning' => ['nullable', 'string', 'max:5000'],
        ])['measures'];

        $writer = new BallotMeasureWriter;
        $counts = ['created' => 0, 'updated' => 0, 'unchanged' => 0];

        foreach ($rows as $row) {
            if (empty($row['include'])) {
                continue;
            }

            $attrs = BallotMeasureWriter::normalize(
                $row,
                state: $context['state'],
                county: $context['county'],
                electionDate: $context['election_date'],
                source: 'voter_guide',
                fallbackUrl: $context['source_url'],
                level: $context['level'],
                locality: $context['locality'],
            );
            if ($attrs !== null) {
                $counts[$writer->upsert($attrs)]++;
            }
        }

        return redirect()->route('admin.ballot-measures.index', ['level' => $context['level'] === 'state' ? null : $context['level']])
            ->with('success', "Imported from voter guide — {$counts['created']} created, {$counts['updated']} filled in, {$counts['unchanged']} already up to date.");
    }

    /** @return array{state: string, level: string, county: ?string, locality: ?string, election_date: ?string, source_url: ?string} */
    private function validatedContext(Request $request): array
    {
        $data = $request->validate([
            'state' => ['required', 'string', 'size:2'],
            'level' => ['required', Rule::in(array_keys(BallotMeasure::LEVELS))],
            'county' => ['nullable', 'string', 'max:100'],
            'locality' => ['nullable', 'string', 'max:150'],
            'election_date' => ['nullable', 'date'],
            'source_url' => ['nullable', 'url', 'max:2048'],
        ]);

        if ($data['level'] !== 'state' && blank($data['county'] ?? null) && blank($data['locality'] ?? null)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'county' => 'Local measures need a county and/or a city or district name.',
            ]);
        }

        return [
            'state' => strtoupper($data['state']),
            'level' => $data['level'],
            'county' => ($data['county'] ?? null) ?: null,
            'locality' => ($data['locality'] ?? null) ?: null,
            'election_date' => ($data['election_date'] ?? null) ?: null,
            'source_url' => ($data['source_url'] ?? null) ?: null,
        ];
    }
}
