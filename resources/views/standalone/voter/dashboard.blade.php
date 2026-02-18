@extends('layouts.voter')

@section('title', 'Dashboard')

@section('content')
<div class="px-4 sm:px-6 lg:px-8 py-8 max-w-5xl mx-auto space-y-7">

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

    {{-- Payout CTA --}}
    @if(($summary['pending_earnings'] ?? 0) >= config('u9itus.batch_payout_min', 10))
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
