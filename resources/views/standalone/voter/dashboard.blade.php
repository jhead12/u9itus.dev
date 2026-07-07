@extends('layouts.voter')

@section('title', 'Dashboard')

@section('content')
<div class="px-4 sm:px-6 lg:px-8 py-8 max-w-5xl mx-auto space-y-7">

    @php
        $minPayout = (float) \App\Services\PlatformSettingsService::get('min_payout_amount', null, 5.00);
        $defaultPayout = (float) \App\Services\PlatformSettingsService::get('viewer_payout_per_view', null, 0.25);
    @endphp

    {{-- Page Header --}}
    <div class="flex items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-white">Dashboard</h1>
            <p class="text-slate-400 text-sm mt-0.5">Welcome back, <span class="text-emerald-400 font-medium">{{ $user->name }}</span></p>
        </div>
        <div class="flex items-center gap-2 shrink-0">
            @if(auth()->user()->hasRole('citizen'))
            <a href="{{ route('citizen.dashboard') }}"
               class="text-xs font-medium text-amber-400 hover:text-amber-300 border border-amber-500/30 hover:border-amber-400/50 rounded-lg px-3 py-1.5 transition">
                🏘️ Citizen Portal
            </a>
            @endif
            <button id="dash-help-btn"
                    onclick="window.startDashTour(true)"
                    aria-label="Launch dashboard walkthrough"
                    title="Dashboard help tour"
                    class="inline-flex items-center gap-1.5 text-xs font-medium text-slate-400 hover:text-white border border-slate-700 hover:border-slate-500 rounded-lg px-3 py-1.5 transition">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                Help
            </button>
            <span class="text-xs bg-emerald-900/30 border border-emerald-700/40 text-emerald-400 rounded-full px-3 py-1">
                {{ now()->format('l, M j') }} · <span id="dash-local-clock" aria-live="polite" aria-atomic="true">—</span>
            </span>
        </div>
    </div>

    @include('standalone.voter.partials.authentic-user-verifier-banner')

    {{-- Running Campaigns -- primary action shown first for better accessibility and task clarity --}}
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-2xl p-5">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
            <div>
                <h2 class="text-white font-semibold text-lg">Running Campaigns</h2>
                <p class="text-slate-400 text-sm mt-0.5">
                    {{ number_format($availableCampaignsCount ?? 0) }} available. Start watching to earn
                    <span class="text-emerald-400 font-semibold">${{ number_format($defaultPayout, 2) }}</span> per completed view.
                </p>
            </div>
            <a href="{{ route('voter.ad-room') }}"
               class="inline-flex items-center justify-center gap-1.5 bg-emerald-600 hover:bg-emerald-500 text-white px-4 py-2 rounded-xl text-sm font-semibold transition whitespace-nowrap">
                View All Campaigns
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </a>
        </div>

        @if(($availableCampaignsCount ?? 0) === 0)
            <div class="rounded-xl border border-slate-700 bg-slate-900/30 p-4">
                <p class="text-slate-300 font-medium text-sm">No campaigns available right now</p>
                <p class="text-slate-500 text-xs mt-1">New campaigns are added regularly. Check back soon.</p>
            </div>
        @else
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
                @foreach($availableCampaigns as $campaign)
                    @php
                        $remainingViews = max(0, ($campaign->total_views_requested ?? 0) - ($campaign->views_completed ?? 0));
                        $payout = (float) ($campaign->voter_payout_per_view ?? $defaultPayout);
                    @endphp
                    <article class="rounded-xl border border-slate-700 bg-slate-900/30 p-4 flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h3 class="text-white font-semibold text-sm truncate">{{ $campaign->title }}</h3>
                            <p class="text-slate-400 text-xs mt-1 truncate">{{ $campaign->politician->full_name ?? 'Verified politician' }}</p>
                            <div class="mt-2 flex flex-wrap items-center gap-2 text-xs">
                                @if($campaign->governance_level)
                                    <span class="px-2 py-0.5 rounded-full bg-slate-700 text-slate-300">{{ $campaign->governance_level }}</span>
                                @endif
                                <span class="px-2 py-0.5 rounded-full bg-emerald-900/30 text-emerald-400">${{ number_format($payout, 2) }} / view</span>
                                <span class="px-2 py-0.5 rounded-full bg-slate-700 text-slate-300">{{ number_format($remainingViews) }} left</span>
                            </div>
                        </div>
                        <a href="{{ route('voter.ad-room') }}"
                           aria-label="Open running campaigns dashboard"
                           class="shrink-0 inline-flex items-center gap-1 text-emerald-400 hover:text-emerald-300 text-xs font-semibold transition">
                            Open
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </a>
                    </article>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Earnings Stats --}}
    @if($voter)
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        @php
            $statCards = [
                ['label' => 'Wallet Balance',   'value' => '$' . number_format($summary['wallet_balance'] ?? 0, 2),   'color' => 'emerald',
                 'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>'],
                ['label' => 'Pending Earnings', 'value' => '$' . number_format($summary['pending_earnings'] ?? 0, 2), 'color' => 'amber',
                 'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>'],
                ['label' => 'Total Earned',     'value' => '$' . number_format($summary['total_earned'] ?? 0, 2),     'color' => 'blue',
                 'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>'],
                ['label' => 'Total Views',      'value' => $summary['total_views'] ?? 0,                              'color' => 'purple',
                 'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.069A1 1 0 0121 8.882v6.236a1 1 0 01-1.447.894L15 14M3 8a2 2 0 00-2 2v4a2 2 0 002 2h9a2 2 0 002-2v-4a2 2 0 00-2-2H3z"/>'],
            ];
        @endphp
        @foreach($statCards as $s)
        <div class="bg-slate-800/50 border border-slate-700/60 rounded-2xl p-5 group hover:border-{{ $s['color'] }}-500/40 transition">
            <div class="flex items-center justify-between mb-3">
                <p class="text-slate-400 text-xs font-medium uppercase tracking-wide">{{ $s['label'] }}</p>
                <svg class="w-4 h-4 text-{{ $s['color'] }}-500/60" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    {!! $s['icon'] !!}
                </svg>
            </div>
            <p class="text-2xl font-bold text-{{ $s['color'] }}-400">{{ $s['value'] }}</p>
        </div>
        @endforeach
    </div>

    {{-- Active Promotions --}}
    @if(isset($activePromotions) && $activePromotions->isNotEmpty())
    <div class="bg-gradient-to-r from-amber-900/20 to-orange-900/20 border border-amber-500/30 rounded-2xl p-5">
        <div class="flex items-start gap-3 mb-4">
            <div class="w-10 h-10 rounded-xl bg-amber-500/20 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7"/>
                </svg>
            </div>
            <div class="flex-1">
                <h3 class="text-white font-semibold text-base">🎉 Active Promotions</h3>
                <p class="text-slate-400 text-sm mt-0.5">Limited-time bonuses &amp; special rates currently running</p>
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
                        <p class="text-emerald-400 font-bold text-lg">{{ $promo->getTypedValue() }}{{ in_array($promo->key, ['referral_commission_percent', 'procurement_commission_percent']) ? '%' : '' }}</p>
                        <p class="text-slate-500 text-xs mt-0.5">Ends {{ $promo->effective_until->diffForHumans() }}</p>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Payout CTA --}}
    @if(($summary['approved_earnings'] ?? 0) > 0)
    {{-- Already requested — show in-review banner instead of the request button --}}
    <div class="bg-gradient-to-r from-amber-900/30 to-yellow-900/20 border border-amber-500/30 rounded-2xl p-5 flex items-center gap-5">
        <div class="w-10 h-10 rounded-xl bg-amber-500/20 flex items-center justify-center shrink-0">
            <svg class="w-5 h-5 text-amber-400 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
        </div>
        <div>
            <p class="text-amber-300 font-semibold text-sm">
                Payout of <strong class="text-amber-200 text-base">${{ number_format($summary['approved_earnings'], 2) }}</strong> is being processed
            </p>
            <p class="text-slate-400 text-xs mt-0.5">Your payout request was received and will be paid within 1–2 business days.</p>
        </div>
    </div>
    @elseif(($summary['pending_earnings'] ?? 0) >= $minPayout)
    <div class="bg-gradient-to-r from-emerald-900/40 to-teal-900/30 border border-emerald-500/30 rounded-2xl p-5 flex items-center gap-5 justify-between">
        <div class="flex items-center gap-4">
            <div class="w-10 h-10 rounded-xl bg-emerald-500/20 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                </svg>
            </div>
            <div>
                <p class="text-emerald-300 font-semibold text-sm">
                    <strong class="text-emerald-200 text-base">${{ number_format($summary['pending_earnings'], 2) }}</strong> ready for payout!
                </p>
                <p class="text-slate-400 text-xs mt-0.5">Processed within 1–2 business days</p>
            </div>
        </div>
        <form action="{{ route('voter.earnings.payout') }}" method="POST" class="shrink-0">
            @csrf
            <button class="bg-emerald-600 hover:bg-emerald-500 text-white font-semibold px-5 py-2 rounded-xl transition text-sm">
                Request Payout
            </button>
        </form>
    </div>
    @endif

    {{-- Referral Banner --}}
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-2xl p-5 flex items-center gap-4 justify-between">
        <div class="flex items-center gap-4">
            <div class="w-10 h-10 rounded-xl bg-purple-500/20 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
            </div>
            <div>
                <p class="text-white font-semibold text-sm">Refer friends</p>
                <p class="text-slate-400 text-xs mt-0.5">
                    Share your referral links — earn commissions via <span class="text-indigo-400 font-semibold">Early-bank</span>
                </p>
            </div>
        </div>
        <a href="{{ route('voter.referrals') }}"
           class="shrink-0 bg-slate-700 hover:bg-slate-600 text-white px-4 py-2 rounded-xl text-sm font-medium transition">
            View Referrals →
        </a>
    </div>

    {{-- Browse Politicians Directory --}}
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-2xl p-5 flex items-center gap-4 justify-between hover:border-blue-500/40 transition group">
        <div class="flex items-center gap-4">
            <div class="w-10 h-10 rounded-xl bg-blue-500/20 flex items-center justify-center shrink-0 group-hover:bg-blue-500/30 transition">
                <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
            </div>
            <div>
                <p class="text-white font-semibold text-sm">Browse Politicians</p>
                <p class="text-slate-400 text-xs mt-0.5">
                    Research verified officials &amp; view their profiles before watching their campaigns
                </p>
            </div>
        </div>
        <a href="{{ route('politicians.directory') }}"
           class="shrink-0 bg-slate-700 hover:bg-slate-600 text-white px-4 py-2 rounded-xl text-sm font-medium transition">
            View Directory →
        </a>
    </div>

    <!-- {{-- Earnings Calculator Widget --}}
    <x-earnings-calculator /> -->

    {{-- Local Candidate News --}}
    @include('standalone.voter.partials.candidate-news', ['candidateNews' => $candidateNews])

    {{-- Voter Registration Prompt (shown if status unknown or not registered) --}}
    @if(is_null($voter->is_registered_voter) || $voter->is_registered_voter === false)
    <div class="bg-blue-500/10 border border-blue-500/30 rounded-2xl p-5 flex items-start gap-4">
        <div class="w-10 h-10 rounded-xl bg-blue-500/20 flex items-center justify-center shrink-0 mt-0.5">
            <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
        </div>
        <div class="flex-1 min-w-0">
            @if($voter->is_registered_voter === false)
            <p class="text-blue-200 font-semibold text-sm">You're not registered to vote yet</p>
            <p class="text-slate-400 text-xs mt-0.5 mb-3">
                Registering takes just a few minutes and may unlock additional campaigns in your area targeted at registered voters.
            </p>
            @else
            <p class="text-blue-200 font-semibold text-sm">Are you registered to vote?</p>
            <p class="text-slate-400 text-xs mt-0.5 mb-3">
                You haven't confirmed your voter registration status yet. Let us know &mdash; registered voters may receive more targeted campaigns.
            </p>
            @endif
            <div class="flex flex-wrap gap-2">
                <a href="https://vote.gov" target="_blank" rel="noopener noreferrer"
                   class="inline-flex items-center gap-1.5 bg-blue-600 hover:bg-blue-500 text-white text-xs font-semibold px-3 py-1.5 rounded-lg transition">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                    Register to vote at vote.gov
                </a>
                <a href="{{ route('voter.profile') }}"
                   class="inline-flex items-center gap-1.5 bg-slate-700 hover:bg-slate-600 text-slate-300 text-xs font-medium px-3 py-1.5 rounded-lg transition">
                    Update registration status in profile &rarr;
                </a>
            </div>
        </div>
    </div>
    @endif

    {{-- Voter Registration Prompt (shown if status unknown or not registered) --}}
    @if(is_null($voter->is_registered_voter) || $voter->is_registered_voter === false)
    <div class="bg-blue-500/10 border border-blue-500/30 rounded-2xl p-5 flex items-start gap-4">
        <div class="w-10 h-10 rounded-xl bg-blue-500/20 flex items-center justify-center shrink-0 mt-0.5">
            <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
        </div>
        <div class="flex-1 min-w-0">
            @if($voter->is_registered_voter === false)
            <p class="text-blue-200 font-semibold text-sm">You're not registered to vote yet</p>
            <p class="text-slate-400 text-xs mt-0.5 mb-3">
                Registering takes just a few minutes and may unlock additional campaigns targeted at registered voters in your area.
            </p>
            @else
            <p class="text-blue-200 font-semibold text-sm">Are you registered to vote?</p>
            <p class="text-slate-400 text-xs mt-0.5 mb-3">
                You haven't confirmed your voter registration status yet. Registered voters may receive more targeted campaigns.
            </p>
            @endif
            <div class="flex flex-wrap gap-2">
                <a href="https://vote.gov" target="_blank" rel="noopener noreferrer"
                   class="inline-flex items-center gap-1.5 bg-blue-600 hover:bg-blue-500 text-white text-xs font-semibold px-3 py-1.5 rounded-lg transition">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                    Register to vote at vote.gov
                </a>
                <a href="{{ route('voter.profile') }}"
                   class="inline-flex items-center gap-1.5 bg-slate-700 hover:bg-slate-600 text-slate-300 text-xs font-medium px-3 py-1.5 rounded-lg transition">
                    Update registration status in profile &rarr;
                </a>
            </div>
        </div>
    </div>
    @endif

    {{-- Recent Sessions --}}
    @if($recentSessions->isNotEmpty() || ($voter && $voter->earlybank_member_id))
    <div id="dash-section-activity">
        <h2 class="text-lg font-semibold text-white mb-4">Recent Activity</h2>
        <div class="bg-slate-800/50 border border-slate-700 rounded-xl overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-700 text-slate-400 text-left">
                        <th class="px-4 py-3 font-medium">Campaign</th>
                        <th class="px-4 py-3 font-medium">Watched</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                        <th class="px-4 py-3 font-medium text-right">Earned</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700/50">
                    {{-- EarlyBank membership — synthetic first-transaction row --}}
                    @if($voter && $voter->earlybank_member_id)
                    <tr class="hover:bg-amber-950/20 transition bg-amber-950/10">
                        <td class="px-4 py-3 text-white">
                            <span class="inline-flex items-center gap-1.5">
                                🏦 Joined Early-bank
                                <a href="https://earlybank.com" target="_blank" rel="noopener"
                                   class="text-[10px] text-amber-400 hover:text-amber-300 underline">earlybank.com ↗</a>
                            </span>
                        </td>
                        <td class="px-4 py-3 text-slate-400 text-xs">
                            {{ $voter->earlybank_linked_at?->format('M j, Y') ?? '—' }}
                        </td>
                        <td class="px-4 py-3">
                            <span class="inline-block px-2 py-0.5 rounded text-xs bg-amber-900/50 text-amber-400">Member</span>
                        </td>
                        <td class="px-4 py-3 text-right text-amber-400 font-medium">−$20.00</td>
                    </tr>
                    @endif
                    @foreach($recentSessions as $session)
                    <tr class="hover:bg-slate-700/20 transition">
                        <td class="px-4 py-3 text-white">{{ $session->campaign->title ?? '—' }}</td>
                        <td class="px-4 py-3 text-slate-300">{{ $session->watch_time_seconds }}s</td>
                        <td class="px-4 py-3">
                            @php
                                $badge = match($session->status->value ?? '') {
                                    'completed'   => 'bg-emerald-900/50 text-emerald-400',
                                    'in_progress' => 'bg-blue-900/50 text-blue-400',
                                    'flagged'     => 'bg-red-900/50 text-red-400',
                                    default       => 'bg-slate-700 text-slate-300',
                                };
                            @endphp
                            <span class="inline-block px-2 py-0.5 rounded text-xs {{ $badge }}">
                                {{ ucfirst(str_replace('_', ' ', $session->status->value ?? '')) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right text-emerald-400 font-medium">
                            ${{ number_format($session->voter_payout_amount ?? 0, 2) }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-3 text-right">
            <a href="{{ route('voter.earnings.history') }}" class="text-emerald-400 hover:text-emerald-300 text-sm">View full history →</a>
        </div>
    </div>
    @endif

    {{-- ═══════════════════════════════════════════════════════════════
         CITIZEN ACCOUNT CARD
         Shown to all voters. Explains the Citizen role — local businesses,
         community groups, and ballot-issue advocates who want to reach
         nearby voters via pay-per-view ads. Links directly to registration.
    ═══════════════════════════════════════════════════════════════ --}}
    <div class="relative overflow-hidden rounded-2xl border border-amber-500/30
                bg-gradient-to-br from-amber-950/40 via-slate-900/80 to-slate-900/60
                p-6 sm:p-8">

        {{-- Background glow --}}
        <div class="pointer-events-none absolute -top-16 -right-16 w-64 h-64 rounded-full
                    bg-amber-500/10 blur-3xl"></div>

        <div class="relative flex flex-col sm:flex-row gap-6 items-start sm:items-center">
            {{-- Icon --}}
            <div class="w-14 h-14 rounded-2xl bg-amber-500/15 border border-amber-500/30
                        flex items-center justify-center shrink-0 text-3xl">
                🏘️
            </div>

            {{-- Copy --}}
            <div class="flex-1 min-w-0">
                <div class="flex flex-wrap items-center gap-2 mb-1">
                    <h3 class="text-base font-bold text-white">Want to reach voters as a Citizen?</h3>
                    <span class="text-xs font-semibold px-2 py-0.5 rounded-full
                                 bg-amber-400/15 border border-amber-400/30 text-amber-300">
                        Now Open
                    </span>
                </div>
                <p class="text-sm text-slate-400 leading-relaxed mb-3">
                    A <strong class="text-slate-200">Citizen account</strong> lets local businesses,
                    community groups, and civic advocates run short video ads to nearby voters —
                    without the FEC/election-commission requirements that apply to politicians.
                    Pay only when someone watches. Start from <strong class="text-slate-200">$0.75/view</strong>,
                    with a 500-view daily cap to keep costs predictable.
                </p>

                {{-- Ad type chips --}}
                <div class="flex flex-wrap gap-2 mb-3">
                    @foreach ([
                        ['🏪', 'Local Business',       'Promote your shop, restaurant, or service'],
                        ['📢', 'Community Notice',     'Events, drives, neighborhood announcements'],
                        ['🗳️', 'Ballot Issue',         'PAC-registered measures — $1.00/view, admin reviewed'],
                        ['📣', 'General Announcement', 'Other local messages'],
                    ] as [$icon, $label, $desc])
                    <span title="{{ $desc }}"
                          class="inline-flex items-center gap-1.5 text-xs px-2.5 py-1 rounded-lg
                                 bg-slate-800 border border-slate-700/60 text-slate-300 cursor-default">
                        {{ $icon }} {{ $label }}
                    </span>
                    @endforeach
                </div>

                {{-- Pricing row --}}
                <div class="flex flex-wrap gap-x-5 gap-y-1 text-xs text-slate-400">
                    <span><span class="text-amber-400 font-medium">$0.75</span> / view (standard)</span>
                    <span><span class="text-amber-400 font-medium">$0.50</span> voter payout</span>
                    <span><span class="text-amber-400 font-medium">$10</span> minimum campaign</span>
                    <span><span class="text-amber-400 font-medium">500</span> daily view cap</span>
                </div>
            </div>

            {{-- CTA --}}
            <div class="shrink-0 flex flex-col items-stretch gap-2 w-full sm:w-auto">
                @if(auth()->user()->citizen)
                <a href="{{ route('citizen.dashboard') }}"
                   class="inline-flex items-center justify-center gap-2
                          bg-amber-500 hover:bg-amber-400
                          text-slate-900 text-sm font-semibold
                          px-5 py-2.5 rounded-xl transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2"
                         viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                    </svg>
                    Go to Citizen Portal
                </a>
                <p class="text-center text-xs text-slate-500">Already on this account</p>
                @else
                <a href="{{ route('voter.add-citizen-profile') }}"
                   class="inline-flex items-center justify-center gap-2
                          bg-amber-500 hover:bg-amber-400
                          text-slate-900 text-sm font-semibold
                          px-5 py-2.5 rounded-xl transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2"
                         viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                    </svg>
                    Add Citizen Profile
                </a>
                <p class="text-center text-xs text-slate-500">Same account · No new email needed</p>
                @endif
            </div>
        </div>
    </div>

    @else
    <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-10 text-center">
        <p class="text-slate-400">No voter profile found for your account.</p>
        <p class="text-slate-500 text-sm mt-2">Please contact support if you believe this is an error.</p>
    </div>
    @endif

<!-- Create a local news for candidates in the GEO location of account, take the news from the auto generated profiles and post a small thumbnail of a picture to a news site article with the link of the politician beneath it.  -->

</div>

{{-- ── Dashboard Help Tour overlay ── --}}
<div id="dash-tour-overlay" role="dialog" aria-modal="true" aria-label="Dashboard walkthrough">
    <div id="dash-tour-backdrop" onclick="window._dashTourEnd()"></div>
    <div id="dash-tour-card"></div>
</div>

@endsection

@push('styles')
<style>
    #dash-tour-overlay { position:fixed; inset:0; z-index:9000; display:none; }
    #dash-tour-overlay.active { display:block; }
    #dash-tour-backdrop { position:absolute; inset:0; background:rgba(0,0,0,0.68); }
    #dash-tour-card {
        position:fixed; bottom:0; left:50%; transform:translateX(-50%);
        width:min(480px, calc(100vw - 24px));
        max-height:85vh; overflow-y:auto;
        background:#0f172a; border:1px solid rgba(99,102,241,0.45);
        border-radius:18px 18px 0 0; padding:22px 22px 28px;
        z-index:9001; pointer-events:all;
        box-shadow:0 -8px 40px rgba(0,0,0,0.6), 0 0 0 1px rgba(99,102,241,0.08);
        -webkit-overflow-scrolling: touch;
    }
    @media (min-width: 640px) {
        #dash-tour-card {
            bottom:auto; top:50%; left:50%;
            transform:translate(-50%,-50%);
            border-radius:14px;
        }
    }
    .dt-label { font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.1em; color:#6366f1; margin:0 0 6px; }
    .dt-title { font-size:16px; font-weight:700; color:#e2e8f0; margin:0 0 10px; line-height:1.35; }
    .dt-body  { font-size:13px; color:#94a3b8; line-height:1.65; margin:0 0 18px; }
    .dt-body strong { color:#e2e8f0; }
    .dt-body em { color:#a5b4fc; font-style:normal; }
    .dt-actions { display:flex; align-items:center; justify-content:space-between; gap:8px; }
    .dt-dots { display:flex; gap:5px; align-items:center; }
    .dt-dot { width:6px; height:6px; border-radius:50%; background:#334155; transition:all .2s; flex-shrink:0; }
    .dt-dot.active { background:#6366f1; width:16px; border-radius:3px; }
    .dt-btns { display:flex; gap:8px; align-items:center; }
    .dt-btn-skip  { font-size:11px; color:#475569; background:none; border:none; cursor:pointer; padding:0; text-decoration:underline; }
    .dt-btn-skip:hover  { color:#64748b; }
    .dt-btn-back  { background:#1e293b; color:#94a3b8; border:1px solid #334155; border-radius:8px; padding:8px 14px; font-size:12px; cursor:pointer; min-height:44px; }
    .dt-btn-back:hover  { background:#263248; }
    .dt-btn-next  { background:#6366f1; color:#fff; border:none; border-radius:8px; padding:8px 18px; font-size:12px; font-weight:600; cursor:pointer; min-height:44px; }
    .dt-btn-next:hover  { background:#818cf8; }
</style>
@endpush

@push('scripts')
<script>
(function () {
    var TOUR_KEY = 'u9itus_dash_tour_v1';
    var _step = 0;

    var STEPS = [
        {
            title: '👋 Welcome to Your Dashboard',
            body: 'This is your home base on U9itus. Here you watch campaign videos, track your earnings, request payouts, and browse politicians.<br><br>This quick tour walks through each section. Skip at any time.',
        },
        {
            title: '▶ Running Campaigns',
            body: 'These are the live campaign videos available to watch right now. Each completed view earns you <strong>$0.50</strong>. Click <em>View All Campaigns</em> to open the full ad room.',
        },
        {
            title: '💰 Your Earnings',
            body: 'Four cards track your money at a glance: <strong>Wallet Balance</strong> (cleared to withdraw), <strong>Pending Earnings</strong> (views being verified), <strong>Total Earned</strong> all-time, and <strong>Total Views</strong> completed.',
        },
        {
            title: '🏦 Requesting a Payout',
            body: 'Once your pending earnings reach the minimum threshold, a <em>Request Payout</em> button appears here. Payouts are processed within 1–2 business days via your connected payout method.',
        },
        {
            title: '🔗 Referrals & Early-bank',
            body: 'Share your referral link to earn commissions on every recruit. If you joined <strong>Early-bank</strong> for $20, your membership appears as your first transaction in Recent Activity below.',
        },
        {
            title: '📋 Recent Activity',
            body: 'Every session you start or complete appears here. <em>Completed</em> rows show your payout amount. If you joined Early-bank, that $20 membership is pinned at the top as your first transaction.',
            isLast: true,
        },
    ];

    window.startDashTour = function (force) {
        if (!force && localStorage.getItem(TOUR_KEY)) return;
        _step = 0;
        document.getElementById('dash-tour-overlay').classList.add('active');
        _render(0);
    };

    window._dashTourNext = function () {
        _step = Math.min(_step + 1, STEPS.length - 1);
        _render(_step);
    };
    window._dashTourBack = function () {
        _step = Math.max(_step - 1, 0);
        _render(_step);
    };
    window._dashTourEnd = function () {
        document.getElementById('dash-tour-overlay').classList.remove('active');
        try { localStorage.setItem(TOUR_KEY, '1'); } catch (e) {}
    };

    function _render(idx) {
        var step  = STEPS[idx];
        var total = STEPS.length;
        var card  = document.getElementById('dash-tour-card');

        var dots = STEPS.map(function (_, i) {
            return '<span class="dt-dot' + (i === idx ? ' active' : '') + '"></span>';
        }).join('');

        card.innerHTML =
            '<p class="dt-label">Step ' + (idx + 1) + ' of ' + total + '</p>' +
            '<p class="dt-title">' + step.title + '</p>' +
            '<div class="dt-body">' + step.body + '</div>' +
            '<div class="dt-actions">' +
                '<div class="dt-dots">' + dots + '</div>' +
                '<div class="dt-btns">' +
                    (idx > 0 ? '<button class="dt-btn-back" onclick="window._dashTourBack()">← Back</button>' : '') +
                    (step.isLast
                        ? '<button class="dt-btn-next" onclick="window._dashTourEnd()">Finish 🎉</button>'
                        : '<button class="dt-btn-skip" onclick="window._dashTourEnd()">Skip</button>' +
                          '<button class="dt-btn-next" onclick="window._dashTourNext()">Next →</button>'
                    ) +
                '</div>' +
            '</div>';
    }

    document.addEventListener('DOMContentLoaded', function () {
        window.startDashTour(false);
    });
}());

// Local clock
(function () {
    function tick() {
        var el = document.getElementById('dash-local-clock');
        if (!el) return;
        var now = new Date();
        var h = now.getHours();
        var m = now.getMinutes();
        var ampm = h >= 12 ? 'PM' : 'AM';
        h = h % 12 || 12;
        el.textContent = h + ':' + (m < 10 ? '0' : '') + m + ' ' + ampm;
    }
    tick();
    setInterval(tick, 1000);
}());
</script>
@endpush
