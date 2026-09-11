@extends('standalone.layouts.dashboard')

@section('title', 'Data Quality Reviews')
@section('page-title', 'Data Quality Reviews')

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

    <p class="text-xs text-slate-500">Findings from <code class="text-slate-400">politicians:cleanup-workflow</code> too risky to auto-apply — merges, deactivations, and unrepairable names. Approving applies the change; rejecting leaves the row untouched.</p>

    {{-- Stats --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <a href="{{ route('admin.data-quality.index', ['status' => 'pending'] + request()->except('status', 'page')) }}"
           class="stat-card {{ $statusFilter === 'pending' ? 'ring-1 ring-amber-500/40' : '' }}">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">Pending</p>
            <p class="text-3xl font-bold text-amber-400">{{ number_format($stats['pending']) }}</p>
        </a>
        <a href="{{ route('admin.data-quality.index', ['status' => 'approved'] + request()->except('status', 'page')) }}"
           class="stat-card {{ $statusFilter === 'approved' ? 'ring-1 ring-emerald-500/40' : '' }}">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">Approved</p>
            <p class="text-3xl font-bold text-emerald-400">{{ number_format($stats['approved']) }}</p>
        </a>
        <a href="{{ route('admin.data-quality.index', ['status' => 'rejected'] + request()->except('status', 'page')) }}"
           class="stat-card {{ $statusFilter === 'rejected' ? 'ring-1 ring-red-500/40' : '' }}">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">Rejected</p>
            <p class="text-3xl font-bold text-red-400">{{ number_format($stats['rejected']) }}</p>
        </a>
    </div>

    {{-- Filter bar --}}
    <form method="GET" action="{{ route('admin.data-quality.index') }}"
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
        <a href="{{ route('admin.data-quality.index') }}"
            class="px-3 py-2 rounded-lg bg-slate-700/50 hover:bg-slate-700 text-slate-400 text-sm transition shrink-0 text-center">
            Clear
        </a>
        @endif
    </form>

    {{-- Main table --}}
    <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-700/50 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-white capitalize">{{ $statusFilter ?: 'All' }} Data Quality Reviews</h3>
            <span class="text-xs text-slate-500">{{ $reviews->total() }} total</span>
        </div>

        <form id="bulk-dq-form" method="POST" action="{{ route('admin.data-quality.bulk-action') }}" class="px-5 py-3 border-b border-slate-700/50 bg-slate-900/30">
            @csrf
            <div class="flex flex-col sm:flex-row sm:items-center gap-2">
                <div class="flex items-center gap-2">
                    <select id="bulk-dq-action-select" name="action"
                        class="bg-slate-900/60 border border-slate-700 rounded-lg px-3 py-2 text-sm text-slate-300 focus:outline-none focus:border-emerald-500/50 transition">
                        <option value="">Bulk Actions</option>
                        <option value="approve">Approve</option>
                        <option value="reject">Reject</option>
                    </select>
                    <button id="bulk-dq-apply-btn" type="submit" disabled
                        class="px-3 py-2 rounded-lg bg-emerald-500 hover:bg-emerald-400 disabled:opacity-50 disabled:cursor-not-allowed text-white text-sm font-semibold transition">
                        Apply
                    </button>
                </div>
                <p id="selected-dq-count" class="text-xs text-slate-500">0 selected</p>
            </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-700/50">
                        <th class="px-5 py-3 text-left">
                            <input id="select-all-dq" type="checkbox"
                                class="rounded border-slate-600 bg-slate-900 text-emerald-500 focus:ring-emerald-500/40">
                        </th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wide">Type</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wide">Politician (kept)</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wide">Duplicate / Detail</th>
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
                        $typeLabel = match($review->review_type) {
                            'merge' => 'Merge Duplicate',
                            'deactivate' => 'Deactivate',
                            'name_reject' => 'Unrepairable Name',
                            default => ucfirst($review->review_type),
                        };
                        $payload = $review->payload ?? [];
                    @endphp
                    <tr class="hover:bg-slate-700/20 transition {{ !$isPending ? 'opacity-75' : '' }}">
                        <td class="px-5 py-4 align-top">
                            @if($isPending)
                            <input type="checkbox" name="review_ids[]" value="{{ $review->id }}"
                                class="dq-row-checkbox rounded border-slate-600 bg-slate-900 text-emerald-500 focus:ring-emerald-500/40">
                            @else
                            <span class="block w-4 h-4"></span>
                            @endif
                        </td>
                        <td class="px-5 py-4 align-top">
                            <span class="text-xs px-2 py-0.5 rounded-full bg-slate-700/50 text-slate-300">{{ $typeLabel }}</span>
                        </td>
                        <td class="px-5 py-4 align-top">
                            <p class="font-medium text-white">{{ $review->politician?->full_name ?? '—' }}</p>
                            <p class="text-xs text-slate-500 mt-1">{{ $review->politician?->political_office ?? 'Unknown office' }}</p>
                            <p class="text-xs text-slate-500">{{ implode(', ', array_filter([$review->politician?->city, $review->politician?->state])) }}</p>
                        </td>
                        <td class="px-5 py-4 align-top">
                            @if($review->review_type === 'merge')
                                <p class="font-medium text-slate-200">{{ $review->duplicatePolitician?->full_name ?? '—' }}</p>
                                <p class="text-xs text-slate-500 mt-1">{{ $review->duplicatePolitician?->political_office ?? 'Unknown office' }}</p>
                                @if(!empty($payload['related_data_table']))
                                <p class="text-xs text-amber-400 mt-1">has related data: {{ $payload['related_data_table'] }}</p>
                                @endif
                            @elseif($review->review_type === 'name_reject')
                                <p class="text-xs text-slate-400">full_name: <span class="text-slate-200">{{ $payload['full_name'] ?? '—' }}</span></p>
                                <p class="text-xs text-slate-500 mt-1">{{ $payload['political_office'] ?? '' }} {{ $payload['state'] ?? '' }}</p>
                            @else
                                <p class="text-xs text-slate-500">{{ $review->reason }}</p>
                            @endif
                        </td>
                        <td class="px-5 py-4 align-top">
                            <span class="text-xs px-2 py-0.5 rounded-full {{ $statusColor }}">
                                {{ ucfirst($review->status) }}
                            </span>
                            @if($review->reason)
                            <p class="text-[11px] text-slate-500 mt-1 max-w-[160px] truncate" title="{{ $review->reason }}">{{ $review->reason }}</p>
                            @endif
                        </td>
                        <td class="px-5 py-4 align-top">
                            @if($isPending)
                            <div class="flex flex-col items-end gap-2">
                                <form method="POST" action="{{ route('admin.data-quality.approve', $review) }}">
                                    @csrf
                                    <button type="submit" class="text-xs bg-emerald-600 hover:bg-emerald-500 text-white px-3 py-1.5 rounded-lg transition">
                                        Approve
                                    </button>
                                </form>

                                <form method="POST" action="{{ route('admin.data-quality.reject', $review) }}" class="flex items-center gap-2">
                                    @csrf
                                    <input type="text" name="reason" placeholder="Optional reason"
                                           class="w-36 bg-slate-900 border border-slate-700 text-xs text-slate-200 rounded-lg px-2 py-1.5 focus:outline-none focus:ring-1 focus:ring-red-500" />
                                    <button type="submit" class="text-xs bg-red-600 hover:bg-red-500 text-white px-3 py-1.5 rounded-lg transition">
                                        Reject
                                    </button>
                                </form>
                            </div>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-5 py-8 text-center text-sm text-slate-500">
                            No {{ $statusFilter ?: '' }} data-quality reviews found.
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

</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const bulkForm = document.getElementById('bulk-dq-form');
    const selectAll = document.getElementById('select-all-dq');
    const actionSelect = document.getElementById('bulk-dq-action-select');
    const applyButton = document.getElementById('bulk-dq-apply-btn');
    const selectedCount = document.getElementById('selected-dq-count');

    if (!bulkForm || !selectAll || !actionSelect || !applyButton || !selectedCount) {
        return;
    }

    const rowCheckboxes = Array.from(document.querySelectorAll('.dq-row-checkbox'));

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

        const verb = actionSelect.value === 'approve' ? 'approve (apply the change for)' : 'reject';
        const confirmed = confirm('Bulk ' + verb + ' ' + checkedCount + ' selected review(s)?');
        if (!confirmed) {
            event.preventDefault();
        }
    });

    updateSelectionState();
});
</script>
@endpush
