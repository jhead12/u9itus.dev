<x-guest-layout>
    <div class="space-y-6">
        <div>
            <h2 class="text-2xl font-bold text-white">Create your account</h2>
            <p class="mt-2 text-slate-400">Join U9itus and start earning today</p>
        </div>

        <form method="POST" action="{{ route('register') }}" class="space-y-5">
            @csrf

            <!-- Name -->
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="first_name" class="block text-sm font-medium text-slate-300 mb-2">First Name</label>
                    <input id="first_name" 
                           type="text" 
                           name="first_name" 
                           value="{{ old('first_name') }}" 
                           required 
                           autofocus 
                           autocomplete="given-name"
                           class="w-full px-4 py-3 bg-slate-700/50 border border-slate-600 rounded-lg text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition" 
                           placeholder="John" />
                    @error('first_name')
                        <p class="mt-2 text-sm text-red-400">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="last_name" class="block text-sm font-medium text-slate-300 mb-2">Last Name</label>
                    <input id="last_name" 
                           type="text" 
                           name="last_name" 
                           value="{{ old('last_name') }}" 
                           required 
                           autocomplete="family-name"
                           class="w-full px-4 py-3 bg-slate-700/50 border border-slate-600 rounded-lg text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition" 
                           placeholder="Doe" />
                    @error('last_name')
                        <p class="mt-2 text-sm text-red-400">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <!-- Email Address -->
            <div>
                <label for="email" class="block text-sm font-medium text-slate-300 mb-2">Email</label>
                <input id="email" 
                       type="email" 
                       name="email" 
                       value="{{ old('email') }}" 
                       required 
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
                    autocomplete="new-password"
                    class="w-full pr-16 px-4 py-3 bg-slate-700/50 border border-slate-600 rounded-lg text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition"
                    placeholder="••••••••"
                />
                @error('password')
                    <p class="mt-2 text-sm text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <!-- Confirm Password -->
            <div>
                <label for="password_confirmation" class="block text-sm font-medium text-slate-300 mb-2">Confirm Password</label>
                <x-password-input
                    id="password_confirmation"
                    name="password_confirmation"
                    required
                    autocomplete="new-password"
                    class="w-full pr-16 px-4 py-3 bg-slate-700/50 border border-slate-600 rounded-lg text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition"
                    placeholder="••••••••"
                />
                @error('password_confirmation')
                    <p class="mt-2 text-sm text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <!-- Submit Button -->
            <button type="submit" 
                    class="w-full px-4 py-3 bg-gradient-to-r from-emerald-500 to-teal-500 text-white font-semibold rounded-lg hover:from-emerald-600 hover:to-teal-600 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 focus:ring-offset-slate-900 transition transform hover:scale-[1.02]">
                Create account
            </button>
        </form>

        <!-- Login Link -->
        <div class="text-center pt-4 border-t border-slate-700">
            <p class="text-slate-400 text-sm">
                Already have an account?
                <a href="{{ route('login') }}" class="text-emerald-400 hover:text-emerald-300 font-semibold transition">
                    Sign in
                </a>
            </p>
        </div>
    </div>
</x-guest-layout>
