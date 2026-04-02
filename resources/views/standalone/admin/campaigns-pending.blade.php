@extends('standalone.layouts.dashboard')

@section('title', 'Pending Campaigns')
@section('page-title', 'Campaign Approval Queue')

@section('content')
<div class="space-y-6">

    {{-- ── Live new-submission indicator (Echo / Reverb) ──────────────── --}}
    <div x-data="{ newSubmissions: 0 }"
         x-init="
             if (window.Echo) {
                 window.Echo.private('admin.monitor')
                     .listen('.campaign.submitted', () => { newSubmissions++ });
             }
         "
         x-show="newSubmissions > 0"
         style="display:none;"
         class="flex items-center gap-3 bg-amber-500/10 border border-amber-500/30 text-amber-300 text-sm rounded-xl px-4 py-3">
        <span class="relative flex h-2.5 w-2.5 shrink-0">
            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
            <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-amber-400"></span>
        </span>
        <span>
            <span x-text="newSubmissions"></span>
            new campaign submission<span x-show="newSubmissions > 1">s</span> received.
        </span>
        <a href="{{ request()->fullUrl() }}"
           class="ml-auto text-xs font-medium underline underline-offset-2 hover:text-amber-200 transition whitespace-nowrap">
            Refresh to review →
        </a>
    </div>

    @if(session('success'))
    <div class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm rounded-lg px-4 py-3">
        {{ session('success') }}
    </div>
    @endif

    <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-700/50">
            <h3 class="text-sm font-semibold text-white">Campaigns Awaiting Review</h3>
            <p class="text-xs text-slate-500 mt-0.5">{{ $campaigns->total() }} campaign(s) pending approval</p>
        </div>

        @if($campaigns->isEmpty())
        <div class="px-5 py-12 text-center">
            <svg class="w-10 h-10 text-slate-600 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
            </svg>
            <p class="text-sm text-slate-500">No campaigns pending approval.</p>
        </div>
        @else
        <div class="divide-y divide-slate-700/30">
            @foreach($campaigns as $campaign)
            <div class="px-5 py-4 space-y-3">
                {{-- Main row --}}
                <div class="flex flex-col sm:flex-row sm:items-start gap-4">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <h4 class="text-sm font-semibold text-white">{{ $campaign->title }}</h4>
                            <span class="text-xs px-2 py-0.5 rounded-full bg-slate-700 text-slate-300">
                                {{ $campaign->campaign_type instanceof \BackedEnum ? $campaign->campaign_type->value : $campaign->campaign_type }}
                            </span>
                        </div>
                        <p class="text-xs text-slate-400 mt-0.5">
                            {{ $campaign->politician?->full_name ?? '—' }}
                            @if($campaign->politician?->political_office) · {{ $campaign->politician->political_office }} @endif
                            @if($campaign->politician?->state) · {{ $campaign->politician->state }} @endif
                        </p>
                        <div class="flex flex-wrap gap-x-4 gap-y-1 mt-2 text-xs text-slate-500">
                            <span>Budget: <span class="text-white">${{ number_format($campaign->total_budget ?? 0, 2) }}</span></span>
                            <span>Target views: <span class="text-white">{{ number_format($campaign->total_views_requested ?? 0) }}</span></span>
                            <span>Per-view payout: <span class="text-white">${{ number_format($campaign->voter_payout_per_view ?? config('u9itus.viewer_payout_per_view', 0.50), 2) }}</span></span>
                            <span>Submitted: <span class="text-white">{{ $campaign->created_at->diffForHumans() }}</span></span>
                        </div>
                        <button type="button"
                                onclick="document.getElementById('details-{{ $campaign->id }}').classList.toggle('hidden')"
                                class="mt-2 text-xs text-slate-400 hover:text-white transition underline underline-offset-2">
                            Show / hide details
                        </button>
                    </div>

                    {{-- Action buttons --}}
                    <div class="flex gap-2 shrink-0 flex-wrap">
                        <form method="POST" action="{{ route('admin.campaigns.approve', $campaign) }}">
                            @csrf
                            <button type="submit"
                                class="px-3 py-1.5 rounded-lg bg-emerald-500 hover:bg-emerald-400 text-white text-xs font-semibold transition">
                                Approve
                            </button>
                        </form>
                        <a href="{{ route('admin.campaigns.edit', $campaign) }}"
                            class="px-3 py-1.5 rounded-lg bg-amber-500/10 hover:bg-amber-500/20 text-amber-400 text-xs font-semibold transition border border-amber-500/20">
                            Edit
                        </a>
                        <button type="button"
                                onclick="document.getElementById('stop-form-{{ $campaign->id }}').classList.toggle('hidden')"
                                class="px-3 py-1.5 rounded-lg bg-orange-500/10 hover:bg-orange-500/20 text-orange-400 text-xs font-semibold transition border border-orange-500/20">
                            Stop
                        </button>
                        <button type="button"
                                onclick="document.getElementById('reject-form-{{ $campaign->id }}').classList.toggle('hidden')"
                                class="px-3 py-1.5 rounded-lg bg-red-500/10 hover:bg-red-500/20 text-red-400 text-xs font-semibold transition border border-red-500/20">
                            Reject
                        </button>
                        <a href="{{ route('admin.campaigns.audit', $campaign) }}"
                            class="px-3 py-1.5 rounded-lg bg-slate-700/50 hover:bg-slate-700 text-slate-400 text-xs font-semibold transition">
                            Log
                        </a>
                    </div>
                </div>

                {{-- Expandable details --}}
                <div id="details-{{ $campaign->id }}" class="hidden bg-slate-900/50 rounded-lg p-4 space-y-3 text-xs">
                    <div>
                        <p class="text-slate-500 uppercase tracking-wide font-semibold mb-2">Public Politician Page Preview</p>
                        <div class="max-w-xl">
                            @include('standalone.public.partials.campaign-preview-card', ['campaign' => $campaign])
                        </div>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                        @if($campaign->governance_level)
                        <div>
                            <p class="text-slate-500 font-semibold">Governance Level</p>
                            <p class="text-slate-300 mt-0.5">{{ $campaign->governance_level }}</p>
                        </div>
                        @endif
                        @if($campaign->target_states)
                        <div>
                            <p class="text-slate-500 font-semibold">Target States</p>
                            <p class="text-slate-300 mt-0.5">{{ implode(', ', (array) $campaign->target_states) }}</p>
                        </div>
                        @endif
                        @if($campaign->target_cities)
                        <div>
                            <p class="text-slate-500 font-semibold">Target Cities</p>
                            <p class="text-slate-300 mt-0.5">{{ implode(', ', (array) $campaign->target_cities) }}</p>
                        </div>
                        @endif
                        @if($campaign->media_duration)
                        <div>
                            <p class="text-slate-500 font-semibold">Video Duration</p>
                            <p class="text-slate-300 mt-0.5">{{ $campaign->media_duration }}s ({{ round($campaign->media_duration / 60, 1) }} min)</p>
                        </div>
                        @endif
                        <div>
                            <p class="text-slate-500 font-semibold">Min Watch %</p>
                            <p class="text-slate-300 mt-0.5">{{ $campaign->min_watch_time_percent ?? 100 }}%</p>
                        </div>
                        <div>
                            <p class="text-slate-500 font-semibold">Revenue / View</p>
                            <p class="text-slate-300 mt-0.5">${{ number_format($campaign->revenue_per_view ?? config('u9itus.revenue_per_view', 1.00), 2) }}</p>
                        </div>
                    </div>
                    @if($campaign->politician?->bio)
                    <div>
                        <p class="text-slate-500 font-semibold mb-1">Politician Bio</p>
                        <p class="text-slate-400 line-clamp-3">{{ $campaign->politician->bio }}</p>
                    </div>
                    @endif
                </div>

                {{-- Stop form (hidden by default) --}}
                <div id="stop-form-{{ $campaign->id }}" class="hidden">
                    <form method="POST" action="{{ route('admin.campaigns.stop', $campaign) }}" class="flex gap-2 items-start">
                        @csrf
                        <textarea name="reason" rows="2" required
                            placeholder="Reason for stopping (e.g. video not playing, incorrect targeting)…"
                            class="flex-1 bg-slate-900/70 border border-slate-600/50 rounded-lg px-3 py-2 text-sm text-slate-300 placeholder-slate-600 focus:outline-none focus:border-orange-500/50 resize-none"></textarea>
                        <button type="submit"
                            class="px-4 py-2 rounded-lg bg-orange-500 hover:bg-orange-400 text-white text-xs font-semibold transition shrink-0">
                            Confirm Stop
                        </button>
                    </form>
                </div>

                {{-- Reject form (hidden by default) --}}
                <div id="reject-form-{{ $campaign->id }}" class="hidden">
                    <form method="POST" action="{{ route('admin.campaigns.reject', $campaign) }}" class="flex gap-2 items-start">
                        @csrf
                        <textarea name="reason" rows="2"
                            placeholder="Rejection reason (required for the record)…"
                            class="flex-1 bg-slate-900/70 border border-slate-600/50 rounded-lg px-3 py-2 text-sm text-slate-300 placeholder-slate-600 focus:outline-none focus:border-red-500/50 resize-none">Does not meet content guidelines.</textarea>
                        <button type="submit"
                            class="px-4 py-2 rounded-lg bg-red-500 hover:bg-red-400 text-white text-xs font-semibold transition shrink-0">
                            Confirm Reject
                        </button>
                    </form>
                </div>
            </div>
            @endforeach
        </div>
        <div class="px-5 py-4 border-t border-slate-700/50">
            {{ $campaigns->links() }}
        </div>
        @endif
    </div>

</div>
@endsection
