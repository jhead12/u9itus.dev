@php
    $voterPayoutPerView = number_format((float) \App\Services\PlatformSettingsService::get('viewer_payout_per_view', null, 0.25), 2);
@endphp

<x-onboarding-layout
    :progress="$progress"
    :phases="$phases"
    :total-phases="$totalPhases"
    current-phase="first_watch"
    title="Watch Your First Ad"
    description="Learn how the viewing process works"
>
    <div class="space-y-6">
        <div class="bg-gray-700 rounded-lg p-6 space-y-4">
            <h3 class="text-xl font-semibold text-white">How Ad Viewing Works:</h3>
            
            <ul class="space-y-3 text-gray-300">
                <li class="flex items-start space-x-2">
                    <span class="text-blue-500">✓</span>
                    <span>You'll receive secure ad tokens via email or SMS</span>
                </li>
                <li class="flex items-start space-x-2">
                    <span class="text-blue-500">✓</span>
                    <span>Each token is valid for 24 hours and can only be used once</span>
                </li>
                <li class="flex items-start space-x-2">
                    <span class="text-blue-500">✓</span>
                    <span>Watch the full video to earn ${{ $voterPayoutPerView }}</span>
                </li>
                <li class="flex items-start space-x-2">
                    <span class="text-blue-500">✓</span>
                    <span>Your earnings appear instantly in your wallet</span>
                </li>
                <li class="flex items-start space-x-2">
                    <span class="text-blue-500">✓</span>
                    <span>No clicking around - ads come to you!</span>
                </li>
            </ul>
        </div>

        <div class="bg-blue-900 border border-blue-700 rounded-lg p-4">
            <p class="text-blue-100 text-sm">
                <strong>Security First:</strong> Our token-based system prevents fraud and ensures fair compensation for your time.
            </p>
        </div>

        <form method="POST" action="{{ route('voter.onboarding.complete-first-watch') }}">
            @csrf
            <button type="submit"
                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-6 rounded-lg transition duration-200">
                Got It! Continue →
            </button>
        </form>
    </div>
</x-onboarding-layout>
