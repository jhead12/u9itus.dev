<?php

namespace App\Http\Controllers\Standalone;

use App\Models\DataReport;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Admin queue for visitor "Report a data problem" submissions. Resolving or
 * dismissing only records the outcome — the actual data fix happens at source.
 */
class AdminDataReportController
{
    public function index(Request $request)
    {
        $statusFilter = $request->query('status', DataReport::STATUS_PENDING);
        $query = DataReport::query()->latest();

        if (in_array($statusFilter, [DataReport::STATUS_PENDING, DataReport::STATUS_RESOLVED, DataReport::STATUS_DISMISSED], true)) {
            $query->where('status', $statusFilter);
        }

        if ($state = strtoupper(trim((string) $request->query('state', '')))) {
            $query->where('state', $state);
        }

        if ($search = trim((string) $request->query('q', ''))) {
            $query->where(fn ($q) => $q->where('subject_name', 'like', "%{$search}%")
                ->orWhere('message', 'like', "%{$search}%"));
        }

        $reports = $query->paginate(30)->withQueryString();

        $stats = [
            'pending' => DataReport::where('status', DataReport::STATUS_PENDING)->count(),
            'resolved' => DataReport::where('status', DataReport::STATUS_RESOLVED)->count(),
            'dismissed' => DataReport::where('status', DataReport::STATUS_DISMISSED)->count(),
        ];

        return view('standalone.admin.data-reports', [
            'reports' => $reports,
            'stats' => $stats,
            'statusFilter' => $statusFilter,
            'problems' => DataReport::PROBLEMS,
        ]);
    }

    public function update(Request $request, DataReport $report)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in([DataReport::STATUS_RESOLVED, DataReport::STATUS_DISMISSED])],
            'resolution_note' => ['nullable', 'string', 'max:500'],
        ]);

        $report->update([
            'status' => $data['status'],
            'resolution_note' => $data['resolution_note'] ?? null,
            'resolved_by_user_id' => auth()->id(),
            'resolved_at' => now(),
        ]);

        return back()->with('success', 'Report '.$data['status'].'.');
    }
}
