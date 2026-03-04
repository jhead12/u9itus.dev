<x-onboarding-layout :progress="$progress" :phases="$phases" :total-phases="$totalPhases" current-phase="add_credits" title="Add Credits & Go Live" description="Fund your account to start reaching voters">
    <div class="space-y-6">
        <p class="text-gray-300">Add credits to your account to activate campaigns. Minimum $30 recommended.</p>
        <form method="POST" action="{{ route('politician.onboarding.complete-credits') }}">
            @csrf
            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-6 rounded-lg">Complete Onboarding →</button>
        </form>
    </div>
</x-onboarding-layout>
