@php
    $hideUntilRaw = (string) config('u9itus.authentic_user_verifier_action_hide_until', '');
    $hideActionPrompt = false;

    if ($hideUntilRaw !== '') {
        try {
            $hideActionPrompt = now()->lt(\Illuminate\Support\Carbon::parse($hideUntilRaw));
        } catch (\Throwable $e) {
            $hideActionPrompt = false;
        }
    }

    // If Stripe Connect is not wired up, hide the CTA rather than letting
    // voters click into a guaranteed error.
    $stripeConnectReady = ! empty(config('services.stripe.secret'));

    // A voter who has a stripe_account_id but hasn't reached 'active' status has
    // already started the flow — their verification is in-progress / pending Stripe
    // confirmation. Do NOT show the "Action Required" prompt; show a softer notice.
    $auvInProgress = !empty($voter->stripe_account_id)
        && ($voter->stripe_account_status ?? '') !== 'active';
@endphp

@if($auvInProgress && !empty($needsAuthenticUserVerifierMigration))
{{-- Verification submitted / pending Stripe confirmation ──────────────── --}}
<div class="bg-sky-500/10 border border-sky-400/20 rounded-2xl p-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
    <div class="min-w-0">
        <p class="text-sky-200 font-semibold text-sm">Verification In Progress</p>
        <p class="text-slate-300 text-sm mt-1">
            Your identity verification has been submitted and is being reviewed by Stripe.
            This usually completes within a few minutes. No action is needed — refresh this page to check your status.
        </p>
        <p class="text-slate-500 text-xs mt-1">If it's been more than 24 hours, you can restart the flow below.</p>
    </div>
    <form method="POST" action="{{ route('voter.authentic-user-verifier.start') }}" class="shrink-0">
        @csrf
        <button type="submit"
                class="inline-flex items-center justify-center gap-2 bg-sky-700 hover:bg-sky-600 text-white text-sm font-semibold px-4 py-2 rounded-xl transition">
            Continue Verification
        </button>
    </form>
</div>
@elseif(!empty($needsAuthenticUserVerifierMigration) && !$hideActionPrompt && $stripeConnectReady)
{{-- Action required: legacy holder who hasn't started the flow yet ─────── --}}
<div class="bg-cyan-500/10 border border-cyan-400/30 rounded-2xl p-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
    <div class="min-w-0">
        <p class="text-cyan-200 font-semibold text-sm">Action Required: Complete Authentic User Verifier</p>
        <p class="text-slate-300 text-sm mt-1">
            Your account was verified under a legacy system. To keep payouts active, complete the
            <span class="text-cyan-300 font-medium">Authentic User Verifier</span> flow powered by Stripe Connect.
        </p>
        <p class="text-slate-500 text-xs mt-1">Legacy verification records remain read-only for historical reference.</p>
    </div>
    <form method="POST" action="{{ route('voter.authentic-user-verifier.start') }}" class="shrink-0">
        @csrf
        <button type="submit"
                class="inline-flex items-center justify-center gap-2 bg-cyan-600 hover:bg-cyan-500 text-white text-sm font-semibold px-4 py-2 rounded-xl transition">
            Start Authentic User Verifier
        </button>
    </form>
</div>
@endif
