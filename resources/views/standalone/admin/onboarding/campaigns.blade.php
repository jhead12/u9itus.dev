<x-onboarding-layout :progress="$progress" :phases="$phases" :total-phases="$totalPhases" current-phase="campaign_approval" title="Campaign Approval Tutorial" description="Learn how to review and approve campaigns">
    <div class="space-y-6">
        <p class="text-gray-300">Review campaigns for policy compliance, appropriate content, and accurate targeting.</p>
        <form method="POST" action="{{ route('admin.onboarding.complete-campaigns') }}">
            @csrf
            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-6 rounded-lg">Continue →</button>
        </form>
    </div>
</x-onboarding-layout>
