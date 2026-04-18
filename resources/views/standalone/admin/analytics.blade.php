@extends('standalone.layouts.dashboard')

@section('title', 'Analytics')
@section('page-title', 'Platform Analytics')

@section('content')
<div class="space-y-6">

    <div class="flex items-center justify-end">
        <span class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-semibold uppercase tracking-wide {{ ($activePaymentMode ?? null) === 'live' ? 'border-emerald-500/40 bg-emerald-500/10 text-emerald-300' : 'border-amber-500/40 bg-amber-500/10 text-amber-300' }}">
            {{ ($activePaymentMode ?? 'test') === 'live' ? 'Live Mode Analytics' : 'Test Mode Analytics' }}
        </span>
    </div>

    {{-- KPIs --}}
    <div class="grid grid-cols-2 lg:grid-cols-3 gap-4">
        <div class="stat-card">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">Total Views</p>
            <p class="text-3xl font-bold text-white">{{ number_format($stats['total_views']) }}</p>
            <p class="text-xs text-slate-500 mt-1">completed sessions</p>
        </div>
        <div class="stat-card">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">Gross Revenue</p>
            <p class="text-3xl font-bold text-white">${{ number_format($stats['gross_revenue'], 2) }}</p>
            <p class="text-xs text-slate-500 mt-1">delivered per-view charges</p>
        </div>
        <div class="stat-card">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">Voter Payouts</p>
            <p class="text-3xl font-bold text-emerald-400">${{ number_format($stats['total_payouts'], 2) }}</p>
        </div>
        <div class="stat-card">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">Referral Commissions</p>
            <p class="text-3xl font-bold text-amber-400">${{ number_format($stats['total_referrals'], 2) }}</p>
            <p class="text-xs text-slate-500 mt-1">paid to referrers</p>
        </div>
        <div class="stat-card">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">Platform Net</p>
            <p class="text-3xl font-bold {{ $stats['net_revenue'] >= 0 ? 'text-emerald-400' : 'text-red-400' }}">${{ number_format($stats['net_revenue'], 2) }}</p>
            <p class="text-xs text-slate-500 mt-1">after payouts and referrals</p>
        </div>
        <div class="stat-card">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">Margin</p>
            <p class="text-3xl font-bold text-white">{{ number_format($stats['margin_percent'], 1) }}%</p>
            <p class="text-xs text-slate-500 mt-1">platform take</p>
        </div>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-3 gap-4">
        <div class="stat-card">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">Total Campaigns</p>
            <p class="text-3xl font-bold text-white">{{ number_format($stats['total_campaigns']) }}</p>
            <p class="text-xs text-slate-500 mt-1">{{ $stats['active_campaigns'] }} active</p>
        </div>
        <div class="stat-card">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">Avg Gross / View</p>
            <p class="text-3xl font-bold text-white">${{ number_format($stats['avg_revenue_per_view'], 2) }}</p>
        </div>
        <div class="stat-card">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">Avg Payout / View</p>
            <p class="text-3xl font-bold text-emerald-400">${{ number_format($stats['avg_payout_per_view'], 2) }}</p>
        </div>
        <div class="stat-card">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">Avg Referral / View</p>
            <p class="text-3xl font-bold text-amber-400">${{ number_format($stats['avg_referral_per_view'], 2) }}</p>
        </div>
        <div class="stat-card">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">Avg Net / View</p>
            <p class="text-3xl font-bold {{ $stats['avg_profit_per_view'] >= 0 ? 'text-emerald-400' : 'text-red-400' }}">${{ number_format($stats['avg_profit_per_view'], 2) }}</p>
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
            <p class="text-xs text-slate-500 mt-1">View sessions, survey response trends, and voter question moderation queue</p>
        </a>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div class="bg-slate-800/50 border border-slate-700/50 hover:border-sky-500/30 rounded-xl p-5 transition group flex items-start justify-between gap-3">
            <div>
                <p class="text-sm font-semibold text-white group-hover:text-sky-300 transition">Campaign Accounting</p>
                <p class="text-xs text-slate-500 mt-1">Transaction and session-level ledger with monthly rollups</p>
                <a href="{{ route('admin.analytics.ledger.campaign') }}" class="inline-block mt-3 text-xs font-semibold text-sky-400 hover:text-sky-300 transition">View Ledger →</a>
            </div>
            <a href="{{ route('admin.analytics.export.campaign-accounting') }}" title="Download CSV" class="shrink-0 rounded-lg border border-slate-600 hover:border-sky-400/50 text-slate-400 hover:text-sky-300 px-2.5 py-1.5 text-xs transition">
                ↓ CSV
            </a>
        </div>
        <div class="bg-slate-800/50 border border-slate-700/50 hover:border-sky-500/30 rounded-xl p-5 transition group flex items-start justify-between gap-3">
            <div>
                <p class="text-sm font-semibold text-white group-hover:text-sky-300 transition">Voter Accounting</p>
                <p class="text-xs text-slate-500 mt-1">Session payouts and referral earnings with monthly voter rollups</p>
                <a href="{{ route('admin.analytics.ledger.voter') }}" class="inline-block mt-3 text-xs font-semibold text-sky-400 hover:text-sky-300 transition">View Ledger →</a>
            </div>
            <a href="{{ route('admin.analytics.export.voter-accounting') }}" title="Download CSV" class="shrink-0 rounded-lg border border-slate-600 hover:border-sky-400/50 text-slate-400 hover:text-sky-300 px-2.5 py-1.5 text-xs transition">
                ↓ CSV
            </a>
        </div>
    </div>

    @php
        $handoff = $stats['onboarding_handoff'] ?? [
            'window_days' => 30,
            'total_opened' => 0,
            'total_dismissed' => 0,
            'voter' => ['opened' => 0, 'dismissed' => 0, 'unique_openers' => 0, 'unique_dismissers' => 0, 'dismiss_rate_pct' => 0],
            'politician' => ['opened' => 0, 'dismissed' => 0, 'unique_openers' => 0, 'unique_dismissers' => 0, 'dismiss_rate_pct' => 0],
        ];
    @endphp

    <section class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-5 space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
            <div>
                <h3 class="text-sm font-semibold text-white">Onboarding Handoff Widget Performance</h3>
                <p class="text-xs text-slate-500 mt-1">Interaction events from the floating Start Here helper in the last {{ $handoff['window_days'] }} days.</p>
            </div>
            <div class="flex items-center gap-2 text-xs">
                <span class="inline-flex items-center rounded-full border border-slate-600 px-2.5 py-1 text-slate-300">Opened: {{ number_format($handoff['total_opened']) }}</span>
                <span class="inline-flex items-center rounded-full border border-slate-600 px-2.5 py-1 text-slate-300">Dismissed: {{ number_format($handoff['total_dismissed']) }}</span>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="rounded-xl border border-slate-700/70 bg-slate-900/40 p-4">
                <div class="flex items-center justify-between gap-2 mb-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-emerald-300">Voter Handoff</p>
                    <span class="text-xs text-slate-400">Dismiss Rate: {{ number_format($handoff['voter']['dismiss_rate_pct'], 1) }}%</span>
                </div>
                <div class="grid grid-cols-2 gap-3 text-xs">
                    <div class="rounded-lg border border-slate-700/70 bg-slate-800/40 px-3 py-2">
                        <p class="text-slate-500">Opens</p>
                        <p class="text-white text-base font-semibold">{{ number_format($handoff['voter']['opened']) }}</p>
                    </div>
                    <div class="rounded-lg border border-slate-700/70 bg-slate-800/40 px-3 py-2">
                        <p class="text-slate-500">Dismisses</p>
                        <p class="text-white text-base font-semibold">{{ number_format($handoff['voter']['dismissed']) }}</p>
                    </div>
                    <div class="rounded-lg border border-slate-700/70 bg-slate-800/40 px-3 py-2">
                        <p class="text-slate-500">Unique Openers</p>
                        <p class="text-white text-base font-semibold">{{ number_format($handoff['voter']['unique_openers']) }}</p>
                    </div>
                    <div class="rounded-lg border border-slate-700/70 bg-slate-800/40 px-3 py-2">
                        <p class="text-slate-500">Unique Dismissers</p>
                        <p class="text-white text-base font-semibold">{{ number_format($handoff['voter']['unique_dismissers']) }}</p>
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-slate-700/70 bg-slate-900/40 p-4">
                <div class="flex items-center justify-between gap-2 mb-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-sky-300">Politician Handoff</p>
                    <span class="text-xs text-slate-400">Dismiss Rate: {{ number_format($handoff['politician']['dismiss_rate_pct'], 1) }}%</span>
                </div>
                <div class="grid grid-cols-2 gap-3 text-xs">
                    <div class="rounded-lg border border-slate-700/70 bg-slate-800/40 px-3 py-2">
                        <p class="text-slate-500">Opens</p>
                        <p class="text-white text-base font-semibold">{{ number_format($handoff['politician']['opened']) }}</p>
                    </div>
                    <div class="rounded-lg border border-slate-700/70 bg-slate-800/40 px-3 py-2">
                        <p class="text-slate-500">Dismisses</p>
                        <p class="text-white text-base font-semibold">{{ number_format($handoff['politician']['dismissed']) }}</p>
                    </div>
                    <div class="rounded-lg border border-slate-700/70 bg-slate-800/40 px-3 py-2">
                        <p class="text-slate-500">Unique Openers</p>
                        <p class="text-white text-base font-semibold">{{ number_format($handoff['politician']['unique_openers']) }}</p>
                    </div>
                    <div class="rounded-lg border border-slate-700/70 bg-slate-800/40 px-3 py-2">
                        <p class="text-slate-500">Unique Dismissers</p>
                        <p class="text-white text-base font-semibold">{{ number_format($handoff['politician']['unique_dismissers']) }}</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

</div>
@endsection
