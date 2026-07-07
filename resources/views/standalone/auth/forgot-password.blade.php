<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reset Password — {{ config('app.name', 'U9itus') }}</title>
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
    </div>

    <div class="bg-slate-800/60 border border-slate-700/50 rounded-2xl p-8 shadow-2xl">
        <h2 class="text-xl font-semibold text-white mb-1">Forgot your password?</h2>
        <p class="text-sm text-slate-400 mb-6">Enter your email and we'll send you a reset link.</p>

        @if(session('status'))
            <div class="mb-4 p-3 rounded-lg bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm">
                {{ session('status') }}
            </div>
        @endif

        @if($errors->any())
            <div class="mb-4 p-3 rounded-lg bg-red-500/10 border border-red-500/30 text-red-400 text-sm">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
            @csrf

            <div>
                <label for="email" class="block text-sm font-medium text-slate-300 mb-1.5">Email address</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus
                    class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition"
                    placeholder="you@example.com" />
            </div>

            <button type="submit"
                class="w-full bg-emerald-500 hover:bg-emerald-400 text-slate-900 font-semibold rounded-lg px-4 py-2.5 text-sm transition-colors focus:outline-none focus:ring-2 focus:ring-emerald-500/50">
                Send Reset Link
            </button>
        </form>

        <p class="mt-6 text-center text-sm text-slate-500">
            <a href="{{ route('login') }}" class="text-emerald-400 hover:text-emerald-300 font-medium transition">← Back to sign in</a>
        </p>
    </div>

</div>
</body>
</html>
