<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Choose Portal — {{ config('app.name', 'U9itus') }}</title>
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

<div class="w-full max-w-lg">

    {{-- Logo --}}
    <div class="text-center mb-8">
        <a href="/" class="text-3xl font-light tracking-tight">
            <span class="font-bold text-white">U9</span><span class="text-emerald-400">itus</span>
        </a>
        <p class="mt-2 text-slate-400 text-sm">Choose your portal</p>
    </div>

    @if(session('success'))
    <div class="mb-6 rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-5 py-3 text-sm text-emerald-300 text-center">
        {{ session('success') }}
    </div>
    @endif

    <p class="text-center text-slate-400 text-sm mb-6">
        Welcome back, <span class="text-white font-medium">{{ $user->name }}</span>.
        Your account has both a Voter and a Citizen profile — pick which portal to open.
    </p>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

        {{-- Voter portal --}}
        <a href="{{ route('voter.dashboard') }}"
           class="group block bg-slate-800/60 border border-slate-700/50 hover:border-blue-500/60 rounded-2xl p-8 text-center transition-all hover:-translate-y-1 hover:shadow-blue-500/10 hover:shadow-xl">
            <div class="text-5xl mb-4">🗳️</div>
            <h2 class="text-lg font-semibold text-white mb-2 group-hover:text-blue-400 transition-colors">Voter Portal</h2>
            <p class="text-sm text-slate-400 leading-relaxed mb-4">
                Watch campaigns, track your earnings, manage payouts and referrals.
            </p>
            <span class="inline-flex items-center justify-center gap-2 bg-blue-600 hover:bg-blue-500 text-white text-sm font-semibold px-5 py-2.5 rounded-lg transition-colors w-full">
                Go to Voter Dashboard
            </span>
        </a>

        {{-- Citizen portal --}}
        <a href="{{ route('citizen.dashboard') }}"
           class="group block bg-slate-800/60 border border-slate-700/50 hover:border-amber-500/60 rounded-2xl p-8 text-center transition-all hover:-translate-y-1 hover:shadow-amber-500/10 hover:shadow-xl">
            <div class="text-5xl mb-4">🏘️</div>
            <h2 class="text-lg font-semibold text-white mb-2 group-hover:text-amber-400 transition-colors">Citizen Portal</h2>
            <p class="text-sm text-slate-400 leading-relaxed mb-4">
                Create and manage local business, community, or ballot-issue ad campaigns.
            </p>
            <span class="inline-flex items-center justify-center gap-2 bg-amber-500 hover:bg-amber-400 text-slate-900 text-sm font-semibold px-5 py-2.5 rounded-lg transition-colors w-full">
                Go to Citizen Dashboard
            </span>
        </a>

    </div>

    <div class="text-center mt-6">
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="text-xs text-slate-500 hover:text-slate-400 underline transition">
                Sign out
            </button>
        </form>
    </div>

</div>

</body>
</html>
