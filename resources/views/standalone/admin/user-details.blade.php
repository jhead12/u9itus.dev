@extends('standalone.layouts.dashboard')

@section('title', 'User Details')
@section('page-title', 'User Details')

@section('content')
<div class="space-y-6 max-w-4xl">

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
        <form method="PUT" action="{{ route('admin.users.unsuspend', $user) }}">
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
            <div class="w-12 h-12 rounded-full bg-slate-700 flex items-center justify-center text-lg font-bold text-slate-200 shrink-0">
                {{ strtoupper(substr($user->name, 0, 1)) }}
            </div>
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
                <dt class="text-xs text-slate-500">Payment Method</dt>
                <dd class="mt-1 text-sm text-white">{{ $user->voter->payment_method ?? '—' }}</dd>
            </div>
            <div class="bg-slate-800/50 px-5 py-4">
                <dt class="text-xs text-slate-500">Referral Code</dt>
                <dd class="mt-1 text-sm font-mono text-white">{{ $user->voter->referral_code }}</dd>
            </div>
        </dl>
    </div>
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
                <form method="PUT" action="{{ route('admin.users.suspend', $user) }}" class="flex gap-2 items-start">
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
            <form method="PUT" action="{{ route('admin.users.unsuspend', $user) }}">
                @csrf
                @method('PUT')
                <button type="submit"
                    class="px-4 py-2 rounded-lg bg-emerald-500 hover:bg-emerald-400 text-white text-sm font-medium transition">
                    Unsuspend Account
                </button>
            </form>
            @endif
        </div>
    </div>
    @endif

</div>
@endsection
