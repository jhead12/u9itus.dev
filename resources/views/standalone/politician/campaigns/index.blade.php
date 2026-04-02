@extends('standalone.layouts.dashboard')

@section('title', 'My Campaigns')
@section('page-title', 'Campaigns')

@section('content')
<div class="space-y-5">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center gap-3">
        <div class="flex-1">
            <p class="text-slate-400 text-sm">
                {{ $campaigns->total() }} campaign{{ $campaigns->total() !== 1 ? 's' : '' }} total
                @php
                    $draftsCount = $campaigns->where('status', 'draft')->count();
                @endphp
                @if($draftsCount > 0)
                    <span class="mx-2">•</span>
                    <span class="text-amber-400">{{ $draftsCount }} draft{{ $draftsCount !== 1 ? 's' : '' }}</span>
                @endif
            </p>
        </div>
        <div class="flex items-center gap-2">
            @if($politician->page_published && $politician->slug)
            <a href="{{ route('politician.public.show', $politician->slug) }}" target="_blank" rel="noopener"
               class="inline-flex items-center gap-1.5 text-xs font-medium text-emerald-400 hover:text-emerald-300 border border-emerald-500/30 hover:border-emerald-400/50 rounded-lg px-3 py-2 transition">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                </svg>
                View Public Page
            </a>
            @else
            <a href="{{ route('politician.public-page') }}"
               class="inline-flex items-center gap-1.5 text-xs font-medium text-slate-400 hover:text-white border border-slate-700 hover:border-slate-500 rounded-lg px-3 py-2 transition">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                Set Up Public Page
            </a>
            @endif
            <a href="{{ route('politician.campaigns.create') }}"
               class="inline-flex items-center gap-2 bg-emerald-500 hover:bg-emerald-400 text-slate-900 font-semibold rounded-lg px-4 py-2.5 text-sm transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                New Campaign
            </a>
        </div>
    </div>

    {{-- Quick Filters --}}
    @php
        $statusFilter = request('status', 'all');
        $draftCampaigns = $campaigns->where('status', 'draft');
        $activeCampaigns = $campaigns->whereIn('status', ['active', 'paused', 'scheduled']);
        $completedCampaigns = $campaigns->whereIn('status', ['completed', 'cancelled']);
    @endphp
    <div class="flex gap-2 overflow-x-auto pb-2">
        <a href="{{ route('politician.campaigns.index') }}" 
           class="px-4 py-2 rounded-lg text-sm font-medium whitespace-nowrap transition {{ $statusFilter === 'all' ? 'bg-slate-700 text-white' : 'bg-slate-800/50 text-slate-400 hover:text-white hover:bg-slate-800' }}">
            All ({{ $campaigns->count() }})
        </a>
        @if($draftCampaigns->count() > 0)
        <a href="{{ route('politician.campaigns.index', ['status' => 'draft']) }}" 
           class="px-4 py-2 rounded-lg text-sm font-medium whitespace-nowrap transition {{ $statusFilter === 'draft' ? 'bg-amber-500/20 text-amber-300 border border-amber-500/30' : 'bg-slate-800/50 text-slate-400 hover:text-white hover:bg-slate-800' }}">
            📝 Drafts ({{ $draftCampaigns->count() }})
        </a>
        @endif
        <a href="{{ route('politician.campaigns.index', ['status' => 'active']) }}" 
           class="px-4 py-2 rounded-lg text-sm font-medium whitespace-nowrap transition {{ $statusFilter === 'active' ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30' : 'bg-slate-800/50 text-slate-400 hover:text-white hover:bg-slate-800' }}">
            Active ({{ $activeCampaigns->count() }})
        </a>
        <a href="{{ route('politician.campaigns.index', ['status' => 'completed']) }}" 
           class="px-4 py-2 rounded-lg text-sm font-medium whitespace-nowrap transition {{ $statusFilter === 'completed' ? 'bg-slate-600/50 text-slate-300' : 'bg-slate-800/50 text-slate-400 hover:text-white hover:bg-slate-800' }}">
            Completed ({{ $completedCampaigns->count() }})
        </a>
    </div>

    @if($campaigns->isEmpty())
        <div class="bg-slate-800/50 border border-slate-700/50 border-dashed rounded-xl py-16 text-center">
            <svg class="w-12 h-12 text-slate-600 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 10l4.553-2.069A1 1 0 0121 8.87v6.26a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
            <p class="text-slate-400 font-medium">No campaigns yet</p>
            <p class="text-slate-500 text-sm mt-1 mb-4">Create your first video campaign to reach voters.</p>
            <a href="{{ route('politician.campaigns.create') }}"
               class="inline-flex items-center gap-2 bg-emerald-500 hover:bg-emerald-400 text-slate-900 font-semibold rounded-lg px-4 py-2 text-sm transition-colors">
                Create Campaign
            </a>
        </div>
    @else
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach($campaigns as $campaign)
            @php
                $statusColor = match($campaign->status?->value ?? $campaign->status) {
                    'active'           => 'bg-emerald-500/15 text-emerald-400',
                    'paused'           => 'bg-yellow-500/15 text-yellow-400',
                    'completed'        => 'bg-slate-500/15 text-slate-300',
                    'pending_approval' => 'bg-blue-500/15 text-blue-400',
                    'cancelled'        => 'bg-red-500/15 text-red-400',
                    default            => 'bg-slate-700/50 text-slate-400',
                };
                $statusLabel = ucfirst(str_replace('_', ' ', $campaign->status?->value ?? $campaign->status ?? 'draft'));
                $progress = $campaign->total_views_requested > 0
                    ? min(100, round(($campaign->views_completed / $campaign->total_views_requested) * 100))
                    : 0;
            @endphp
            <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl overflow-hidden hover:border-slate-600/80 transition flex flex-col">
                {{-- Thumbnail / placeholder --}}
                <div class="h-36 bg-slate-700/40 relative flex items-center justify-center">
                    @if($campaign->thumbnail_url)
                        <img src="{{ $campaign->thumbnail_url }}" alt="" class="w-full h-full object-cover">
                    @else
                        <svg class="w-10 h-10 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 10l4.553-2.069A1 1 0 0121 8.87v6.26a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                    @endif
                    <span class="absolute top-2 left-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $statusColor }}">
                        {{ $statusLabel }}
                    </span>
                </div>

                <div class="p-4 flex-1 flex flex-col">
                    <div class="flex items-start justify-between gap-2 mb-1">
                        <h3 class="text-sm font-semibold text-slate-200 truncate flex-1">{{ $campaign->title }}</h3>
                        @if(($campaign->status?->value ?? $campaign->status) === 'draft')
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium bg-amber-500/10 text-amber-400 border border-amber-500/20 shrink-0">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                            </svg>
                            Draft
                        </span>
                        @endif
                    </div>
                    <p class="text-xs text-slate-500 mt-0.5 line-clamp-2">{{ $campaign->message_summary ?? '—' }}</p>

                    {{-- Progress --}}
                    <div class="mt-3">
                        <div class="flex justify-between text-xs text-slate-500 mb-1">
                            <span>{{ number_format($campaign->views_completed) }} / {{ number_format($campaign->total_views_requested) }} views</span>
                            <span>{{ $progress }}%</span>
                        </div>
                        <div class="h-1.5 bg-slate-700 rounded-full overflow-hidden">
                            <div class="h-full bg-emerald-500 rounded-full" style="width: {{ $progress }}%"></div>
                        </div>
                    </div>

                    <div class="mt-3 flex justify-between text-xs text-slate-400">
                        <span>Budget: ${{ number_format($campaign->total_budget, 2) }}</span>
                        <span>Spent: ${{ number_format($campaign->amount_spent, 2) }}</span>
                    </div>

                    {{-- Actions --}}
                    <div class="mt-4 flex gap-2 pt-3 border-t border-slate-700/50">
                        <a href="{{ route('politician.campaigns.show', $campaign) }}"
                           class="flex-1 text-center text-xs font-medium text-slate-300 hover:text-white bg-slate-700/50 hover:bg-slate-700 rounded-lg py-1.5 transition">
                            View
                        </a>
                        @if(in_array($campaign->status?->value ?? $campaign->status, ['draft', 'paused', 'cancelled']))
                        <a href="{{ route('politician.campaigns.edit', $campaign) }}"
                           class="flex-1 text-center text-xs font-medium text-slate-300 hover:text-white bg-slate-700/50 hover:bg-slate-700 rounded-lg py-1.5 transition">
                            Edit
                        </a>
                        @endif
                        @if(($campaign->status?->value ?? $campaign->status) === 'active')
                        <form method="POST" action="{{ route('politician.campaigns.pause', $campaign) }}" class="flex-1">
                            @csrf
                            <button type="submit" class="w-full text-xs font-medium text-yellow-400 hover:text-yellow-300 bg-yellow-500/10 hover:bg-yellow-500/20 rounded-lg py-1.5 transition">
                                Pause
                            </button>
                        </form>
                        @elseif(($campaign->status?->value ?? $campaign->status) === 'paused')
                        <form method="POST" action="{{ route('politician.campaigns.resume', $campaign) }}" class="flex-1">
                            @csrf
                            <button type="submit" class="w-full text-xs font-medium text-emerald-400 hover:text-emerald-300 bg-emerald-500/10 hover:bg-emerald-500/20 rounded-lg py-1.5 transition">
                                Resume
                            </button>
                        </form>
                        @endif
                    </div>
                </div>
            </div>
            @endforeach
        </div>

        {{-- Pagination --}}
        @if($campaigns->hasPages())
        <div class="flex justify-center">
            {{ $campaigns->links() }}
        </div>
        @endif
    @endif

</div>
@endsection
