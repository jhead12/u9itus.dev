<x-guest-layout>
    <div class="space-y-6">
        <div>
            <h2 class="text-2xl font-bold text-white">Verify your email</h2>
            <p class="mt-2 text-slate-400">
                Thanks for signing up! Before getting started, could you verify your email address by clicking on the link we just emailed to you? If you didn't receive the email, we'll gladly send you another.
            </p>
        </div>

        @if (session('status') == 'verification-link-sent')
            <div class="p-4 bg-emerald-900/30 border border-emerald-500/50 rounded-lg">
                <p class="text-sm text-emerald-300">
                    A new verification link has been sent to your email address.
                </p>
            </div>
        @endif

        <div class="flex flex-col space-y-4">
            <form method="POST" action="{{ route('verification.send') }}">
                @csrf
                <button type="submit" 
                        class="w-full px-4 py-3 bg-gradient-to-r from-emerald-500 to-teal-500 text-white font-semibold rounded-lg hover:from-emerald-600 hover:to-teal-600 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 focus:ring-offset-slate-900 transition transform hover:scale-[1.02]">
                    Resend verification email
                </button>
            </form>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" 
                        class="w-full px-4 py-3 bg-slate-700/50 border border-slate-600 text-slate-300 font-semibold rounded-lg hover:bg-slate-700 hover:text-white focus:outline-none focus:ring-2 focus:ring-slate-500 transition">
                    Log out
                </button>
            </form>
        </div>
    </div>
</x-guest-layout>
