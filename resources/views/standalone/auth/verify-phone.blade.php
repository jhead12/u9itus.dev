<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verify Phone — {{ config('app.name', 'U9itus') }}</title>
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

    <div class="text-center mb-8">
        <a href="/" class="inline-block"><img src="{{ asset('media/u9itus-logo.svg') }}" alt="U9itus" class="h-10 mx-auto mb-2"></a>
        <p class="mt-2 text-slate-400 text-sm">Political Loyalty Ads Platform</p>
    </div>

    <div class="bg-slate-800/60 border border-slate-700/50 rounded-2xl p-8 shadow-2xl text-center">
        <div class="flex items-center justify-center w-16 h-16 rounded-full bg-emerald-500/10 border border-emerald-500/30 mx-auto mb-5">
            <svg class="w-8 h-8 text-emerald-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75A2.25 2.25 0 0 1 4.5 4.5h15a2.25 2.25 0 0 1 2.25 2.25v10.5A2.25 2.25 0 0 1 19.5 19.5h-15a2.25 2.25 0 0 1-2.25-2.25V6.75Zm7.75 8.25h4" />
            </svg>
        </div>

        <h2 class="text-xl font-semibold text-white mb-2">Verify your phone number</h2>
        <p class="text-slate-400 text-sm mb-6">
            Enter the 6-digit code sent to
            <span class="text-white font-medium">{{ $user->phone }}</span>.
        </p>

        @if (session('success'))
            <div class="mb-5 p-3 rounded-lg bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-5 p-3 rounded-lg bg-red-500/10 border border-red-500/30 text-red-400 text-sm text-left">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('phone.verify.submit') }}" class="space-y-4">
            @csrf

            <div>
                <label for="code" class="block text-sm font-medium text-slate-300 mb-1.5 text-left">Verification code</label>
                <input
                    id="code"
                    type="text"
                    name="code"
                    value="{{ old('code') }}"
                    inputmode="numeric"
                    maxlength="6"
                    required
                    autofocus
                    class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm tracking-[0.35em] text-center placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition"
                    placeholder="123456"
                />
            </div>

            <button
                type="submit"
                class="w-full py-2.5 px-4 bg-emerald-500 hover:bg-emerald-400 text-slate-900 font-semibold rounded-xl transition-colors text-sm"
            >
                Verify Phone
            </button>
        </form>

        <form method="POST" action="{{ route('phone.resend') }}" class="mt-4">
            @csrf
            <button type="submit" class="text-emerald-400 hover:text-emerald-300 text-sm transition-colors">
                Resend Code
            </button>
        </form>

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
