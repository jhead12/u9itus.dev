<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign In — {{ config('app.name', 'U9itus') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700&display=swap" rel="stylesheet" />
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
    @endif
    <style>* { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="min-h-screen bg-slate-900 flex items-center justify-center px-4 antialiased">

<div class="w-full max-w-md">

    {{-- Logo --}}
    <div class="text-center mb-8">
        <a href="/" class="text-3xl font-light tracking-tight">
            <span class="font-bold text-white">U9</span><span class="text-emerald-400">itus</span>
        </a>
        <p class="mt-2 text-slate-400 text-sm">Political Loyalty Ads Platform</p>
    </div>

    {{-- Role tabs --}}
    <div class="flex gap-1 bg-slate-800/50 rounded-xl p-1 mb-4">
        <button type="button" id="tab-politician"
            onclick="setPortal('politician')"
            class="portal-tab flex-1 py-2 rounded-lg text-sm font-medium transition-colors text-slate-400 hover:text-white">
            🏛️ Politician
        </button>
        <button type="button" id="tab-citizen"
            onclick="setPortal('citizen')"
            class="portal-tab flex-1 py-2 rounded-lg text-sm font-medium transition-colors text-slate-400 hover:text-white">
            🏘️ Citizen
        </button>
        <button type="button" id="tab-voter"
            onclick="setPortal('voter')"
            class="portal-tab flex-1 py-2 rounded-lg text-sm font-medium transition-colors text-slate-400 hover:text-white">
            🗳️ Voter
        </button>
    </div>

    {{-- Card --}}
    <div class="bg-slate-800/60 border border-slate-700/50 rounded-2xl p-8 shadow-2xl">
        <h2 class="text-xl font-semibold text-white mb-1">Welcome back</h2>
        <p class="text-sm text-slate-400 mb-6" id="login-subtitle">Sign in to your account to continue</p>

        {{-- Session errors --}}
        @if($errors->any())
            <div class="mb-4 p-3 rounded-lg bg-red-500/10 border border-red-500/30 text-red-400 text-sm">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('login') }}" class="space-y-4">
            @csrf

            <div>
                <label for="email" class="block text-sm font-medium text-slate-300 mb-1.5">Email address</label>
                <input
                    id="email"
                    type="email"
                    name="email"
                    value="{{ old('email') }}"
                    required
                    autofocus
                    autocomplete="email"
                    class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm placeholder-slate-500
                           focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition"
                    placeholder="you@example.com"
                />
            </div>

            <div>
                <div class="flex items-center justify-between mb-1.5">
                    <label for="password" class="block text-sm font-medium text-slate-300">Password</label>
                    @if(Route::has('password.request'))
                        <a href="{{ route('password.request') }}" class="text-xs text-emerald-400 hover:text-emerald-300 transition">Forgot password?</a>
                    @endif
                </div>
                <x-password-input
                    id="password"
                    name="password"
                    required
                    autocomplete="current-password"
                    class="w-full pr-16 bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition"
                    placeholder="••••••••"
                />
            </div>

            <div class="flex items-center gap-2">
                <input id="remember_me" type="checkbox" name="remember" class="w-4 h-4 rounded border-slate-600 bg-slate-700 text-emerald-500 focus:ring-emerald-500/50">
                <label for="remember_me" class="text-sm text-slate-400">Remember me</label>
            </div>

            <button
                type="submit"
                class="w-full bg-emerald-500 hover:bg-emerald-400 text-slate-900 font-semibold rounded-lg px-4 py-2.5 text-sm transition-colors focus:outline-none focus:ring-2 focus:ring-emerald-500/50 mt-2"
            >
                Sign In
            </button>
        </form>

        <p class="mt-6 text-center text-sm text-slate-500">
            Don't have an account?
            @if(\App\Services\PlatformSettingsService::get('registration_open', null, true))
                <a href="{{ route('register') }}" class="text-emerald-400 hover:text-emerald-300 font-medium transition">Create one</a>
            @else
                <span class="text-slate-600">Registration is currently closed.</span>
            @endif
        </p>
        <p class="text-center mt-2 text-slate-600 text-xs">
            Admin staff? <a href="{{ route('admin.login') }}" class="text-slate-500 hover:text-slate-400 underline transition">Admin portal</a>
        </p>
    </div>

</div>

<script>
    const subtitles = {
        politician: 'Sign in to manage your campaigns and billing.',
        citizen: 'Sign in to manage your local and community ads.',
        voter: 'Sign in to watch ads and check your earnings.',
    };
    const colors = {
        politician: 'border-emerald-500/40',
        citizen: 'border-amber-500/40',
        voter: 'border-blue-500/40',
    };
    const card = document.querySelector('.bg-slate-800\\/60');

    function setPortal(role) {
        document.getElementById('login-subtitle').textContent = subtitles[role];
        document.querySelectorAll('.portal-tab').forEach(t => {
            t.classList.remove('bg-slate-700', 'text-white');
            t.classList.add('text-slate-400');
        });
        const active = document.getElementById('tab-' + role);
        active.classList.add('bg-slate-700', 'text-white');
        active.classList.remove('text-slate-400');
    }

    // Default: show politician portal
    setPortal('politician');
</script>
</body>
</html>
