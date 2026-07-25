{{-- Early-bank referral CTA block.
     Shown on the voter referrals page (and the voter onboarding referrals step)
     in two states:
       1. Voter is NOT an Early-bank member (earlybank_own_member_uuid is null)
          → upsell: join to unlock recurring voter-view commissions.
       2. Voter IS an Early-bank member (earlybank_own_member_uuid is set)
          → confirmation + link to their Early-bank dashboard.
     Note: earlybank_member_id (different field) tracks who REFERRED this voter
     and is unrelated to whether the voter holds their own EB membership.

     Optional overrides (defaults reproduce the main voter referrals page):
       $returnToRoute     — route EB redirects back to with ?eb_member={member_uuid}
       $upsellHeading     $upsellBody     $upsellFootnote
       $enrolledHeading   $enrolledBody
       $showMemberId      — show the "Member ID" badge in the enrolled state
--}}
@php
    $earlybankUrl = rtrim(config('services.earlybank.public_url', 'https://www.early-bank.com'), '/');
    $returnToRoute    = $returnToRoute    ?? route('voter.referrals');
    $upsellHeading    = $upsellHeading    ?? 'Earn more with Early-bank';
    $upsellBody       = $upsellBody       ?? 'Join Early-bank for a one-time $20 fee and get a dedicated referral link. Earn a <strong class="text-indigo-300">$10 bonus</strong> every time someone you invite joins Early-bank, plus <strong class="text-indigo-300">10% recurring</strong> on all of their U9itus viewing activity — paid weekly via Stripe.';
    $upsellFootnote   = $upsellFootnote   ?? 'Your existing U9itus referrals are unaffected.';
    $enrolledHeading  = $enrolledHeading ?? "You're an Early-bank member";
    $enrolledBody     = $enrolledBody     ?? 'Your referral commissions and voter-view earnings flow through Early-bank. Access your full dashboard, QR code, referral link, and weekly payout status below.';
    $showMemberId     = $showMemberId     ?? true;
@endphp

@if(empty($voter->earlybank_own_member_uuid))
{{-- ── Upsell: not yet an Early-bank member ────────────────────────────── --}}
<div class="bg-indigo-900/20 border border-indigo-500/30 rounded-2xl p-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
    <div class="flex items-start gap-3 min-w-0">
        <div class="w-9 h-9 rounded-lg bg-indigo-500/20 flex items-center justify-center shrink-0 mt-0.5">
            <svg class="w-5 h-5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
            </svg>
        </div>
        <div class="min-w-0">
            <p class="text-indigo-200 font-semibold text-sm">
                {{ $upsellHeading }}
            </p>
            <p class="text-slate-300 text-sm mt-1">
                {!! $upsellBody !!}
            </p>
            @if($upsellFootnote)
            <p class="text-slate-500 text-xs mt-1.5">
                {{ $upsellFootnote }}
            </p>
            @endif
        </div>
    </div>
    <a href="{{ $earlybankUrl . '/register?u9itus_voter_uuid=' . ($voter->uuid ?? '') . '&return_to=' . urlencode($returnToRoute . '?eb_member={member_uuid}') }}"
       target="_blank"
       rel="noopener noreferrer"
       class="shrink-0 inline-flex items-center justify-center gap-2 bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-semibold px-4 py-2.5 rounded-xl transition whitespace-nowrap">
        Join Early-bank to Earn
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
        </svg>
    </a>
</div>

@else
{{-- ── Enrolled: voter is already an Early-bank member ────────────────── --}}
@php
    // SSO disabled: earlybank.com has no /sso route (see doc/EARLYBANK_INTEGRATION.md
    // Phase 2 route table) — voter.earlybank.sso currently redirects to a 404/400.
    // Re-enable once earlybank.com implements the signed SSO endpoint.
    $ebSsoAvailable = false;
    $ebDashboardHref = $ebSsoAvailable
        ? route('voter.earlybank.sso')
        : $earlybankUrl . '/dashboard';
@endphp
<div class="bg-emerald-900/20 border border-emerald-500/20 rounded-2xl p-5">
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
        {{-- Left: status + copy --}}
        <div class="flex items-start gap-3 min-w-0">
            <div class="w-9 h-9 rounded-lg bg-emerald-500/20 flex items-center justify-center shrink-0 mt-0.5">
                <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <div class="min-w-0">
                <p class="text-emerald-200 font-semibold text-sm">{{ $enrolledHeading }}</p>
                <p class="text-slate-300 text-sm mt-1">
                    {!! $enrolledBody !!}
                </p>
                @if($showMemberId)
                {{-- Member UUID badge --}}
                <p class="text-slate-500 text-xs mt-2 font-mono truncate" title="{{ $voter->earlybank_own_member_uuid }}">
                    Member ID: <span class="text-slate-400">{{ $voter->earlybank_own_member_uuid }}</span>
                </p>
                @endif
                @if($voter->earlybank_own_linked_at)
                <p class="text-slate-600 text-xs mt-0.5">
                    Linked {{ $voter->earlybank_own_linked_at->format('M j, Y') }}
                </p>
                @endif
            </div>
        </div>

        {{-- Right: CTA button --}}
        <a href="{{ $ebDashboardHref }}"
           @if(!$ebSsoAvailable) target="_blank" rel="noopener noreferrer" @endif
           class="shrink-0 inline-flex items-center justify-center gap-2 bg-emerald-600 hover:bg-emerald-500 text-white text-sm font-semibold px-4 py-2.5 rounded-xl transition whitespace-nowrap self-start sm:self-center">
            @if($ebSsoAvailable)
            Open My Dashboard
            {{-- Arrow-right icon (no external-link icon since it's same-tab SSO) --}}
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
            </svg>
            @else
            Early-bank Dashboard
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
            </svg>
            @endif
        </a>
    </div>
</div>
@endif