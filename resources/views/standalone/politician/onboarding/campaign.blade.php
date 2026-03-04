<x-onboarding-layout :progress="$progress" :phases="$phases" :total-phases="$totalPhases" current-phase="first_campaign" title="Create Your First Campaign" description="Upload your video and set targeting">
    <div class="space-y-6">
        <p class="text-gray-300">Ready to create your first campaign? You'll upload a video (10-20 seconds) and set geographic targeting.</p>
        <form method="POST" action="{{ route('politician.onboarding.complete-campaign') }}">
            @csrf
            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-6 rounded-lg">Continue →</button>
        </form>
    </div>
</x-onboarding-layout>
