<x-onboarding-layout
    :progress="$progress"
    :phases="$phases"
    :total-phases="$totalPhases"
    current-phase="welcome"
    title="Welcome to U9itus!"
    description="Reach voters directly with your political message"
>
    @php($revenuePerView = (float) \App\Services\PlatformSettingsService::get('revenue_per_view', null, 0.60))

    <div class="space-y-6">
        <div class="text-center py-6">
            <div class="text-6xl mb-4">🏛️</div>
            <h2 class="text-2xl font-bold text-white mb-4">Welcome, {{ auth()->user()->name }}!</h2>
            <p class="text-gray-300 text-lg">
                Connect directly with voters through targeted video messages
            </p>
        </div>

        <div class="bg-gray-700 rounded-lg p-6 space-y-4">
            <h3 class="text-xl font-semibold text-white mb-4">Platform Benefits:</h3>
            
            <div class="space-y-3">
                <div class="flex items-start space-x-3">
                    <span class="text-blue-500">✓</span>
                    <span class="text-gray-300">Direct access to engaged voters</span>
                </div>
                <div class="flex items-start space-x-3">
                    <span class="text-blue-500">✓</span>
                    <span class="text-gray-300">${{ number_format($revenuePerView, 2) }} per guaranteed view</span>
                </div>
                <div class="flex items-start space-x-3">
                    <span class="text-blue-500">✓</span>
                    <span class="text-gray-300">Precise geographic targeting</span>
                </div>
                <div class="flex items-start space-x-3">
                    <span class="text-blue-500">✓</span>
                    <span class="text-gray-300">Public profile page with your platform</span>
                </div>
                <div class="flex items-start space-x-3">
                    <span class="text-blue-500">✓</span>
                    <span class="text-gray-300">100% watch requirement (no skips)</span>
                </div>
            </div>
        </div>

        <form method="POST" action="{{ route('politician.onboarding.complete-welcome') }}">
            @csrf
            <button type="submit"
                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-6 rounded-lg transition duration-200">
                Get Started →
            </button>
        </form>
    </div>
</x-onboarding-layout>
