@extends('standalone.layouts.dashboard')

@section('title', 'Candidate Match Reviews')
@section('page-title', 'Candidate Match Reviews')

@section('content')
<div class="space-y-6">

    {{-- Flash messages --}}
    @if(session('success'))
    <div class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm rounded-lg px-4 py-3">
        {{ session('success') }}
    </div>
    @endif

    @if($errors->any())
    <div class="bg-red-500/10 border border-red-500/30 text-red-400 text-sm rounded-lg px-4 py-3">
        {{ $errors->first() }}
    </div>
    @endif

    {{-- Stats --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <a href="{{ route('admin.candidate-matches.index', ['status' => 'pending'] + request()->except('status', 'page')) }}"
           class="stat-card {{ $statusFilter === 'pending' ? 'ring-1 ring-amber-500/40' : '' }}">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">Pending</p>
            <p class="text-3xl font-bold text-amber-400">{{ number_format($stats['pending']) }}</p>
        </a>
        <a href="{{ route('admin.candidate-matches.index', ['status' => 'approved'] + request()->except('status', 'page')) }}"
           class="stat-card {{ $statusFilter === 'approved' ? 'ring-1 ring-emerald-500/40' : '' }}">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">Approved</p>
            <p class="text-3xl font-bold text-emerald-400">{{ number_format($stats['approved']) }}</p>
        </a>
        <a href="{{ route('admin.candidate-matches.index', ['status' => 'rejected'] + request()->except('status', 'page')) }}"
           class="stat-card {{ $statusFilter === 'rejected' ? 'ring-1 ring-red-500/40' : '' }}">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">Rejected</p>
            <p class="text-3xl font-bold text-red-400">{{ number_format($stats['rejected']) }}</p>
        </a>
    </div>

    {{-- Filter bar --}}
    <form method="GET" action="{{ route('admin.candidate-matches.index') }}"
          class="flex flex-col lg:flex-row gap-3 bg-slate-800/50 border border-slate-700/50 rounded-xl px-5 py-4">
        <div class="flex-1 min-w-0">
            <input
                type="text"
                name="q"
                value="{{ request('q') }}"
                placeholder="Search by name or office..."
                class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-emerald-500/50 focus:ring-1 focus:ring-emerald-500/20 transition"
            >
        </div>
        <div>
            <select name="status"
                class="w-full lg:w-auto bg-slate-900/60 border border-slate-700 rounded-lg px-3 py-2 text-sm text-slate-300 focus:outline-none focus:border-emerald-500/50 transition">
                <option value="pending" {{ $statusFilter === 'pending' ? 'selected' : '' }}>Pending</option>
                <option value="approved" {{ $statusFilter === 'approved' ? 'selected' : '' }}>Approved</option>
                <option value="rejected" {{ $statusFilter === 'rejected' ? 'selected' : '' }}>Rejected</option>
                <option value="" {{ !in_array($statusFilter, ['pending','approved','rejected']) ? 'selected' : '' }}>All Statuses</option>
            </select>
        </div>
        <button type="submit"
            class="px-4 py-2 rounded-lg bg-emerald-500 hover:bg-emerald-400 text-white text-sm font-semibold transition shrink-0">
            Apply
        </button>
        @if(request('q') || (request('status') && request('status') !== 'pending'))
        <a href="{{ route('admin.candidate-matches.index') }}"
            class="px-3 py-2 rounded-lg bg-slate-700/50 hover:bg-slate-700 text-slate-400 text-sm transition shrink-0 text-center">
            Clear
        </a>
        @endif
    </form>

    {{-- Main table --}}
    <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-700/50 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-white capitalize">{{ $statusFilter ?: 'All' }} Candidate Matches</h3>
            <span class="text-xs text-slate-500">{{ $reviews->total() }} total</span>
        </div>

        <form id="bulk-matches-form" method="POST" action="{{ route('admin.candidate-matches.bulk-action') }}" class="px-5 py-3 border-b border-slate-700/50 bg-slate-900/30">
            @csrf
            <div class="flex flex-col sm:flex-row sm:items-center gap-2">
                <div class="flex items-center gap-2">
                    <select id="bulk-action-select" name="action"
                        class="bg-slate-900/60 border border-slate-700 rounded-lg px-3 py-2 text-sm text-slate-300 focus:outline-none focus:border-emerald-500/50 transition">
                        <option value="">Bulk Actions</option>
                        <option value="approve">Approve</option>
                        <option value="reject">Reject</option>
                    </select>
                    <button id="bulk-apply-btn" type="submit" disabled
                        class="px-3 py-2 rounded-lg bg-emerald-500 hover:bg-emerald-400 disabled:opacity-50 disabled:cursor-not-allowed text-white text-sm font-semibold transition">
                        Apply
                    </button>
                </div>
                <p id="selected-matches-count" class="text-xs text-slate-500">0 selected</p>
            </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-700/50">
                        <th class="px-5 py-3 text-left">
                            <input id="select-all-matches" type="checkbox"
                                class="rounded border-slate-600 bg-slate-900 text-emerald-500 focus:ring-emerald-500/40">
                        </th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wide">Politician</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wide">Candidate Record</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wide">Score</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wide">Breakdown</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wide">Status</th>
                        <th class="px-5 py-3 text-right text-xs font-semibold text-slate-400 uppercase tracking-wide">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700/30">
                    @forelse($reviews as $review)
                    @php
                        $isPending = $review->status === 'pending';
                        $statusColor = match($review->status) {
                            'approved' => 'bg-emerald-500/10 text-emerald-400',
                            'rejected' => 'bg-red-500/10 text-red-400',
                            default    => 'bg-amber-500/10 text-amber-400',
                        };
                    @endphp
                    <tr class="hover:bg-slate-700/20 transition {{ !$isPending ? 'opacity-75' : '' }}">
                        <td class="px-5 py-4 align-top">
                            @if($isPending)
                            <input type="checkbox" name="review_ids[]" value="{{ $review->id }}"
                                class="match-row-checkbox rounded border-slate-600 bg-slate-900 text-emerald-500 focus:ring-emerald-500/40">
                            @else
                            <span class="block w-4 h-4"></span>
                            @endif
                        </td>
                        <td class="px-5 py-4 align-top">
                            <p class="font-medium text-white">{{ $review->politician?->full_name ?? '—' }}</p>
                            <p class="text-xs text-slate-500 mt-1">{{ $review->politician?->political_office ?? 'Unknown office' }}</p>
                            <p class="text-xs text-slate-500">{{ implode(', ', array_filter([$review->politician?->city, $review->politician?->state])) }}</p>
                        </td>
                        <td class="px-5 py-4 align-top">
                            <p class="font-medium text-slate-200">{{ $review->candidateRecord?->full_name ?? '—' }}</p>
                            <p class="text-xs text-slate-500 mt-1">{{ $review->candidateRecord?->political_office ?? 'Unknown office' }}</p>
                            <p class="text-xs text-slate-500">{{ implode(', ', array_filter([$review->candidateRecord?->city, $review->candidateRecord?->state])) }}</p>
                            <p class="text-xs text-slate-600 mt-1">{{ $review->candidateRecord?->source }} · {{ $review->candidateRecord?->external_candidate_id }}</p>
                        </td>
                        <td class="px-5 py-4 align-top">
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold {{ $review->match_score >= 0.85 ? 'bg-emerald-500/15 text-emerald-400' : 'bg-amber-500/15 text-amber-400' }}">
                                {{ number_format((float) $review->match_score, 4) }}
                            </span>
                        </td>
                        <td class="px-5 py-4 align-top">
                            @php $b = $review->match_breakdown ?? []; @endphp
                            <div class="text-xs text-slate-400 space-y-1">
                                <p>Name: {{ number_format((float) ($b['name'] ?? 0), 2) }}</p>
                                <p>Office: {{ number_format((float) ($b['office'] ?? 0), 2) }}</p>
                                <p>State: {{ number_format((float) ($b['state'] ?? 0), 2) }}</p>
                                <p>Geo: {{ number_format((float) ($b['geo'] ?? 0), 2) }}</p>
                                <p>Party: {{ number_format((float) ($b['party'] ?? 0), 2) }}</p>
                            </div>
                        </td>
                        <td class="px-5 py-4 align-top">
                            <span class="text-xs px-2 py-0.5 rounded-full {{ $statusColor }}">
                                {{ ucfirst($review->status) }}
                            </span>
                            @if($review->reason)
                            <p class="text-[11px] text-slate-500 mt-1 max-w-[120px] truncate" title="{{ $review->reason }}">{{ $review->reason }}</p>
                            @endif
                        </td>
                        <td class="px-5 py-4 align-top">
                            <div class="flex flex-col items-end gap-2">
                                @if($isPending)
                                <form method="POST" action="{{ route('admin.candidate-matches.approve', $review) }}">
                                    @csrf
                                    <button type="submit" class="text-xs bg-emerald-600 hover:bg-emerald-500 text-white px-3 py-1.5 rounded-lg transition">
                                        Approve
                                    </button>
                                </form>

                                <form method="POST" action="{{ route('admin.candidate-matches.reject', $review) }}" class="flex items-center gap-2">
                                    @csrf
                                    <input type="text" name="reason" placeholder="Optional reason"
                                           class="w-36 bg-slate-900 border border-slate-700 text-xs text-slate-200 rounded-lg px-2 py-1.5 focus:outline-none focus:ring-1 focus:ring-red-500" />
                                    <button type="submit" class="text-xs bg-red-600 hover:bg-red-500 text-white px-3 py-1.5 rounded-lg transition">
                                        Reject
                                    </button>
                                </form>
                                @endif

                                @if($review->politician)
                                <form method="POST" action="{{ route('admin.candidate-matches.retry', $review->politician) }}">
                                    @csrf
                                    <button type="submit" class="text-xs bg-slate-700 hover:bg-slate-600 text-slate-200 px-3 py-1.5 rounded-lg transition">
                                        Retry Match
                                    </button>
                                </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-5 py-8 text-center text-sm text-slate-500">
                            No {{ $statusFilter ?: '' }} candidate match reviews found.
                            @if(request('q') || request('status'))
                                Try clearing your filters.
                            @endif
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        </form>

        <div class="px-5 py-4 border-t border-slate-700/50">
            {{ $reviews->links() }}
        </div>
    </div>

    {{-- Import form --}}
    <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-4 sm:p-5">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
            <h3 class="text-sm font-semibold text-white">Import Candidate Data</h3>
            <p class="text-xs text-slate-500">Runs artisan command: elections:import-candidates</p>
        </div>

        <form method="POST" action="{{ route('admin.candidate-matches.import') }}" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-4 gap-3">
            @csrf
            <div>
                <label for="import-source" class="block text-xs text-slate-400 mb-1">Source</label>
                <input type="text"
                       id="import-source"
                       name="source"
                       value="{{ old('source', 'local_feed') }}"
                       class="w-full bg-slate-900 border border-slate-700 text-sm text-slate-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-1 focus:ring-emerald-500" />
            </div>
            <div class="md:col-span-2">
                <label for="import-file" class="block text-xs text-slate-400 mb-1">File Path</label>
                <input type="text"
                       id="import-file"
                       name="file"
                       value="{{ old('file', 'imports/local-elections.json') }}"
                       class="w-full bg-slate-900 border border-slate-700 text-sm text-slate-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-1 focus:ring-emerald-500" />
            </div>
            <div>
                <label for="import-upload" class="block text-xs text-slate-400 mb-1">Upload JSON (optional)</label>
                <input type="file"
                       id="import-upload"
                       name="file_upload"
                       accept=".json,application/json,text/plain"
                       class="w-full bg-slate-900 border border-slate-700 text-xs text-slate-200 rounded-lg px-3 py-2 file:mr-3 file:rounded file:border-0 file:bg-slate-700 file:px-2 file:py-1 file:text-xs file:text-slate-200" />
                <p class="text-[11px] text-slate-500 mt-1">Use either File Path or Upload JSON.</p>
            </div>
            <div class="flex items-end gap-3 md:col-span-4">
                <label class="inline-flex items-center gap-2 text-xs text-slate-300">
                    <input type="checkbox" name="dry_run" value="1" {{ old('dry_run') ? 'checked' : '' }} class="rounded border-slate-600 bg-slate-900 text-emerald-500 focus:ring-emerald-500" />
                    Dry Run
                </label>
                <button type="submit" class="text-xs bg-emerald-600 hover:bg-emerald-500 text-white px-3 py-2 rounded-lg transition">
                    Run Import
                </button>
            </div>
        </form>

        @if(session('import_output'))
        <div class="mt-4 rounded-lg border border-slate-700 bg-slate-900/70 p-3">
            <p class="text-xs text-slate-400 mb-2">Import Output</p>
            <pre class="text-xs text-slate-200 whitespace-pre-wrap">{{ session('import_output') }}</pre>
        </div>
        @endif
    </div>

</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const bulkForm = document.getElementById('bulk-matches-form');
    const selectAll = document.getElementById('select-all-matches');
    const actionSelect = document.getElementById('bulk-action-select');
    const applyButton = document.getElementById('bulk-apply-btn');
    const selectedCount = document.getElementById('selected-matches-count');

    if (!bulkForm || !selectAll || !actionSelect || !applyButton || !selectedCount) {
        return;
    }

    const rowCheckboxes = Array.from(document.querySelectorAll('.match-row-checkbox'));

    const updateSelectionState = function () {
        const checkedCount = rowCheckboxes.filter((cb) => cb.checked).length;
        selectedCount.textContent = checkedCount + ' selected';

        const allChecked = checkedCount > 0 && checkedCount === rowCheckboxes.length;
        selectAll.checked = allChecked;
        selectAll.indeterminate = checkedCount > 0 && !allChecked;

        applyButton.disabled = checkedCount === 0 || !actionSelect.value;
    };

    selectAll.addEventListener('change', function () {
        rowCheckboxes.forEach((cb) => { cb.checked = selectAll.checked; });
        updateSelectionState();
    });

    rowCheckboxes.forEach((cb) => cb.addEventListener('change', updateSelectionState));

    actionSelect.addEventListener('change', updateSelectionState);

    bulkForm.addEventListener('submit', function (event) {
        const checkedCount = rowCheckboxes.filter((cb) => cb.checked).length;

        if (checkedCount === 0) {
            event.preventDefault();
            alert('Select at least one review.');
            return;
        }

        if (!actionSelect.value) {
            event.preventDefault();
            alert('Choose a bulk action.');
            return;
        }

        if (actionSelect.value === 'reject') {
            const confirmed = confirm('Bulk reject ' + checkedCount + ' selected match(es)?');
            if (!confirmed) {
                event.preventDefault();
            }
        }
    });

    updateSelectionState();
});
</script>
@endpush
