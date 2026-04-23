@extends('layouts.voter')

@section('title', 'My Earnings')

@section('content')
<div class="px-4 sm:px-6 lg:px-8 py-8 max-w-5xl mx-auto space-y-7">

    @php
        $minPayout = (float) \App\Services\PlatformSettingsService::get('min_payout_amount', null, 5.00);
    @endphp

    {{-- Page Header --}}
    <div>
        <h1 class="text-2xl font-bold text-white">My Earnings</h1>
        <p class="text-slate-400 text-sm mt-0.5">Track your ad-view income and request payouts</p>
    </div>

    @include('standalone.voter.partials.authentic-user-verifier-banner')

    @if($voter)

    {{-- Stats Grid --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        @php
            $cards = [
                ['label' => 'Wallet Balance',   'value' => '$' . number_format($summary['wallet_balance'] ?? 0, 2),   'color' => 'emerald', 'sub' => 'Available to withdraw'],
                ['label' => 'Pending Earnings', 'value' => '$' . number_format($summary['pending_earnings'] ?? 0, 2), 'color' => 'amber',   'sub' => 'Awaiting approval'],
                ['label' => 'Total Earned',     'value' => '$' . number_format($summary['total_earned'] ?? 0, 2),     'color' => 'blue',    'sub' => 'All time'],
                ['label' => 'Views Today',      'value' => $summary['views_today'] ?? 0,                              'color' => 'purple',  'sub' => 'Completed today'],
            ];
        @endphp
        @foreach($cards as $card)
        <div class="bg-slate-800/50 border border-slate-700/60 rounded-2xl p-5 hover:border-{{ $card['color'] }}-500/40 transition">
            <p class="text-slate-400 text-xs font-medium uppercase tracking-wide">{{ $card['label'] }}</p>
            <p class="text-2xl font-bold text-{{ $card['color'] }}-400 mt-2">{{ $card['value'] }}</p>
            <p class="text-slate-500 text-xs mt-1">{{ $card['sub'] }}</p>
        </div>
        @endforeach
    </div>

    {{-- Referral Earnings row --}}
    @if(($summary['referral_earnings'] ?? 0) > 0)
    <div class="bg-purple-900/20 border border-purple-500/20 rounded-2xl p-5 flex items-center justify-between">
        <div class="flex items-center gap-4">
            <div class="w-10 h-10 rounded-xl bg-purple-500/20 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
            </div>
            <div>
                <p class="text-white font-semibold text-sm">Referral Earnings</p>
                <p class="text-slate-400 text-xs mt-0.5">From {{ $summary['referrals_count'] ?? 0 }} referred voter(s)</p>
            </div>
        </div>
        <p class="text-purple-400 font-bold text-xl">${{ number_format($summary['referral_earnings'], 2) }}</p>
    </div>
    @endif

    {{-- Payout Request Card --}}
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-2xl overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-700/60 flex items-center gap-3">
            <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
            </svg>
            <h2 class="text-base font-semibold text-white">Request Payout</h2>
        </div>
        <div class="px-6 py-5">
            @php
                $pending  = $summary['pending_earnings'] ?? 0;
                $approved = $summary['approved_earnings'] ?? 0;
            @endphp

            @if($approved > 0)
                {{-- Payout already in-flight — don't show the request button --}}
                <div class="flex items-start gap-4">
                    <div class="w-9 h-9 rounded-lg bg-amber-500/15 flex items-center justify-center shrink-0 mt-0.5">
                        <svg class="w-4 h-4 text-amber-400 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div class="flex-1">
                        <p class="text-amber-300 font-semibold text-sm">Payout in review</p>
                        <p class="text-slate-300 text-sm mt-1">
                            <strong class="text-amber-400">${{ number_format($approved, 2) }}</strong> has been queued and will be paid to your {{ $voter->payment_method ?? 'account' }} within 1–2 business days.
                        </p>
                        <p class="text-slate-500 text-xs mt-2">You will receive an email confirmation once your payment is sent.</p>
                    </div>
                </div>
            @elseif($pending >= $minPayout)
                <p class="text-slate-300 mb-4 text-sm">
                    You have <strong class="text-emerald-400 text-base">${{ number_format($pending, 2) }}</strong>
                    available. Payouts are processed within 48 hours.
                </p>
                @php $progress = min(($pending / $minPayout) * 100, 100); @endphp
                <div class="mb-5">
                    <div class="flex justify-between text-xs text-slate-500 mb-1.5">
                        <span>Payout threshold</span><span>${{ $minPayout }} minimum</span>
                    </div>
                    <div class="w-full bg-slate-700 rounded-full h-2">
                        <div class="h-2 rounded-full bg-emerald-500" style="width:{{ $progress }}%"></div>
                    </div>
                </div>
                <form action="{{ route('voter.earnings.payout') }}" method="POST" class="flex items-center gap-3 flex-wrap">
                    @csrf
                    <button type="submit"
                        class="bg-emerald-600 hover:bg-emerald-500 text-white font-semibold px-6 py-2.5 rounded-xl transition text-sm">
                        Request ${{ number_format($pending, 2) }} Payout
                    </button>
                    <a href="{{ route('voter.preferences') }}" class="text-slate-400 hover:text-slate-300 text-xs transition">
                        Change payout method →
                    </a>
                </form>
            @else
                <div class="flex items-start gap-4">
                    <div class="w-9 h-9 rounded-lg bg-amber-500/15 flex items-center justify-center shrink-0 mt-0.5">
                        <svg class="w-4 h-4 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div class="flex-1">
                        <p class="text-slate-300 text-sm">
                            Minimum payout is <strong class="text-white">${{ $minPayout }}</strong>.
                            You have <strong class="text-amber-400">${{ number_format($pending, 2) }}</strong> pending.
                        </p>
                        @php $remaining = max(0, $minPayout - $pending); $pct = $minPayout > 0 ? min(($pending / $minPayout) * 100, 100) : 0; @endphp
                        <p class="text-slate-500 text-xs mt-1">Earn <strong class="text-slate-300">${{ number_format($remaining, 2) }}</strong> more to unlock your first payout.</p>
                        <div class="mt-3 w-full bg-slate-700 rounded-full h-1.5">
                            <div class="h-1.5 rounded-full bg-amber-500" style="width:{{ $pct }}%"></div>
                        </div>
                        <div class="flex justify-between text-xs text-slate-600 mt-1">
                            <span>${{ number_format($pending, 2) }}</span><span>${{ $minPayout }}</span>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- Recent completed views --}}
    <div>
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-base font-semibold text-white">Recent Completed Views</h2>
            <a href="{{ route('voter.earnings.history') }}" class="text-emerald-400 hover:text-emerald-300 text-xs transition">
                View all →
            </a>
        </div>

        @if(isset($sessions) && $sessions->count() > 0)
        <div class="bg-slate-800/50 border border-slate-700/60 rounded-2xl overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-700/60 text-slate-500 text-left text-xs uppercase tracking-wide">
                        <th class="px-5 py-3 font-medium">Campaign</th>
                        <th class="px-5 py-3 font-medium hidden sm:table-cell">Politician</th>
                        <th class="px-5 py-3 font-medium text-right">Earned</th>
                        <th class="px-5 py-3 font-medium text-right hidden sm:table-cell">Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700/40">
                    @foreach($sessions as $session)
                    <tr class="hover:bg-slate-700/20 transition">
                        <td class="px-5 py-3.5 text-white">{{ $session->campaign->title ?? '—' }}</td>
                        <td class="px-5 py-3.5 text-slate-400 hidden sm:table-cell">{{ $session->campaign->politician->full_name ?? '—' }}</td>
                        <td class="px-5 py-3.5 text-right font-semibold text-emerald-400">${{ number_format($session->voter_payout_amount ?? 0, 2) }}</td>
                        <td class="px-5 py-3.5 text-slate-500 text-xs text-right hidden sm:table-cell">{{ $session->completed_at?->format('M j, Y') ?? '—' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if(method_exists($sessions, 'links'))
        <div class="mt-3">{{ $sessions->links() }}</div>
        @endif
        @else
        <div class="bg-slate-800/50 border border-slate-700/60 rounded-2xl p-10 text-center">
            <p class="text-slate-500 text-sm">No completed views yet. Watch an ad to start earning!</p>
        </div>
        @endif
    </div>

    @else
    <div class="bg-slate-800/50 border border-slate-700 rounded-2xl p-10 text-center">
        <p class="text-slate-400">No voter profile found. Contact support.</p>
    </div>
    @endif

</div>
@endsection
