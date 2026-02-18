@extends('standalone.layouts.dashboard')

@section('title', 'My Dashboard')
@section('page-title', 'Dashboard')

@section('content')
<div class="space-y-6">

    {{-- Welcome banner --}}
    <div class="bg-gradient-to-r from-emerald-500/10 to-slate-800/50 border border-emerald-500/20 rounded-xl p-6 flex flex-col sm:flex-row sm:items-center gap-4">
        <div class="flex-1">
            <h2 class="text-lg font-semibold text-white">
                Welcome back, {{ $politician->full_name ?? $user->name }} 👋
            </h2>
            <p class="text-slate-400 text-sm mt-0.5">
                {{ $politician->political_office ?? 'Politician' }}
                @if($politician->state) · {{ $politician->state }} @endif
            </p>
        </div>
        <a href="{{ route('politician.campaigns.create') }}"
           class="inline-flex items-center gap-2 bg-emerald-500 hover:bg-emerald-400 text-slate-900 font-semibold rounded-lg px-4 py-2.5 text-sm transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            New Campaign
        </a>
    </div>

    {{-- Stats grid --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">

        <div class="stat-card">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">Active Campaigns</p>
            <p class="text-3xl font-bold text-white">{{ $stats['active_campaigns'] }}</p>
            <p class="text-xs text-slate-500 mt-1">of {{ $stats['total_campaigns'] }} total</p>
        </div>

        <div class="stat-card">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">Total Views</p>
            <p class="text-3xl font-bold text-white">{{ number_format($stats['total_views']) }}</p>
            <p class="text-xs text-slate-500 mt-1">all time</p>
        </div>

        <div class="stat-card">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">Total Spent</p>
            <p class="text-3xl font-bold text-white">${{ number_format($stats['total_spent'], 2) }}</p>
            <p class="text-xs text-slate-500 mt-1">USD</p>
        </div>

        <div class="stat-card">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">Credit Balance</p>
            <p class="text-3xl font-bold {{ $stats['credit_balance'] > 0 ? 'text-emerald-400' : 'text-red-400' }}">
                ${{ number_format($stats['credit_balance'], 2) }}
            </p>
            <a href="{{ route('politician.billing') }}" class="text-xs text-emerald-400 hover:text-emerald-300 mt-1 inline-block">Add credits →</a>
        </div>

    </div>

    {{-- Recent Campaigns --}}
    <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl overflow-hidden">
        <div class="flex items-center justify-between px-5 py-4 border-b border-slate-700/50">
            <h3 class="text-sm font-semibold text-slate-200">Recent Campaigns</h3>
            <a href="{{ route('politician.campaigns.index') }}" class="text-xs text-emerald-400 hover:text-emerald-300 transition">View all →</a>
        </div>

        @if($recentCampaigns->isEmpty())
            <div class="px-5 py-10 text-center">
                <p class="text-slate-500 text-sm">No campaigns yet.</p>
                <a href="{{ route('politician.campaigns.create') }}"
                   class="mt-3 inline-flex items-center gap-1.5 text-emerald-400 hover:text-emerald-300 text-sm font-medium transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Create your first campaign
                </a>
            </div>
        @else
            <div class="divide-y divide-slate-700/50">
                @foreach($recentCampaigns as $campaign)
                <div class="flex items-center gap-4 px-5 py-4">
                    {{-- Status badge --}}
                    <div class="flex-shrink-0">
                        @php
                            $statusColor = match($campaign->status ?? 'draft') {
                                'active' => 'bg-emerald-500/15 text-emerald-400',
                                'paused' => 'bg-yellow-500/15 text-yellow-400',
                                'completed' => 'bg-slate-500/15 text-slate-400',
                                'pending_approval' => 'bg-blue-500/15 text-blue-400',
                                default => 'bg-slate-700/50 text-slate-400',
                            };
                        @endphp
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusColor }}">
                            {{ ucfirst(str_replace('_', ' ', $campaign->status ?? 'draft')) }}
                        </span>
                    </div>

                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-slate-200 truncate">{{ $campaign->title }}</p>
                        <p class="text-xs text-slate-500">
                            {{ number_format($campaign->total_views ?? 0) }} views · ${{ number_format($campaign->total_budget ?? 0, 2) }} budget
                        </p>
                    </div>

                    <a href="{{ route('politician.campaigns.show', $campaign) }}"
                       class="text-xs text-slate-400 hover:text-white transition flex-shrink-0">View →</a>
                </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Quick actions --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <a href="{{ route('politician.campaigns.create') }}"
           class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-5 hover:border-emerald-500/30 hover:bg-slate-800/80 transition group">
            <div class="w-10 h-10 rounded-lg bg-emerald-500/10 flex items-center justify-center mb-3 group-hover:bg-emerald-500/20 transition">
                <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.069A1 1 0 0121 8.87v6.26a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
            </div>
            <p class="text-sm font-semibold text-white">New Campaign</p>
            <p class="text-xs text-slate-500 mt-0.5">Launch a video ad campaign</p>
        </a>

        <a href="{{ route('politician.billing') }}"
           class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-5 hover:border-emerald-500/30 hover:bg-slate-800/80 transition group">
            <div class="w-10 h-10 rounded-lg bg-blue-500/10 flex items-center justify-center mb-3 group-hover:bg-blue-500/20 transition">
                <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
            </div>
            <p class="text-sm font-semibold text-white">Add Credits</p>
            <p class="text-xs text-slate-500 mt-0.5">Top up campaign budget</p>
        </a>

        <a href="{{ route('politician.analytics') }}"
           class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-5 hover:border-emerald-500/30 hover:bg-slate-800/80 transition group">
            <div class="w-10 h-10 rounded-lg bg-purple-500/10 flex items-center justify-center mb-3 group-hover:bg-purple-500/20 transition">
                <svg class="w-5 h-5 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
            </div>
            <p class="text-sm font-semibold text-white">View Analytics</p>
            <p class="text-xs text-slate-500 mt-0.5">Performance insights</p>
        </a>
    </div>

</div>
@endsection
