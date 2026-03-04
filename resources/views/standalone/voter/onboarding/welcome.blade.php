<x-onboarding-layout
    :progress="$progress"
    :phases="$phases"
    :total-phases="$totalPhases"
    current-phase="welcome"
    title="Welcome to U9itus!"
    description="Get paid to watch political messages and make your voice heard"
>
    <div class="space-y-6">
        <!-- Welcome Content -->
        <div class="text-center py-6">
            <div class="text-6xl mb-4">👋</div>
            <h2 class="text-2xl font-bold text-white mb-4">Hi {{ auth()->user()->name }}!</h2>
            <p class="text-gray-300 text-lg">
                Welcome to U9itus, the platform where you earn money for watching political messages.
            </p>
        </div>

        <!-- How It Works -->
        <div class="bg-gray-700 rounded-lg p-6 space-y-4">
            <h3 class="text-xl font-semibold text-white mb-4">How It Works:</h3>
            
            <div class="flex items-start space-x-3">
                <div class="flex-shrink-0 w-8 h-8 bg-blue-600 rounded-full flex items-center justify-center text-white font-bold">1</div>
                <div>
                    <h4 class="font-semibold text-white">Watch Political Messages</h4>
                    <p class="text-gray-300">View video messages from politicians and local governance officials</p>
                </div>
            </div>

            <div class="flex items-start space-x-3">
                <div class="flex-shrink-0 w-8 h-8 bg-blue-600 rounded-full flex items-center justify-center text-white font-bold">2</div>
                <div>
                    <h4 class="font-semibold text-white">Earn $0.25 Per View</h4>
                    <p class="text-gray-300">Get paid for every complete video you watch</p>
                </div>
            </div>

            <div class="flex items-start space-x-3">
                <div class="flex-shrink-0 w-8 h-8 bg-blue-600 rounded-full flex items-center justify-center text-white font-bold">3</div>
                <div>
                    <h4 class="font-semibold text-white">Earn Extra with Referrals</h4>
                    <p class="text-gray-300">Get 10% recurring commission from voter referrals + 10% one-time bonus from politician referrals</p>
                </div>
            </div>

            <div class="flex items-start space-x-3">
                <div class="flex-shrink-0 w-8 h-8 bg-blue-600 rounded-full flex items-center justify-center text-white font-bold">4</div>
                <div>
                    <h4 class="font-semibold text-white">Cash Out Anytime</h4>
                    <p class="text-gray-300">Request payouts via PayPal or CashApp</p>
                </div>
            </div>
        </div>

        <!-- Next Steps -->
        <form method="POST" action="{{ route('voter.onboarding.complete-welcome') }}">
            @csrf
            <button type="submit"
                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-6 rounded-lg transition duration-200">
                Get Started →
            </button>
        </form>
    </div>
</x-onboarding-layout>
