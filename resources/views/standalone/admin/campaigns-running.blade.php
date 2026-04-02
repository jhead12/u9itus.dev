@extends('standalone.layouts.dashboard')

@section('title', 'Running Campaigns')
@section('page-title', 'Running Campaigns')

@section('content')
<div class="space-y-6">

    {{-- ── Page header ─────────────────────────────────────────────────── --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h2 class="text-lg font-semibold text-white">Live Campaign Monitor</h2>
            <p class="text-sm text-slate-400 mt-0.5">All active and paused campaigns across every politician — real-time spend, reach &amp; engagement.</p>
        </div>
        <a href="{{ route('admin.campaigns.pending') }}"
           class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-amber-500/10 hover:bg-amber-500/20 text-amber-400 text-xs font-semibold border border-amber-500/20 transition whitespace-nowrap">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            Pending Approval
        </a>
    </div>

    {{-- ── Flash messages ───────────────────────────────────────────────── --}}
    @if(session('success'))
    <div class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm rounded-lg px-4 py-3">
        {{ session('success') }}
    </div>
    @endif
    @if(session('error'))
    <div class="bg-red-500/10 border border-red-500/30 text-red-400 text-sm rounded-lg px-4 py-3">
        {{ session('error') }}
    </div>
    @endif

    {{-- ── Summary stats ────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-2 xl:grid-cols-4 gap-4">
        <div class="stat-card">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">Active Campaigns</p>
            <p class="text-3xl font-bold text-emerald-400">{{ number_format($summary['total_active']) }}</p>
            <p class="text-xs text-slate-500 mt-1">currently running</p>
        </div>
        <div class="stat-card">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">Paused Campaigns</p>
            <p class="text-3xl font-bold {{ $summary['total_paused'] > 0 ? 'text-amber-400' : 'text-white' }}">{{ number_format($summary['total_paused']) }}</p>
            <p class="text-xs text-slate-500 mt-1">paused by politician</p>
        </div>
        <div class="stat-card">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">Total Campaign Spend</p>
            <p class="text-3xl font-bold text-white">${{ number_format($summary['total_spend'], 2) }}</p>
            <p class="text-xs text-slate-500 mt-1">across running campaigns</p>
        </div>
        <div class="stat-card">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">Views Delivered</p>
            <p class="text-3xl font-bold text-white">{{ number_format($summary['total_views']) }}</p>
            <p class="text-xs text-slate-500 mt-1">completed voter views</p>
        </div>
    </div>

    {{-- ── Filters ──────────────────────────────────────────────────────── --}}
    <form method="GET" action="{{ route('admin.campaigns.running') }}"
          class="flex flex-col sm:flex-row gap-3 bg-slate-800/50 border border-slate-700/50 rounded-xl px-5 py-4">
        <div class="flex-1 min-w-0">
            <input
                type="text"
                name="search"
                value="{{ request('search') }}"
                placeholder="Search by politician name or office…"
                class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-emerald-500/50 focus:ring-1 focus:ring-emerald-500/20 transition"
            >
        </div>
        <div>
            <select name="status"
                    class="bg-slate-900/60 border border-slate-700 rounded-lg px-3 py-2 text-sm text-slate-300 focus:outline-none focus:border-emerald-500/50 transition">
                <option value="">All Statuses</option>
                <option value="active"  {{ request('status') === 'active'  ? 'selected' : '' }}>Active</option>
                <option value="paused"  {{ request('status') === 'paused'  ? 'selected' : '' }}>Paused</option>
            </select>
        </div>
        <div>
            <select name="type"
                    class="bg-slate-900/60 border border-slate-700 rounded-lg px-3 py-2 text-sm text-slate-300 focus:outline-none focus:border-emerald-500/50 transition">
                <option value="">All Types</option>
                <option value="video"     {{ request('type') === 'video'     ? 'selected' : '' }}>Video</option>
                <option value="live_feed" {{ request('type') === 'live_feed' ? 'selected' : '' }}>Live Feed</option>
            </select>
        </div>
        <button type="submit"
                class="px-4 py-2 rounded-lg bg-emerald-500 hover:bg-emerald-400 text-white text-sm font-semibold transition shrink-0">
            Filter
        </button>
        @if(request('search') || request('status') || request('type'))
        <a href="{{ route('admin.campaigns.running') }}"
           class="px-3 py-2 rounded-lg bg-slate-700/50 hover:bg-slate-700 text-slate-400 text-sm transition shrink-0">
            Clear
        </a>
        @endif
    </form>

    {{-- ── Campaign list ────────────────────────────────────────────────── --}}
    <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-700/50 flex items-center justify-between">
            <div>
                <h3 class="text-sm font-semibold text-white">Running Campaigns</h3>
                <p class="text-xs text-slate-500 mt-0.5">{{ $campaigns->total() }} campaign(s) matched</p>
            </div>
        </div>

        <form id="bulk-campaign-form" method="POST" action="{{ route('admin.campaigns.bulk-action') }}" class="px-5 py-3 border-b border-slate-700/50 bg-slate-900/30">
            @csrf
            <div class="flex flex-col lg:flex-row lg:items-center gap-2 lg:gap-3">
                <div class="flex items-center gap-2">
                    <input id="select-all-campaigns" type="checkbox"
                        class="rounded border-slate-600 bg-slate-900 text-emerald-500 focus:ring-emerald-500/40">
                    <span class="text-xs text-slate-400">Select all on this page</span>
                </div>

                <div class="flex items-center gap-2">
                    <select id="bulk-campaign-action-select" name="action"
                        class="bg-slate-900/60 border border-slate-700 rounded-lg px-3 py-2 text-sm text-slate-300 focus:outline-none focus:border-emerald-500/50 transition">
                        <option value="">Bulk Actions</option>
                        <option value="stop">Stop Campaigns</option>
                        <option value="reactivate">Reactivate Campaigns</option>
                    </select>
                    <input id="bulk-campaign-reason" type="text" name="reason"
                        placeholder="Reason (optional)"
                        class="bg-slate-900/60 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-emerald-500/50 transition">
                    <button id="bulk-campaign-apply-btn" type="submit" disabled
                        class="px-3 py-2 rounded-lg bg-emerald-500 hover:bg-emerald-400 disabled:opacity-50 disabled:cursor-not-allowed text-white text-sm font-semibold transition">
                        Apply
                    </button>
                </div>

                <p id="selected-campaign-count" class="text-xs text-slate-500">0 selected</p>
            </div>
        </form>

        @if($campaigns->isEmpty())
        <div class="px-5 py-14 text-center">
            <svg class="w-10 h-10 text-slate-600 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 10l4.553-2.069A1 1 0 0121 8.87v6.26a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
            </svg>
            <p class="text-sm text-slate-500">No running campaigns found.</p>
            @if(request('search') || request('status') || request('type'))
            <p class="text-xs text-slate-600 mt-1">Try clearing the filters.</p>
            @endif
        </div>
        @else
        <div class="divide-y divide-slate-700/30">
            @foreach($campaigns as $campaign)

            {{-- Work out shared values --}}
            @php
                $budget        = max($campaign->total_budget ?? 0, 0.01);
                $spent         = $campaign->amount_spent ?? 0;
                $spentPct      = min(100, round(($spent / $budget) * 100, 1));

                $targetViews   = max($campaign->total_views_requested ?? 1, 1);
                $completedViews= $campaign->views_completed ?? 0;
                $viewsPct      = min(100, round(($completedViews / $targetViews) * 100, 1));

                $isActive      = ($campaign->status instanceof \App\Enums\CampaignStatus)
                                    ? $campaign->status === \App\Enums\CampaignStatus::Active
                                    : $campaign->status === 'active';

                $statusValue   = ($campaign->status instanceof \BackedEnum) ? $campaign->status->value : $campaign->status;

                $campaignType  = ($campaign->campaign_type instanceof \BackedEnum) ? $campaign->campaign_type->value : $campaign->campaign_type;

                $mediaUrl      = trim((string) ($campaign->media_url ?? ''));
                $mediaType     = (string) ($campaign->media_type ?? 'youtube');
                $ytId          = null;
                $vimeoId       = null;

                if ($mediaUrl !== '') {
                    if (preg_match('~(?:youtu\.be/|youtube\.com/(?:watch\?v=|embed/|shorts/))([A-Za-z0-9_-]{6,})~', $mediaUrl, $m)) {
                        $ytId = $m[1];
                    }

                    if (preg_match('~vimeo\.com/(?:video/)?(\d+)~', $mediaUrl, $m)) {
                        $vimeoId = $m[1];
                    }
                }

                $runningStart  = $campaign->scheduled_start_at ?? $campaign->started_at ?? $campaign->created_at;
                $runningEnd    = $campaign->scheduled_end_at;
            @endphp

            <div class="px-5 py-5 space-y-4">

                {{-- ── Row header ──────────────────────────────────────────── --}}
                <div class="flex flex-col sm:flex-row sm:items-start gap-4">

                    <div class="pt-1">
                        <input type="checkbox"
                            class="campaign-row-checkbox rounded border-slate-600 bg-slate-900 text-emerald-500 focus:ring-emerald-500/40"
                            value="{{ $campaign->id }}">
                    </div>

                    {{-- Left: Politician + Campaign info --}}
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <h4 class="text-sm font-semibold text-white">{{ $campaign->title }}</h4>

                            {{-- Campaign type --}}
                            <span class="text-xs px-2 py-0.5 rounded-full
                                {{ $campaignType === 'live_feed' ? 'bg-purple-500/10 text-purple-400 border border-purple-500/20' : 'bg-blue-500/10 text-blue-400 border border-blue-500/20' }}">
                                {{ $campaignType === 'live_feed' ? '🔴 Live Feed' : '🎬 Video' }}
                            </span>

                            {{-- Status badge --}}
                            <span class="text-xs px-2.5 py-0.5 rounded-full font-semibold
                                {{ $isActive ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-amber-500/10 text-amber-400 border border-amber-500/20' }}">
                                <span class="inline-block w-1.5 h-1.5 rounded-full mr-1 align-middle
                                    {{ $isActive ? 'bg-emerald-400' : 'bg-amber-400' }}
                                    {{ $isActive ? 'animate-pulse' : '' }}"></span>
                                {{ ucfirst($statusValue) }}
                            </span>
                        </div>

                        {{-- Politician attribution --}}
                        <div class="flex items-center gap-1.5 mt-1 flex-wrap">
                            <div class="w-5 h-5 rounded-full bg-slate-700 flex items-center justify-center text-xs font-semibold text-slate-300 shrink-0">
                                {{ strtoupper(substr($campaign->politician?->full_name ?? '?', 0, 1)) }}
                            </div>
                            <span class="text-xs font-medium text-slate-300">{{ $campaign->politician?->full_name ?? '—' }}</span>
                            @if($campaign->politician?->political_office)
                                <span class="text-xs text-slate-500">· {{ $campaign->politician->political_office }}</span>
                            @endif
                            @if($campaign->politician?->party_affiliation)
                                <span class="text-xs px-1.5 py-0 rounded bg-slate-700 text-slate-400">{{ $campaign->politician->party_affiliation }}</span>
                            @endif
                            @if($campaign->politician?->state)
                                <span class="text-xs text-slate-500">{{ $campaign->politician->state }}{{ $campaign->politician->city ? ', ' . $campaign->politician->city : '' }}</span>
                            @endif
                        </div>

                        {{-- Meta line --}}
                        <div class="flex flex-wrap gap-x-4 gap-y-1 mt-2 text-xs text-slate-500">
                            <span>Started:
                                <span class="text-slate-300">
                                    {{ $campaign->started_at ? $campaign->started_at->diffForHumans() : ($campaign->created_at->diffForHumans()) }}
                                </span>
                            </span>
                            <span>Running dates:
                                <span class="text-slate-300">
                                    {{ $runningStart ? $runningStart->format('M j, Y') : '—' }}
                                    -
                                    {{ $runningEnd ? $runningEnd->format('M j, Y') : 'Ongoing' }}
                                </span>
                            </span>
                            <span>Per-view rate:
                                <span class="text-slate-300">${{ number_format($campaign->voter_payout_per_view ?? 0.25, 2) }}</span>
                            </span>
                            @if($campaign->governance_level)
                            <span>Level:
                                <span class="text-slate-300">{{ ucwords(str_replace('_', ' ', $campaign->governance_level)) }}</span>
                            </span>
                            @endif
                        </div>
                    </div>

                    {{-- Right: Action buttons --}}
                    <div class="flex flex-wrap gap-2 shrink-0">
                        @if($isActive)
                        <form method="POST" action="{{ route('admin.campaigns.stop', $campaign) }}">
                            @csrf
                            <button type="submit"
                                    onclick="return confirm('Stop campaign \'{{ addslashes($campaign->title) }}\'? This cannot be undone.')"
                                    class="px-3 py-1.5 rounded-lg bg-red-500/10 hover:bg-red-500/20 text-red-400 text-xs font-semibold border border-red-500/20 transition">
                                Stop
                            </button>
                        </form>
                        @else
                        <form method="POST" action="{{ route('admin.campaigns.reactivate', $campaign) }}">
                            @csrf
                            <button type="submit"
                                    class="px-3 py-1.5 rounded-lg bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-400 text-xs font-semibold border border-emerald-500/20 transition">
                                Reactivate
                            </button>
                        </form>
                        @endif

                        <a href="{{ route('admin.campaigns.edit', $campaign) }}"
                           class="px-3 py-1.5 rounded-lg bg-slate-700/50 hover:bg-slate-700 text-slate-300 text-xs font-semibold transition">
                            Edit
                        </a>

                        @if($mediaUrl !== '')
                        <button type="button"
                                onclick="document.getElementById('preview-{{ $campaign->id }}').classList.toggle('hidden')"
                                class="px-3 py-1.5 rounded-lg bg-blue-500/10 hover:bg-blue-500/20 text-blue-400 text-xs font-semibold border border-blue-500/20 transition">
                            Preview
                        </button>
                        @endif

                        <a href="{{ route('admin.campaigns.audit', $campaign) }}"
                           class="px-3 py-1.5 rounded-lg bg-slate-700/50 hover:bg-slate-700 text-slate-400 text-xs font-semibold transition">
                            Audit Log
                        </a>
                    </div>
                </div>

                @if($mediaUrl !== '')
                <div id="preview-{{ $campaign->id }}" class="hidden bg-slate-900/60 border border-slate-700/50 rounded-lg p-3 space-y-2">
                    <p class="text-xs text-slate-400 font-medium">Campaign Video Preview</p>
                    <div class="rounded-lg border border-slate-700/60 overflow-hidden bg-black">
                        @if(($mediaType === 'youtube' && $ytId) || ($ytId && ! $vimeoId && ! in_array($mediaType, ['vimeo', 'direct_file', 's3_cloudfront'], true)))
                            <div class="relative w-full" style="padding-top:56.25%;">
                                <iframe
                                    class="absolute inset-0 h-full w-full"
                                    src="https://www.youtube-nocookie.com/embed/{{ $ytId }}?rel=0&modestbranding=1"
                                    title="Campaign video preview"
                                    loading="lazy"
                                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                                    allowfullscreen></iframe>
                            </div>
                        @elseif(($mediaType === 'vimeo' && $vimeoId) || ($vimeoId && ! $ytId))
                            <div class="relative w-full" style="padding-top:56.25%;">
                                <iframe
                                    class="absolute inset-0 h-full w-full"
                                    src="https://player.vimeo.com/video/{{ $vimeoId }}"
                                    title="Campaign video preview"
                                    loading="lazy"
                                    allow="autoplay; fullscreen; picture-in-picture"
                                    allowfullscreen></iframe>
                            </div>
                        @else
                            <div class="relative w-full" style="padding-top:56.25%;">
                                <iframe
                                    class="absolute inset-0 h-full w-full"
                                    src="{{ $mediaUrl }}"
                                    title="Campaign video preview"
                                    loading="lazy"
                                    allow="autoplay; fullscreen; picture-in-picture"
                                    allowfullscreen></iframe>
                            </div>
                        @endif
                    </div>
                    <a href="{{ $mediaUrl }}" target="_blank" rel="noopener" class="text-xs text-emerald-400 hover:text-emerald-300 break-all">
                        Open media URL in new tab
                    </a>
                </div>
                @endif

                {{-- ── Metrics grid ─────────────────────────────────────────── --}}
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">

                    {{-- Campaign Spend --}}
                    <div class="bg-slate-900/50 rounded-lg p-3">
                        <p class="text-xs text-slate-500 mb-1">Campaign Spend</p>
                        <p class="text-base font-bold text-white">${{ number_format($spent, 2) }}</p>
                        <p class="text-xs text-slate-500">of ${{ number_format($budget, 2) }} budget</p>
                        <div class="mt-2 h-1.5 bg-slate-700 rounded-full overflow-hidden">
                            <div class="h-full rounded-full transition-all
                                {{ $spentPct >= 90 ? 'bg-red-400' : ($spentPct >= 70 ? 'bg-amber-400' : 'bg-emerald-400') }}"
                                 style="width: {{ $spentPct }}%"></div>
                        </div>
                        <p class="text-xs text-slate-600 mt-1">{{ $spentPct }}% used</p>
                    </div>

                    {{-- Views Progress --}}
                    <div class="bg-slate-900/50 rounded-lg p-3">
                        <p class="text-xs text-slate-500 mb-1">Views Delivered</p>
                        <p class="text-base font-bold text-white">{{ number_format($completedViews) }}</p>
                        <p class="text-xs text-slate-500">of {{ number_format($targetViews) }} target</p>
                        <div class="mt-2 h-1.5 bg-slate-700 rounded-full overflow-hidden">
                            <div class="h-full bg-blue-400 rounded-full transition-all"
                                 style="width: {{ $viewsPct }}%"></div>
                        </div>
                        <p class="text-xs text-slate-600 mt-1">{{ $viewsPct }}% complete</p>
                    </div>

                    {{-- Unique Voters --}}
                    <div class="bg-slate-900/50 rounded-lg p-3">
                        <p class="text-xs text-slate-500 mb-1">Unique Voters Reached</p>
                        <p class="text-base font-bold text-white">{{ number_format($campaign->unique_voters_count ?? 0) }}</p>
                        <p class="text-xs text-slate-500 mt-1">distinct voters interacted</p>
                        <div class="mt-2 flex items-center gap-1">
                            <svg class="w-3 h-3 text-emerald-400" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"/>
                            </svg>
                            <span class="text-xs text-slate-600">voter sessions started</span>
                        </div>
                    </div>

                    {{-- Avg Completion --}}
                    <div class="bg-slate-900/50 rounded-lg p-3">
                        <p class="text-xs text-slate-500 mb-1">Avg Watch Completion</p>
                        @php $avgPct = $campaign->avg_completion_pct ?? 0; @endphp
                        <p class="text-base font-bold
                            {{ $avgPct >= 80 ? 'text-emerald-400' : ($avgPct >= 50 ? 'text-amber-400' : 'text-red-400') }}">
                            {{ number_format($avgPct, 1) }}%
                        </p>
                        <p class="text-xs text-slate-500 mt-1">{{ number_format($campaign->completed_sessions_count ?? 0) }} completed sessions</p>
                        <div class="mt-2 h-1.5 bg-slate-700 rounded-full overflow-hidden">
                            <div class="h-full rounded-full transition-all
                                {{ $avgPct >= 80 ? 'bg-emerald-400' : ($avgPct >= 50 ? 'bg-amber-400' : 'bg-red-400') }}"
                                 style="width: {{ min(100, $avgPct) }}%"></div>
                        </div>
                    </div>
                </div>

            </div>
            @endforeach
        </div>

        {{-- ── Pagination ──────────────────────────────────────────────── --}}
        @if($campaigns->hasPages())
        <div class="px-5 py-4 border-t border-slate-700/50">
            {{ $campaigns->links('vendor.pagination.tailwind') }}
        </div>
        @endif
        @endif

    </div>{{-- end card --}}

</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('bulk-campaign-form');
    const selectAll = document.getElementById('select-all-campaigns');
    const actionSelect = document.getElementById('bulk-campaign-action-select');
    const applyButton = document.getElementById('bulk-campaign-apply-btn');
    const selectedCountText = document.getElementById('selected-campaign-count');

    if (!form || !selectAll || !actionSelect || !applyButton || !selectedCountText) {
        return;
    }

    const rowCheckboxes = Array.from(document.querySelectorAll('.campaign-row-checkbox'));

    const updateSelectionState = function () {
        const checkedCount = rowCheckboxes.filter((checkbox) => checkbox.checked).length;
        const allChecked = checkedCount > 0 && checkedCount === rowCheckboxes.length;

        selectAll.checked = allChecked;
        selectAll.indeterminate = checkedCount > 0 && !allChecked;
        selectedCountText.textContent = checkedCount + ' selected';
        applyButton.disabled = checkedCount === 0;
    };

    selectAll.addEventListener('change', function () {
        rowCheckboxes.forEach((checkbox) => {
            checkbox.checked = selectAll.checked;
        });

        updateSelectionState();
    });

    rowCheckboxes.forEach((checkbox) => {
        checkbox.addEventListener('change', updateSelectionState);
    });

    form.addEventListener('submit', function (event) {
        const checked = rowCheckboxes.filter((checkbox) => checkbox.checked);

        if (checked.length === 0) {
            event.preventDefault();
            alert('Select at least one campaign.');
            return;
        }

        if (!actionSelect.value) {
            event.preventDefault();
            alert('Choose a bulk action.');
            return;
        }

        const existingHiddenIds = form.querySelectorAll('input[name="campaign_ids[]"]');
        existingHiddenIds.forEach((input) => input.remove());

        checked.forEach((checkbox) => {
            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = 'campaign_ids[]';
            hidden.value = checkbox.value;
            form.appendChild(hidden);
        });

        if (actionSelect.value === 'stop') {
            const confirmed = confirm('Stop ' + checked.length + ' selected campaign(s)?');

            if (!confirmed) {
                event.preventDefault();
            }
        }
    });

    updateSelectionState();
});
</script>
@endpush
