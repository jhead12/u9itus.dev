<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Two-Factor Verification — {{ config('app.name', 'U9itus') }}</title>
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
    @include('standalone.partials.auth-logo', ['subtitle' => 'Security Verification'])

    <div class="bg-slate-800/60 border border-slate-700/50 rounded-2xl p-8 shadow-2xl">
        <h1 class="text-xl font-semibold text-white mb-1">Security Challenge</h1>
        <p class="text-sm text-slate-400 mb-6">Enter your authenticator code or a one-time recovery code to continue.</p>

        @if(session('warning'))
            <div class="mb-4 p-3 rounded-lg bg-amber-500/10 border border-amber-500/30 text-amber-300 text-sm">
                {{ session('warning') }}
            </div>
        @endif

        @if($errors->any())
            <div class="mb-4 p-3 rounded-lg bg-red-500/10 border border-red-500/30 text-red-300 text-sm">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('2fa.challenge.verify') }}" class="space-y-4">
            @csrf
            <div>
                <label for="code" class="block text-sm font-medium text-slate-300 mb-1.5">Code</label>
                <input
                    id="code"
                    type="text"
                    name="code"
                    maxlength="32"
                    required
                    autofocus
                    placeholder="123456 or XXXX-XXXX"
                    class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm tracking-[0.12em] text-center focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500"
                    autocomplete="one-time-code"
                />
            </div>

            <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-500 text-white font-semibold py-2.5 rounded-lg transition">
                Verify and Continue
            </button>
        </form>

        <div class="mt-5 text-xs text-slate-500">
            Need to reconfigure 2FA? Visit your
            <a class="text-emerald-400 hover:text-emerald-300" href="{{ route('2fa.setup') }}">account security settings</a>.
        </div>
    </div>
</div>

</body>
</html>
