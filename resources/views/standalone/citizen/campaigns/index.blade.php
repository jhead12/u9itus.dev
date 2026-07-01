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
                @php $draftsCount = $campaigns->getCollection()->where('status.value', 'draft')->count(); @endphp
                @if($draftsCount > 0)
                    <span class="mx-2">•</span>
                    <span class="text-amber-400">{{ $draftsCount }} draft{{ $draftsCount !== 1 ? 's' : '' }}</span>
                @endif
            </p>
        </div>
        <a href="{{ route('citizen.campaigns.create') }}"
           class="inline-flex items-center gap-2 bg-amber-500 hover:bg-amber-400 text-slate-900 font-semibold rounded-lg px-4 py-2.5 text-sm transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            New Campaign
        </a>
    </div>

    {{-- Flash messages --}}
    @if(session('success'))
    <div class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-5 py-3 text-sm text-emerald-300">{{ session('success') }}</div>
    @endif

    {{-- Campaign list --}}
    @if($campaigns->isEmpty())
    <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-10 text-center">
        <div class="inline-flex items-center justify-center w-12 h-12 bg-slate-700/60 rounded-full mb-4">
            <svg class="w-6 h-6 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.069A1 1 0 0121 8.82V15.18a1 1 0 01-1.447.89L15 14M3 8a2 2 0 012-2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8z"/>
            </svg>
        </div>
        <h3 class="text-white font-semibold mb-1">No campaigns yet</h3>
        <p class="text-slate-400 text-sm mb-4">Create your first local ad campaign and start reaching your community.</p>
        <a href="{{ route('citizen.campaigns.create') }}"
           class="inline-flex items-center gap-2 bg-amber-500 hover:bg-amber-400 text-slate-900 font-semibold rounded-lg px-4 py-2.5 text-sm transition-colors">
            Create Campaign
        </a>
    </div>
    @else
    <div class="space-y-3">
        @foreach($campaigns as $campaign)
        @php
            $rawStatus = $campaign->getRawOriginal('status') ?? $campaign->status?->value ?? '';
            $statusColors = [
                'draft'            => 'text-slate-400 bg-slate-700/60',
                'pending_approval' => 'text-blue-400 bg-blue-500/10',
                'active'           => 'text-emerald-400 bg-emerald-500/10',
                'paused'           => 'text-yellow-400 bg-yellow-500/10',
                'scheduled'        => 'text-purple-400 bg-purple-500/10',
                'completed'        => 'text-slate-300 bg-slate-700/40',
                'cancelled'        => 'text-red-400 bg-red-500/10',
            ];
            $statusColor = $statusColors[$rawStatus] ?? 'text-slate-400 bg-slate-700/60';
            $adType = $campaign->getRawOriginal('citizen_ad_type') ?? $campaign->citizen_ad_type?->value ?? '';
            $adTypeLabels = [
                'local_business'       => 'Local Business',
                'community_notice'     => 'Community Notice',
                'ballot_issue'         => 'Ballot Issue',
                'general_announcement' => 'General Announcement',
            ];
        @endphp
        <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-5 flex flex-col sm:flex-row sm:items-center gap-4 hover:border-slate-600/60 transition">
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 mb-1">
                    <span class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium {{ $statusColor }}">
                        {{ ucfirst(str_replace('_', ' ', $rawStatus)) }}
                    </span>
                    <span class="text-xs text-slate-500">{{ $adTypeLabels[$adType] ?? $adType }}</span>
                </div>
                <h3 class="text-white font-medium text-sm truncate">{{ $campaign->title }}</h3>
                <p class="text-slate-400 text-xs mt-0.5">
                    {{ $campaign->views_completed }} / {{ $campaign->total_views_requested }} views
                    <span class="mx-1.5">·</span>
                    ${{ number_format($campaign->revenue_per_view, 2) }}/view
                </p>
            </div>
            <a href="{{ route('citizen.campaigns.show', $campaign) }}"
               class="shrink-0 text-xs font-medium text-slate-400 hover:text-white border border-slate-600 hover:border-slate-500 rounded-lg px-3 py-1.5 transition">
                View →
            </a>
        </div>
        @endforeach
    </div>

    {{ $campaigns->links() }}
    @endif

</div>
@endsection
