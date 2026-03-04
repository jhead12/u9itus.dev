<x-onboarding-layout :progress="$progress" :phases="$phases" :total-phases="$totalPhases" current-phase="fraud_management" title="Fraud Management Overview" description="Monitor fraud signals and trust scores">
    <div class="space-y-6">
        <p class="text-gray-300">Learn to identify and manage fraud signals, review flagged accounts, and adjust trust scores.</p>
        <form method="POST" action="{{ route('admin.onboarding.complete-fraud') }}">
            @csrf
            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-6 rounded-lg">Continue →</button>
        </form>
    </div>
</x-onboarding-layout>
