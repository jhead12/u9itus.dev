@extends('standalone.layouts.dashboard')

@section('title', 'Engagement Report')
@section('page-title', 'Engagement Report')

@section('content')
<div class="space-y-6 max-w-3xl">

    <div>
        <a href="{{ route('admin.analytics') }}" class="text-sm text-slate-400 hover:text-white transition">← Back to analytics</a>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="stat-card">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">Total Sessions</p>
            <p class="text-3xl font-bold text-white">{{ number_format($engagement['total_sessions']) }}</p>
        </div>
        <div class="stat-card">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">Completed</p>
            <p class="text-3xl font-bold text-emerald-400">{{ number_format($engagement['completed_sessions']) }}</p>
        </div>
        <div class="stat-card">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">Flagged</p>
            <p class="text-3xl font-bold text-amber-400">{{ number_format($engagement['flagged_sessions']) }}</p>
        </div>
        <div class="stat-card">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">Avg Watch %</p>
            <p class="text-3xl font-bold text-white">{{ number_format($engagement['avg_watch_percent'], 1) }}%</p>
        </div>
    </div>

    <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-6">
        <h3 class="text-sm font-semibold text-white mb-4">Session Breakdown</h3>
        <dl class="space-y-3 text-sm">
            <div class="flex justify-between items-center py-2 border-b border-slate-700/30">
                <dt class="text-slate-400">Total sessions created</dt>
                <dd class="font-semibold text-white">{{ number_format($engagement['total_sessions']) }}</dd>
            </div>
            <div class="flex justify-between items-center py-2 border-b border-slate-700/30">
                <dt class="text-slate-400">Completed (paid)</dt>
                <dd class="font-semibold text-emerald-400">{{ number_format($engagement['completed_sessions']) }}</dd>
            </div>
            <div class="flex justify-between items-center py-2 border-b border-slate-700/30">
                <dt class="text-slate-400">Fraud-flagged</dt>
                <dd class="font-semibold text-amber-400">{{ number_format($engagement['flagged_sessions']) }}</dd>
            </div>
            <div class="flex justify-between items-center py-2 border-b border-slate-700/30">
                <dt class="text-slate-400">Completion rate</dt>
                @php
                    $cr = $engagement['total_sessions'] > 0
                        ? round($engagement['completed_sessions'] / $engagement['total_sessions'] * 100, 1)
                        : 0;
                @endphp
                <dd class="font-semibold text-white">{{ $cr }}%</dd>
            </div>
            <div class="flex justify-between items-center py-2">
                <dt class="text-slate-400">Avg watch percentage</dt>
                <dd class="font-semibold text-white">{{ number_format($engagement['avg_watch_percent'], 1) }}%</dd>
            </div>
        </dl>
    </div>

</div>
@endsection
