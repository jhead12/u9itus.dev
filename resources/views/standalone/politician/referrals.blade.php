@extends('standalone.layouts.dashboard')

@section('title', 'My Referrals')
@section('page-title', 'Referrals')

@section('content')
@php
    $earlybankUrl = rtrim(config('services.earlybank.public_url', 'https://www.early-bank.com'), '/');
@endphp
<div class="space-y-7 max-w-4xl">

    <div>
        <h1 class="text-2xl font-bold text-white">Referrals</h1>
        <p class="text-slate-400 text-sm mt-0.5">Share U9itus with others — referral commissions are earned through Early-bank</p>
    </div>

    {{-- ── Early-bank CTA ────────────────────────────────────────────── --}}
    @if(empty($politician->earlybank_own_member_uuid))
    <div class="bg-indigo-900/20 border border-indigo-500/30 rounded-2xl p-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div class="flex items-start gap-3 min-w-0">
            <div class="w-9 h-9 rounded-lg bg-indigo-500/20 flex items-center justify-center shrink-0 mt-0.5">
                <svg class="w-5 h-5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                </svg>
            </div>
            <div class="min-w-0">
                <p class="text-indigo-200 font-semibold text-sm">Earn from your referrals with Early-bank</p>
                <p class="text-slate-300 text-sm mt-1">
                    Join Early-bank for a one-time $20 fee and get a dedicated referral link.
                    Earn a <strong class="text-indigo-300">$10 bonus</strong> every time someone you invite joins,
                    plus <strong class="text-indigo-300">10% recurring</strong> on their U9itus viewing activity.
                </p>
                <p class="text-slate-500 text-xs mt-1.5">Your existing U9itus referrals are unaffected.</p>
            </div>
        </div>
        <a href="{{ $earlybankUrl }}"
           target="_blank" rel="noopener noreferrer"
           class="shrink-0 inline-flex items-center justify-center gap-2 bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-semibold px-4 py-2.5 rounded-xl transition whitespace-nowrap">
            Learn More
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
            </svg>
        </a>
    </div>
    @else
    <div class="bg-emerald-900/20 border border-emerald-500/20 rounded-2xl p-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div class="flex items-start gap-3 min-w-0">
            <div class="w-9 h-9 rounded-lg bg-emerald-500/20 flex items-center justify-center shrink-0 mt-0.5">
                <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <div class="min-w-0">
                <p class="text-emerald-200 font-semibold text-sm">You're an Early-bank member</p>
                <p class="text-slate-300 text-sm mt-1">Your referral commissions flow through Early-bank. Log in to view your dashboard, QR code, and payout status.</p>
                @if($politician->earlybank_own_linked_at)
                <p class="text-slate-500 text-xs mt-1">Linked {{ $politician->earlybank_own_linked_at->format('M j, Y') }}</p>
                @endif
            </div>
        </div>
        <a href="{{ $earlybankUrl }}/dashboard"
           target="_blank" rel="noopener noreferrer"
           class="shrink-0 inline-flex items-center justify-center gap-2 bg-emerald-600 hover:bg-emerald-500 text-white text-sm font-semibold px-4 py-2.5 rounded-xl transition whitespace-nowrap">
            Early-bank Dashboard
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
            </svg>
        </a>
    </div>
    @endif

    {{-- ── Activity Summary ──────────────────────────────────────────── --}}
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
            <p class="text-slate-400 text-xs font-medium uppercase tracking-wide">Conversions</p>
            <p class="text-xl font-bold text-emerald-400 mt-2">{{ number_format($referralConversions) }}</p>
        </div>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="bg-slate-800/50 border border-slate-700/60 rounded-2xl p-4">
            <p class="text-slate-400 text-xs font-medium uppercase tracking-wide">Referral Visits</p>
            <p class="text-xl font-bold text-white mt-2">{{ number_format($totalReferralVisits) }}</p>
        </div>
        <div class="bg-slate-800/50 border border-slate-700/60 rounded-2xl p-4">
            <p class="text-slate-400 text-xs font-medium uppercase tracking-wide">Unique Visitors</p>
            <p class="text-xl font-bold text-white mt-2">{{ number_format($uniqueReferralVisitors) }}</p>
        </div>
        <div class="bg-slate-800/50 border border-slate-700/60 rounded-2xl p-4">
            <p class="text-slate-400 text-xs font-medium uppercase tracking-wide">Conversions</p>
            <p class="text-xl font-bold text-emerald-400 mt-2">{{ number_format($referralConversions) }}</p>
        </div>
        <div class="bg-slate-800/50 border border-slate-700/60 rounded-2xl p-4">
            <p class="text-slate-400 text-xs font-medium uppercase tracking-wide">Conversion Rate</p>
            <p class="text-xl font-bold text-emerald-400 mt-2">{{ number_format($referralConversionRate, 1) }}%</p>
        </div>
    </div>

    {{-- ── Share Links ───────────────────────────────────────────── --}}
    @php
        $voterRefUrl = url('/?ref=' . ($politician->referral_code ?? '') . '&target=voter');
        $politicianRefUrl = url('/?ref=' . ($politician->referral_code ?? '') . '&target=politician');
        $voterQrSrc  = 'https://api.qrserver.com/v1/create-qr-code/?size=120x120&color=059669&bgcolor=FFFFFF&data=' . rawurlencode($voterRefUrl) . '&qzone=1';
        $politicianQrSrc  = 'https://api.qrserver.com/v1/create-qr-code/?size=120x120&color=d97706&bgcolor=FFFFFF&data=' . rawurlencode($politicianRefUrl) . '&qzone=1';
        $voterTpl = \App\Models\EmailTemplate::forKey('referral_voter_share');
        $politicianTpl = \App\Models\EmailTemplate::forKey('referral_politician_share');
        $voterShareSubject = ($voterTpl && $voterTpl->is_active && $voterTpl->subject_override)
            ? $voterTpl->subject_override
            : 'Join U9itus as a voter with my referral link';
        $voterShareMessage = ($voterTpl && $voterTpl->is_active && $voterTpl->body_override)
            ? $voterTpl->body_override
            : 'Join U9itus as a voter using my referral link and start participating on the platform.';
        $voterShareBody = $voterShareMessage . "\n\n" . $voterRefUrl;
        $politicianShareSubject = ($politicianTpl && $politicianTpl->is_active && $politicianTpl->subject_override)
            ? $politicianTpl->subject_override
            : 'Join U9itus as a politician with my referral link';
        $politicianShareMessage = ($politicianTpl && $politicianTpl->is_active && $politicianTpl->body_override)
            ? $politicianTpl->body_override
            : 'Join U9itus as a politician using my referral link and launch your campaign presence on the platform.';
        $politicianShareBody = $politicianShareMessage . "\n\n" . $politicianRefUrl;
    @endphp
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-2xl p-6 space-y-6">
        <h2 class="text-base font-semibold text-white">Your Referral Links</h2>

        {{-- Voter link --}}
        <div class="grid grid-cols-1 sm:grid-cols-[1fr_auto] gap-6 items-start">
            <div class="space-y-2">
                <p class="text-sm font-medium text-emerald-400">Voter Registration Link</p>
                <p class="text-slate-400 text-xs">Invite others to join U9itus as a voter — earn commissions via Early-bank.</p>
                <div class="flex gap-2">
                    <input id="voter-referral-link" type="text" readonly
                        value="{{ $voterRefUrl }}"
                        class="flex-1 bg-slate-900 border border-slate-600 rounded-lg px-4 py-2.5 text-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    <button onclick="copyLink('voter-referral-link')"
                        class="shrink-0 px-4 py-2.5 bg-emerald-500/20 hover:bg-emerald-500/30 text-emerald-400 text-sm rounded-lg transition border border-emerald-500/30 font-medium">
                        Copy
                    </button>
                </div>
                @include('standalone.shared.referral-share-actions', [
                    'shareLink' => $voterRefUrl,
                    'shareSubject' => $voterShareSubject,
                    'shareMessage' => $voterShareMessage,
                    'shareBody' => $voterShareBody,
                ])
            </div>
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
                <p class="text-slate-400 text-xs">Invite other politicians to join U9itus — politician recruitment commissions coming via Early-bank.</p>
                <div class="flex gap-2">
                    <input id="politician-referral-link" type="text" readonly
                        value="{{ $politicianRefUrl }}"
                        class="flex-1 bg-slate-900 border border-slate-600 rounded-lg px-4 py-2.5 text-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500">
                    <button onclick="copyLink('politician-referral-link')"
                        class="shrink-0 px-4 py-2.5 bg-amber-500/20 hover:bg-amber-500/30 text-amber-400 text-sm rounded-lg transition border border-amber-500/30 font-medium">
                        Copy
                    </button>
                </div>
                @include('standalone.shared.referral-share-actions', [
                    'shareLink' => $politicianRefUrl,
                    'shareSubject' => $politicianShareSubject,
                    'shareMessage' => $politicianShareMessage,
                    'shareBody' => $politicianShareBody,
                ])
            </div>
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
