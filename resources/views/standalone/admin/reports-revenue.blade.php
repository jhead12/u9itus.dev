@extends('standalone.layouts.dashboard')

@section('title', 'Revenue Report')
@section('page-title', 'Revenue Report')

@section('content')
<div class="space-y-6 max-w-3xl">

    <div>
        <a href="{{ route('admin.analytics') }}" class="text-sm text-slate-400 hover:text-white transition">← Back to analytics</a>
    </div>

    <div class="flex items-center justify-between gap-3">
        <span class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-semibold uppercase tracking-wide {{ ($activePaymentMode ?? null) === 'live' ? 'border-emerald-500/40 bg-emerald-500/10 text-emerald-300' : 'border-amber-500/40 bg-amber-500/10 text-amber-300' }}">
            {{ ($activePaymentMode ?? 'test') === 'live' ? 'Live Mode Revenue' : 'Test Mode Revenue' }}
        </span>
        <div class="flex items-center gap-2">
            <a href="{{ route('admin.analytics.ledger.campaign') }}" class="inline-flex items-center rounded-lg border border-slate-600 px-3 py-2 text-xs font-semibold text-slate-200 hover:border-emerald-400/50 hover:text-emerald-200 transition">
                Campaign Ledger
            </a>
            <a href="{{ route('admin.analytics.ledger.voter') }}" class="inline-flex items-center rounded-lg border border-slate-600 px-3 py-2 text-xs font-semibold text-slate-200 hover:border-emerald-400/50 hover:text-emerald-200 transition">
                Voter Ledger
            </a>
            <a href="{{ route('admin.analytics.export.campaign-accounting') }}" class="inline-flex items-center rounded-lg border border-slate-600 px-3 py-2 text-xs font-semibold text-slate-200 hover:border-sky-400/50 hover:text-sky-200 transition">
                ↓ Campaign CSV
            </a>
            <a href="{{ route('admin.analytics.export.voter-accounting') }}" class="inline-flex items-center rounded-lg border border-slate-600 px-3 py-2 text-xs font-semibold text-slate-200 hover:border-sky-400/50 hover:text-sky-200 transition">
                ↓ Voter CSV
            </a>
        </div>
    </div>

    <div class="grid grid-cols-3 gap-4">
        <div class="stat-card">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">Gross Revenue</p>
            <p class="text-3xl font-bold text-white">${{ number_format($revenue['total'], 2) }}</p>
            <p class="text-xs text-slate-500 mt-1">delivered per-view charges</p>
        </div>
        <div class="stat-card">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">Voter Payouts</p>
            <p class="text-3xl font-bold text-emerald-400">${{ number_format($revenue['payouts'], 2) }}</p>
        </div>
        <div class="stat-card">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">Platform Net</p>
            <p class="text-3xl font-bold {{ $revenue['profit'] >= 0 ? 'text-emerald-400' : 'text-red-400' }}">${{ number_format($revenue['profit'], 2) }}</p>
        </div>
    </div>

    <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-6">
        <h3 class="text-sm font-semibold text-white mb-4">Revenue Breakdown</h3>
        <dl class="space-y-4 text-sm">
            <div class="flex justify-between items-center py-2 border-b border-slate-700/30">
                <dt class="text-slate-400">Politician charges (gross)</dt>
                <dd class="font-semibold text-white">${{ number_format($revenue['total'], 2) }}</dd>
            </div>
            <div class="flex justify-between items-center py-2 border-b border-slate-700/30">
                <dt class="text-slate-400">Voter payouts</dt>
                <dd class="font-semibold text-red-400">-${{ number_format($revenue['payouts'], 2) }}</dd>
            </div>
            <div class="flex justify-between items-center py-2 border-b border-slate-700/30">
                <dt class="text-slate-400">Referral commissions</dt>
                <dd class="font-semibold text-amber-400">-${{ number_format($revenue['referrals'], 2) }}</dd>
            </div>
            <div class="flex justify-between items-center py-2">
                <dt class="text-slate-300 font-medium">Platform net revenue</dt>
                <dd class="font-bold text-lg {{ $revenue['profit'] >= 0 ? 'text-emerald-400' : 'text-red-400' }}">${{ number_format($revenue['profit'], 2) }}</dd>
            </div>
        </dl>
        <p class="mt-4 text-xs text-slate-500">Net margin: {{ number_format($revenue['margin_percent'], 1) }}%</p>
    </div>

</div>
@endsection
