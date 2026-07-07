<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reset Password — {{ config('app.name', 'U9itus') }}</title>
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
<body class="min-h-screen bg-slate-900 flex items-center justify-center px-4 antialiased">

<div class="w-full max-w-md">

    <div class="text-center mb-8">
        <a href="/" class="inline-block"><img src="{{ asset('media/u9itus-logo.svg') }}" alt="U9itus" class="h-10 mx-auto mb-2"></a>
        <p class="mt-2 text-slate-400 text-sm">Political Loyalty Ads Platform</p>
    </div>

    <div class="bg-slate-800/60 border border-slate-700/50 rounded-2xl p-8 shadow-2xl">
        <h2 class="text-xl font-semibold text-white mb-1">Set new password</h2>
        <p class="text-sm text-slate-400 mb-6">Enter your email and choose a new password below.</p>

        @if($errors->any())
            <div class="mb-4 p-3 rounded-lg bg-red-500/10 border border-red-500/30 text-red-400 text-sm">
                <ul class="list-disc list-inside space-y-1">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('password.update') }}" class="space-y-4">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">

            <div>
                <label for="email" class="block text-sm font-medium text-slate-300 mb-1.5">Email address</label>
                <input id="email" type="email" name="email" value="{{ old('email', request('email')) }}"
                    required autofocus autocomplete="username"
                    class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm placeholder-slate-500
                           focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition"
                    placeholder="you@example.com" />
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-slate-300 mb-1.5">New password</label>
                <x-password-input
                    id="password"
                    name="password"
                    required
                    autocomplete="new-password"
                    class="w-full pr-16 bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition"
                    placeholder="••••••••"
                />
            </div>

            <div>
                <label for="password_confirmation" class="block text-sm font-medium text-slate-300 mb-1.5">Confirm new password</label>
                <x-password-input
                    id="password_confirmation"
                    name="password_confirmation"
                    required
                    autocomplete="new-password"
                    class="w-full pr-16 bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition"
                    placeholder="••••••••"
                />
            </div>

            <button type="submit"
                class="w-full bg-emerald-500 hover:bg-emerald-400 text-white font-semibold py-3 rounded-lg text-sm transition-colors mt-2">
                Reset Password
            </button>
        </form>
    </div>

    <p class="text-center mt-6 text-slate-500 text-sm">
        Remember your password?
        <a href="{{ route('login') }}" class="text-emerald-400 hover:text-emerald-300 font-medium transition-colors">Sign in</a>
    </p>

</div>
</body>
</html>
