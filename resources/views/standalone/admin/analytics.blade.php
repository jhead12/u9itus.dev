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
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">Gross Platform Revenue</p>
            <p class="text-3xl font-bold text-white">${{ number_format($stats['gross_revenue'], 2) }}</p>
            <p class="text-xs text-slate-500 mt-1">
                ${{ number_format($stats['political_revenue'] ?? 0, 2) }} political
                · ${{ number_format($stats['citizen_revenue'] ?? 0, 2) }} citizen
            </p>
            @if(($stats['eb_attributed_revenue'] ?? 0) > 0)
            <p class="text-xs text-emerald-600 mt-0.5">${{ number_format($stats['eb_attributed_revenue'], 2) }} EB-attributed</p>
            @endif
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

    {{-- ── Early-bank Enrollment ─────────────────────────────────────── --}}
    @php $eb = $stats['earlybank'] ?? ['enrolled' => 0, 'attributed' => 0, 'total_voters' => 0, 'enroll_rate_pct' => 0]; @endphp
    <section class="bg-emerald-950/30 border border-emerald-700/30 rounded-xl p-5 space-y-4">
        <h3 class="text-sm font-semibold text-emerald-200">Early-bank Enrollment</h3>
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="rounded-xl border border-slate-700/70 bg-slate-900/40 px-4 py-3">
                <p class="text-xs text-slate-500">EB Members (own UUID)</p>
                <p class="text-2xl font-bold text-emerald-400 mt-1">{{ number_format($eb['enrolled']) }}</p>
            </div>
            <div class="rounded-xl border border-slate-700/70 bg-slate-900/40 px-4 py-3">
                <p class="text-xs text-slate-500">EB-Attributed Voters</p>
                <p class="text-2xl font-bold text-emerald-300 mt-1">{{ number_format($eb['attributed']) }}</p>
            </div>
            <div class="rounded-xl border border-slate-700/70 bg-slate-900/40 px-4 py-3">
                <p class="text-xs text-slate-500">Total Voters</p>
                <p class="text-2xl font-bold text-white mt-1">{{ number_format($eb['total_voters']) }}</p>
            </div>
            <div class="rounded-xl border border-slate-700/70 bg-slate-900/40 px-4 py-3">
                <p class="text-xs text-slate-500">Enrollment Rate</p>
                <p class="text-2xl font-bold text-emerald-400 mt-1">{{ number_format($eb['enroll_rate_pct'], 1) }}%</p>
            </div>
        </div>
    </section>

    {{-- ── Citizen Campaigns ─────────────────────────────────────────── --}}
    @php $cc = $stats['citizen_campaigns'] ?? ['total' => 0, 'active' => 0, 'pending' => 0, 'revenue' => 0, 'views' => 0]; @endphp
    <section class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-5 space-y-4">
        <h3 class="text-sm font-semibold text-white">Citizen Campaigns</h3>
        <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
            <div class="rounded-xl border border-slate-700/70 bg-slate-900/40 px-4 py-3">
                <p class="text-xs text-slate-500">Total</p>
                <p class="text-2xl font-bold text-white mt-1">{{ number_format($cc['total']) }}</p>
            </div>
            <div class="rounded-xl border border-slate-700/70 bg-slate-900/40 px-4 py-3">
                <p class="text-xs text-slate-500">Active</p>
                <p class="text-2xl font-bold text-emerald-400 mt-1">{{ number_format($cc['active']) }}</p>
            </div>
            <div class="rounded-xl border border-slate-700/70 bg-slate-900/40 px-4 py-3">
                <p class="text-xs text-slate-500">Pending Approval</p>
                <p class="text-2xl font-bold {{ $cc['pending'] > 0 ? 'text-amber-400' : 'text-white' }} mt-1">{{ number_format($cc['pending']) }}</p>
            </div>
            <div class="rounded-xl border border-slate-700/70 bg-slate-900/40 px-4 py-3">
                <p class="text-xs text-slate-500">Revenue (incl. in Gross)</p>
                <p class="text-2xl font-bold text-emerald-400 mt-1">${{ number_format($cc['revenue'], 2) }}</p>
            </div>
            <div class="rounded-xl border border-slate-700/70 bg-slate-900/40 px-4 py-3">
                <p class="text-xs text-slate-500">Views Delivered</p>
                <p class="text-2xl font-bold text-white mt-1">{{ number_format($cc['views']) }}</p>
            </div>
        </div>
    </section>

    {{-- ── Referral Funnel ───────────────────────────────────────────── --}}
    @php $rf = $stats['referral_funnel'] ?? ['total_visits' => 0, 'unique_visitors' => 0, 'conversions' => 0, 'conversion_rate_pct' => 0]; @endphp
    <section class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-5 space-y-4">
        <h3 class="text-sm font-semibold text-white">Platform-wide Referral Funnel</h3>
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="rounded-xl border border-slate-700/70 bg-slate-900/40 px-4 py-3">
                <p class="text-xs text-slate-500">Total Link Visits</p>
                <p class="text-2xl font-bold text-white mt-1">{{ number_format($rf['total_visits']) }}</p>
            </div>
            <div class="rounded-xl border border-slate-700/70 bg-slate-900/40 px-4 py-3">
                <p class="text-xs text-slate-500">Unique Visitors</p>
                <p class="text-2xl font-bold text-white mt-1">{{ number_format($rf['unique_visitors']) }}</p>
            </div>
            <div class="rounded-xl border border-slate-700/70 bg-slate-900/40 px-4 py-3">
                <p class="text-xs text-slate-500">Conversions</p>
                <p class="text-2xl font-bold text-emerald-400 mt-1">{{ number_format($rf['conversions']) }}</p>
            </div>
            <div class="rounded-xl border border-slate-700/70 bg-slate-900/40 px-4 py-3">
                <p class="text-xs text-slate-500">Conversion Rate</p>
                <p class="text-2xl font-bold text-emerald-400 mt-1">{{ number_format($rf['conversion_rate_pct'], 1) }}%</p>
            </div>
        </div>
    </section>

    {{-- ── Payout Health ─────────────────────────────────────────────── --}}
    @php $ph = $stats['payout_health'] ?? ['unpaid_liability' => 0, 'total_attempts' => 0, 'failed_attempts' => 0, 'fail_rate_pct' => 0, 'by_method' => []]; @endphp
    <section class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-5 space-y-4">
        <h3 class="text-sm font-semibold text-white">Payout Health</h3>
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="rounded-xl border border-slate-700/70 bg-slate-900/40 px-4 py-3">
                <p class="text-xs text-slate-500">Unpaid Session Liability</p>
                <p class="text-2xl font-bold {{ $ph['unpaid_liability'] > 0 ? 'text-amber-400' : 'text-white' }} mt-1">${{ number_format($ph['unpaid_liability'], 2) }}</p>
                <p class="text-xs text-slate-500 mt-1">pending/approved sessions</p>
            </div>
            <div class="rounded-xl border border-slate-700/70 bg-slate-900/40 px-4 py-3">
                <p class="text-xs text-slate-500">Total Payout Attempts</p>
                <p class="text-2xl font-bold text-white mt-1">{{ number_format($ph['total_attempts']) }}</p>
            </div>
            <div class="rounded-xl border border-slate-700/70 bg-slate-900/40 px-4 py-3">
                <p class="text-xs text-slate-500">Failed Attempts</p>
                <p class="text-2xl font-bold {{ $ph['failed_attempts'] > 0 ? 'text-red-400' : 'text-white' }} mt-1">{{ number_format($ph['failed_attempts']) }}</p>
            </div>
            <div class="rounded-xl border border-slate-700/70 bg-slate-900/40 px-4 py-3">
                <p class="text-xs text-slate-500">Failure Rate</p>
                <p class="text-2xl font-bold {{ $ph['fail_rate_pct'] > 5 ? 'text-red-400' : 'text-white' }} mt-1">{{ number_format($ph['fail_rate_pct'], 1) }}%</p>
            </div>
        </div>
        @if(!empty($ph['by_method']) && count($ph['by_method']))
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
            @foreach($ph['by_method'] as $method => $mdata)
            <div class="rounded-lg border border-slate-700/70 bg-slate-900/40 px-3 py-2 text-xs">
                <p class="text-slate-400 uppercase font-semibold">{{ $method }}</p>
                <p class="text-white font-bold mt-1">{{ number_format($mdata['total']) }} attempts</p>
                <p class="text-slate-400">${{ number_format($mdata['amount'], 2) }}</p>
            </div>
            @endforeach
        </div>
        @endif
    </section>

    {{-- ── Voter Payment Method Breakdown ───────────────────────────── --}}
    @php $pmb = $stats['payment_method_breakdown'] ?? collect(); @endphp
    @if($pmb->isNotEmpty())
    <section class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-5 space-y-4">
        <h3 class="text-sm font-semibold text-white">Voter Payout Method Distribution</h3>
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            @foreach($pmb as $method => $count)
            <div class="rounded-xl border border-slate-700/70 bg-slate-900/40 px-4 py-3">
                <p class="text-xs text-slate-500 uppercase">{{ $method === 'not_set' ? 'Not Configured' : $method }}</p>
                <p class="text-2xl font-bold text-white mt-1">{{ number_format($count) }}</p>
            </div>
            @endforeach
        </div>
    </section>
    @endif

    {{-- ── Fraud Session Rate ────────────────────────────────────────── --}}
    <section class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-5">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h3 class="text-sm font-semibold text-white">Fraud Session Rate</h3>
                <p class="text-xs text-slate-500 mt-1">Completed sessions with fraud_score &gt; 50 (political campaigns only)</p>
            </div>
            <div class="text-right">
                <p class="text-3xl font-bold {{ ($stats['fraud_session_rate_pct'] ?? 0) > 5 ? 'text-red-400' : 'text-white' }}">{{ number_format($stats['fraud_session_rate_pct'] ?? 0, 1) }}%</p>
                <p class="text-xs text-slate-500 mt-1">{{ number_format($stats['fraud_session_count'] ?? 0) }} flagged sessions</p>
            </div>
        </div>
    </section>

    {{-- ── User Growth (12-week) ────────────────────────────────────── --}}
    @php $growth = $stats['user_growth'] ?? collect(); @endphp
    @if($growth->isNotEmpty())
    <section class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-5 space-y-3">
        <h3 class="text-sm font-semibold text-white">New Users — Last 12 Weeks</h3>
        <div class="flex items-end gap-1 h-20">
            @php $maxCount = $growth->max('count') ?: 1; @endphp
            @foreach($growth as $bucket)
            <div class="flex-1 flex flex-col items-center gap-1 group relative">
                <div class="w-full bg-emerald-500/70 rounded-sm transition-all"
                     style="height: {{ round(($bucket['count'] / $maxCount) * 64) }}px"
                     title="{{ $bucket['week'] }}: {{ $bucket['count'] }} users">
                </div>
                <span class="text-slate-500 text-[9px] rotate-45 origin-left whitespace-nowrap">{{ $bucket['week'] }}</span>
            </div>
            @endforeach
        </div>
    </section>
    @endif

</div>
@endsection
