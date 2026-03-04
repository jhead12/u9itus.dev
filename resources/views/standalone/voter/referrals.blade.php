@extends('layouts.voter')

@section('title', 'My Referrals')

@section('content')
<div class="px-4 sm:px-6 lg:px-8 py-8 max-w-4xl mx-auto space-y-7">

    <div>
        <h1 class="text-2xl font-bold text-white">Referrals</h1>
        <p class="text-slate-400 text-sm mt-0.5">Two ways to earn — refer voters or recruit politicians to the platform</p>
    </div>

    @if($voter)

    {{-- ── Commission Structure Banner ─────────────────────────── --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        {{-- Voter referral card --}}
        <div class="bg-emerald-900/20 border border-emerald-500/20 rounded-2xl p-5">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-9 h-9 rounded-xl bg-emerald-500/20 flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                </div>
                <div>
                    <p class="text-white font-semibold text-sm">Refer a Voter</p>
                    <p class="text-emerald-400 text-xs font-mono">10% per view completed</p>
                </div>
            </div>
            <p class="text-slate-400 text-xs leading-relaxed">
                Earn <strong class="text-emerald-400">$0.025</strong> every time someone you referred watches a full ad.
                Recurring — pays as long as your referral is active.
            </p>
            <p class="text-slate-500 text-xs mt-2">{{ $referrals->count() }} voter{{ $referrals->count() === 1 ? '' : 's' }} referred &nbsp;·&nbsp; <span class="text-emerald-400">${{ number_format($totalReferralEarnings, 2) }} earned</span></p>
        </div>

        {{-- Politician procurement card --}}
        <div class="bg-amber-900/20 border border-amber-500/20 rounded-2xl p-5">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-9 h-9 rounded-xl bg-amber-500/20 flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 5h2a2 2 0 002-2v-2a2 2 0 00-2-2h-2a2 2 0 00-2 2v2a2 2 0 002 2z"/>
                    </svg>
                </div>
                <div>
                    <p class="text-white font-semibold text-sm">Recruit a Politician</p>
                    <p class="text-amber-400 text-xs font-mono">10% of first credit purchase</p>
                </div>
            </div>
            <p class="text-slate-400 text-xs leading-relaxed">
                Earn <strong class="text-amber-400">10%</strong> of the first credit purchase when a politician you recruited
                funds their first campaign. One-time bonus per politician.
            </p>
            <p class="text-slate-500 text-xs mt-2">{{ $referredPoliticians->count() }} politician{{ $referredPoliticians->count() === 1 ? '' : 's' }} recruited &nbsp;·&nbsp; <span class="text-amber-400">${{ number_format($totalProcurementEarnings, 2) }} earned</span></p>
        </div>
    </div>

    {{-- ── Stats Row ────────────────────────────────────────────── --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="bg-slate-800/50 border border-slate-700/60 rounded-2xl p-4">
            <p class="text-slate-400 text-xs font-medium uppercase tracking-wide">Referral Code</p>
            <p class="text-xl font-bold text-emerald-400 mt-2 tracking-widest font-mono">{{ $voter->referral_code }}</p>
        </div>
        <div class="bg-slate-800/50 border border-slate-700/60 rounded-2xl p-4">
            <p class="text-slate-400 text-xs font-medium uppercase tracking-wide">Voters Referred</p>
            <p class="text-xl font-bold text-white mt-2">{{ $referrals->count() }}</p>
        </div>
        <div class="bg-slate-800/50 border border-slate-700/60 rounded-2xl p-4">
            <p class="text-slate-400 text-xs font-medium uppercase tracking-wide">Politicians Recruited</p>
            <p class="text-xl font-bold text-white mt-2">{{ $referredPoliticians->count() }}</p>
        </div>
        <div class="bg-slate-800/50 border border-slate-700 rounded-2xl p-4">
            <p class="text-slate-400 text-xs font-medium uppercase tracking-wide">Total Earned</p>
            <p class="text-xl font-bold text-purple-400 mt-2">${{ number_format($totalReferralEarnings + $totalProcurementEarnings, 2) }}</p>
        </div>
    </div>

    {{-- ── Earnings Calculator Widget ────────────────────────────── --}}
    <x-earnings-calculator />

    {{-- ── Share Links ──────────────────────────────────────────── --}}
    @php
        $voterRefUrl      = route('register.voter')      . '?ref=' . $voter->referral_code;
        $politicianRefUrl = route('register.politician') . '?ref=' . $voter->referral_code;
        $voterQrSrc       = 'https://api.qrserver.com/v1/create-qr-code/?size=120x120&color=059669&bgcolor=FFFFFF&data=' . rawurlencode($voterRefUrl)      . '&qzone=1';
        $politicianQrSrc  = 'https://api.qrserver.com/v1/create-qr-code/?size=120x120&color=d97706&bgcolor=FFFFFF&data=' . rawurlencode($politicianRefUrl) . '&qzone=1';
    @endphp
    <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-6 space-y-7">
        <h2 class="text-lg font-semibold text-white">Share Your Referral Links</h2>

        {{-- Voter link --}}
        <div class="grid grid-cols-1 sm:grid-cols-[1fr_auto] gap-6 items-start">
            <div class="space-y-2">
                <p class="text-sm font-medium text-emerald-400">Voter Registration Link</p>
                <p class="text-slate-400 text-xs">Earn $0.025 per view forever for every voter you refer.</p>
                <div class="flex gap-2">
                    <input id="voter-referral-link" type="text" readonly
                        value="{{ $voterRefUrl }}"
                        class="flex-1 bg-slate-900 border border-slate-600 rounded-lg px-4 py-2.5 text-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    <button onclick="copyLink('voter-referral-link', 'voter-copy-confirm')"
                        class="bg-emerald-600 hover:bg-emerald-500 text-white px-4 py-2.5 rounded-lg text-sm font-medium transition whitespace-nowrap">
                        Copy
                    </button>
                </div>
                <p id="voter-copy-confirm" class="text-emerald-400 text-xs hidden">✓ Copied!</p>
            </div>
            {{-- Voter QR --}}
            <div class="flex flex-col items-center gap-2">
                <div class="w-32 h-32 bg-white rounded-xl p-1 shadow-lg shadow-black/40 flex items-center justify-center">
                    <img src="{{ $voterQrSrc }}"
                         alt="Voter Referral QR Code"
                         class="w-28 h-28 rounded-lg"
                         loading="lazy">
                </div>
                <a href="{{ $voterQrSrc }}&download=1"
                   download="voter-referral-qr.png"
                   class="text-xs text-emerald-400 hover:text-emerald-300 flex items-center gap-1 transition">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    Download PNG
                </a>
            </div>
        </div>

        <div class="border-t border-slate-700/50"></div>

        {{-- Politician link --}}
        <div class="grid grid-cols-1 sm:grid-cols-[1fr_auto] gap-6 items-start">
            <div class="space-y-2">
                <p class="text-sm font-medium text-amber-400">Politician Registration Link</p>
                <p class="text-slate-400 text-xs">Earn 10% of the first credit purchase when a politician you recruit funds their campaign.</p>
                <div class="flex gap-2">
                    <input id="politician-referral-link" type="text" readonly
                        value="{{ $politicianRefUrl }}"
                        class="flex-1 bg-slate-900 border border-slate-600 rounded-lg px-4 py-2.5 text-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500">
                    <button onclick="copyLink('politician-referral-link', 'politician-copy-confirm')"
                        class="bg-amber-600 hover:bg-amber-500 text-white px-4 py-2.5 rounded-lg text-sm font-medium transition whitespace-nowrap">
                        Copy
                    </button>
                </div>
                <p id="politician-copy-confirm" class="text-amber-400 text-xs hidden">✓ Copied!</p>
            </div>
            {{-- Politician QR --}}
            <div class="flex flex-col items-center gap-2">
                <div class="w-32 h-32 bg-white rounded-xl p-1 shadow-lg shadow-black/40 flex items-center justify-center">
                    <img src="{{ $politicianQrSrc }}"
                         alt="Politician Referral QR Code"
                         class="w-28 h-28 rounded-lg"
                         loading="lazy">
                </div>
                <a href="{{ $politicianQrSrc }}&download=1"
                   download="politician-referral-qr.png"
                   class="text-xs text-amber-400 hover:text-amber-300 flex items-center gap-1 transition">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    Download PNG
                </a>
            </div>
        </div>
    </div>

    {{-- ── Referred Voters Table ────────────────────────────────── --}}
    @if($referrals->isNotEmpty())
    <div>
        <h2 class="text-base font-semibold text-white mb-4">Referred Voters ({{ $referrals->count() }})</h2>
        <div class="bg-slate-800/50 border border-slate-700/60 rounded-2xl overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-700/60 text-slate-500 text-xs uppercase tracking-wide text-left">
                        <th class="px-5 py-3 font-medium">Name</th>
                        <th class="px-5 py-3 font-medium">Joined</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700/40">
                    @foreach($referrals as $referred)
                    <tr class="hover:bg-slate-700/20 transition">
                        <td class="px-5 py-4">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full bg-emerald-900/40 flex items-center justify-center text-emerald-400 text-xs font-bold shrink-0">
                                    {{ strtoupper(substr($referred->full_name ?? '?', 0, 1)) }}
                                </div>
                                <span class="text-white">{{ $referred->full_name ?? 'Anonymous' }}</span>
                            </div>
                        </td>
                        <td class="px-5 py-4 text-slate-400 text-sm">{{ $referred->created_at?->format('M j, Y') }}</td>
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
                    @foreach($referredPoliticians as $politician)
                    <tr class="hover:bg-slate-700/20 transition">
                        <td class="px-5 py-4">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full bg-amber-900/40 flex items-center justify-center text-amber-400 text-xs font-bold shrink-0">
                                    {{ strtoupper(substr($politician->full_name ?? '?', 0, 1)) }}
                                </div>
                                <span class="text-white">{{ $politician->full_name ?? 'Anonymous' }}</span>
                            </div>
                        </td>
                        <td class="px-5 py-4 text-slate-400">{{ $politician->political_office ?? '—' }}</td>
                        <td class="px-5 py-4">
                            @if($politician->procurementReferralEarning)
                                <span class="text-amber-400 font-medium">${{ number_format($politician->procurementReferralEarning->commission_amount, 2) }}</span>
                                <span class="text-xs text-slate-500 ml-1">paid</span>
                            @else
                                <span class="text-slate-500 text-xs">Pending first purchase</span>
                            @endif
                        </td>
                        <td class="px-5 py-4 text-slate-400 text-sm">{{ $politician->created_at?->format('M j, Y') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- ── Per-View Earnings History ─────────────────────────────── --}}
    @if($referralEarnings->isNotEmpty())
    <div>
        <h2 class="text-base font-semibold text-white mb-4">
            Voter-View Commissions
            <span class="text-emerald-400 text-sm font-normal ml-2">${{ number_format($totalReferralEarnings, 2) }} total</span>
        </h2>
        <div class="bg-slate-800/50 border border-slate-700/60 rounded-2xl overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-700/60 text-slate-500 text-xs uppercase tracking-wide text-left">
                        <th class="px-5 py-3 font-medium">Campaign</th>
                        <th class="px-5 py-3 font-medium">Commission</th>
                        <th class="px-5 py-3 font-medium">Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700/40">
                    @foreach($referralEarnings as $earning)
                    <tr class="hover:bg-slate-700/20 transition">
                        <td class="px-5 py-4 text-slate-300">{{ $earning->viewSession?->campaign?->title ?? '—' }}</td>
                        <td class="px-5 py-4 text-emerald-400 font-medium">${{ number_format($earning->commission_amount, 3) }}</td>
                        <td class="px-5 py-4 text-slate-400 text-xs">{{ $earning->created_at?->format('M j, Y') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- ── Procurement Earnings History ──────────────────────────── --}}
    @if($procurementEarnings->isNotEmpty())
    <div>
        <h2 class="text-base font-semibold text-white mb-4">
            Politician Procurement Commissions
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
    @if($referrals->isEmpty() && $referredPoliticians->isEmpty())
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-2xl p-10 text-center">
        <div class="w-12 h-12 rounded-full bg-slate-700/50 flex items-center justify-center mx-auto mb-4">
            <svg class="w-6 h-6 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                    d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
        </div>
        <p class="text-slate-400 text-sm font-medium">No referrals yet</p>
        <p class="text-slate-600 text-xs mt-1">Share the voter or politician links above to start earning!</p>
    </div>
    @endif

    @else
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-2xl p-10 text-center">
        <p class="text-slate-400">No voter profile found. Contact support.</p>
    </div>
    @endif

</div>

@push('scripts')
<script>
window.copyLink = function (inputId, confirmId) {
    const input = document.getElementById(inputId);
    if (!input) return;
    navigator.clipboard?.writeText(input.value).then(() => {
        const el = document.getElementById(confirmId);
        if (el) {
            el.classList.remove('hidden');
            setTimeout(() => el.classList.add('hidden'), 3000);
        }
    }).catch(() => {
        input.select();
        document.execCommand('copy');
    });
};
</script>
@endpush
@endsection
