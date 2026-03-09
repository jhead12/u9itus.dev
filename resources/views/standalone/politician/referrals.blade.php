@extends('standalone.layouts.dashboard')

@section('title', 'My Referrals')
@section('page-title', 'Referrals')

@section('content')
<div class="space-y-7 max-w-4xl">

    <div>
        <h1 class="text-2xl font-bold text-white">Referrals</h1>
        <p class="text-slate-400 text-sm mt-0.5">Earn cash by recruiting voters or other politicians to the platform</p>
    </div>

    {{-- ── Commission Structure Banner ─────────────────────────────── --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

        {{-- Voter recruitment card --}}
        <div class="bg-emerald-900/20 border border-emerald-500/20 rounded-2xl p-5">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-9 h-9 rounded-xl bg-emerald-500/20 flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                </div>
                <div>
                    <p class="text-white font-semibold text-sm">Recruit a Voter</p>
                    <p class="text-emerald-400 text-xs font-mono">10% of their payout per view</p>
                </div>
            </div>
            <p class="text-slate-400 text-xs leading-relaxed">
                Earn <strong class="text-emerald-400">$0.025</strong> every time a voter you recruited
                completes a qualifying view. Recurring — pays as long as your recruit is active.
            </p>
            <p class="text-slate-500 text-xs mt-2">
                {{ $referredVoters->count() }} voter{{ $referredVoters->count() === 1 ? '' : 's' }} recruited
                &nbsp;·&nbsp;
                <span class="text-emerald-400">${{ number_format($totalVoterViewEarnings, 2) }} earned</span>
            </p>
        </div>

        {{-- Politician recruitment card --}}
        <div class="bg-amber-900/20 border border-amber-500/20 rounded-2xl p-5">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-9 h-9 rounded-xl bg-amber-500/20 flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 5h2a2 2 0 002-2v-2a2 2 0 00-2-2h-2a2 2 0 00-2 2v2a2 2 0 002 2z"/>
                    </svg>
                </div>
                <div>
                    <p class="text-white font-semibold text-sm">Recruit a Politician</p>
                    <p class="text-amber-400 text-xs font-mono">10% residual income</p>
                </div>
            </div>
            <p class="text-slate-400 text-xs leading-relaxed">
                Earn <strong class="text-amber-400">10% residual income</strong> as a Founding Member when you recruit a politician.
                Ongoing commissions on their spending.
            </p>
            <p class="text-slate-500 text-xs mt-2">
                {{ $referredPoliticians->count() }} politician{{ $referredPoliticians->count() === 1 ? '' : 's' }} recruited
                &nbsp;·&nbsp;
                <span class="text-amber-400">${{ number_format($totalProcurementEarnings, 2) }} earned</span>
            </p>
        </div>
    </div>

    {{-- ── Stats Row ─────────────────────────────────────────────── --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="bg-slate-800/50 border border-slate-700/60 rounded-2xl p-4">
            <p class="text-slate-400 text-xs font-medium uppercase tracking-wide">Referral Code</p>
            <p class="text-xl font-bold text-emerald-400 mt-2 tracking-widest font-mono">{{ $politician->referral_code ?? '—' }}</p>
        </div>
        <div class="bg-slate-800/50 border border-slate-700/60 rounded-2xl p-4">
            <p class="text-slate-400 text-xs font-medium uppercase tracking-wide">Voters Recruited</p>
            <p class="text-xl font-bold text-white mt-2">{{ $referredVoters->count() }}</p>
        </div>
        <div class="bg-slate-800/50 border border-slate-700/60 rounded-2xl p-4">
            <p class="text-slate-400 text-xs font-medium uppercase tracking-wide">Politicians Recruited</p>
            <p class="text-xl font-bold text-white mt-2">{{ $referredPoliticians->count() }}</p>
        </div>
        <div class="bg-slate-800/50 border border-slate-700/60 rounded-2xl p-4">
            <p class="text-slate-400 text-xs font-medium uppercase tracking-wide">Total Earned</p>
            <p class="text-xl font-bold text-emerald-400 mt-2">${{ number_format($totalVoterViewEarnings + $totalProcurementEarnings, 2) }}</p>
        </div>
    </div>

    {{-- ── Share Links ───────────────────────────────────────────── --}}
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-2xl p-6 space-y-6">
        <h2 class="text-base font-semibold text-white">Your Referral Links</h2>

        {{-- Voter link --}}
        <div class="grid grid-cols-1 sm:grid-cols-[1fr_auto] gap-6 items-start">
            <div class="space-y-2">
                <p class="text-sm font-medium text-emerald-400">Voter Registration Link</p>
                <p class="text-slate-400 text-xs">Earn 10% of each view payout from every voter you recruit.</p>
                <div class="flex gap-2">
                    <input id="voter-referral-link" type="text" readonly
                        value="{{ route('register.voter') }}?ref={{ $politician->referral_code }}"
                        class="flex-1 bg-slate-900 border border-slate-600 rounded-lg px-4 py-2.5 text-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    <button onclick="copyLink('voter-referral-link')"
                        class="shrink-0 px-4 py-2.5 bg-emerald-500/20 hover:bg-emerald-500/30 text-emerald-400 text-sm rounded-lg transition border border-emerald-500/30 font-medium">
                        Copy
                    </button>
                </div>
            </div>
@php
                $voterRefUrl = route('register.voter') . '?ref=' . ($politician->referral_code ?? '');
                $voterQrSrc  = 'https://api.qrserver.com/v1/create-qr-code/?size=120x120&color=059669&bgcolor=FFFFFF&data=' . rawurlencode($voterRefUrl) . '&qzone=1';
            @endphp
            <div class="flex flex-col items-center gap-2">
                <img src="{{ $voterQrSrc }}"
                     alt="Voter Referral QR Code"
                     class="bg-white rounded-xl p-1.5 w-24 h-24 object-contain">
                <a href="{{ $voterQrSrc }}&download=1"
                   download="voter-referral-qr.png"
                   class="text-xs text-emerald-400 hover:text-emerald-300 underline underline-offset-2 transition">
                    Download PNG
                </a>
            </div>
        </div>

        <div class="border-t border-slate-700/50"></div>

        {{-- Politician link --}}
        <div class="grid grid-cols-1 sm:grid-cols-[1fr_auto] gap-6 items-start">
            <div class="space-y-2">
                <p class="text-sm font-medium text-amber-400">Politician Registration Link</p>
                <p class="text-slate-400 text-xs">Earn 10% residual income as a Founding Member when you recruit a politician.</p>
                <div class="flex gap-2">
                    <input id="politician-referral-link" type="text" readonly
                        value="{{ route('register.politician') }}?ref={{ $politician->referral_code }}"
                        class="flex-1 bg-slate-900 border border-slate-600 rounded-lg px-4 py-2.5 text-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500">
                    <button onclick="copyLink('politician-referral-link')"
                        class="shrink-0 px-4 py-2.5 bg-amber-500/20 hover:bg-amber-500/30 text-amber-400 text-sm rounded-lg transition border border-amber-500/30 font-medium">
                        Copy
                    </button>
                </div>
            </div>
@php
                $politicianRefUrl = route('register.politician') . '?ref=' . ($politician->referral_code ?? '');
                $politicianQrSrc  = 'https://api.qrserver.com/v1/create-qr-code/?size=120x120&color=d97706&bgcolor=FFFFFF&data=' . rawurlencode($politicianRefUrl) . '&qzone=1';
            @endphp
            <div class="flex flex-col items-center gap-2">
                <img src="{{ $politicianQrSrc }}"
                     alt="Politician Referral QR Code"
                     class="bg-white rounded-xl p-1.5 w-24 h-24 object-contain">
                <a href="{{ $politicianQrSrc }}&download=1"
                   download="politician-referral-qr.png"
                   class="text-xs text-amber-400 hover:text-amber-300 underline underline-offset-2 transition">
                    Download PNG
                </a>
            </div>
        </div>
    </div>

    {{-- ── Recruited Voters Table ───────────────────────────────── --}}
    @if($referredVoters->isNotEmpty())
    <div>
        <h2 class="text-base font-semibold text-white mb-4">Recruited Voters ({{ $referredVoters->count() }})</h2>
        <div class="bg-slate-800/50 border border-slate-700/60 rounded-2xl overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-700/60 text-slate-500 text-xs uppercase tracking-wide text-left">
                        <th class="px-5 py-3 font-medium">Name</th>
                        <th class="px-5 py-3 font-medium">Views</th>
                        <th class="px-5 py-3 font-medium">Joined</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700/40">
                    @foreach($referredVoters as $voter)
                    <tr class="hover:bg-slate-700/20 transition">
                        <td class="px-5 py-4">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full bg-emerald-900/40 flex items-center justify-center text-emerald-400 text-xs font-bold shrink-0">
                                    {{ strtoupper(substr($voter->full_name ?? '?', 0, 1)) }}
                                </div>
                                <span class="text-white">{{ $voter->full_name ?? 'Anonymous' }}</span>
                            </div>
                        </td>
                        <td class="px-5 py-4 text-slate-300">{{ $voter->total_views ?? 0 }}</td>
                        <td class="px-5 py-4 text-slate-400 text-sm">{{ $voter->created_at?->format('M j, Y') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- ── Recruited Politicians Table ─────────────────────────── --}}
    @if($referredPoliticians->isNotEmpty())
    <div>
        <h2 class="text-base font-semibold text-white mb-4">Recruited Politicians ({{ $referredPoliticians->count() }})</h2>
        <div class="bg-slate-800/50 border border-slate-700/60 rounded-2xl overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-700/60 text-slate-500 text-xs uppercase tracking-wide text-left">
                        <th class="px-5 py-3 font-medium">Name</th>
                        <th class="px-5 py-3 font-medium">Office</th>
                        <th class="px-5 py-3 font-medium">Commission</th>
                        <th class="px-5 py-3 font-medium">Joined</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700/40">
                    @foreach($referredPoliticians as $pol)
                    <tr class="hover:bg-slate-700/20 transition">
                        <td class="px-5 py-4">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full bg-amber-900/40 flex items-center justify-center text-amber-400 text-xs font-bold shrink-0">
                                    {{ strtoupper(substr($pol->full_name ?? '?', 0, 1)) }}
                                </div>
                                <span class="text-white">{{ $pol->full_name ?? 'Anonymous' }}</span>
                            </div>
                        </td>
                        <td class="px-5 py-4 text-slate-400 text-sm">{{ $pol->political_office ?? '—' }}</td>
                        <td class="px-5 py-4">
                            @php
                                $polEarning = $procurementEarnings->firstWhere('politician_id', $pol->id);
                            @endphp
                            @if($polEarning)
                                <span class="text-amber-400 font-medium">${{ number_format($polEarning->commission_amount, 2) }}</span>
                            @else
                                <span class="text-slate-500 text-xs">Pending first purchase</span>
                            @endif
                        </td>
                        <td class="px-5 py-4 text-slate-400 text-sm">{{ $pol->created_at?->format('M j, Y') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- ── Voter-View Commissions History ──────────────────────── --}}
    @if($voterViewEarnings->isNotEmpty())
    <div>
        <h2 class="text-base font-semibold text-white mb-4">
            Voter-View Commissions
            <span class="text-emerald-400 text-sm font-normal ml-2">${{ number_format($totalVoterViewEarnings, 2) }} total</span>
        </h2>
        <div class="bg-slate-800/50 border border-slate-700/60 rounded-2xl overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-700/60 text-slate-500 text-xs uppercase tracking-wide text-left">
                        <th class="px-5 py-3 font-medium">Voter</th>
                        <th class="px-5 py-3 font-medium">Commission</th>
                        <th class="px-5 py-3 font-medium">Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700/40">
                    @foreach($voterViewEarnings as $earning)
                    <tr class="hover:bg-slate-700/20 transition">
                        <td class="px-5 py-4 text-slate-300">{{ $earning->referredVoter?->full_name ?? '—' }}</td>
                        <td class="px-5 py-4 text-emerald-400 font-medium">${{ number_format($earning->commission_amount, 4) }}</td>
                        <td class="px-5 py-4 text-slate-400 text-xs">{{ $earning->created_at?->format('M j, Y') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- ── Procurement Commissions History ─────────────────────── --}}
    @if($procurementEarnings->isNotEmpty())
    <div>
        <h2 class="text-base font-semibold text-white mb-4">
            Procurement Commissions
            <span class="text-amber-400 text-sm font-normal ml-2">${{ number_format($totalProcurementEarnings, 2) }} total</span>
        </h2>
        <div class="bg-slate-800/50 border border-slate-700/60 rounded-2xl overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-700/60 text-slate-500 text-xs uppercase tracking-wide text-left">
                        <th class="px-5 py-3 font-medium">Politician</th>
                        <th class="px-5 py-3 font-medium">Commission</th>
                        <th class="px-5 py-3 font-medium">Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700/40">
                    @foreach($procurementEarnings as $earning)
                    <tr class="hover:bg-slate-700/20 transition">
                        <td class="px-5 py-4 text-slate-300">{{ $earning->politician?->full_name ?? '—' }}</td>
                        <td class="px-5 py-4 text-amber-400 font-medium">${{ number_format($earning->commission_amount, 2) }}</td>
                        <td class="px-5 py-4 text-slate-400 text-xs">{{ $earning->created_at?->format('M j, Y') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- Empty state --}}
    @if($referredVoters->isEmpty() && $referredPoliticians->isEmpty())
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-2xl p-10 text-center">
        <div class="w-12 h-12 rounded-full bg-slate-700/50 flex items-center justify-center mx-auto mb-4">
            <svg class="w-6 h-6 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
        </div>
        <p class="text-slate-400 text-sm font-medium">No referrals yet</p>
        <p class="text-slate-600 text-xs mt-1">Share your voter or politician links above to start earning!</p>
    </div>
    @endif

</div>

@push('scripts')
<script>
window.copyLink = function (inputId) {
    const input = document.getElementById(inputId);
    if (!input) return;
    navigator.clipboard?.writeText(input.value).catch(() => {
        input.select();
        document.execCommand('copy');
    });
    const btn = input.nextElementSibling;
    if (btn) {
        const orig = btn.textContent.trim();
        btn.textContent = 'Copied!';
        setTimeout(() => { btn.textContent = orig; }, 1800);
    }
};
</script>
@endpush

@endsection
