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
        <span class="text-xs bg-emerald-900/30 border border-emerald-700/40 text-emerald-400 rounded-full px-3 py-1 mt-1 shrink-0">
            {{ now()->format('l, M j') }}
        </span>
    </div>

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
    @if(($summary['pending_earnings'] ?? 0) >= $minPayout)
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
                <p class="text-white font-semibold text-sm">Refer friends &amp; earn</p>
                <p class="text-slate-400 text-xs mt-0.5">
                    Earn <span class="text-purple-400 font-semibold">10% commission</span> on every view your referrals complete
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

    {{-- Earnings Calculator Widget --}}
    <x-earnings-calculator />

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
    @if($recentSessions->isNotEmpty())
    <div>
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

    @else
    <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-10 text-center">
        <p class="text-slate-400">No voter profile found for your account.</p>
        <p class="text-slate-500 text-sm mt-2">Please contact support if you believe this is an error.</p>
    </div>
    @endif

</div>
@endsection
