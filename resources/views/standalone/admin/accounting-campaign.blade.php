@extends('standalone.layouts.dashboard')

@section('title', 'Campaign Accounting Ledger')
@section('page-title', 'Campaign Accounting Ledger')

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
    <form method="GET" action="{{ route('admin.analytics.ledger.campaign') }}" class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-4">
        <input type="hidden" name="tab" value="{{ $tab }}">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
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
                <label class="block text-xs text-slate-400 mb-1">Campaign</label>
                <select name="campaign_id" class="w-full rounded-lg bg-slate-900 border border-slate-700 text-sm text-white px-3 py-2 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                    <option value="">All campaigns</option>
                    @foreach ($campaigns as $c)
                        <option value="{{ $c->id }}" {{ $campaignFilter == $c->id ? 'selected' : '' }}>{{ $c->title }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs text-slate-400 mb-1">Search</label>
                <input type="text" name="campaign_search" value="{{ $campaignSearch ?? '' }}" placeholder="Campaign, politician, voter, or reference"
                    class="w-full rounded-lg bg-slate-900 border border-slate-700 text-sm text-white px-3 py-2 focus:outline-none focus:ring-1 focus:ring-emerald-500">
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="flex-1 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-sm font-semibold px-4 py-2 transition">
                    Filter
                </button>
                @if($from || $to || $campaignFilter || ($campaignSearch ?? null))
                    <a href="{{ route('admin.analytics.ledger.campaign', ['tab' => $tab]) }}" class="rounded-lg border border-slate-600 text-slate-300 hover:text-white text-sm px-3 py-2 transition">
                        Clear
                    </a>
                @endif
            </div>
        </div>
    </form>

    {{-- KPI summary --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="stat-card">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">Sessions</p>
            <p class="text-2xl font-bold text-white">{{ number_format($totals->total_sessions ?? 0) }}</p>
        </div>
        <div class="stat-card">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">Platform Revenue</p>
            <p class="text-2xl font-bold text-white">${{ number_format($totals->total_revenue ?? 0, 2) }}</p>
        </div>
        <div class="stat-card">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">Voter Payouts</p>
            <p class="text-2xl font-bold text-emerald-400">${{ number_format($totals->total_payouts ?? 0, 2) }}</p>
        </div>
        <div class="stat-card">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">Referral Commissions</p>
            <p class="text-2xl font-bold text-sky-400">${{ number_format($totals->total_referrals ?? 0, 2) }}</p>
        </div>
    </div>

    {{-- Tab bar + export button --}}
    <div class="flex items-center justify-between gap-3">
        <div class="flex rounded-lg border border-slate-700 overflow-hidden text-sm">
            <a href="{{ route('admin.analytics.ledger.campaign', array_merge(request()->query(), ['tab' => 'sessions'])) }}"
               class="px-4 py-2 font-semibold transition {{ $tab === 'sessions' ? 'bg-emerald-600 text-white' : 'text-slate-400 hover:text-white hover:bg-slate-700/50' }}">
                Sessions <span class="ml-1 text-xs opacity-70">({{ number_format($sessions->total()) }})</span>
            </a>
            <a href="{{ route('admin.analytics.ledger.campaign', array_merge(request()->query(), ['tab' => 'transactions'])) }}"
               class="px-4 py-2 font-semibold transition {{ $tab === 'transactions' ? 'bg-emerald-600 text-white' : 'text-slate-400 hover:text-white hover:bg-slate-700/50' }}">
                Transactions <span class="ml-1 text-xs opacity-70">({{ number_format($transactions->total()) }})</span>
            </a>
        </div>

        <a href="{{ route('admin.analytics.export.campaign-accounting', array_filter(['from' => $from, 'to' => $to, 'campaign_id' => $campaignFilter, 'campaign_search' => $campaignSearch ?? null])) }}"
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
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-400">Campaign</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-400">Politician</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-400">Voter</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-400">Status</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-400 text-right">Platform Rev</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-400 text-right">Voter Payout</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-400 text-right">Net</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700/30">
                    @forelse ($sessions as $session)
                        @php
                            $status = (string) ($session->getRawOriginal('status') ?? '');
                            $payStatus = (string) ($session->getRawOriginal('payment_status') ?? '');
                            $platformRev = (float) ($session->platform_revenue ?? 0);
                            $voterPayout = (float) ($session->voter_payout_amount ?? 0);
                            $referral    = (float) ($session->referral_commission ?? 0);
                            $net = $platformRev - $voterPayout - $referral;
                        @endphp
                        <tr class="hover:bg-slate-700/20 transition">
                            <td class="px-4 py-3 text-slate-300 whitespace-nowrap">
                                {{ optional($session->created_at)->format('M j, Y') }}<br>
                                <span class="text-xs text-slate-500">{{ optional($session->created_at)->format('g:i a') }}</span>
                            </td>
                            <td class="px-4 py-3 text-white max-w-[180px]">
                                <span class="truncate block">{{ optional($session->campaign)->title ?? '—' }}</span>
                                <span class="text-xs text-slate-500">ID #{{ $session->political_campaign_id }}</span>
                            </td>
                            <td class="px-4 py-3 text-slate-300">
                                {{ optional(optional($session->campaign)->politician)->full_name ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-slate-300">
                                {{ optional($session->voter)->full_name ?? '—' }}<br>
                                <span class="text-xs text-slate-500">ID #{{ $session->voter_id }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium
                                    {{ $status === 'completed' ? 'bg-emerald-500/15 text-emerald-300' : ($status === 'skipped' ? 'bg-slate-700 text-slate-400' : 'bg-amber-500/15 text-amber-300') }}">
                                    {{ $status ?: '—' }}
                                </span>
                                @if($payStatus)
                                    <span class="mt-0.5 inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium
                                        {{ $payStatus === 'paid' ? 'bg-sky-500/15 text-sky-300' : 'bg-slate-700 text-slate-400' }}">
                                        {{ $payStatus }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right font-mono tabular-nums text-white">${{ number_format($platformRev, 2) }}</td>
                            <td class="px-4 py-3 text-right font-mono tabular-nums text-emerald-400">${{ number_format($voterPayout, 2) }}</td>
                            <td class="px-4 py-3 text-right font-mono tabular-nums {{ $net >= 0 ? 'text-white' : 'text-red-400' }}">${{ number_format($net, 2) }}</td>
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

    {{-- Transactions tab --}}
    @if ($tab === 'transactions')
        <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-700/50 text-left">
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-400">Date</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-400">Campaign</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-400">Politician</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-400">Type</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-400">Status</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-400">Stripe Ref</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-400 text-right">Amount</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700/30">
                    @forelse ($transactions as $txn)
                        @php
                            $txnType = (string) ($txn->transaction_type ?? '');
                            $txnStatus = (string) ($txn->status ?? '');
                        @endphp
                        <tr class="hover:bg-slate-700/20 transition">
                            <td class="px-4 py-3 text-slate-300 whitespace-nowrap">
                                {{ optional($txn->created_at)->format('M j, Y') }}<br>
                                <span class="text-xs text-slate-500">{{ optional($txn->created_at)->format('g:i a') }}</span>
                            </td>
                            <td class="px-4 py-3 text-white max-w-[180px]">
                                <span class="truncate block">{{ optional($txn->campaign)->title ?? '—' }}</span>
                                <span class="text-xs text-slate-500">ID #{{ $txn->campaign_id }}</span>
                            </td>
                            <td class="px-4 py-3 text-slate-300">
                                {{ optional($txn->politician)->full_name ?? '—' }}
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium
                                    {{ $txnType === 'refund' ? 'bg-red-500/15 text-red-300' : 'bg-sky-500/15 text-sky-300' }}">
                                    {{ $txnType ?: '—' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium
                                    {{ $txnStatus === 'succeeded' ? 'bg-emerald-500/15 text-emerald-300' : 'bg-amber-500/15 text-amber-300' }}">
                                    {{ $txnStatus ?: '—' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-slate-500 text-xs font-mono">
                                @if($txn->stripe_payment_intent_id)
                                    <span title="{{ $txn->stripe_payment_intent_id }}">{{ Str::limit($txn->stripe_payment_intent_id, 20) }}</span>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right font-mono tabular-nums {{ $txnType === 'refund' ? 'text-red-400' : 'text-white' }}">
                                {{ $txnType === 'refund' ? '-' : '' }}${{ number_format((float)($txn->amount ?? 0), 2) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-10 text-center text-slate-500">No transactions found for the selected filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($transactions->hasPages())
            <div class="flex justify-center">
                {{ $transactions->links() }}
            </div>
        @endif
    @endif

    <div class="bg-slate-800/40 border border-slate-700/50 rounded-xl overflow-hidden">
        <div class="px-4 py-3 border-b border-slate-700/50">
            <p class="text-sm font-semibold text-white">Account-Level Funding Events</p>
            <p class="text-xs text-slate-400 mt-1">These are funding transactions with no campaign ID. They are shown separately and are not treated as campaign payments.</p>
        </div>
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-slate-700/50 text-left">
                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-400">Date</th>
                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-400">Politician</th>
                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-400">Type</th>
                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-400">Status</th>
                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-400">Stripe Ref</th>
                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-400 text-right">Amount</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-700/30">
                @forelse ($accountFunding as $funding)
                    @php
                        $fundingType = (string) ($funding->transaction_type ?? '');
                        $fundingStatus = (string) ($funding->status ?? '');
                        $fundingRef = $funding->stripe_payment_intent_id ?: ($funding->stripe_charge_id ?: $funding->stripe_refund_id);
                    @endphp
                    <tr class="hover:bg-slate-700/20 transition">
                        <td class="px-4 py-3 text-slate-300 whitespace-nowrap">
                            {{ optional($funding->created_at)->format('M j, Y') }}<br>
                            <span class="text-xs text-slate-500">{{ optional($funding->created_at)->format('g:i a') }}</span>
                        </td>
                        <td class="px-4 py-3 text-slate-200">
                            {{ optional($funding->politician)->full_name ?? '—' }}<br>
                            <span class="text-xs text-slate-500">Account funding event</span>
                        </td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $fundingType === 'refund' ? 'bg-red-500/15 text-red-300' : 'bg-sky-500/15 text-sky-300' }}">
                                {{ $fundingType ?: '—' }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $fundingStatus === 'succeeded' ? 'bg-emerald-500/15 text-emerald-300' : 'bg-amber-500/15 text-amber-300' }}">
                                {{ $fundingStatus ?: '—' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-slate-500 text-xs font-mono">
                            @if($fundingRef)
                                <span title="{{ $fundingRef }}">{{ Str::limit($fundingRef, 24) }}</span>
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right font-mono tabular-nums {{ $fundingType === 'refund' ? 'text-red-400' : 'text-white' }}">
                            {{ $fundingType === 'refund' ? '-' : '' }}${{ number_format((float)($funding->amount ?? 0), 2) }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-slate-500">No account-level funding events found for the selected filters.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        @if ($accountFunding->hasPages())
            <div class="flex justify-center py-4">
                {{ $accountFunding->links() }}
            </div>
        @endif
    </div>

</div>
@endsection
