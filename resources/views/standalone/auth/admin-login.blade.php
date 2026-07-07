<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Portal — {{ config('app.name', 'U9itus') }}</title>
    @include('standalone.partials.seo-head')
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700&display=swap" rel="stylesheet" />
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
    @endif
    <style>* { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="min-h-screen bg-slate-950 flex items-center justify-center px-4 antialiased">

<div class="w-full max-w-sm">

    {{-- Logo with admin badge --}}
    <div class="text-center mb-8">
        <a href="/" class="inline-block">
            <img src="{{ asset('media/u9itus-logo.svg') }}" alt="U9itus" class="h-10 mx-auto mb-2">
        </a>
        <div class="mt-3 inline-flex items-center gap-1.5 bg-amber-500/15 border border-amber-500/30 rounded-full px-3 py-1">
            <svg class="w-3.5 h-3.5 text-amber-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-3a1 1 0 00-.867.5 1 1 0 11-1.731-1A3 3 0 0113 8a3.001 3.001 0 01-2 2.83V11a1 1 0 11-2 0v-1a1 1 0 011-1 1 1 0 100-2zm0 8a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg>
            <span class="text-amber-400 text-xs font-semibold tracking-wide">ADMIN PORTAL</span>
        </div>
    </div>

    <div class="bg-slate-800/80 border border-amber-500/20 rounded-2xl p-8 shadow-2xl">
        <h2 class="text-xl font-semibold text-white mb-1">Admin Sign In</h2>
        <p class="text-sm text-slate-400 mb-6">Restricted access — authorized personnel only.</p>

        {{-- Error --}}
        @if($errors->any())
            <div class="mb-4 p-3 rounded-lg bg-red-500/10 border border-red-500/30 text-red-400 text-sm">
                {{ $errors->first() }}
            </div>
        @endif

        @if(session('status'))
            <div class="mb-4 p-3 rounded-lg bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm">
                {{ session('status') }}
            </div>
        @endif

        <form method="POST" action="{{ route('admin.login.submit') }}" class="space-y-4">
            @csrf

            <div>
                <label for="email" class="block text-sm font-medium text-slate-300 mb-1.5">Admin email</label>
                <input
                    id="email"
                    type="email"
                    name="email"
                    value="{{ old('email') }}"
                    required
                    autofocus
                    autocomplete="email"
                    class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm placeholder-slate-500
                           focus:outline-none focus:ring-2 focus:ring-amber-500/40 focus:border-amber-500 transition"
                    placeholder="admin@u9itus.com"
                />
            </div>

            <div>
                <div class="flex items-center justify-between mb-1.5">
                    <label for="password" class="block text-sm font-medium text-slate-300">Password</label>
                    <a href="{{ route('password.request') }}" class="text-xs text-amber-400 hover:text-amber-300 transition-colors">
                        Forgot password?
                    </a>
                </div>
                <x-password-input
                    id="password"
                    name="password"
                    required
                    autocomplete="current-password"
                    btn-class="text-amber-400 hover:text-amber-300"
                    class="w-full pr-16 bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-amber-500/40 focus:border-amber-500 transition"
                    placeholder="••••••••"
                />
            </div>

            <button type="submit"
                class="w-full bg-amber-500 hover:bg-amber-400 text-slate-900 font-semibold py-3 rounded-lg text-sm transition-colors flex items-center justify-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                Sign in to Admin Portal
            </button>
        </form>
    </div>

    <div class="text-center mt-6 space-y-2">
        <p class="text-slate-600 text-xs">
            Not an admin?
            <a href="{{ route('login') }}" class="text-slate-500 hover:text-slate-400 underline transition-colors">Politician / Voter sign in</a>
        </p>
        <p class="text-slate-700 text-xs">
            All admin access attempts are logged.
        </p>
    </div>

</div>
</body>
</html>
