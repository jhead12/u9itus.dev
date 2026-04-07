@extends('standalone.layouts.dashboard')

@section('title', 'Payouts')
@section('page-title', 'Payout Management')

@section('content')
<div class="space-y-6">

    @if(session('success'))
    <div class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm rounded-lg px-4 py-3">
        {{ session('success') }}
    </div>
    @endif

    {{-- Runtime payout diagnostics (non-secret) --}}
    <div class="{{ $paypalConfigured ? 'bg-cyan-500/10 border-cyan-500/30 text-cyan-300' : 'bg-amber-500/10 border-amber-500/30 text-amber-300' }} border rounded-lg px-4 py-3 text-sm">
        <p class="font-semibold">
            PayPal Runtime Status:
            <span class="{{ $paypalConfigured ? 'text-cyan-200' : 'text-amber-200' }}">
                {{ $paypalConfigured ? 'Configured' : 'Not Configured' }}
            </span>
        </p>
        <p class="text-xs mt-1 opacity-90">
            Environment mode: {{ $paypalSandbox ? 'Sandbox' : 'Live' }}.
            Status is read from current runtime environment (e.g., Railway in production) and does not expose secrets.
        </p>
    </div>

    {{-- Stats --}}
    <div class="grid grid-cols-3 gap-4">
        <div class="stat-card">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">Pending Amount</p>
            <p class="text-3xl font-bold text-amber-400">${{ number_format($stats['pending_amount'], 2) }}</p>
            <p class="text-xs text-slate-500 mt-1">{{ number_format($stats['pending_count']) }} sessions</p>
        </div>
        <div class="stat-card">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">Total Paid Out</p>
            <p class="text-3xl font-bold text-emerald-400">${{ number_format($stats['paid_amount'], 2) }}</p>
        </div>
        <div class="stat-card">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">Payout Rate</p>
            @php
                $rate = ($stats['pending_amount'] + $stats['paid_amount']) > 0
                    ? round($stats['paid_amount'] / ($stats['pending_amount'] + $stats['paid_amount']) * 100, 1)
                    : 0;
            @endphp
            <p class="text-3xl font-bold text-white">{{ $rate }}%</p>
        </div>
    </div>

    {{-- Batch payout action --}}
    <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-6 flex flex-col sm:flex-row sm:items-center gap-4">
        <div class="flex-1">
            <h3 class="text-sm font-semibold text-white">Run Batch Payout</h3>
            <p class="text-xs text-slate-400 mt-1">Process all pending voter payouts in a single batch. This will mark all eligible sessions as paid.</p>
        </div>
        <form method="POST" action="{{ route('admin.payouts.batch') }}">
            @csrf
            <button type="submit"
                class="px-4 py-2 rounded-lg bg-emerald-500 hover:bg-emerald-400 text-white font-semibold text-sm transition">
                Run Batch Payout
            </button>
        </form>
    </div>

    {{-- Quick links --}}
    <div class="flex gap-3">
        <a href="{{ route('admin.payouts.pending') }}" class="text-sm text-emerald-400 hover:text-emerald-300 transition">
            View pending payout sessions →
        </a>
    </div>

</div>
@endsection
