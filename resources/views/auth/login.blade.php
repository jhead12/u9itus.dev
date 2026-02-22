<x-guest-layout>
    <div class="space-y-6">
        <div>
            <h2 class="text-2xl font-bold text-white">Welcome back</h2>
            <p class="mt-2 text-slate-400">Sign in to your account to continue</p>
        </div>

        <!-- Session Status -->
        <x-auth-session-status class="mb-4" :status="session('status')" />

        <form method="POST" action="{{ route('login') }}" class="space-y-5">
            @csrf

            <!-- Email Address -->
            <div>
                <label for="email" class="block text-sm font-medium text-slate-300 mb-2">Email</label>
                <input id="email" 
                       type="email" 
                       name="email" 
                       value="{{ old('email') }}" 
                       required 
                       autofocus 
                       autocomplete="username"
                       class="w-full px-4 py-3 bg-slate-700/50 border border-slate-600 rounded-lg text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition" 
                       placeholder="you@example.com" />
                @error('email')
                    <p class="mt-2 text-sm text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <!-- Password -->
            <div>
                <label for="password" class="block text-sm font-medium text-slate-300 mb-2">Password</label>
                <x-password-input
                    id="password"
                    name="password"
                    required
                    autocomplete="current-password"
                    class="w-full pr-16 px-4 py-3 bg-slate-700/50 border border-slate-600 rounded-lg text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition"
                    placeholder="••••••••"
                />
                @error('password')
                    <p class="mt-2 text-sm text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <!-- Remember Me & Forgot Password -->
            <div class="flex items-center justify-between">
                <label for="remember_me" class="flex items-center">
                    <input id="remember_me" 
                           type="checkbox" 
                           name="remember"
                           class="w-4 h-4 bg-slate-700 border-slate-600 rounded text-emerald-500 focus:ring-emerald-500 focus:ring-2" />
                    <span class="ml-2 text-sm text-slate-300">Remember me</span>
                </label>

                @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}" class="text-sm text-emerald-400 hover:text-emerald-300 transition">
                        Forgot password?
                    </a>
                @endif
            </div>

            <!-- Submit Button -->
            <button type="submit" 
                    class="w-full px-4 py-3 bg-gradient-to-r from-emerald-500 to-teal-500 text-white font-semibold rounded-lg hover:from-emerald-600 hover:to-teal-600 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 focus:ring-offset-slate-900 transition transform hover:scale-[1.02]">
                Sign in
            </button>
        </form>

        <!-- Register Link -->
        <div class="text-center pt-4 border-t border-slate-700">
            <p class="text-slate-400 text-sm">
                Don't have an account?
                <a href="{{ route('register') }}" class="text-emerald-400 hover:text-emerald-300 font-semibold transition">
                    Sign up
                </a>
            </p>
        </div>
    </div>
</x-guest-layout>
