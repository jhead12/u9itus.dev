<x-onboarding-layout
    :progress="$progress"
    :phases="$phases"
    :total-phases="$totalPhases"
    current-phase="referral_setup"
    title="Get Your Referral Links"
    description="Earn passive income by referring others"
>
    <div class="space-y-6">
        <!-- Referral Benefits -->
        <div class="bg-gradient-to-r from-green-900 to-blue-900 rounded-lg p-6">
            <h3 class="text-xl font-bold text-white mb-4">Earn Two Ways:</h3>
            
            <div class="space-y-4">
                <div>
                    <div class="flex items-center space-x-2 mb-2">
                        <span class="text-2xl">👥</span>
                        <h4 class="font-semibold text-white">Refer Voters (Recurring)</h4>
                    </div>
                    <p class="text-gray-200">Earn 10% commission ($0.025) on every ad your referrals watch - forever!</p>
                    <p class="text-green-400 font-bold mt-1">Your Code: {{ $voter->referral_code ?? 'N/A' }}</p>
                </div>

                <div>
                    <div class="flex items-center space-x-2 mb-2">
                        <span class="text-2xl">🏛️</span>
                        <h4 class="font-semibold text-white">Refer Politicians (Residual Income)</h4>
                    </div>
                    <p class="text-gray-200">Earn 10% residual income as a Founding Member (ongoing commissions)</p>
                </div>
            </div>
        </div>

        <!-- Referral Links -->
        <div class="bg-gray-700 rounded-lg p-6 space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Voter Referral Link</label>
                <div class="flex space-x-2">
                    <input type="text" readonly
                           value="{{ route('register.voter', ['ref' => $voter->referral_code ?? '']) }}"
                           class="flex-1 bg-gray-800 border border-gray-600 rounded-lg px-4 py-2 text-gray-300 text-sm">
                    <button type="button" onclick="copyToClipboard(this, '{{ route('register.voter', ['ref' => $voter->referral_code ?? '']) }}')"
                            class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
                        Copy
                    </button>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Politician Referral Link</label>
                <div class="flex space-x-2">
                    <input type="text" readonly
                           value="{{ route('register.politician', ['ref' => $voter->referral_code ?? '']) }}"
                           class="flex-1 bg-gray-800 border border-gray-600 rounded-lg px-4 py-2 text-gray-300 text-sm">
                    <button type="button" onclick="copyToClipboard(this, '{{ route('register.politician', ['ref' => $voter->referral_code ?? '']) }}')"
                            class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
                        Copy
                    </button>
                </div>
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
