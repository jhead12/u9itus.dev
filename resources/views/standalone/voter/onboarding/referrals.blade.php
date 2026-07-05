<x-onboarding-layout
    :progress="$progress"
    :phases="$phases"
    :total-phases="$totalPhases"
    current-phase="referral_setup"
    title="Get Your Referral Links"
    description="Earn passive income by referring others"
>
    @php
        $voterLandingReferralUrl = url('/?ref=' . ($voter->referral_code ?? '') . '&target=voter');
        $politicianLandingReferralUrl = url('/?ref=' . ($voter->referral_code ?? '') . '&target=politician');
        $viewerPayoutPerView = (float) \App\Services\PlatformSettingsService::get('viewer_payout_per_view', null, (float) config('u9itus.viewer_payout_per_view', 0.50));
        $referralCommissionPercent = (float) \App\Services\PlatformSettingsService::get('referral_commission_percent', null, config('u9itus.referral_commission_percent', 10));
        $referralPerViewAmount = $viewerPayoutPerView * ($referralCommissionPercent / 100);
        $referralPerViewAmountDecimals = $referralPerViewAmount < 0.1 ? 3 : 2;
        $voterTpl = \App\Models\EmailTemplate::forKey('referral_voter_share');
        $politicianTpl = \App\Models\EmailTemplate::forKey('referral_politician_share');
        $voterShareSubject = ($voterTpl && $voterTpl->is_active && $voterTpl->subject_override)
            ? $voterTpl->subject_override
            : 'Join U9itus as a voter with my referral link';
        $voterShareMessage = ($voterTpl && $voterTpl->is_active && $voterTpl->body_override)
            ? $voterTpl->body_override
            : 'Join U9itus as a voter using my referral link and start participating on the platform.';
        $voterShareBody = $voterShareMessage . "\n\n" . $voterLandingReferralUrl;
        $politicianShareSubject = ($politicianTpl && $politicianTpl->is_active && $politicianTpl->subject_override)
            ? $politicianTpl->subject_override
            : 'Join U9itus as a politician with my referral link';
        $politicianShareMessage = ($politicianTpl && $politicianTpl->is_active && $politicianTpl->body_override)
            ? $politicianTpl->body_override
            : 'Join U9itus as a politician using my referral link and launch your campaign presence on the platform.';
        $politicianShareBody = $politicianShareMessage . "\n\n" . $politicianLandingReferralUrl;
    @endphp
    <div class="space-y-6">
        <!-- Referral Benefits -->
        <div class="bg-gradient-to-r from-slate-800 to-indigo-900 rounded-lg p-6">
            <h3 class="text-xl font-bold text-white mb-2">Share U9itus with others</h3>
            <p class="text-gray-300 text-sm mb-4">Use your referral links below to invite voters and politicians to the platform. Your referral code is <strong class="text-emerald-400 font-mono">{{ $voter->referral_code ?? 'N/A' }}</strong>.</p>

            <div class="bg-indigo-900/60 border border-indigo-500/30 rounded-xl p-5">
                <div class="flex items-start gap-3">
                    <div class="w-9 h-9 rounded-lg bg-indigo-500/20 flex items-center justify-center shrink-0 mt-0.5">
                        <svg class="w-5 h-5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-indigo-200 font-semibold text-sm">Want to earn referral commissions?</p>
                        <p class="text-gray-300 text-sm mt-1">
                            Join <strong>Early-bank</strong> for a one-time $20 fee and get a dedicated referral link.
                            Earn a <strong class="text-indigo-300">$10 bonus</strong> each time someone you invite joins,
                            plus <strong class="text-indigo-300">10% recurring</strong> on their U9itus viewing activity — paid weekly via Stripe.
                        </p>
                        <a href="{{ rtrim(config('services.earlybank.public_url', 'https://earlybank.com'), '/') . '/register?return_to=' . urlencode(route('voter.onboarding.referrals')) }}"
                           target="_blank" rel="noopener noreferrer"
                           class="inline-flex items-center gap-2 mt-3 bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-semibold px-4 py-2 rounded-xl transition">
                            Join Early-bank to Earn
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                            </svg>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Referral Links -->
        <div class="bg-gray-700 rounded-lg p-6 space-y-4">
            <div>
                <label for="onboarding-voter-ref-link" class="block text-sm font-medium text-gray-300 mb-2">Voter Referral Link</label>
                <div class="flex space-x-2">
                    <input id="onboarding-voter-ref-link" type="text" readonly
                           value="{{ $voterLandingReferralUrl }}"
                           class="flex-1 bg-gray-800 border border-gray-600 rounded-lg px-4 py-2 text-gray-300 text-sm">
                    <button type="button" onclick="copyToClipboard(this, '{{ $voterLandingReferralUrl }}')"
                            class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
                        Copy
                    </button>
                </div>
                @include('standalone.shared.referral-share-actions', [
                    'shareLink' => $voterLandingReferralUrl,
                    'shareSubject' => $voterShareSubject,
                    'shareMessage' => $voterShareMessage,
                    'shareBody' => $voterShareBody,
                ])
            </div>

            <div>
                <label for="onboarding-politician-ref-link" class="block text-sm font-medium text-gray-300 mb-2">Politician Referral Link</label>
                <div class="flex space-x-2">
                    <input id="onboarding-politician-ref-link" type="text" readonly
                           value="{{ $politicianLandingReferralUrl }}"
                           class="flex-1 bg-gray-800 border border-gray-600 rounded-lg px-4 py-2 text-gray-300 text-sm">
                    <button type="button" onclick="copyToClipboard(this, '{{ $politicianLandingReferralUrl }}')"
                            class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
                        Copy
                    </button>
                </div>
                @include('standalone.shared.referral-share-actions', [
                    'shareLink' => $politicianLandingReferralUrl,
                    'shareSubject' => $politicianShareSubject,
                    'shareMessage' => $politicianShareMessage,
                    'shareBody' => $politicianShareBody,
                ])
            </div>
        </div>

        <form method="POST" action="{{ route('voter.onboarding.complete-referrals') }}">
            @csrf
            <button type="submit"
                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-6 rounded-lg transition duration-200">
                Complete Onboarding →
            </button>
        </form>
    </div>

    <script>
        function copyToClipboard(button, text) {
            navigator.clipboard.writeText(text).then(() => {
                const originalText = button.textContent;
                button.textContent = 'Copied!';
                button.classList.add('bg-green-600');
                button.classList.remove('bg-blue-600');
                
                setTimeout(() => {
                    button.textContent = originalText;
                    button.classList.remove('bg-green-600');
                    button.classList.add('bg-blue-600');
                }, 2000);
            });
        }
    </script>
</x-onboarding-layout>
