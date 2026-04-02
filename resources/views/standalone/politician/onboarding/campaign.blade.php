<x-onboarding-layout :progress="$progress" :phases="$phases" :total-phases="$totalPhases" current-phase="first_campaign" title="Create Your First Campaign" description="Upload your video and set targeting">
    <div class="space-y-6">
        @php
            $minVideoDuration = max(30, (int) config('u9itus.min_video_duration', 30));
            $maxVideoDuration = max(60, (int) config('u9itus.max_video_duration', 300), $minVideoDuration);
        @endphp
        <p class="text-gray-300">Ready to create your first campaign? You'll upload a video ({{ $minVideoDuration }}–{{ $maxVideoDuration }} seconds) and set geographic targeting.</p>
        <form method="POST" action="{{ route('politician.onboarding.complete-campaign') }}">
            @csrf
            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-6 rounded-lg">Continue →</button>
        </form>
    </div>
</x-onboarding-layout>
