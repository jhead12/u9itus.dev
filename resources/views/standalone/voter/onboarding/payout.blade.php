<x-onboarding-layout
    :progress="$progress"
    :phases="$phases"
    :total-phases="$totalPhases"
    current-phase="payout_setup"
    title="Set Up Payouts"
    description="Add your payout method to receive earnings"
>
    <form method="POST" action="{{ route('voter.onboarding.complete-payout') }}" class="space-y-6">
        @csrf

        <div>
            <label class="block text-sm font-medium text-gray-300 mb-3">Payout Method</label>
            <div class="space-y-2">
                <label class="flex items-center p-4 bg-gray-700 border border-gray-600 rounded-lg cursor-pointer hover:bg-gray-600">
                    <input type="radio" name="payout_method" value="paypal" required
                           {{ old('payout_method', $voter->payout_method ?? '') === 'paypal' ? 'checked' : '' }}
                           class="text-blue-600 focus:ring-blue-500">
                    <span class="ml-3 text-white font-medium">PayPal</span>
                </label>

                <label class="flex items-center p-4 bg-gray-700 border border-gray-600 rounded-lg cursor-pointer hover:bg-gray-600">
                    <input type="radio" name="payout_method" value="cashapp" required
                           {{ old('payout_method', $voter->payout_method ?? '') === 'cashapp' ? 'checked' : '' }}
                           class="text-blue-600 focus:ring-blue-500">
                    <span class="ml-3 text-white font-medium">CashApp</span>
                </label>
            </div>
        </div>

        <div>
            <label for="payout_email" class="block text-sm font-medium text-gray-300 mb-2">
                Payout Email
            </label>
            <input type="email" id="payout_email" name="payout_email" required
                   value="{{ old('payout_email', $voter->payout_email ?? '') }}"
                   placeholder="your@email.com"
                   class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:ring-2 focus:ring-blue-500">
            <p class="mt-1 text-sm text-gray-400">Enter your PayPal or CashApp email address</p>
        </div>

        <div class="bg-yellow-900 border border-yellow-700 rounded-lg p-4">
            <p class="text-yellow-100 text-sm">
                <strong>Minimum Payout:</strong> $5.00 - You can request a payout once you reach this threshold.
            </p>
        </div>

        <button type="submit"
                class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-6 rounded-lg transition duration-200">
            Save & Continue →
        </button>
    </form>
</x-onboarding-layout>
