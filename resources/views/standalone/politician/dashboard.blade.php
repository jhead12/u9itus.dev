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

    {{-- Active Promotions --}}
    @if(isset($activePromotions) && $activePromotions->isNotEmpty())
    <div class="bg-gradient-to-r from-amber-900/20 to-orange-900/20 border border-amber-500/30 rounded-xl p-5">
        <div class="flex items-start gap-3 mb-4">
            <div class="w-10 h-10 rounded-xl bg-amber-500/20 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7"/>
                </svg>
            </div>
            <div class="flex-1">
                <h3 class="text-white font-semibold text-base">🎉 Active Promotions</h3>
                <p class="text-slate-400 text-sm mt-0.5">Limited-time rates &amp; special pricing currently available</p>
            </div>
        </div>
        <div class="space-y-2.5">
            @foreach($activePromotions as $promo)
            <div class="bg-slate-800/60 rounded-lg p-3.5 border border-slate-700/50">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex-1">
                        <p class="text-white font-medium text-sm">{{ $promo->description ?? ucfirst(str_replace('_', ' ', $promo->key)) }}</p>
                        @if($promo->user_tier)
                            <span class="inline-block mt-1.5 px-2 py-0.5 bg-blue-500/20 text-blue-400 text-xs rounded font-medium">{{ ucfirst($promo->user_tier) }} Only</span>
                        @endif
                    </div>
                    <div class="text-right shrink-0">
                        <p class="text-emerald-400 font-bold text-lg">
                            @if(str_contains($promo->key, 'percent'))
                                {{ $promo->getTypedValue() }}%
                            @elseif(str_contains($promo->key, 'revenue') || str_contains($promo->key, 'payout'))
                                ${{ number_format($promo->getTypedValue(), 2) }}
                            @else
                                {{ $promo->getTypedValue() }}
                            @endif
                        </p>
                        <p class="text-slate-500 text-xs mt-0.5">Ends {{ $promo->effective_until->diffForHumans() }}</p>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Stats grid --}}
    <div class="grid grid-cols-2 lg:grid-cols-6 gap-4">

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

        <div class="stat-card">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">Needs Reply</p>
            <p class="text-3xl font-bold text-cyan-300">{{ number_format($stats['open_voter_questions']) }}</p>
            <a href="{{ route('politician.analytics') }}" class="text-xs text-cyan-300 hover:text-cyan-200 mt-1 inline-block">Review questions →</a>
        </div>

        <div class="stat-card">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">Pending Review</p>
            <p class="text-3xl font-bold text-amber-300">{{ number_format($stats['pending_public_questions']) }}</p>
            <a href="{{ route('politician.analytics') }}" class="text-xs text-amber-300 hover:text-amber-200 mt-1 inline-block">Open moderation queue →</a>
        </div>

    </div>

    {{-- Recent activity --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

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
                                $statusColor = match($campaign->status?->value ?? 'draft') {
                                    'active' => 'bg-emerald-500/15 text-emerald-400',
                                    'paused' => 'bg-yellow-500/15 text-yellow-400',
                                    'completed' => 'bg-slate-500/15 text-slate-400',
                                    'pending_approval' => 'bg-blue-500/15 text-blue-400',
                                    'scheduled' => 'bg-purple-500/15 text-purple-400',
                                    default => 'bg-slate-700/50 text-slate-400',
                                };
                            @endphp
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusColor }}">
                                {{ ucfirst(str_replace('_', ' ', $campaign->status?->value ?? 'draft')) }}
                            </span>
                        </div>

                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-slate-200 truncate">{{ $campaign->title }}</p>
                            <p class="text-xs text-slate-500">
                                {{ number_format($campaign->total_views ?? 0) }} views · ${{ number_format($campaign->total_budget ?? 0, 2) }} budget
                            </p>
                            @if(($campaign->open_voter_questions_count ?? 0) > 0 || ($campaign->pending_public_questions_count ?? 0) > 0)
                            <div class="mt-2 flex flex-wrap gap-2">
                                @if(($campaign->open_voter_questions_count ?? 0) > 0)
                                <span class="inline-flex items-center rounded-full bg-cyan-500/15 px-2 py-0.5 text-[11px] font-medium text-cyan-300">
                                    {{ number_format($campaign->open_voter_questions_count) }} need reply
                                </span>
                                @endif
                                @if(($campaign->pending_public_questions_count ?? 0) > 0)
                                <span class="inline-flex items-center rounded-full bg-amber-500/15 px-2 py-0.5 text-[11px] font-medium text-amber-300">
                                    {{ number_format($campaign->pending_public_questions_count) }} pending review
                                </span>
                                @endif
                            </div>
                            @endif
                        </div>

                        <a href="{{ route('politician.campaigns.questions.index', $campaign) }}"
                           class="text-xs text-slate-400 hover:text-white transition flex-shrink-0">Q&amp;A →</a>
                    </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Recent Events --}}
        <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl overflow-hidden">
            <div class="flex items-center justify-between px-5 py-4 border-b border-slate-700/50">
                <h3 class="text-sm font-semibold text-slate-200">Recent Events</h3>
                <a href="{{ route('politician.events.index') }}" class="text-xs text-emerald-400 hover:text-emerald-300 transition">View all →</a>
            </div>

            @php($recentEvents = $politician->events()->latest()->take(5)->get())
            @if($recentEvents->isEmpty())
                <div class="px-5 py-10 text-center">
                    <p class="text-slate-500 text-sm">No events yet.</p>
                    <a href="{{ route('politician.events.create') }}"
                       class="mt-3 inline-flex items-center gap-1.5 text-emerald-400 hover:text-emerald-300 text-sm font-medium transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        Create your first event
                    </a>
                </div>
            @else
                <div class="divide-y divide-slate-700/50">
                    @foreach($recentEvents as $event)
                    <div class="flex items-center gap-4 px-5 py-4">
                        <div class="flex-shrink-0 w-10 h-10 rounded-lg bg-cyan-500/10 flex items-center justify-center">
                            <svg class="w-5 h-5 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-slate-200 truncate">{{ $event->title }}</p>
                            <p class="text-xs text-slate-500">
                                {{ $event->starts_at?->format('M j, Y g:i A') ?? '—' }}
                                · {{ $event->location ?? 'No location' }}
                            </p>
                        </div>
                        <a href="{{ route('politician.events.edit', $event) }}"
                           class="text-xs text-slate-400 hover:text-white transition flex-shrink-0">Edit →</a>
                    </div>
                    @endforeach
                </div>
            @endif
        </div>

    </div>

    {{-- Quick actions --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <a href="{{ route('politician.campaigns.create') }}" class="bg-slate-800/50 border border-slate-700/50 hover:border-emerald-500/40 rounded-xl p-4 text-center transition group">
            <div class="text-emerald-400 mb-2 flex justify-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.069A1 1 0 0121 8.87v6.26a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
            </div>
            <p class="text-xs font-medium text-slate-300 group-hover:text-white transition">New Campaign</p>
        </a>

        <a href="{{ route('politician.campaigns.index') }}" class="bg-slate-800/50 border border-slate-700/50 hover:border-emerald-500/40 rounded-xl p-4 text-center transition group">
            <div class="text-emerald-400 mb-2 flex justify-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2H5a2 2 0 00-2 2v2M3 9h18"/></svg>
            </div>
            <p class="text-xs font-medium text-slate-300 group-hover:text-white transition">My Campaigns</p>
        </a>

        <a href="{{ route('politician.billing') }}" class="bg-slate-800/50 border border-slate-700/50 hover:border-blue-500/40 rounded-xl p-4 text-center transition group">
            <div class="text-blue-400 mb-2 flex justify-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
            </div>
            <p class="text-xs font-medium text-slate-300 group-hover:text-white transition">Billing</p>
        </a>

        <a href="{{ route('politician.analytics') }}" class="bg-slate-800/50 border border-slate-700/50 hover:border-purple-500/40 rounded-xl p-4 text-center transition group">
            <div class="text-purple-400 mb-2 flex justify-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
            </div>
            <p class="text-xs font-medium text-slate-300 group-hover:text-white transition">Analytics</p>
        </a>

        <a href="{{ route('politician.public-page') }}" class="bg-slate-800/50 border border-slate-700/50 hover:border-cyan-500/40 rounded-xl p-4 text-center transition group">
            <div class="text-cyan-400 mb-2 flex justify-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <p class="text-xs font-medium text-slate-300 group-hover:text-white transition">Public Page</p>
        </a>

        <a href="{{ route('politician.events.create') }}" class="bg-slate-800/50 border border-slate-700/50 hover:border-cyan-500/40 rounded-xl p-4 text-center transition group">
            <div class="text-cyan-400 mb-2 flex justify-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            </div>
            <p class="text-xs font-medium text-slate-300 group-hover:text-white transition">New Event</p>
        </a>

        <a href="{{ route('politician.referrals') }}" class="bg-slate-800/50 border border-slate-700/50 hover:border-indigo-500/40 rounded-xl p-4 text-center transition group">
            <div class="text-indigo-400 mb-2 flex justify-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            </div>
            <p class="text-xs font-medium text-slate-300 group-hover:text-white transition">Referrals</p>
        </a>

        <a href="{{ route('politician.profile') }}" class="bg-slate-800/50 border border-slate-700/50 hover:border-slate-500/40 rounded-xl p-4 text-center transition group">
            <div class="text-slate-400 mb-2 flex justify-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
            </div>
            <p class="text-xs font-medium text-slate-300 group-hover:text-white transition">Profile</p>
        </a>
    </div>

</div>
@endsection
