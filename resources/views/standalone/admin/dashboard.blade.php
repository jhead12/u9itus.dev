@extends('standalone.layouts.dashboard')

@section('title', 'Admin Dashboard')
@section('page-title', 'Admin Dashboard')

@section('content')
<div class="space-y-6">

    {{-- Welcome banner --}}
    <div class="bg-gradient-to-r from-emerald-500/10 to-slate-800/50 border border-emerald-500/20 rounded-xl p-6">
        <h2 class="text-lg font-semibold text-white">Welcome, {{ auth()->user()->name }} 👋</h2>
        <p class="text-slate-400 text-sm mt-0.5">Platform overview — all metrics are real-time.</p>
    </div>

    {{-- Stats grid --}}
    <div class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
        <div class="stat-card">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">Total Users</p>
            <p class="text-3xl font-bold text-white">{{ number_format($stats['total_users']) }}</p>
            <p class="text-xs text-slate-500 mt-1">{{ $stats['total_politicians'] }} politicians · {{ $stats['total_voters'] }} voters</p>
        </div>
        <div class="stat-card">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">Pending Approval</p>
            <p class="text-3xl font-bold {{ $stats['pending_campaigns'] > 0 ? 'text-amber-400' : 'text-white' }}">{{ number_format($stats['pending_campaigns']) }}</p>
            <p class="text-xs text-slate-500 mt-1">of {{ $stats['total_campaigns'] }} total campaigns</p>
        </div>
        <div class="stat-card">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">Total Views</p>
            <p class="text-3xl font-bold text-white">{{ number_format($stats['total_views']) }}</p>
            <p class="text-xs text-slate-500 mt-1">{{ $stats['active_campaigns'] }} active campaigns</p>
        </div>
        <div class="stat-card">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">Platform Revenue</p>
            <p class="text-3xl font-bold text-emerald-400">${{ number_format($stats['total_revenue'], 2) }}</p>
            <p class="text-xs text-slate-500 mt-1">${{ number_format($stats['total_payouts'], 2) }} paid out</p>
        </div>
        <div class="stat-card">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">Politician KYC Pending</p>
            <a href="{{ route('admin.kyc.index') }}" class="block">
                <p class="text-3xl font-bold {{ $stats['kyc_pending'] > 0 ? 'text-yellow-400' : 'text-white' }}">{{ number_format($stats['kyc_pending']) }}</p>
            </a>
            <p class="text-xs text-slate-500 mt-1">Politicians awaiting identity document review</p>
        </div>
        <div class="stat-card">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">Fraud Flagged</p>
            <a href="{{ route('admin.fraud.index') }}" class="block">
                <p class="text-3xl font-bold {{ $stats['flagged_fraud'] > 0 ? 'text-red-400' : 'text-white' }}">{{ number_format($stats['flagged_fraud']) }}</p>
            </a>
            <p class="text-xs text-slate-500 mt-1">voters flagged</p>
        </div>
        <div class="stat-card">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">Suspended</p>
            <p class="text-3xl font-bold {{ $stats['suspended_users'] > 0 ? 'text-orange-400' : 'text-white' }}">{{ number_format($stats['suspended_users']) }}</p>
            <p class="text-xs text-slate-500 mt-1">suspended accounts</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        {{-- Recent users --}}
        <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-700/50 flex items-center justify-between">
                <h3 class="text-sm font-semibold text-white">Recent Registrations</h3>
                <a href="{{ route('admin.users.index') }}" class="text-xs text-emerald-400 hover:text-emerald-300 transition">View all →</a>
            </div>
            <div class="divide-y divide-slate-700/30">
                @forelse($recentUsers as $u)
                <div class="px-5 py-3 flex items-center gap-3">
                    <div class="w-8 h-8 rounded-full bg-slate-700 flex items-center justify-center text-xs font-semibold text-slate-300 shrink-0">
                        {{ strtoupper(substr($u->name, 0, 1)) }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-white truncate">{{ $u->name }}</p>
                        <p class="text-xs text-slate-500 truncate">{{ $u->email }}</p>
                    </div>
                    <span class="text-xs px-2 py-0.5 rounded-full {{ $u->user_type === 'politician' ? 'bg-blue-500/10 text-blue-400' : ($u->user_type === 'admin' ? 'bg-red-500/10 text-red-400' : 'bg-emerald-500/10 text-emerald-400') }}">
                        {{ $u->user_type }}
                    </span>
                </div>
                @empty
                <p class="px-5 py-4 text-sm text-slate-500">No users yet.</p>
                @endforelse
            </div>
        </div>

        {{-- Recent campaigns --}}
        <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-700/50 flex items-center justify-between">
                <h3 class="text-sm font-semibold text-white">Recent Campaigns</h3>
                <a href="{{ route('admin.campaigns.pending') }}" class="text-xs text-emerald-400 hover:text-emerald-300 transition">Review →</a>
            </div>
            <div class="divide-y divide-slate-700/30">
                @forelse($recentCampaigns as $campaign)
                <div class="px-5 py-3 flex items-center gap-3">
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-white truncate">{{ $campaign->title }}</p>
                        <p class="text-xs text-slate-500">{{ $campaign->politician?->full_name ?? '—' }}</p>
                    </div>
                    @php
                        $as = $campaign->approval_status instanceof \App\Enums\ApprovalStatus
                            ? $campaign->approval_status->value
                            : $campaign->approval_status;
                    @endphp
                    <span class="text-xs px-2 py-0.5 rounded-full {{ $as === 'approved' ? 'bg-emerald-500/10 text-emerald-400' : ($as === 'rejected' ? 'bg-red-500/10 text-red-400' : 'bg-amber-500/10 text-amber-400') }}">
                        {{ $as }}
                    </span>
                </div>
                @empty
                <p class="px-5 py-4 text-sm text-slate-500">No campaigns yet.</p>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Quick actions --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <a href="{{ route('admin.campaigns.pending') }}" class="bg-slate-800/50 border border-slate-700/50 hover:border-emerald-500/40 rounded-xl p-4 text-center transition group">
            <div class="text-amber-400 mb-2 flex justify-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
            </div>
            <p class="text-xs font-medium text-slate-300 group-hover:text-white transition">Review Campaigns</p>
        </a>
        <a href="{{ route('admin.kyc.index') }}" class="bg-slate-800/50 border border-slate-700/50 hover:border-yellow-500/40 rounded-xl p-4 text-center transition group relative">
            @if($stats['kyc_pending'] > 0)
            <span class="absolute top-2 right-2 w-5 h-5 rounded-full bg-yellow-500 text-black text-xs font-bold flex items-center justify-center">{{ $stats['kyc_pending'] }}</span>
            @endif
            <div class="text-yellow-400 mb-2 flex justify-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"/></svg>
            </div>
            <p class="text-xs font-medium text-slate-300 group-hover:text-white transition">KYC Review</p>
            <p class="text-xs text-slate-600 group-hover:text-slate-400 transition mt-0.5">Politicians only</p>
        </a>
        <a href="{{ route('admin.fraud.index') }}" class="bg-slate-800/50 border border-slate-700/50 hover:border-red-500/40 rounded-xl p-4 text-center transition group">
            <div class="text-red-400 mb-2 flex justify-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            </div>
            <p class="text-xs font-medium text-slate-300 group-hover:text-white transition">Fraud Review</p>
        </a>
        <a href="{{ route('admin.users.index') }}" class="bg-slate-800/50 border border-slate-700/50 hover:border-blue-500/40 rounded-xl p-4 text-center transition group">
            <div class="text-blue-400 mb-2 flex justify-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
            </div>
            <p class="text-xs font-medium text-slate-300 group-hover:text-white transition">Manage Users</p>
        </a>
        <a href="{{ route('admin.candidate-matches.index') }}" class="bg-slate-800/50 border border-slate-700/50 hover:border-indigo-500/40 rounded-xl p-4 text-center transition group relative">
            @if(($stats['pending_candidate_matches'] ?? 0) > 0)
            <span class="absolute top-2 right-2 w-5 h-5 rounded-full bg-indigo-500 text-white text-xs font-bold flex items-center justify-center">{{ $stats['pending_candidate_matches'] }}</span>
            @endif
            <div class="text-indigo-400 mb-2 flex justify-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9h8M8 13h6m5 8H5a2 2 0 01-2-2V5a2 2 0 012-2h6.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            </div>
            <p class="text-xs font-medium text-slate-300 group-hover:text-white transition">Candidate Matches</p>
        </a>
        <a href="{{ route('admin.payouts.index') }}" class="bg-slate-800/50 border border-slate-700/50 hover:border-emerald-500/40 rounded-xl p-4 text-center transition group">
            <div class="text-emerald-400 mb-2 flex justify-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
            </div>
            <p class="text-xs font-medium text-slate-300 group-hover:text-white transition">Payouts</p>
        </a>
        <a href="{{ route('admin.billing.refunds') }}" class="bg-slate-800/50 border border-slate-700/50 hover:border-amber-500/40 rounded-xl p-4 text-center transition group">
            <div class="text-amber-400 mb-2 flex justify-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 15a3 3 0 11-6 0 3 3 0 016 0zM21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0h4m-2-2v4"/></svg>
            </div>
            <p class="text-xs font-medium text-slate-300 group-hover:text-white transition">Billing Refunds</p>
        </a>
        <a href="{{ route('admin.imports') }}" class="bg-slate-800/50 border border-slate-700/50 hover:border-cyan-500/40 rounded-xl p-4 text-center transition group">
            <div class="text-cyan-400 mb-2 flex justify-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4v.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <p class="text-xs font-medium text-slate-300 group-hover:text-white transition">Data Imports</p>
        </a>
    </div>

</div>
@endsection
