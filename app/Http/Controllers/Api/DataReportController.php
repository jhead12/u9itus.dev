<?php

namespace App\Http\Controllers\Api;

use App\Models\DataReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Public "Report a data problem" endpoint behind candidate cards on the map and
 * profile pages. Anyone may submit; nothing is applied automatically — reports
 * land in the admin Data Reports queue.
 */
class DataReportController
{
    public function store(Request $request): JsonResponse
    {
        // Honeypot: real users never see or fill this field. Answer like a
        // success so bots get no signal to adapt to.
        if (filled($request->input('website'))) {
            return response()->json(['ok' => true], 201);
        }

        $data = $request->validate([
            'subject_type' => ['required', Rule::in(DataReport::SUBJECT_TYPES)],
            'subject_id' => ['nullable', 'integer', 'min:1'],
            'subject_name' => ['nullable', 'string', 'max:191'],
            'subject_office' => ['nullable', 'string', 'max:191'],
            'state' => ['nullable', 'string', 'size:2', 'alpha'],
            'problem' => ['required', Rule::in(array_keys(DataReport::PROBLEMS))],
            'message' => ['nullable', 'string', 'max:1000'],
            'page_url' => ['nullable', 'string', 'max:500'],
            'source_label' => ['nullable', 'string', 'max:100'],
        ]);

        // "Something else" with no words is unactionable.
        if ($data['problem'] === 'other' && ! filled($data['message'] ?? null)) {
            return response()->json([
                'message' => 'Tell us what looks wrong.',
                'errors' => ['message' => ['Tell us what looks wrong.']],
            ], 422);
        }

        $hash = hash('sha256', $request->ip().'|'.config('app.key'));

        // The same visitor re-reporting the same card is one report, not many.
        $existing = DataReport::query()
            ->where('status', DataReport::STATUS_PENDING)
            ->where('reporter_hash', $hash)
            ->where('subject_type', $data['subject_type'])
            ->where('subject_id', $data['subject_id'] ?? null)
            ->where('subject_name', $data['subject_name'] ?? null)
            ->where('problem', $data['problem'])
            ->exists();

        if (! $existing) {
            DataReport::create([
                'subject_type' => $data['subject_type'],
                'subject_id' => $data['subject_id'] ?? null,
                'subject_name' => $data['subject_name'] ?? null,
                'subject_office' => $data['subject_office'] ?? null,
                'state' => isset($data['state']) ? strtoupper($data['state']) : null,
                'problem' => $data['problem'],
                'message' => filled($data['message'] ?? null) ? trim($data['message']) : null,
                'page_url' => $data['page_url'] ?? null,
                'source_label' => $data['source_label'] ?? null,
                'reporter_hash' => $hash,
                'status' => DataReport::STATUS_PENDING,
            ]);
        }

        return response()->json(['ok' => true], 201);
    }
}
