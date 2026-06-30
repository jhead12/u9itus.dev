{{-- Early-bank referral enrollment banner.
     Shown on the voter earnings page whenever the voter signed up through
     an Early-bank referral link. Confirms enrollment so the voter knows
     their referrer is credited but their own $0.25/view payout is unchanged.
--}}
@if(!empty($voter) && !empty($voter->earlybank_member_id))
<div class="bg-emerald-500/10 border border-emerald-400/30 rounded-2xl p-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
    <div class="flex items-start gap-3 min-w-0">
        <div class="w-9 h-9 rounded-lg bg-emerald-500/20 flex items-center justify-center shrink-0 mt-0.5">
            <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
        </div>
        <div class="min-w-0">
            <p class="text-emerald-200 font-semibold text-sm">
                You're enrolled in the Early-bank referral program
            </p>
            <p class="text-slate-300 text-sm mt-1">
                You signed up through an Early-bank referral link. Your payouts are unchanged
                ($0.25 per verified view) — the link simply credits the member who invited you.
            </p>
            @if($voter->earlybank_linked_at)
            <p class="text-slate-500 text-xs mt-1">
                Linked {{ $voter->earlybank_linked_at->format('M j, Y') }}
            </p>
            @endif
        </div>
    </div>
    <a href="https://early-bank.com"
       target="_blank"
       rel="noopener noreferrer"
       class="shrink-0 inline-flex items-center justify-center gap-2 bg-emerald-600 hover:bg-emerald-500 text-white text-sm font-semibold px-4 py-2 rounded-xl transition">
        Learn More
    </a>
</div>
@endif
