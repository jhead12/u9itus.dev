@extends('standalone.layouts.dashboard')

@section('title', 'User Details')
@section('page-title', 'User Details')

@section('content')
<div class="space-y-6 max-w-4xl mx-auto">

    <div>
        <a href="{{ route('admin.users.index') }}" class="text-sm text-slate-400 hover:text-white transition">← Back to users</a>
    </div>

    @if(session('success'))
    <div class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm rounded-lg px-4 py-3">
        {{ session('success') }}
    </div>
    @endif

    @if($errors->any())
    <div class="bg-red-500/10 border border-red-500/30 text-red-400 text-sm rounded-lg px-4 py-3">
        {{ $errors->first() }}
    </div>
    @endif

    {{-- Suspension banner --}}
    @if($user->isSuspended())
    <div class="bg-orange-500/10 border border-orange-500/30 text-orange-300 text-sm rounded-lg px-4 py-3 flex items-center justify-between gap-4">
        <div>
            <p class="font-semibold">Account Suspended</p>
            <p class="text-xs text-orange-400/80 mt-0.5">{{ $user->suspension_reason }} — Suspended {{ $user->suspended_at->diffForHumans() }}</p>
        </div>
        <form method="POST" action="{{ route('admin.users.unsuspend', $user) }}">
            @csrf
            @method('PUT')
            <button type="submit" class="px-3 py-1.5 rounded-lg bg-orange-500 hover:bg-orange-400 text-white text-xs font-semibold transition shrink-0">
                Unsuspend
            </button>
        </form>
    </div>
    @endif

    {{-- User Card --}}
    <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl overflow-hidden">
        <div class="px-6 py-5 border-b border-slate-700/50 flex items-center gap-4">
            <img src="{{ $user->avatar_url }}"
                 alt="{{ $user->name }}"
                 class="w-12 h-12 rounded-full object-cover shrink-0">
            <div>
                <h2 class="text-base font-semibold text-white">{{ $user->name }}</h2>
                <p class="text-sm text-slate-400">{{ $user->email }}</p>
            </div>
            <div class="ml-auto flex items-center gap-2">
                @if($user->isSuspended())
                <span class="text-xs px-2.5 py-1 rounded-full bg-orange-500/10 text-orange-400 border border-orange-500/20">Suspended</span>
                @endif
                <span class="text-xs px-2.5 py-1 rounded-full {{ $user->user_type === 'politician' ? 'bg-blue-500/10 text-blue-400' : ($user->user_type === 'admin' ? 'bg-red-500/10 text-red-400' : 'bg-emerald-500/10 text-emerald-400') }}">
                    {{ $user->user_type ?? '—' }}
                </span>
            </div>
        </div>

        <dl class="grid grid-cols-2 sm:grid-cols-3 gap-px bg-slate-700/30">
            <div class="bg-slate-800/50 px-5 py-4">
                <dt class="text-xs text-slate-500">Email Verified</dt>
                <dd class="mt-1 text-sm text-white">{{ $user->email_verified_at ? $user->email_verified_at->format('M j, Y') : '—' }}</dd>
            </div>
            <div class="bg-slate-800/50 px-5 py-4">
                <dt class="text-xs text-slate-500">Joined</dt>
                <dd class="mt-1 text-sm text-white">{{ $user->created_at->format('M j, Y') }}</dd>
            </div>
            <div class="bg-slate-800/50 px-5 py-4">
                <dt class="text-xs text-slate-500">Phone</dt>
                <dd class="mt-1 text-sm text-white">{{ $user->phone ?? '—' }}</dd>
            </div>
            <div class="bg-slate-800/50 px-5 py-4">
                <dt class="text-xs text-slate-500">Location</dt>
                <dd class="mt-1 text-sm text-white">{{ implode(', ', array_filter([$user->city, $user->state, $user->zip_code])) ?: '—' }}</dd>
            </div>
            <div class="bg-slate-800/50 px-5 py-4">
                <dt class="text-xs text-slate-500">Platform</dt>
                <dd class="mt-1 text-sm text-white">{{ $user->platform ?? 'standalone' }}</dd>
            </div>
            <div class="bg-slate-800/50 px-5 py-4">
                <dt class="text-xs text-slate-500">Identity Verified</dt>
                <dd class="mt-1 text-sm {{ $user->is_verified ? 'text-emerald-400' : 'text-slate-400' }}">
                    {{ $user->is_verified ? 'Yes' : 'No' }}
                </dd>
            </div>
        </dl>
    </div>

    {{-- KYC Section --}}
    <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-700/50 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-white">KYC / Identity Verification</h3>
            @php
                $kycBadge = match($user->kyc_status) {
                    'approved' => 'bg-emerald-500/10 text-emerald-400',
                    'rejected' => 'bg-red-500/10 text-red-400',
                    default    => 'bg-yellow-500/10 text-yellow-400',
                };
            @endphp
            <span class="text-xs px-2.5 py-1 rounded-full {{ $kycBadge }}">{{ $user->kyc_status ?? 'pending' }}</span>
        </div>
        <div class="px-5 py-4 space-y-4">
            @if($user->kyc_reviewed_at)
            <p class="text-xs text-slate-500">
                Reviewed {{ $user->kyc_reviewed_at->format('M j, Y g:i a') }}
                @if($user->kyc_rejection_reason)
                — <span class="text-red-400">{{ $user->kyc_rejection_reason }}</span>
                @endif
            </p>
            @endif

            @if($user->user_type !== 'admin')
            <div class="flex flex-wrap gap-2">
                @if($user->kyc_status !== 'approved')
                <form method="POST" action="{{ route('admin.kyc.approve', $user) }}">
                    @csrf
                    <button type="submit"
                        class="px-4 py-2 rounded-lg bg-emerald-500 hover:bg-emerald-400 text-white text-sm font-medium transition">
                        ✓ Approve KYC
                    </button>
                </form>
                @endif
                @if($user->kyc_status !== 'rejected')
                <button type="button"
                        onclick="document.getElementById('kyc-reject-form').classList.toggle('hidden')"
                        class="px-4 py-2 rounded-lg bg-red-500/10 hover:bg-red-500/20 border border-red-500/20 text-red-400 text-sm font-medium transition">
                    ✗ Reject KYC
                </button>
                @endif
            </div>

            <div id="kyc-reject-form" class="hidden">
                <form method="POST" action="{{ route('admin.kyc.reject', $user) }}" class="flex gap-2 items-start">
                    @csrf
                    <textarea name="reason" rows="2"
                        placeholder="Rejection reason…"
                        class="flex-1 bg-slate-900/70 border border-slate-600/50 rounded-lg px-3 py-2 text-sm text-slate-300 placeholder-slate-600 focus:outline-none focus:border-red-500/50 resize-none"></textarea>
                    <button type="submit"
                        class="px-4 py-2 rounded-lg bg-red-500 hover:bg-red-400 text-white text-sm font-medium transition shrink-0">
                        Confirm Reject
                    </button>
                </form>
            </div>
            @endif
        </div>
    </div>

    {{-- Politician profile --}}
    @if($user->politician)
    <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-700/50">
            <h3 class="text-sm font-semibold text-white">Politician Profile</h3>
        </div>
        <dl class="grid grid-cols-2 sm:grid-cols-3 gap-px bg-slate-700/30">
            <div class="bg-slate-800/50 px-5 py-4">
                <dt class="text-xs text-slate-500">Full Name</dt>
                <dd class="mt-1 text-sm text-white">{{ $user->politician->full_name }}</dd>
            </div>
            <div class="bg-slate-800/50 px-5 py-4">
                <dt class="text-xs text-slate-500">Office</dt>
                <dd class="mt-1 text-sm text-white">{{ $user->politician->political_office ?? '—' }}</dd>
            </div>
            <div class="bg-slate-800/50 px-5 py-4">
                <dt class="text-xs text-slate-500">Governance Level</dt>
                <dd class="mt-1 text-sm text-white">{{ $user->politician->governance_level ?? '—' }}</dd>
            </div>
            <div class="bg-slate-800/50 px-5 py-4">
                <dt class="text-xs text-slate-500">Party</dt>
                <dd class="mt-1 text-sm text-white">{{ $user->politician->party_affiliation ?? '—' }}</dd>
            </div>
            <div class="bg-slate-800/50 px-5 py-4">
                <dt class="text-xs text-slate-500">State</dt>
                <dd class="mt-1 text-sm text-white">{{ $user->politician->state ?? '—' }}</dd>
            </div>
            <div class="bg-slate-800/50 px-5 py-4">
                <dt class="text-xs text-slate-500">Verified Official</dt>
                <dd class="mt-1 text-sm {{ $user->politician->verified_official ? 'text-emerald-400' : 'text-slate-400' }}">
                    {{ $user->politician->verified_official ? 'Yes' : 'No' }}
                </dd>
            </div>
        </dl>
    </div>
    @endif

    {{-- Voter profile --}}
    @if($user->voter)
    <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-700/50 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-white">Voter Profile</h3>
            @if($user->voter->flagged_for_fraud)
            <div class="flex items-center gap-2">
                <span class="text-xs px-2.5 py-1 rounded-full bg-red-500/10 text-red-400">⚠ Fraud Flagged</span>
                <form method="POST" action="{{ route('admin.fraud.clear-voter', $user->voter->id) }}">
                    @csrf
                    <button type="submit" class="text-xs px-2.5 py-1 rounded-lg bg-blue-500/10 hover:bg-blue-500/20 text-blue-400 border border-blue-500/20 transition">
                        Clear Flag
                    </button>
                </form>
            </div>
            @endif
        </div>
        <dl class="grid grid-cols-2 sm:grid-cols-3 gap-px bg-slate-700/30">
            <div class="bg-slate-800/50 px-5 py-4">
                <dt class="text-xs text-slate-500">Trust Score</dt>
                <dd class="mt-1 text-sm font-bold {{ $user->voter->trust_score >= 80 ? 'text-emerald-400' : ($user->voter->trust_score >= 50 ? 'text-amber-400' : 'text-red-400') }}">
                    {{ $user->voter->trust_score }}
                </dd>
            </div>
            <div class="bg-slate-800/50 px-5 py-4">
                <dt class="text-xs text-slate-500">Total Views</dt>
                <dd class="mt-1 text-sm text-white">{{ number_format($user->voter->total_views) }}</dd>
            </div>
            <div class="bg-slate-800/50 px-5 py-4">
                <dt class="text-xs text-slate-500">Total Earned</dt>
                <dd class="mt-1 text-sm text-white">${{ number_format($user->voter->total_earned, 2) }}</dd>
            </div>
            <div class="bg-slate-800/50 px-5 py-4">
                <dt class="text-xs text-slate-500">Wallet Balance</dt>
                <dd class="mt-1 text-sm text-white">${{ number_format($user->voter->wallet_balance, 2) }}</dd>
            </div>
            <div class="bg-slate-800/50 px-5 py-4">
                <dt class="text-xs text-slate-500">Pending Earnings</dt>
                <dd class="mt-1 text-sm {{ $user->voter->pending_earnings > 0 ? 'text-amber-400' : 'text-white' }}">${{ number_format($user->voter->pending_earnings, 2) }}</dd>
            </div>
            <div class="bg-slate-800/50 px-5 py-4">
                <dt class="text-xs text-slate-500">Payment Method</dt>
                <dd class="mt-1 text-sm text-white">{{ $user->voter->payment_method ?? '—' }}</dd>
            </div>
            <div class="bg-slate-800/50 px-5 py-4">
                <dt class="text-xs text-slate-500">Referral Code</dt>
                <dd class="mt-1 text-sm font-mono text-white">{{ $user->voter->referral_code }}</dd>
            </div>
            <div class="bg-slate-800/50 px-5 py-4">
                <dt class="text-xs text-slate-500">Last View</dt>
                <dd class="mt-1 text-sm text-white">{{ $user->voter->last_view_at ? $user->voter->last_view_at->format('M j, Y') : '—' }}</dd>
            </div>
            <div class="bg-slate-800/50 px-5 py-4">
                <dt class="text-xs text-slate-500">Registered Voter</dt>
                <dd class="mt-1 text-sm {{ $user->voter->is_registered_voter ? 'text-emerald-400' : 'text-slate-400' }}">{{ $user->voter->is_registered_voter ? 'Yes' : 'No' }}</dd>
            </div>
            <div class="bg-slate-800/50 px-5 py-4">
                <dt class="text-xs text-slate-500">User Tier</dt>
                <dd class="mt-1 text-sm text-white">{{ $user->voter->user_tier ?? 'regular' }}</dd>
            </div>
            <div class="bg-slate-800/50 px-5 py-4">
                <dt class="text-xs text-slate-500">Congressional District</dt>
                <dd class="mt-1 text-sm text-white">{{ $user->voter->congressional_district ?? '—' }}</dd>
            </div>
            <div class="bg-slate-800/50 px-5 py-4">
                <dt class="text-xs text-slate-500">Stripe Account</dt>
                @php $sas = $user->voter->stripe_account_status ?? null; @endphp
                <dd class="mt-1 text-sm {{ $sas === 'active' ? 'text-emerald-400' : 'text-slate-400' }}">
                    {{ $sas ? ucfirst($sas) : 'None' }}
                </dd>
            </div>
            <div class="bg-slate-800/50 px-5 py-4">
                <dt class="text-xs text-slate-500">Active</dt>
                <dd class="mt-1 text-sm {{ $user->voter->is_active ? 'text-emerald-400' : 'text-red-400' }}">{{ $user->voter->is_active ? 'Yes' : 'No' }}</dd>
            </div>
        </dl>
    </div>

    {{-- Payout Accounts --}}
    @if($user->voter && ($user->voter->paypal_email || $user->voter->cashapp_tag || $user->voter->stripe_account_id))
    <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-700/50">
            <h3 class="text-sm font-semibold text-white">Payout Accounts</h3>
        </div>
        <dl class="grid grid-cols-2 sm:grid-cols-3 gap-px bg-slate-700/30">
            @if($user->voter->paypal_email)
            <div class="bg-slate-800/50 px-5 py-4">
                <dt class="text-xs text-slate-500">PayPal Email</dt>
                <dd class="mt-1 text-sm text-white font-mono">
                    @php
                        $pe = $user->voter->paypal_email;
                        $parts = explode('@', $pe);
                        $masked = substr($parts[0], 0, 3) . str_repeat('*', max(0, strlen($parts[0]) - 3)) . '@' . ($parts[1] ?? '');
                    @endphp
                    {{ $masked }}
                </dd>
            </div>
            @endif
            @if($user->voter->cashapp_tag)
            <div class="bg-slate-800/50 px-5 py-4">
                <dt class="text-xs text-slate-500">Cash App</dt>
                <dd class="mt-1 text-sm text-white font-mono">${{ substr($user->voter->cashapp_tag, 0, 3) }}***</dd>
            </div>
            @endif
            @if($user->voter->stripe_account_id)
            <div class="bg-slate-800/50 px-5 py-4">
                <dt class="text-xs text-slate-500">Stripe Account ID</dt>
                <dd class="mt-1 text-sm text-white font-mono">{{ $user->voter->stripe_account_id }}</dd>
            </div>
            @endif
            @if($voterStats && $voterStats['payout_attempts']->isNotEmpty())
            @foreach($voterStats['payout_attempts'] as $status => $count)
            <div class="bg-slate-800/50 px-5 py-4">
                <dt class="text-xs text-slate-500">Payout Attempts ({{ ucfirst($status) }})</dt>
                <dd class="mt-1 text-sm {{ $status === 'failed' ? 'text-red-400' : ($status === 'paid' ? 'text-emerald-400' : 'text-white') }}">{{ $count }}</dd>
            </div>
            @endforeach
            @endif
        </dl>
    </div>
    @endif

    {{-- Early-bank membership --}}
    @if($user->voter && ($user->voter->earlybank_own_member_uuid || $user->voter->earlybank_member_id))
    <div class="bg-emerald-950/30 border border-emerald-700/30 rounded-xl overflow-hidden">
        <div class="px-5 py-4 border-b border-emerald-800/30">
            <h3 class="text-sm font-semibold text-emerald-200">Early-bank</h3>
        </div>
        <dl class="grid grid-cols-2 sm:grid-cols-3 gap-px bg-emerald-900/10">
            @if($user->voter->earlybank_own_member_uuid)
            <div class="bg-slate-800/50 px-5 py-4">
                <dt class="text-xs text-slate-500">EB Member UUID</dt>
                <dd class="mt-1 text-xs text-emerald-400 font-mono break-all">{{ $user->voter->earlybank_own_member_uuid }}</dd>
            </div>
            <div class="bg-slate-800/50 px-5 py-4">
                <dt class="text-xs text-slate-500">EB Enrolled At</dt>
                <dd class="mt-1 text-sm text-white">{{ $user->voter->earlybank_own_linked_at?->format('M j, Y') ?? '—' }}</dd>
            </div>
            @endif
            @if($user->voter->earlybank_member_id)
            <div class="bg-slate-800/50 px-5 py-4">
                <dt class="text-xs text-slate-500">Attributed EB Member</dt>
                <dd class="mt-1 text-xs text-indigo-400 font-mono break-all">{{ $user->voter->earlybank_member_id }}</dd>
            </div>
            <div class="bg-slate-800/50 px-5 py-4">
                <dt class="text-xs text-slate-500">EB Attribution Date</dt>
                <dd class="mt-1 text-sm text-white">{{ $user->voter->earlybank_linked_at?->format('M j, Y') ?? '—' }}</dd>
            </div>
            @endif
        </dl>
    </div>
    @endif

    {{-- Early-bank webhook event log --}}
    @if($user->voter && $ebWebhookLogs->isNotEmpty())
    <div class="bg-slate-800/50 border border-emerald-700/20 rounded-xl overflow-hidden">
        <div class="px-5 py-4 border-b border-emerald-800/20 flex items-center justify-between gap-4">
            <div>
                <h3 class="text-sm font-semibold text-emerald-200">Early-bank Event Log</h3>
                <p class="text-xs text-slate-500 mt-0.5">Outbound webhook history — registration, referral bonuses &amp; view commissions</p>
            </div>
            <span class="text-xs text-slate-500">{{ $ebWebhookLogs->count() }} events (last 25)</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead>
                    <tr class="border-b border-slate-700/50 text-slate-500 uppercase tracking-wide text-left">
                        <th class="px-5 py-3 font-medium">Event</th>
                        <th class="px-5 py-3 font-medium">EB Member</th>
                        <th class="px-5 py-3 font-medium">Payout</th>
                        <th class="px-5 py-3 font-medium">Delivered</th>
                        <th class="px-5 py-3 font-medium">Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700/30">
                    @foreach($ebWebhookLogs as $log)
                    <tr class="hover:bg-slate-700/20 transition">
                        <td class="px-5 py-3">
                            <div class="flex items-center gap-2">
                                @php
                                    $evColor = match($log->event_type) {
                                        'voter.registered' => 'bg-sky-500/10 text-sky-300 border-sky-500/20',
                                        'voter.referred'   => 'bg-emerald-500/10 text-emerald-300 border-emerald-500/20',
                                        'voter.earned'     => 'bg-indigo-500/10 text-indigo-300 border-indigo-500/20',
                                        default            => 'bg-slate-700 text-slate-300 border-slate-600',
                                    };
                                @endphp
                                <span class="px-2 py-0.5 rounded-full border text-[11px] font-medium {{ $evColor }}">
                                    {{ $log->event_type }}
                                </span>
                            </div>
                            <p class="text-slate-400 mt-0.5">{{ $log->eventLabel() }}</p>
                        </td>
                        <td class="px-5 py-3 font-mono text-slate-400 max-w-[140px] truncate" title="{{ $log->earlybank_member_id }}">
                            {{ $log->earlybank_member_id ? substr($log->earlybank_member_id, 0, 8) . '…' : '—' }}
                        </td>
                        <td class="px-5 py-3 text-white">
                            {{ $log->payout_amount !== null ? '$' . number_format($log->payout_amount, 2) : '—' }}
                        </td>
                        <td class="px-5 py-3">
                            @if($log->delivered)
                                <span class="text-emerald-400 font-semibold">✓ {{ $log->http_status }}</span>
                            @else
                                <span class="text-red-400 font-semibold">✗ {{ $log->http_status ?? 'ERR' }}</span>
                                @if($log->error_message)
                                <p class="text-red-400/70 text-[10px] mt-0.5 max-w-[160px] truncate" title="{{ $log->error_message }}">{{ $log->error_message }}</p>
                                @endif
                            @endif
                        </td>
                        <td class="px-5 py-3 text-slate-400 whitespace-nowrap">{{ $log->created_at->format('M j, Y g:i a') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- Referral summary --}}
    @if($user->voter && $voterStats)
    <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-700/50">
            <h3 class="text-sm font-semibold text-white">Referral Summary</h3>
        </div>
        <dl class="grid grid-cols-2 sm:grid-cols-3 gap-px bg-slate-700/30">
            <div class="bg-slate-800/50 px-5 py-4">
                <dt class="text-xs text-slate-500">Referred By</dt>
                <dd class="mt-1 text-sm text-white">
                    @if($user->voter->referrer)
                        {{ $user->voter->referrer->full_name ?? $user->voter->referrer->user?->name ?? '—' }}
                        <span class="text-slate-500 text-xs ml-1">(voter)</span>
                    @elseif($user->voter->politicianReferrer)
                        {{ $user->voter->politicianReferrer->full_name }}
                        <span class="text-slate-500 text-xs ml-1">(politician)</span>
                    @else
                        <span class="text-slate-500">Organic</span>
                    @endif
                </dd>
            </div>
            <div class="bg-slate-800/50 px-5 py-4">
                <dt class="text-xs text-slate-500">Voters Referred</dt>
                <dd class="mt-1 text-sm text-white">{{ number_format($voterStats['referral_count']) }}</dd>
            </div>
            <div class="bg-slate-800/50 px-5 py-4">
                <dt class="text-xs text-slate-500">Referral Earnings</dt>
                <dd class="mt-1 text-sm text-white">${{ number_format($voterStats['referral_earnings'], 2) }}</dd>
            </div>
        </dl>
    </div>
    @endif

    {{-- Recent view sessions --}}
    @if($user->voter && $user->voter->viewSessions->isNotEmpty())
    <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-700/50">
            <h3 class="text-sm font-semibold text-white">Recent View Sessions <span class="text-slate-500 font-normal text-xs ml-1">(last 10)</span></h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-700/50 text-slate-500 text-xs uppercase tracking-wide text-left">
                        <th class="px-5 py-3 font-medium">Date</th>
                        <th class="px-5 py-3 font-medium">Status</th>
                        <th class="px-5 py-3 font-medium">Completion</th>
                        <th class="px-5 py-3 font-medium">Payout</th>
                        <th class="px-5 py-3 font-medium">Fraud Score</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700/30">
                    @foreach($user->voter->viewSessions as $vs)
                    <tr class="hover:bg-slate-700/20 transition">
                        <td class="px-5 py-3 text-slate-400 whitespace-nowrap">{{ $vs->created_at->format('M j, Y') }}</td>
                        <td class="px-5 py-3">
                            <span class="text-xs px-2 py-0.5 rounded-full {{ $vs->status === 'completed' ? 'bg-emerald-500/10 text-emerald-400' : 'bg-slate-700 text-slate-400' }}">
                                {{ $vs->status }}
                            </span>
                        </td>
                        <td class="px-5 py-3 text-white">{{ $vs->completion_percentage !== null ? number_format($vs->completion_percentage, 0) . '%' : '—' }}</td>
                        <td class="px-5 py-3 text-white">${{ number_format($vs->voter_payout_amount ?? 0, 2) }}</td>
                        <td class="px-5 py-3">
                            @php $fs = $vs->fraud_score ?? 0; @endphp
                            <span class="{{ $fs > 50 ? 'text-red-400 font-semibold' : 'text-slate-400' }}">{{ $fs }}</span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- Fraud signals --}}
    @if($user->voter && $user->voter->fraudSignals->isNotEmpty())
    <div class="bg-red-950/20 border border-red-700/30 rounded-xl overflow-hidden">
        <div class="px-5 py-4 border-b border-red-800/30">
            <h3 class="text-sm font-semibold text-red-300">Fraud Signals <span class="text-red-500 font-normal text-xs ml-1">(last 5)</span></h3>
        </div>
        <div class="divide-y divide-red-900/20">
            @foreach($user->voter->fraudSignals as $signal)
            <div class="px-5 py-3 flex items-start justify-between gap-4">
                <div>
                    <p class="text-sm text-red-300 font-medium">{{ $signal->signal_type ?? 'Unknown' }}</p>
                    @if(!empty($signal->description))
                    <p class="text-xs text-slate-400 mt-0.5">{{ $signal->description }}</p>
                    @endif
                </div>
                <p class="text-xs text-slate-500 whitespace-nowrap shrink-0">{{ $signal->created_at->format('M j, Y') }}</p>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    @endif

    {{-- Suspension actions --}}
    @if($user->user_type !== 'admin')
    <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-700/50">
            <h3 class="text-sm font-semibold text-white">Account Actions</h3>
        </div>
        <div class="px-5 py-4 space-y-4">
            @if(!$user->isSuspended())
            <p class="text-xs text-slate-500">Suspending deactivates the account and all associated profiles. The user will not be able to log in.</p>
            <div>
                <button type="button"
                        onclick="document.getElementById('suspend-form').classList.toggle('hidden')"
                        class="px-4 py-2 rounded-lg bg-amber-500/10 hover:bg-amber-500/20 border border-amber-500/20 text-amber-400 text-sm font-medium transition">
                    Suspend Account
                </button>
            </div>
            <div id="suspend-form" class="hidden">
                <form method="POST" action="{{ route('admin.users.suspend', $user) }}" class="flex gap-2 items-start">
                    @csrf
                    @method('PUT')
                    <input type="text" name="reason"
                        placeholder="Suspension reason (optional)…"
                        class="flex-1 bg-slate-900/70 border border-slate-600/50 rounded-lg px-3 py-2 text-sm text-slate-300 placeholder-slate-600 focus:outline-none focus:border-amber-500/50">
                    <button type="submit"
                        class="px-4 py-2 rounded-lg bg-amber-500 hover:bg-amber-400 text-white text-sm font-medium transition shrink-0">
                        Confirm Suspend
                    </button>
                </form>
            </div>
            @else
            <form method="POST" action="{{ route('admin.users.unsuspend', $user) }}">
                @csrf
                @method('PUT')
                <button type="submit"
                    class="px-4 py-2 rounded-lg bg-emerald-500 hover:bg-emerald-400 text-white text-sm font-medium transition">
                    Unsuspend Account
                </button>
            </form>
            @endif

            {{-- Delete account --}}
            <hr class="border-slate-700/50">
            <p class="text-xs text-slate-500">
                Permanently deletes this account and all associated data. The account is archived and can be restored later (with a new ID).
            </p>
            <div>
                <button type="button"
                        onclick="document.getElementById('delete-account-modal').classList.remove('hidden')"
                        class="px-4 py-2 rounded-lg bg-red-500/10 hover:bg-red-500/20 border border-red-500/20 text-red-400 text-sm font-medium transition">
                    Delete Account
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- Delete confirmation modal --}}
    @if($user->user_type !== 'admin')
    <div id="delete-account-modal"
         class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm"
         onclick="if(event.target===this) this.classList.add('hidden')">
        <div class="bg-slate-900 border border-red-500/30 rounded-xl shadow-2xl w-full max-w-md mx-4 p-6 space-y-4">
            <h3 class="text-base font-semibold text-red-400">Delete Account</h3>
            <p class="text-sm text-slate-400">
                This will permanently delete <span class="text-white font-medium">{{ $user->email }}</span> and all associated profiles, campaigns, and sessions. The account will be archived and can be restored.
            </p>
            <form method="POST" action="{{ route('admin.users.destroy', $user) }}">
                @csrf
                @method('DELETE')
                <div class="space-y-3">
                    <div>
                        <label class="block text-xs text-slate-500 mb-1">Reason (optional)</label>
                        <input type="text" name="deletion_reason"
                            placeholder="e.g. user request, policy violation…"
                            class="w-full bg-slate-800 border border-slate-600/50 rounded-lg px-3 py-2 text-sm text-slate-300 placeholder-slate-600 focus:outline-none focus:border-red-500/50">
                    </div>
                    <div class="flex gap-3 pt-1">
                        <button type="submit"
                            class="flex-1 px-4 py-2 rounded-lg bg-red-600 hover:bg-red-500 text-white text-sm font-semibold transition">
                            Confirm Delete
                        </button>
                        <button type="button"
                            onclick="document.getElementById('delete-account-modal').classList.add('hidden')"
                            class="flex-1 px-4 py-2 rounded-lg bg-slate-700 hover:bg-slate-600 text-slate-300 text-sm font-medium transition">
                            Cancel
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    @endif

</div>
@endsection
