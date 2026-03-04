<x-onboarding-layout
    :progress="$progress"
    :phases="$phases"
    :total-phases="$totalPhases"
    current-phase="profile_setup"
    title="Complete Your Profile"
    description="Help us personalize your experience"
>
    <form method="POST" action="{{ route('voter.onboarding.complete-profile') }}" class="space-y-6">
        @csrf

        <div>
            <label for="city" class="block text-sm font-medium text-gray-300 mb-2">City</label>
            <input type="text" id="city" name="city" value="{{ old('city', $voter->city ?? '') }}"
                   class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:ring-2 focus:ring-blue-500">
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label for="state" class="block text-sm font-medium text-gray-300 mb-2">State</label>
                <input type="text" id="state" name="state" value="{{ old('state', $voter->state ?? '') }}" maxlength="2"
                       class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:ring-2 focus:ring-blue-500">
            </div>

            <div>
                <label for="zip_code" class="block text-sm font-medium text-gray-300 mb-2">ZIP Code</label>
                <input type="text" id="zip_code" name="zip_code" value="{{ old('zip_code', $voter->zip_code ?? '') }}"
                       class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:ring-2 focus:ring-blue-500">
            </div>
        </div>

        <button type="submit"
                class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-6 rounded-lg transition duration-200">
            Continue →
        </button>
    </form>
</x-onboarding-layout>
