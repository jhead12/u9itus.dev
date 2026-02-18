<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verify Email — {{ config('app.name', 'U9itus') }}</title>
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

    {{-- Card --}}
    <div class="bg-slate-800/60 border border-slate-700/50 rounded-2xl p-8 shadow-2xl text-center">

        {{-- Icon --}}
        <div class="flex items-center justify-center w-16 h-16 rounded-full bg-emerald-500/10 border border-emerald-500/30 mx-auto mb-5">
            <svg class="w-8 h-8 text-emerald-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25H4.5a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5H4.5a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
            </svg>
        </div>

        <h2 class="text-xl font-semibold text-white mb-2">Verify your email address</h2>
        <p class="text-slate-400 text-sm mb-6">
            Thanks for signing up! Before getting started, please verify your email address by clicking the link we just sent to
            <span class="text-white font-medium">{{ auth()->user()->email }}</span>.
        </p>

        {{-- Success notice --}}
        @if (session('status') === 'verification-link-sent')
            <div class="mb-5 p-3 rounded-lg bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm">
                A new verification link has been sent to your email address.
            </div>
        @endif

        {{-- Resend button --}}
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <button type="submit"
                class="w-full py-2.5 px-4 bg-emerald-500 hover:bg-emerald-400 text-white font-semibold rounded-xl transition-colors text-sm">
                Resend Verification Email
            </button>
        </form>

        {{-- Log out --}}
        <form method="POST" action="{{ route('logout') }}" class="mt-4">
            @csrf
            <button type="submit" class="text-slate-500 hover:text-slate-300 text-sm transition-colors">
                Log out
            </button>
        </form>

    </div>

</div>

</body>
</html>
