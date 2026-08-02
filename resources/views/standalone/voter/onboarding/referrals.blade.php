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
        $voterShare = \App\Models\EmailTemplate::shareCopy('referral_voter_share', $voterLandingReferralUrl,
            'Join U9itus as a voter with my referral link',
            'Join U9itus as a voter using my referral link and start participating on the platform.');
        $politicianShare = \App\Models\EmailTemplate::shareCopy('referral_politician_share', $politicianLandingReferralUrl,
            'Join U9itus as a politician with my referral link',
            'Join U9itus as a politician using my referral link and launch your campaign presence on the platform.');
    @endphp
    <div class="space-y-6">
        <!-- Referral Benefits -->
        <div class="bg-gradient-to-r from-slate-800 to-indigo-900 rounded-lg p-6">
            <h3 class="text-xl font-bold text-white mb-2">Share U9itus with others</h3>
            <p class="text-gray-300 text-sm mb-4">Use your referral links below to invite voters and politicians to the platform. Your referral code is <strong class="text-emerald-400 font-mono">{{ $voter->referral_code ?? 'N/A' }}</strong>.</p>

            @include('standalone.voter.partials.earlybank-referral-cta', [
                'returnToRoute'   => route('voter.onboarding.referrals'),
                'upsellHeading'   => 'Want to earn referral commissions?',
                'upsellBody'       => 'Join <strong>Early-bank</strong> for a one-time $20 fee and get a dedicated referral link. Earn <strong class="text-indigo-300">10% recurring</strong> on their U9itus viewing activity — paid weekly via Stripe.',
                'upsellFootnote'  => '',
                'enrolledHeading' => "You're already an Early-bank member",
                'enrolledBody'     => 'Your referral commissions flow through Early-bank. Access your referral dashboard, QR code, and weekly payout status directly below.',
                'showMemberId'     => false,
            ])
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
                    'shareSubject' => $voterShare['subject'],
                    'shareMessage' => $voterShare['message'],
                    'shareBody' => $voterShare['body'],
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
                    'shareSubject' => $politicianShare['subject'],
                    'shareMessage' => $politicianShare['message'],
                    'shareBody' => $politicianShare['body'],
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
