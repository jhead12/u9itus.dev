@php($revenuePerView = (float) \App\Services\PlatformSettingsService::get('revenue_per_view', null, (float) config('u9itus.revenue_per_view', 1.00)))

<x-onboarding-layout :progress="$progress" :phases="$phases" :total-phases="$totalPhases" current-phase="payment_method" title="Add Payment Method" description="Set up Stripe for campaign billing">
    <div class="space-y-6">
        <p class="text-gray-300">Connect Stripe to handle campaign billing. You'll be charged ${{ number_format($revenuePerView, 2) }} per completed view.</p>
        <p class="text-gray-400 text-sm mt-2">A 2.5% Stripe processing fee is added to each credit top-up. The fee is shown transparently at checkout before you confirm payment.</p>
        <form method="POST" action="{{ route('politician.onboarding.complete-payment') }}">
            @csrf
            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-6 rounded-lg">Continue →</button>
        </form>
    </div>
</x-onboarding-layout>
