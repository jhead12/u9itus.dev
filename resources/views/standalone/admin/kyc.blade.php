@extends('standalone.layouts.dashboard')

@section('title', 'KYC — Politician Identity Review')
@section('page-title', 'KYC — Politician Identity Document Review')

@section('content')
<div class="space-y-6">

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

    {{-- Stats --}}
    <div class="grid grid-cols-3 gap-4">
        <div class="stat-card border-yellow-500/30">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">Pending Review</p>
            <p class="text-3xl font-bold text-yellow-400">{{ number_format($stats['pending']) }}</p>
        </div>
        <div class="stat-card border-emerald-500/30">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">Approved</p>
            <p class="text-3xl font-bold text-emerald-400">{{ number_format($stats['approved']) }}</p>
        </div>
        <div class="stat-card border-red-500/30">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">Rejected</p>
            <p class="text-3xl font-bold text-red-400">{{ number_format($stats['rejected']) }}</p>
        </div>
    </div>

    {{-- KYC Queue --}}
    <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-700/50">
            <h3 class="text-sm font-semibold text-white">Politician Identity Verification Queue</h3>
            <p class="text-xs text-slate-500 mt-0.5">{{ $users->total() }} politician(s) awaiting identity document review — politicians must upload a government-issued ID before campaigns are activated</p>
            <p class="text-xs text-blue-300/70 mt-1">Voter identity verification is handled automatically through Stripe Connect — voter rows do not appear in this queue.</p>
        </div>

        @if($users->isEmpty())
        <div class="px-5 py-12 text-center">
            <svg class="w-10 h-10 text-slate-500 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <p class="text-sm text-slate-500">No pending politician identity reviews — all caught up!</p>
        </div>
        @else
        <div class="divide-y divide-slate-700/30">
            @foreach($users as $user)
            <div class="px-5 py-5" x-data="{ open: false }">
                <div class="flex flex-col sm:flex-row sm:items-start gap-4">
                    {{-- Avatar + info --}}
                    <div class="flex items-start gap-3 flex-1 min-w-0">
                        <img src="{{ $user->avatar_url }}"
                             alt="{{ $user->name }}"
                             class="w-10 h-10 rounded-full object-cover shrink-0">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <p class="text-sm font-semibold text-white">{{ $user->name }}</p>
                                <span class="text-xs px-2 py-0.5 rounded-full {{ $user->user_type === 'politician' ? 'bg-blue-500/10 text-blue-400' : 'bg-emerald-500/10 text-emerald-400' }}">
                                    {{ $user->user_type }}
                                </span>
                                @if($user->user_type === 'voter')
                                <span class="text-xs px-2 py-0.5 rounded-full bg-amber-500/10 text-amber-400 border border-amber-500/30">
                                    Legacy Manual Review
                                </span>
                                @endif
                            </div>
                            <p class="text-xs text-slate-400 mt-0.5">{{ $user->email }}</p>
                            <div class="flex flex-wrap gap-x-4 gap-y-1 mt-2 text-xs text-slate-500">
                                @if($user->phone)
                                <span>📞 {{ $user->phone }}</span>
                                @endif
                                @if($user->city || $user->state)
                                <span>📍 {{ implode(', ', array_filter([$user->city, $user->state, $user->zip_code])) }}</span>
                                @endif
                                <span>Joined {{ $user->created_at->format('M j, Y') }}</span>
                                @if($user->email_verified_at)
                                <span class="text-emerald-400">✓ Email verified</span>
                                @else
                                <span class="text-amber-400">✗ Email unverified</span>
                                @endif
                            </div>

                            {{-- KYC Document --}}
                            @if($user->kyc_document_path)
                            <div class="mt-2 flex items-center gap-2">
                                <svg class="w-3.5 h-3.5 text-yellow-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                <a href="{{ route('admin.kyc.view', $user) }}" target="_blank"
                                   class="text-xs text-yellow-400 hover:text-yellow-300 underline underline-offset-2 transition">
                                    View uploaded ID document ({{ strtoupper(pathinfo($user->kyc_document_path, PATHINFO_EXTENSION)) }})
                                </a>
                            </div>
                            @else
                            <div class="mt-2 flex items-center gap-1.5">
                                <svg class="w-3.5 h-3.5 text-slate-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                <span class="text-xs text-slate-500 italic">No document uploaded yet</span>
                            </div>
                            @endif

                            {{-- Politician specific info --}}
                            @if($user->politician)
                            <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-500">
                                @if($user->politician->full_name)
                                <span>Full name: <span class="text-slate-300">{{ $user->politician->full_name }}</span></span>
                                @endif
                                @if($user->politician->political_office)
                                <span>Office: <span class="text-slate-300">{{ $user->politician->political_office }}</span></span>
                                @endif
                                @if($user->politician->governance_level)
                                <span>Level: <span class="text-slate-300">{{ $user->politician->governance_level }}</span></span>
                                @endif
                                @if($user->politician->state)
                                <span>State: <span class="text-slate-300">{{ $user->politician->state }}</span></span>
                                @endif
                                @if($user->politician->party_affiliation)
                                <span>Party: <span class="text-slate-300">{{ $user->politician->party_affiliation }}</span></span>
                                @endif
                            </div>
                            @endif

                            {{-- Voter specific info --}}
                            @if($user->voter)
                            <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-500">
                                <span>Trust score: <span class="{{ $user->voter->trust_score >= 80 ? 'text-emerald-400' : ($user->voter->trust_score >= 50 ? 'text-amber-400' : 'text-red-400') }}">{{ $user->voter->trust_score }}</span></span>
                                <span>Views: <span class="text-slate-300">{{ number_format($user->voter->total_views) }}</span></span>
                                <span>Earned: <span class="text-slate-300">${{ number_format($user->voter->total_earned, 2) }}</span></span>
                                @if($user->voter->flagged_for_fraud)
                                <span class="text-red-400 font-semibold">⚠ Fraud flagged</span>
                                @endif
                            </div>
                            @endif
                        </div>
                    </div>

                    {{-- Action buttons --}}
                    <div class="flex flex-col gap-2 shrink-0 sm:items-end">
                        {{-- Approve --}}
                        <form method="POST" action="{{ route('admin.kyc.approve', $user) }}">
                            @csrf
                            <button type="submit"
                                    class="w-full sm:w-auto px-4 py-1.5 rounded-lg bg-emerald-500 hover:bg-emerald-400 text-white text-xs font-semibold transition">
                                ✓ Approve KYC
                            </button>
                        </form>

                        {{-- Reject (toggle form) --}}
                        <button type="button"
                                onclick="document.getElementById('reject-form-{{ $user->id }}').classList.toggle('hidden')"
                                class="w-full sm:w-auto px-4 py-1.5 rounded-lg bg-red-500/10 hover:bg-red-500/20 text-red-400 border border-red-500/20 text-xs font-semibold transition">
                            ✗ Reject KYC
                        </button>

                        {{-- View full profile --}}
                        <a href="{{ route('admin.users.show', $user) }}"
                           class="text-xs text-slate-400 hover:text-white transition text-right">
                            View profile →
                        </a>
                    </div>
                </div>

                {{-- Reject form (hidden by default) --}}
                <div id="reject-form-{{ $user->id }}" class="hidden mt-4 pl-13">
                    <form method="POST" action="{{ route('admin.kyc.reject', $user) }}" class="flex gap-2 items-start">
                        @csrf
                        <textarea name="reason" rows="2"
                            placeholder="Rejection reason (optional)…"
                            class="flex-1 bg-slate-900/70 border border-slate-600/50 rounded-lg px-3 py-2 text-sm text-slate-300 placeholder-slate-600 focus:outline-none focus:border-red-500/50 resize-none"></textarea>
                        <button type="submit"
                            class="px-4 py-2 rounded-lg bg-red-500 hover:bg-red-400 text-white text-xs font-semibold transition shrink-0">
                            Confirm Reject
                        </button>
                    </form>
                </div>
            </div>
            @endforeach
        </div>

        <div class="px-5 py-4 border-t border-slate-700/50">
            {{ $users->links() }}
        </div>
        @endif
    </div>

</div>
@endsection
