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
@endphp

@if(!empty($needsAuthenticUserVerifierMigration) && !$hideActionPrompt)
<div class="bg-cyan-500/10 border border-cyan-400/30 rounded-2xl p-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
    <div class="min-w-0">
        <p class="text-cyan-200 font-semibold text-sm">Action Required: Complete Authentic User Verifier</p>
        <p class="text-slate-300 text-sm mt-1">
            Your account was verified under a legacy system. To keep payouts active, complete the
            <span class="text-cyan-300 font-medium">Authentic User Verifier</span> flow powered by Stripe Connect.
        </p>
        <p class="text-slate-500 text-xs mt-1">Legacy verification records remain read-only for historical reference.</p>
    </div>
    <a href="{{ route('voter.authentic-user-verifier.start') }}"
       class="shrink-0 inline-flex items-center justify-center gap-2 bg-cyan-600 hover:bg-cyan-500 text-white text-sm font-semibold px-4 py-2 rounded-xl transition">
        Start Authentic User Verifier
    </a>
</div>
@endif
