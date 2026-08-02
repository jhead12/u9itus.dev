@extends('layouts.voter')

@section('title', 'My Referrals')

@section('content')
<div class="px-4 sm:px-6 lg:px-8 py-8 max-w-4xl mx-auto space-y-7">

    <div>
        <h1 class="text-2xl font-bold text-white">Referrals</h1>
        <p class="text-slate-400 text-sm mt-0.5">Share U9itus with others — referral commissions are earned through Early-bank</p>
    </div>

    @if($voter)

    {{-- ── Early-bank CTA / enrollment status (always first) ──── --}}
    @include('standalone.voter.partials.earlybank-referral-cta')

    {{-- ── Activity Summary ─────────────────────────────────────── --}}
    @include('standalone.shared.referral-stat-grid', [
        'gridClass' => 'grid-cols-2 sm:grid-cols-3',
        'cards' => [
            ['label' => 'Referral Code', 'value' => $voter->referral_code, 'valueClass' => 'text-emerald-400 tracking-widest font-mono'],
            ['label' => 'Voters Referred', 'value' => $referrals->count()],
            ['label' => 'Politicians Recruited', 'value' => $referredPoliticians->count()],
        ],
    ])

    @include('standalone.shared.referral-stat-grid', [
        'gridClass' => 'grid-cols-2 sm:grid-cols-4',
        'cards' => [
            ['label' => 'Referral Visits', 'value' => number_format($totalReferralVisits)],
            ['label' => 'Unique Visitors', 'value' => number_format($uniqueReferralVisitors)],
            ['label' => 'Conversions', 'value' => number_format($referralConversions), 'valueClass' => 'text-emerald-400'],
            ['label' => 'Conversion Rate', 'value' => number_format($referralConversionRate, 1) . '%', 'valueClass' => 'text-emerald-400'],
        ],
    ])

    {{-- ── Early-bank Reported Earnings ─────────────────────────── --}}
    {{-- Bonuses card hidden until the bonus program is fully defined on the
         Early-bank side — $ebBonusTotal is still computed below in case admin
         reports or a future re-enable need it. --}}
    @include('standalone.shared.referral-stat-grid', [
        'gridClass' => 'grid-cols-1',
        'cards' => [
            ['label' => 'Early-bank Commissions', 'value' => '$' . number_format($ebCommissionTotal, 2), 'valueClass' => 'text-emerald-400'],
        ],
    ])

    {{-- ── Share Links ──────────────────────────────────────────── --}}
    @php
        // Early-bank member links — only built when this voter holds an EB membership.
        // EB invite:        early-bank.com/?ref=<uuid>  → recruits new EB members
        // U9itus earn link: u9itus.com/earn?ref=<uuid>  → CaptureEarlyBankReferral middleware
        //                   stores the UUID cookie so new registrants are attributed to this member.
        // NOTE: the flat join bonus amount is not yet finalized on the Early-bank
        // side — don't quote a dollar figure in this page's copy until it is.
        $ebMemberUuid  = $voter->earlybank_own_member_uuid ?? null;
        $ebBaseUrl     = rtrim((string) config('services.earlybank.public_url', 'https://www.early-bank.com'), '/');
        $ebInviteUrl   = $ebMemberUuid ? ($ebBaseUrl . '/?ref=' . $ebMemberUuid)  : null;
        $ebEarnUrl     = $ebMemberUuid ? (url('/earn') . '?ref=' . $ebMemberUuid) : null;
        $ebInviteQrSrc = $ebInviteUrl ? 'https://api.qrserver.com/v1/create-qr-code/?size=120x120&color=059669&bgcolor=FFFFFF&data=' . rawurlencode($ebInviteUrl) . '&qzone=1' : null;
        $ebEarnQrSrc   = $ebEarnUrl   ? 'https://api.qrserver.com/v1/create-qr-code/?size=120x120&color=6366f1&bgcolor=FFFFFF&data=' . rawurlencode($ebEarnUrl)   . '&qzone=1' : null;
        $ebInviteShareSubject = 'Join Early-bank and earn referral commissions';
        $ebInviteShareMessage = 'Join Early-bank to earn referral commissions — you\'ll also be automatically enrolled in U9itus.';
        $ebInviteShareBody    = $ebInviteShareMessage . "\n\n" . ($ebInviteUrl ?? '');
        $ebEarnShareSubject   = 'Join U9itus and start earning with my referral link';
        $ebEarnShareMessage   = 'Join U9itus as a Citizen, Voter, or Politician using my referral link. You earn real money watching political content.';
        $ebEarnShareBody      = $ebEarnShareMessage . "\n\n" . ($ebEarnUrl ?? '');
    @endphp

    {{-- ── Early-bank Referral Links (EB members only) ─────────── --}}
    @if($ebMemberUuid)
    <div class="bg-emerald-950/40 border border-emerald-700/30 rounded-xl p-6 space-y-7">
        <div class="flex items-center justify-between gap-4 flex-wrap">
            <div>
                <h2 class="text-lg font-semibold text-emerald-200">Your Early-bank Referral Links</h2>
                <p class="text-slate-400 text-sm mt-0.5">Tied to your Early-bank membership — share these to earn 10% recurring commissions.</p>
            </div>
            <a href="{{ route('voter.earlybank.sso') }}"
               class="shrink-0 text-xs text-emerald-400 hover:text-emerald-300 flex items-center gap-1.5 transition">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
                Open EB Dashboard
            </a>
        </div>

        {{-- EB invite link --}}
        <div class="grid grid-cols-1 sm:grid-cols-[1fr_auto] gap-6 items-start">
            <div class="space-y-2">
                <p class="text-sm font-medium text-emerald-400">Your Early-bank Invite Link</p>
                <p class="text-slate-400 text-xs">Share with anyone who wants to join Early-bank and start earning referral commissions themselves. When they pay their $20 activation fee, they're automatically enrolled into U9itus too.</p>
                <div class="flex gap-2">
                    <input id="eb-invite-link" type="text" readonly value="{{ $ebInviteUrl }}"
                        class="flex-1 bg-slate-900 border border-emerald-700/40 rounded-lg px-4 py-2.5 text-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    <button onclick="copyLink('eb-invite-link', 'eb-invite-confirm')"
                        class="bg-emerald-600 hover:bg-emerald-500 text-white px-4 py-2.5 rounded-lg text-sm font-medium transition whitespace-nowrap">Copy</button>
                </div>
                <p id="eb-invite-confirm" class="text-emerald-400 text-xs hidden">✓ Copied!</p>
                @include('standalone.shared.referral-share-actions', [
                    'shareLink'    => $ebInviteUrl,
                    'shareSubject' => $ebInviteShareSubject,
                    'shareMessage' => $ebInviteShareMessage,
                    'shareBody'    => $ebInviteShareBody,
                ])
            </div>
            <div class="flex flex-col items-center gap-2">
                <div class="w-32 h-32 bg-white rounded-xl p-1 shadow-lg shadow-black/40 flex items-center justify-center">
                    <img src="{{ $ebInviteQrSrc }}" alt="Early-bank Invite QR Code" class="w-28 h-28 rounded-lg" loading="lazy">
                </div>
                <a href="{{ $ebInviteQrSrc }}&download=1" download="eb-invite-qr.png"
                   class="text-xs text-emerald-400 hover:text-emerald-300 flex items-center gap-1 transition">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    Download PNG
                </a>
            </div>
        </div>

        <div class="border-t border-emerald-800/40"></div>

        {{-- U9itus earn link (EB-attributed) --}}
        <div class="grid grid-cols-1 sm:grid-cols-[1fr_auto] gap-6 items-start">
            <div class="space-y-2">
                <p class="text-sm font-medium text-indigo-400">Your U9itus Referral Link</p>
                <p class="text-slate-400 text-xs">Share with anyone who wants to join U9itus as a Citizen, Voter, or Politician. You earn <strong class="text-indigo-300">10%</strong> of every U9itus viewing session your referred subscribers complete.</p>
                <div class="flex gap-2">
                    <input id="eb-earn-link" type="text" readonly value="{{ $ebEarnUrl }}"
                        class="flex-1 bg-slate-900 border border-indigo-700/40 rounded-lg px-4 py-2.5 text-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <button onclick="copyLink('eb-earn-link', 'eb-earn-confirm')"
                        class="bg-indigo-600 hover:bg-indigo-500 text-white px-4 py-2.5 rounded-lg text-sm font-medium transition whitespace-nowrap">Copy</button>
                </div>
                <p id="eb-earn-confirm" class="text-indigo-400 text-xs hidden">✓ Copied!</p>
                @include('standalone.shared.referral-share-actions', [
                    'shareLink'    => $ebEarnUrl,
                    'shareSubject' => $ebEarnShareSubject,
                    'shareMessage' => $ebEarnShareMessage,
                    'shareBody'    => $ebEarnShareBody,
                ])
            </div>
            <div class="flex flex-col items-center gap-2">
                <div class="w-32 h-32 bg-white rounded-xl p-1 shadow-lg shadow-black/40 flex items-center justify-center">
                    <img src="{{ $ebEarnQrSrc }}" alt="U9itus Earn QR Code" class="w-28 h-28 rounded-lg" loading="lazy">
                </div>
                <a href="{{ $ebEarnQrSrc }}&download=1" download="u9itus-earn-qr.png"
                   class="text-xs text-indigo-400 hover:text-indigo-300 flex items-center gap-1 transition">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    Download PNG
                </a>
            </div>
        </div>
    </div>
    @endif

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
                                <span class="text-white">{{ \App\Support\Name::firstNameLastInitial($referred->full_name) }}</span>
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
                                <span class="text-white">{{ \App\Support\Name::firstNameLastInitial($politician->full_name) }}</span>
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
@include('standalone.shared.copy-link-script')
@endpush
@endsection
