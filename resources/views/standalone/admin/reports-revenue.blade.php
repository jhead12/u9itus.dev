@extends('standalone.layouts.dashboard')

@section('title', 'Revenue Report')
@section('page-title', 'Revenue Report')

@section('content')
<div class="space-y-6 max-w-3xl">

    <div>
        <a href="{{ route('admin.analytics') }}" class="text-sm text-slate-400 hover:text-white transition">← Back to analytics</a>
    </div>

    <div class="grid grid-cols-3 gap-4">
        <div class="stat-card">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">Gross Revenue</p>
            <p class="text-3xl font-bold text-white">${{ number_format($revenue['total'], 2) }}</p>
            <p class="text-xs text-slate-500 mt-1">total politician charges</p>
        </div>
        <div class="stat-card">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">Voter Payouts</p>
            <p class="text-3xl font-bold text-emerald-400">${{ number_format($revenue['payouts'], 2) }}</p>
        </div>
        <div class="stat-card">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">Net Profit</p>
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
            <div class="flex justify-between items-center py-2">
                <dt class="text-slate-300 font-medium">Platform net profit</dt>
                <dd class="font-bold text-lg {{ $revenue['profit'] >= 0 ? 'text-emerald-400' : 'text-red-400' }}">${{ number_format($revenue['profit'], 2) }}</dd>
            </div>
        </dl>
    </div>

</div>
@endsection
