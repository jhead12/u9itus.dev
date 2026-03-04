<x-onboarding-layout :progress="$progress" :phases="$phases" :total-phases="$totalPhases" current-phase="welcome" title="Welcome to Admin Portal" description="Learn essential admin workflows">
    <div class="space-y-6">
        <div class="text-center py-6">
            <div class="text-6xl mb-4">⚙️</div>
            <h2 class="text-2xl font-bold text-white mb-4">Welcome, Admin!</h2>
            <p class="text-gray-300 text-lg">Manage campaigns, fraud prevention, and payouts</p>
        </div>
        <form method="POST" action="{{ route('admin.onboarding.complete-welcome') }}">
            @csrf
            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-6 rounded-lg">Get Started →</button>
        </form>
    </div>
</x-onboarding-layout>
