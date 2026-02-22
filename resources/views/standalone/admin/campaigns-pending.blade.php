@extends('standalone.layouts.dashboard')

@section('title', 'Pending Campaigns')
@section('page-title', 'Campaign Approval Queue')

@section('content')
<div class="space-y-6">

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
                            <span>Per-view payout: <span class="text-white">${{ number_format($campaign->voter_payout_per_view ?? 0.25, 2) }}</span></span>
                            <span>Submitted: <span class="text-white">{{ $campaign->created_at->diffForHumans() }}</span></span>
                        </div>
                        <button type="button"
                                onclick="document.getElementById('details-{{ $campaign->id }}').classList.toggle('hidden')"
                                class="mt-2 text-xs text-slate-400 hover:text-white transition underline underline-offset-2">
                            Show / hide details
                        </button>
                    </div>

                    {{-- Action buttons --}}
                    <div class="flex gap-2 shrink-0">
                        <form method="POST" action="{{ route('admin.campaigns.approve', $campaign) }}">
                            @csrf
                            <button type="submit"
                                class="px-3 py-1.5 rounded-lg bg-emerald-500 hover:bg-emerald-400 text-white text-xs font-semibold transition">
                                Approve
                            </button>
                        </form>
                        <button type="button"
                                onclick="document.getElementById('reject-form-{{ $campaign->id }}').classList.toggle('hidden')"
                                class="px-3 py-1.5 rounded-lg bg-red-500/10 hover:bg-red-500/20 text-red-400 text-xs font-semibold transition border border-red-500/20">
                            Reject
                        </button>
                    </div>
                </div>

                {{-- Expandable details --}}
                <div id="details-{{ $campaign->id }}" class="hidden bg-slate-900/50 rounded-lg p-4 space-y-3 text-xs">
                    @if($campaign->message_summary)
                    <div>
                        <p class="text-slate-500 uppercase tracking-wide font-semibold mb-1">Summary</p>
                        <p class="text-slate-300">{{ $campaign->message_summary }}</p>
                    </div>
                    @endif
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
                            <p class="text-slate-300 mt-0.5">${{ number_format($campaign->revenue_per_view ?? 0.60, 2) }}</p>
                        </div>
                    </div>
                    @if($campaign->media_url)
                    <div>
                        <p class="text-slate-500 font-semibold mb-1">Media URL</p>
                        <a href="{{ $campaign->media_url }}" target="_blank" rel="noopener"
                           class="text-emerald-400 hover:text-emerald-300 break-all">{{ $campaign->media_url }}</a>
                    </div>
                    @endif
                    @if($campaign->politician?->bio)
                    <div>
                        <p class="text-slate-500 font-semibold mb-1">Politician Bio</p>
                        <p class="text-slate-400 line-clamp-3">{{ $campaign->politician->bio }}</p>
                    </div>
                    @endif
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
