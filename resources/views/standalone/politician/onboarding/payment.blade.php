<x-onboarding-layout :progress="$progress" :phases="$phases" :total-phases="$totalPhases" current-phase="payment_method" title="Add Payment Method" description="Set up Stripe for campaign billing">
    <div class="space-y-6">
        <p class="text-gray-300">Connect Stripe to handle campaign billing. You'll be charged $0.60 per completed view.</p>
        <form method="POST" action="{{ route('politician.onboarding.complete-payment') }}">
            @csrf
            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-6 rounded-lg">Continue →</button>
        </form>
    </div>
</x-onboarding-layout>
