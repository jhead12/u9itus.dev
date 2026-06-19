<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Registration Closed — U9itus</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700,800&display=swap" rel="stylesheet" />
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
    @endif
    <style>* { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="min-h-screen bg-slate-900 flex items-center justify-center px-4 py-12 antialiased">

<div class="w-full max-w-md">

    {{-- Logo --}}
    <div class="text-center mb-10">
        <a href="/" class="text-3xl font-light tracking-tight">
            <span class="font-bold text-white">U9</span><span class="text-emerald-400">itus</span>
        </a>
        <p class="mt-2 text-slate-400 text-sm">Political Loyalty Ads Platform</p>
    </div>

    <div class="bg-slate-800/60 border border-slate-700/50 rounded-2xl p-8 shadow-2xl">

        {{-- Icon --}}
        <div class="flex justify-center mb-6">
            <div class="w-16 h-16 rounded-full bg-amber-500/10 border border-amber-500/20 flex items-center justify-center">
                <svg class="w-8 h-8 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                </svg>
            </div>
        </div>

        <h1 class="text-xl font-bold text-white text-center mb-2">Registrations are temporarily closed</h1>
        <p class="text-slate-400 text-sm text-center mb-8">
            We're not accepting new accounts right now, but we'll reopen soon.
            Drop your email below and we'll notify you the moment signups go live.
        </p>

        {{-- Success flash --}}
        @if(session('mailing_list_success'))
            <div class="mb-6 flex items-start gap-3 px-4 py-3 rounded-xl bg-emerald-500/10 border border-emerald-500/30">
                <svg class="w-5 h-5 text-emerald-400 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                <p class="text-sm text-emerald-300">{{ session('mailing_list_success') }}</p>
            </div>
        @endif

        {{-- Validation errors --}}
        @if($errors->any())
            <div class="mb-4 px-4 py-3 rounded-xl bg-red-500/10 border border-red-500/30 text-red-400 text-sm">
                {{ $errors->first() }}
            </div>
        @endif

        @if(!session('mailing_list_success'))
        <form method="POST" action="{{ route('register.mailing-list.store') }}" class="space-y-4">
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
                    placeholder="you@example.com"
                    class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm placeholder-slate-500
                           focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition"
                />
            </div>

            <button
                type="submit"
                class="w-full bg-emerald-500 hover:bg-emerald-400 text-slate-900 font-semibold rounded-lg px-4 py-2.5 text-sm transition-colors focus:outline-none focus:ring-2 focus:ring-emerald-500/50"
            >
                Notify me when registration opens
            </button>
        </form>
        @endif

        <p class="mt-6 text-center text-xs text-slate-600">
            Already have an account?
            <a href="{{ route('login') }}" class="text-slate-400 hover:text-white transition">Sign in</a>
        </p>

    </div>

    <p class="mt-6 text-center text-xs text-slate-600">
        We'll only use your email to let you know when registration opens. No spam.
    </p>

</div>

</body>
</html>
