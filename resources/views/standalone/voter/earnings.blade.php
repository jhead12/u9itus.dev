@extends('layouts.app')

@section('title', 'My Earnings')

@section('content')
<div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">

    <h1 class="text-3xl font-bold text-white">My Earnings</h1>

    @if($voter)
    {{-- Stats --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        @foreach([
            ['Wallet Balance',   '$' . number_format($summary['wallet_balance'] ?? 0, 2),   'emerald'],
            ['Pending Earnings', '$' . number_format($summary['pending_earnings'] ?? 0, 2), 'amber'],
            ['Total Earned',     '$' . number_format($summary['total_earned'] ?? 0, 2),     'blue'],
            ['Views Today',      $summary['views_today'] ?? 0,                               'slate'],
        ] as [$label, $value, $color])
        <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-5">
            <p class="text-slate-400 text-sm">{{ $label }}</p>
            <p class="text-2xl font-bold text-{{ $color }}-400 mt-1">{{ $value }}</p>
        </div>
        @endforeach
    </div>

    {{-- Referral Earnings --}}
    @if(($summary['referral_earnings'] ?? 0) > 0)
    <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-5 flex items-center justify-between">
        <div>
            <p class="text-white font-semibold">Referral Earnings</p>
            <p class="text-slate-400 text-sm">From {{ $summary['referrals_count'] ?? 0 }} referred voter(s)</p>
        </div>
        <p class="text-purple-400 font-bold text-xl">${{ number_format($summary['referral_earnings'], 2) }}</p>
    </div>
    @endif

    {{-- Payout CTA --}}
    <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-6">
        <h2 class="text-lg font-semibold text-white mb-4">Request Payout</h2>
        @if(($summary['pending_earnings'] ?? 0) >= config('u9itus.batch_payout_min', 10))
            @if(session('success'))
                <div class="bg-emerald-900/40 border border-emerald-500/30 text-emerald-300 px-4 py-3 rounded-lg mb-4 text-sm">
                    {{ session('success') }}
                </div>
            @endif
            <form action="{{ route('voter.earnings.payout') }}" method="POST">
                @csrf
                <p class="text-slate-300 mb-4">
                    You have <strong class="text-emerald-400">${{ number_format($summary['pending_earnings'], 2) }}</strong> available for payout.
                    Payouts are processed within 48 hours.
                </p>
                <button type="submit"
                    class="bg-emerald-600 hover:bg-emerald-500 text-white font-semibold px-6 py-2.5 rounded-lg transition">
                    Request Payout
                </button>
            </form>
        @else
            <p class="text-slate-400">
                Minimum payout is <strong class="text-white">${{ config('u9itus.batch_payout_min', 10) }}</strong>.
                You currently have <strong class="text-amber-400">${{ number_format($summary['pending_earnings'] ?? 0, 2) }}</strong> pending.
                Keep watching to reach the threshold!
            </p>
        @endif
    </div>

    {{-- Recent completed views --}}
    @if(isset($sessions) && $sessions->count() > 0)
    <div>
        <h2 class="text-lg font-semibold text-white mb-4">Recent Completed Views</h2>
        <div class="bg-slate-800/50 border border-slate-700 rounded-xl overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-700 text-slate-400 text-left">
                        <th class="px-4 py-3 font-medium">Campaign</th>
                        <th class="px-4 py-3 font-medium">Earned</th>
                        <th class="px-4 py-3 font-medium text-right">Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700/50">
                    @foreach($sessions as $session)
                    <tr class="hover:bg-slate-700/20 transition">
                        <td class="px-4 py-3 text-white">{{ $session->campaign->title ?? '—' }}</td>
                        <td class="px-4 py-3 font-medium text-emerald-400">
                            ${{ number_format($session->voter_payout_amount ?? 0, 2) }}
                        </td>
                        <td class="px-4 py-3 text-slate-400 text-xs text-right">
                            {{ $session->completed_at?->format('M j, Y') }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if(method_exists($sessions, 'links'))
        <div class="mt-3">{{ $sessions->links() }}</div>
        @endif
    </div>
    @endif

    <div class="text-right">
        <a href="{{ route('voter.earnings.history') }}" class="text-emerald-400 hover:text-emerald-300 text-sm">View full history →</a>
    </div>

    @else
    <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-10 text-center">
        <p class="text-slate-400">No voter profile found.</p>
    </div>
    @endif

</div>
@endsection
