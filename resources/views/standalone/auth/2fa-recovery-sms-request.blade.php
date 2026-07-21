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
        <h1 class="text-xl font-semibold text-white mb-1">Recover Your Account</h1>

        @if($errors->any())
            <div class="mb-4 p-3 rounded-lg bg-red-500/10 border border-red-500/30 text-red-300 text-sm">
                {{ $errors->first() }}
            </div>
        @endif

        @if($phoneVerified)
            <p class="text-sm text-slate-400 mb-6">
                We'll text a one-time code to your phone ending in
                <span class="text-slate-200 font-medium">{{ substr((string) auth()->user()->phone, -4) }}</span>.
                Entering it will disable your current two-factor authentication so you can log back in.
                You can set up 2FA again afterward.
            </p>

            <form method="POST" action="{{ route('2fa.recovery-sms.send') }}">
                @csrf
                <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-500 text-white font-semibold py-2.5 rounded-lg transition">
                    Text Me a Recovery Code
                </button>
            </form>
        @else
            <p class="text-sm text-slate-400 mb-6">
                This account doesn't have a verified phone number on file, so SMS recovery isn't available.
                Please contact support for manual account recovery.
            </p>
        @endif

        <div class="mt-5 text-xs text-slate-500">
            <a class="text-emerald-400 hover:text-emerald-300" href="{{ route('2fa.challenge') }}">Back to security challenge</a>.
        </div>
    </div>
</div>

</body>
</html>
