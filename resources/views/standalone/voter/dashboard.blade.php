@extends('layouts.app')

@section('title', 'Voter Dashboard')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">

    {{-- Header --}}
    <div>
        <h1 class="text-3xl font-bold text-white">Voter Dashboard</h1>
        <p class="text-slate-400 mt-1">Welcome back, {{ $user->name }}</p>
    </div>

    {{-- Earnings Stats --}}
    @if($voter)
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        @php
            $stats = [
                ['label' => 'Wallet Balance',    'value' => '$' . number_format($summary['wallet_balance'] ?? 0, 2),   'color' => 'emerald'],
                ['label' => 'Pending Earnings',  'value' => '$' . number_format($summary['pending_earnings'] ?? 0, 2), 'color' => 'amber'],
                ['label' => 'Total Earned',      'value' => '$' . number_format($summary['total_earned'] ?? 0, 2),     'color' => 'blue'],
                ['label' => 'Total Views',       'value' => $summary['total_views'] ?? 0,                               'color' => 'purple'],
            ];
        @endphp
        @foreach($stats as $s)
        <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-5">
            <p class="text-slate-400 text-sm">{{ $s['label'] }}</p>
            <p class="text-2xl font-bold text-{{ $s['color'] }}-400 mt-1">{{ $s['value'] }}</p>
        </div>
        @endforeach
    </div>

    {{-- Payout CTA --}}
    @if(($summary['pending_earnings'] ?? 0) >= config('u9itus.batch_payout_min', 10))
    <div class="bg-emerald-900/30 border border-emerald-500/30 rounded-xl p-5 flex items-center justify-between">
        <div>
            <p class="text-emerald-300 font-semibold">You have <strong>${{ number_format($summary['pending_earnings'], 2) }}</strong> ready for payout!</p>
            <p class="text-slate-400 text-sm mt-0.5">Minimum payout is ${{ config('u9itus.batch_payout_min', 10) }}</p>
        </div>
        <form action="{{ route('voter.earnings.payout') }}" method="POST">
            @csrf
            <button class="bg-emerald-600 hover:bg-emerald-500 text-white font-semibold px-5 py-2 rounded-lg transition text-sm">
                Request Payout
            </button>
        </form>
    </div>
    @endif

    {{-- Referral Banner --}}
    <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-5 flex items-center justify-between gap-4">
        <div>
            <p class="text-white font-semibold">Refer friends &amp; earn</p>
            <p class="text-slate-400 text-sm mt-0.5">Earn 10% commission on every view your referrals complete</p>
        </div>
        <a href="{{ route('voter.referrals') }}"
           class="shrink-0 bg-slate-700 hover:bg-slate-600 text-white px-4 py-2 rounded-lg text-sm transition">
            My Referrals →
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
