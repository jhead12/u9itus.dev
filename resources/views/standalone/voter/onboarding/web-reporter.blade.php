<x-onboarding-layout
    :progress="$progress"
    :phases="$phases"
    :total-phases="$totalPhases"
    current-phase="web_reporter"
    title="Become a Web Reporter"
    description="Optional — collect public news and social posts for editorial review"
>
    @php
        $user = auth()->user();
        $alreadyApproved = \App\Support\ChatterContributorAccess::allowed($user);
        $alreadyRequested = (bool) $user->chatter_contributor_requested_at;
    @endphp
    <div class="space-y-6">
        <div class="bg-gray-700 rounded-lg p-6 space-y-4">
            <h3 class="text-xl font-semibold text-white">What Web Reporters do:</h3>
            <ul class="space-y-3 text-gray-300">
                <li class="flex items-start space-x-2">
                    <span class="text-blue-500">✓</span>
                    <span>Send in public news articles and social posts about candidates you find while browsing</span>
                </li>
                <li class="flex items-start space-x-2">
                    <span class="text-blue-500">✓</span>
                    <span>Add neutral context explaining why it's relevant — nothing you submit publishes automatically</span>
                </li>
                <li class="flex items-start space-x-2">
                    <span class="text-blue-500">✓</span>
                    <span>U9itus editors review every submission before it ever appears on a candidate's profile</span>
                </li>
                <li class="flex items-start space-x-2">
                    <span class="text-blue-500">✓</span>
                    <span>An optional browser extension can send you straight from the page you're reading</span>
                </li>
            </ul>
        </div>

        <div class="bg-blue-900 border border-blue-700 rounded-lg p-4">
            <p class="text-blue-100 text-sm">
                <strong>Completely optional.</strong> This doesn't change your voter account, earnings, or any existing access. A Super Admin reviews every request before it's granted.
            </p>
        </div>

        @if($alreadyApproved)
            <p class="text-emerald-300 text-sm">You already have Web Reporter access. <a href="{{ route('contributor.chatter.index') }}" class="underline">Submit a source →</a></p>
            <form method="POST" action="{{ route('voter.onboarding.complete-web-reporter') }}">
                @csrf
                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-6 rounded-lg transition duration-200">
                    Continue →
                </button>
            </form>
        @elseif($alreadyRequested)
            <p class="text-amber-300 text-sm">Your request is pending review.</p>
            <form method="POST" action="{{ route('voter.onboarding.complete-web-reporter') }}">
                @csrf
                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-6 rounded-lg transition duration-200">
                    Continue →
                </button>
            </form>
        @else
            <form method="POST" action="{{ route('voter.onboarding.complete-web-reporter') }}" class="space-y-3">
                @csrf
                <button type="submit" name="action" value="request"
                        class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-3 px-6 rounded-lg transition duration-200">
                    Request Web Reporter access
                </button>
                <button type="submit" name="action" value="skip"
                        class="w-full bg-gray-600 hover:bg-gray-500 text-white font-semibold py-3 px-6 rounded-lg transition duration-200">
                    Not now →
                </button>
            </form>
        @endif
    </div>
</x-onboarding-layout>
