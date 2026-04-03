@extends('standalone.layouts.dashboard')

@section('title', 'Voter Accounting Ledger')
@section('page-title', 'Voter Accounting Ledger')

@section('content')
<div class="space-y-6">

    {{-- Header bar --}}
    <div class="flex items-center justify-between gap-3">
        <a href="{{ route('admin.analytics') }}" class="text-sm text-slate-400 hover:text-white transition">← Back to analytics</a>
        <span class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-semibold uppercase tracking-wide {{ ($activePaymentMode ?? null) === 'live' ? 'border-emerald-500/40 bg-emerald-500/10 text-emerald-300' : 'border-amber-500/40 bg-amber-500/10 text-amber-300' }}">
            {{ ($activePaymentMode ?? 'test') === 'live' ? 'Live Mode' : 'Test Mode' }}
        </span>
    </div>

    {{-- Filter form --}}
    <form method="GET" action="{{ route('admin.analytics.ledger.voter') }}" class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-4">
        <input type="hidden" name="tab" value="{{ $tab }}">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <div>
                <label class="block text-xs text-slate-400 mb-1">From</label>
                <input type="date" name="from" value="{{ $from ?? '' }}"
                    class="w-full rounded-lg bg-slate-900 border border-slate-700 text-sm text-white px-3 py-2 focus:outline-none focus:ring-1 focus:ring-emerald-500">
            </div>
            <div>
                <label class="block text-xs text-slate-400 mb-1">To</label>
                <input type="date" name="to" value="{{ $to ?? '' }}"
                    class="w-full rounded-lg bg-slate-900 border border-slate-700 text-sm text-white px-3 py-2 focus:outline-none focus:ring-1 focus:ring-emerald-500">
            </div>
            <div>
                <label class="block text-xs text-slate-400 mb-1">Voter name / email</label>
                <input type="text" name="voter_search" value="{{ $voterSearch ?? '' }}" placeholder="Search voter…"
                    class="w-full rounded-lg bg-slate-900 border border-slate-700 text-sm text-white px-3 py-2 focus:outline-none focus:ring-1 focus:ring-emerald-500">
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="flex-1 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-sm font-semibold px-4 py-2 transition">
                    Filter
                </button>
                @if($from || $to || $voterSearch)
                    <a href="{{ route('admin.analytics.ledger.voter', ['tab' => $tab]) }}" class="rounded-lg border border-slate-600 text-slate-300 hover:text-white text-sm px-3 py-2 transition">
                        Clear
                    </a>
                @endif
            </div>
        </div>
    </form>

    {{-- KPI summary --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="stat-card">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">Sessions</p>
            <p class="text-2xl font-bold text-white">{{ number_format($totals->total_sessions ?? 0) }}</p>
        </div>
        <div class="stat-card">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">Total Voter Payouts</p>
            <p class="text-2xl font-bold text-emerald-400">${{ number_format($totals->total_payouts ?? 0, 2) }}</p>
        </div>
        <div class="stat-card">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">Total Referral Commissions</p>
            <p class="text-2xl font-bold text-sky-400">${{ number_format($totals->total_referrals ?? 0, 2) }}</p>
        </div>
    </div>

    {{-- Tab bar + export button --}}
    <div class="flex items-center justify-between gap-3">
        <div class="flex rounded-lg border border-slate-700 overflow-hidden text-sm">
            <a href="{{ route('admin.analytics.ledger.voter', array_merge(request()->query(), ['tab' => 'sessions'])) }}"
               class="px-4 py-2 font-semibold transition {{ $tab === 'sessions' ? 'bg-emerald-600 text-white' : 'text-slate-400 hover:text-white hover:bg-slate-700/50' }}">
                Sessions <span class="ml-1 text-xs opacity-70">({{ number_format($sessions->total()) }})</span>
            </a>
            <a href="{{ route('admin.analytics.ledger.voter', array_merge(request()->query(), ['tab' => 'referrals'])) }}"
               class="px-4 py-2 font-semibold transition {{ $tab === 'referrals' ? 'bg-emerald-600 text-white' : 'text-slate-400 hover:text-white hover:bg-slate-700/50' }}">
                Referrals <span class="ml-1 text-xs opacity-70">({{ number_format($referrals->total()) }})</span>
            </a>
        </div>

        <a href="{{ route('admin.analytics.export.voter-accounting', array_filter(['from' => $from, 'to' => $to, 'voter_search' => $voterSearch])) }}"
           class="inline-flex items-center rounded-lg border border-sky-500/40 bg-sky-500/10 text-sky-300 hover:bg-sky-500/20 px-4 py-2 text-sm font-semibold transition">
            ↓ Export CSV
        </a>
    </div>

    {{-- Sessions tab --}}
    @if ($tab === 'sessions')
        <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-700/50 text-left">
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-400">Date</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-400">Voter</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-400">Campaign</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-400">Status</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-400">Payment</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-400">Payout To</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-400 text-right">Payout</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-400 text-right">Referral</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700/30">
                    @forelse ($sessions as $session)
                        @php
                            $voter = $session->voter;
                            $status = (string) ($session->getRawOriginal('status') ?? '');
                            $payStatus = (string) ($session->getRawOriginal('payment_status') ?? '');
                            $payDest = $voter?->payment_method === 'cashapp'
                                ? ($voter?->cashapp_tag ? '$' . $voter->cashapp_tag : '—')
                                : ($voter?->paypal_email ?? '—');
                        @endphp
                        <tr class="hover:bg-slate-700/20 transition">
                            <td class="px-4 py-3 text-slate-300 whitespace-nowrap">
                                {{ optional($session->created_at)->format('M j, Y') }}<br>
                                <span class="text-xs text-slate-500">{{ optional($session->created_at)->format('g:i a') }}</span>
                            </td>
                            <td class="px-4 py-3 text-white">
                                {{ $voter?->full_name ?? '—' }}<br>
                                <span class="text-xs text-slate-500">{{ $voter?->email ?? '' }}</span>
                            </td>
                            <td class="px-4 py-3 text-slate-300 max-w-[160px]">
                                <span class="truncate block">{{ optional($session->campaign)->title ?? '—' }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium
                                    {{ $status === 'completed' ? 'bg-emerald-500/15 text-emerald-300' : 'bg-slate-700 text-slate-400' }}">
                                    {{ $status ?: '—' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium
                                    {{ $payStatus === 'paid' ? 'bg-sky-500/15 text-sky-300' : 'bg-slate-700 text-slate-400' }}">
                                    {{ $payStatus ?: 'unpaid' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-slate-400 text-xs font-mono">
                                {{ $payDest }}
                                @if($voter?->payment_method)
                                    <span class="ml-1 text-slate-600">({{ $voter->payment_method }})</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right font-mono tabular-nums text-emerald-400">
                                ${{ number_format((float)($session->voter_payout_amount ?? 0), 2) }}
                            </td>
                            <td class="px-4 py-3 text-right font-mono tabular-nums text-sky-400">
                                ${{ number_format((float)($session->referral_commission ?? 0), 2) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-10 text-center text-slate-500">No sessions found for the selected filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($sessions->hasPages())
            <div class="flex justify-center">
                {{ $sessions->links() }}
            </div>
        @endif
    @endif

    {{-- Referrals tab --}}
    @if ($tab === 'referrals')
        <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-700/50 text-left">
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-400">Date</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-400">Referrer</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-400">Campaign</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-400">Type</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-400">Paid</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-400">Paid At</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-400 text-right">Commission</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700/30">
                    @forelse ($referrals as $earning)
                        @php
                            $referrer = $earning->referrer;
                            $refSession = $earning->viewSession;
                            $payDest = $referrer?->payment_method === 'cashapp'
                                ? ($referrer?->cashapp_tag ? '$' . $referrer->cashapp_tag : '—')
                                : ($referrer?->paypal_email ?? '—');
                        @endphp
                        <tr class="hover:bg-slate-700/20 transition">
                            <td class="px-4 py-3 text-slate-300 whitespace-nowrap">
                                {{ optional($earning->created_at)->format('M j, Y') }}<br>
                                <span class="text-xs text-slate-500">{{ optional($earning->created_at)->format('g:i a') }}</span>
                            </td>
                            <td class="px-4 py-3 text-white">
                                {{ $referrer?->full_name ?? '—' }}<br>
                                <span class="text-xs text-slate-500">{{ $referrer?->email ?? '' }}</span>
                            </td>
                            <td class="px-4 py-3 text-slate-300 max-w-[160px]">
                                <span class="truncate block">{{ optional($refSession?->campaign)->title ?? '—' }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-purple-500/15 text-purple-300">
                                    {{ $earning->referral_type ?? '—' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium
                                    {{ $earning->paid ? 'bg-emerald-500/15 text-emerald-300' : 'bg-slate-700 text-slate-400' }}">
                                    {{ $earning->paid ? 'Paid' : 'Pending' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-slate-400 text-xs">
                                {{ optional($earning->paid_at)->format('M j, Y') ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-right font-mono tabular-nums text-sky-400">
                                ${{ number_format((float)($earning->commission_amount ?? 0), 2) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-10 text-center text-slate-500">No referral earnings found for the selected filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($referrals->hasPages())
            <div class="flex justify-center">
                {{ $referrals->links() }}
            </div>
        @endif
    @endif

</div>
@endsection
