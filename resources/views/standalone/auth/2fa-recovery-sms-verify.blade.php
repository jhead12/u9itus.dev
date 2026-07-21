<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SMS Recovery — {{ config('app.name', 'U9itus') }}</title>
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
    @include('standalone.partials.auth-logo', ['subtitle' => 'Security Verification'])

    <div class="bg-slate-800/60 border border-slate-700/50 rounded-2xl p-8 shadow-2xl">
        <h1 class="text-xl font-semibold text-white mb-1">Enter Recovery Code</h1>
        <p class="text-sm text-slate-400 mb-6">Enter the 6-digit code we texted you. This will disable your current two-factor authentication.</p>

        @if(session('success'))
            <div class="mb-4 p-3 rounded-lg bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 text-sm">
                {{ session('success') }}
            </div>
        @endif

        @if($errors->any())
            <div class="mb-4 p-3 rounded-lg bg-red-500/10 border border-red-500/30 text-red-300 text-sm">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('2fa.recovery-sms.verify.submit') }}" class="space-y-4">
            @csrf
            <div>
                <label for="code" class="block text-sm font-medium text-slate-300 mb-1.5">Code</label>
                <input
                    id="code"
                    type="text"
                    name="code"
                    maxlength="6"
                    inputmode="numeric"
                    pattern="\d{6}"
                    required
                    autofocus
                    placeholder="123456"
                    class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm tracking-[0.12em] text-center focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500"
                    autocomplete="one-time-code"
                />
            </div>

            <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-500 text-white font-semibold py-2.5 rounded-lg transition">
                Verify and Disable 2FA
            </button>
        </form>

        <form method="POST" action="{{ route('2fa.recovery-sms.send') }}" class="mt-3">
            @csrf
            <button type="submit" class="text-xs text-emerald-400 hover:text-emerald-300">Didn't get a code? Request another</button>
        </form>

        <div class="mt-5 text-xs text-slate-500">
            <a class="text-emerald-400 hover:text-emerald-300" href="{{ route('2fa.challenge') }}">Back to security challenge</a>.
        </div>
    </div>
</div>

</body>
</html>
