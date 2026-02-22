@extends('standalone.layouts.dashboard')

@section('title', 'Analytics')
@section('page-title', 'Platform Analytics')

@section('content')
<div class="space-y-6">

    {{-- KPIs --}}
    <div class="grid grid-cols-2 lg:grid-cols-3 gap-4">
        <div class="stat-card">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">Total Views</p>
            <p class="text-3xl font-bold text-white">{{ number_format($stats['total_views']) }}</p>
            <p class="text-xs text-slate-500 mt-1">completed sessions</p>
        </div>
        <div class="stat-card">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">Gross Revenue</p>
            <p class="text-3xl font-bold text-white">${{ number_format($stats['total_revenue'], 2) }}</p>
            <p class="text-xs text-slate-500 mt-1">politician charges</p>
        </div>
        <div class="stat-card">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">Voter Payouts</p>
            <p class="text-3xl font-bold text-emerald-400">${{ number_format($stats['total_payouts'], 2) }}</p>
        </div>
        <div class="stat-card">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">Net Profit</p>
            <p class="text-3xl font-bold {{ $stats['total_profit'] >= 0 ? 'text-emerald-400' : 'text-red-400' }}">${{ number_format($stats['total_profit'], 2) }}</p>
        </div>
        <div class="stat-card">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">Total Campaigns</p>
            <p class="text-3xl font-bold text-white">{{ number_format($stats['total_campaigns']) }}</p>
            <p class="text-xs text-slate-500 mt-1">{{ $stats['active_campaigns'] }} active</p>
        </div>
        <div class="stat-card">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">Margin</p>
            @php
                $margin = $stats['total_revenue'] > 0
                    ? round($stats['total_profit'] / $stats['total_revenue'] * 100, 1)
                    : 0;
            @endphp
            <p class="text-3xl font-bold text-white">{{ $margin }}%</p>
            <p class="text-xs text-slate-500 mt-1">platform take</p>
        </div>
    </div>

    {{-- Report links --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <a href="{{ route('admin.reports.revenue') }}" class="bg-slate-800/50 border border-slate-700/50 hover:border-emerald-500/30 rounded-xl p-5 transition group">
            <p class="text-sm font-semibold text-white group-hover:text-emerald-400 transition">Revenue Report →</p>
            <p class="text-xs text-slate-500 mt-1">Detailed breakdown of charges, payouts, and profit</p>
        </a>
        <a href="{{ route('admin.reports.engagement') }}" class="bg-slate-800/50 border border-slate-700/50 hover:border-emerald-500/30 rounded-xl p-5 transition group">
            <p class="text-sm font-semibold text-white group-hover:text-emerald-400 transition">Engagement Report →</p>
            <p class="text-xs text-slate-500 mt-1">View sessions, completion rates, fraud rates</p>
        </a>
    </div>

</div>
@endsection
