<x-onboarding-layout :progress="$progress" :phases="$phases" :total-phases="$totalPhases" current-phase="payout_processing" title="Payout Processing Tutorial" description="Process batch payouts and manage withdrawals">
    <div class="space-y-6">
        <p class="text-gray-300">Manage voter payouts, process batch payments via PayPal, and handle withdrawal requests.</p>
        <form method="POST" action="{{ route('admin.onboarding.complete-payouts') }}">
            @csrf
            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-6 rounded-lg">Complete Onboarding →</button>
        </form>
    </div>
</x-onboarding-layout>
