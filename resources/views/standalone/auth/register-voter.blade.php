<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Voter Registration — {{ config('app.name', 'U9itus') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700&display=swap" rel="stylesheet" />
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
    @endif
    <style>* { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="min-h-screen bg-slate-900 flex items-center justify-center px-4 py-10 antialiased">

<div class="w-full max-w-md">

    {{-- Logo --}}
    <div class="text-center mb-8">
        <a href="/" class="text-3xl font-light tracking-tight">
            <span class="font-bold text-white">U9</span><span class="text-emerald-400">itus</span>
        </a>
        <p class="mt-2 text-slate-400 text-sm">Political Loyalty Ads Platform</p>
    </div>

    {{-- Header --}}
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('register') }}" class="text-slate-500 hover:text-slate-300 transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <div>
            <h1 class="text-xl font-bold text-white flex items-center gap-2">🗳️ Voter Registration</h1>
            <p class="text-sm text-slate-400">Earn money watching political messages</p>
        </div>
    </div>

    {{-- Earnings callout --}}
    <div class="bg-blue-500/10 border border-blue-500/30 rounded-xl p-4 mb-6 flex items-start gap-3">
        <span class="text-2xl">💰</span>
        <div>
            <p class="text-blue-400 font-medium text-sm">Earn $0.25 per verified view</p>
            <p class="text-slate-400 text-xs mt-0.5">Watch political messages in your area at your own pace. Cash out anytime above $10.</p>
        </div>
    </div>

    <div class="bg-slate-800/60 border border-blue-500/20 rounded-2xl p-8 shadow-2xl">

        @if($errors->any())
            <div class="mb-5 p-3 rounded-lg bg-red-500/10 border border-red-500/30 text-red-400 text-sm">
                <ul class="list-disc list-inside space-y-1">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('register.voter.submit') }}" class="space-y-4">
            @csrf

            <div>
                <label for="name" class="block text-sm font-medium text-slate-300 mb-1.5">Full name <span class="text-red-400">*</span></label>
                <input id="name" type="text" name="name" value="{{ old('name') }}" required autofocus
                    class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:border-blue-500 transition"
                    placeholder="Jane Doe" />
            </div>

            <div>
                <label for="email" class="block text-sm font-medium text-slate-300 mb-1.5">Email address <span class="text-red-400">*</span></label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required
                    class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:border-blue-500 transition"
                    placeholder="you@example.com" />
            </div>

            <div class="space-y-4">
                <div>
                    <label for="password" class="block text-sm font-medium text-slate-300 mb-1.5">Password <span class="text-red-400">*</span></label>
                    <x-password-input
                        id="password"
                        name="password"
                        required
                        autocomplete="new-password"
                        btn-class="text-blue-400 hover:text-blue-300"
                        class="w-full pr-16 bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:border-blue-500 transition"
                        placeholder="••••••••"
                    />
                </div>
                <div>
                    <label for="password_confirmation" class="block text-sm font-medium text-slate-300 mb-1.5">Confirm password <span class="text-red-400">*</span></label>
                    <x-password-input
                        id="password_confirmation"
                        name="password_confirmation"
                        required
                        autocomplete="new-password"
                        btn-class="text-blue-400 hover:text-blue-300"
                        class="w-full pr-16 bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:border-blue-500 transition"
                        placeholder="••••••••"
                    />
                </div>
            </div>

            <div>
                <label for="phone" class="block text-sm font-medium text-slate-300 mb-1.5">Phone number</label>
                <input id="phone" type="tel" name="phone" value="{{ old('phone') }}"
                    class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:border-blue-500 transition"
                    placeholder="+1 (555) 000-0000" />
            </div>

            {{-- Location (for ad targeting) --}}
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="state" class="block text-sm font-medium text-slate-300 mb-1.5">State</label>
                    <select id="state" name="state"
                        class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:border-blue-500 transition">
                        <option value="">Select state...</option>
                        @foreach(config('u9itus.us_states', []) as $abbr => $stateName)
                            <option value="{{ $abbr }}" {{ old('state') === $abbr ? 'selected' : '' }}>{{ $stateName }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="zip_code" class="block text-sm font-medium text-slate-300 mb-1.5">ZIP code</label>
                    <input id="zip_code" type="text" name="zip_code" value="{{ old('zip_code') }}" maxlength="10"
                        class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:border-blue-500 transition"
                        placeholder="78701" />
                </div>
            </div>

            {{-- Optional referral code --}}
            <div>
                <label for="referral_code" class="block text-sm font-medium text-slate-300 mb-1.5">Referral code <span class="text-slate-500">(optional)</span></label>
                <input id="referral_code" type="text" name="referral_code" value="{{ old('referral_code', request('ref')) }}"
                    class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:border-blue-500 transition"
                    placeholder="e.g. JANE2024" />
            </div>

            {{-- ToS --}}
            <div class="flex items-start gap-3 pt-1">
                <input id="terms" type="checkbox" name="terms" required
                    class="mt-0.5 w-4 h-4 rounded border-slate-600 bg-slate-700 text-blue-500 focus:ring-blue-500/50">
                <label for="terms" class="text-sm text-slate-400">
                    I agree to the <a href="#" class="text-blue-400 hover:text-blue-300 underline">Terms of Service</a>
                    and <a href="#" class="text-blue-400 hover:text-blue-300 underline">Privacy Policy</a>.
                    I understand I must watch the full video to earn the $0.25 reward.
                </label>
            </div>

            <button type="submit"
                class="w-full bg-blue-600 hover:bg-blue-500 text-white font-semibold py-3 rounded-lg text-sm transition-colors mt-2">
                Create Voter Account
            </button>

        </form>
    </div>

    <p class="text-center mt-6 text-slate-500 text-sm">
        Already have an account?
        <a href="{{ route('login') }}" class="text-blue-400 hover:text-blue-300 font-medium transition-colors">Sign in</a>
        &nbsp;·&nbsp;
        <a href="{{ route('register') }}" class="text-slate-400 hover:text-slate-300 transition-colors">Change role</a>
    </p>

</div>
</body>
</html>
