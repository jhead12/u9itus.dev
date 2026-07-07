<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Two-Factor Authentication — {{ config('app.name', 'U9itus') }}</title>
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

<div class="w-full max-w-lg">
    @include('standalone.partials.auth-logo', ['subtitle' => 'Account Security'])

    <div class="bg-slate-800/60 border border-slate-700/50 rounded-2xl p-8 shadow-2xl space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-semibold text-white">Two-Factor Authentication</h1>
                <p class="text-xs text-slate-400 mt-0.5">{{ $isEnabled ? 'Enabled and confirmed' : 'Not enabled yet' }}</p>
            </div>
            <span class="px-3 py-1 rounded-full text-xs font-medium {{ $isEnabled ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30' : 'bg-slate-700 text-slate-300 border border-slate-600' }}">
                {{ $isEnabled ? 'Enabled' : 'Disabled' }}
            </span>
        </div>

        @if(session('success'))
            <div class="p-3 rounded-lg bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 text-sm">
                {{ session('success') }}
            </div>
        @endif

        @if(session('warning'))
            <div class="p-3 rounded-lg bg-amber-500/10 border border-amber-500/30 text-amber-300 text-sm">
                {{ session('warning') }}
            </div>
        @endif

        @if($errors->any())
            <div class="p-3 rounded-lg bg-red-500/10 border border-red-500/30 text-red-300 text-sm">
                {{ $errors->first() }}
            </div>
        @endif

        {{-- Recovery codes shown once after enabling or rotating --}}
        @if(!empty($newRecoveryCodes))
            <div class="p-4 rounded-xl bg-indigo-500/10 border border-indigo-500/30 text-indigo-100 text-sm space-y-3">
                <p class="font-semibold text-indigo-200">Save these recovery codes now</p>
                <p class="text-indigo-200/80 text-xs">These codes are shown only once. Each can be used once if you lose your authenticator app.</p>
                <div class="grid grid-cols-2 gap-2">
                    @foreach($newRecoveryCodes as $code)
                        <div class="font-mono text-xs bg-slate-950/70 border border-indigo-500/20 rounded-md px-3 py-2 tracking-[0.1em]">
                            {{ $code }}
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @if(!$isEnabled)
            {{-- ── Setup / Enroll ── --}}
            <div class="space-y-4">
                <p class="text-sm text-slate-300">1. Scan this QR code with an authenticator app (Google Authenticator, Authy, 2FAS, etc.).</p>

                @if(!empty($qrSvg))
                    <div class="bg-white rounded-xl border border-slate-700 p-4 inline-block">
                        {!! $qrSvg !!}
                    </div>
                @else
                    <div class="text-xs text-amber-300 bg-amber-500/10 border border-amber-500/30 rounded-lg px-3 py-2">
                        QR preview unavailable. Use the secret key below to add your account manually.
                    </div>
                @endif

                <div class="bg-slate-900/60 rounded-lg border border-slate-700 p-3">
                    <p class="text-xs text-slate-400 mb-1">Manual secret key</p>
                    <p class="text-emerald-300 font-mono tracking-[0.12em] text-sm break-all select-all">{{ $setupSecret }}</p>
                </div>
            </div>

            <form method="POST" action="{{ route('2fa.setup.enable') }}" class="space-y-4">
                @csrf
                <div>
                    <label for="code" class="block text-sm font-medium text-slate-300 mb-1.5">
                        2. Enter the current 6-digit code
                    </label>
                    <input
                        id="code"
                        type="text"
                        name="code"
                        inputmode="numeric"
                        pattern="[0-9]*"
                        maxlength="6"
                        required
                        autofocus
                        placeholder="123456"
                        autocomplete="one-time-code"
                        class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-lg tracking-[0.3em] text-center focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500"
                    />
                </div>

                <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-500 text-white font-semibold py-2.5 rounded-lg transition">
                    Enable Two-Factor Authentication
                </button>
            </form>

        @else
            {{-- ── Manage (already enabled) ── --}}
            <div class="space-y-5">

                {{-- Rotate recovery codes --}}
                <div class="p-4 rounded-xl bg-slate-900/60 border border-slate-700 space-y-3">
                    <p class="text-sm font-semibold text-white">Rotate recovery codes</p>
                    <p class="text-xs text-slate-400">Generate a new set of one-time codes. Previous codes stop working immediately.</p>

                    <form method="POST" action="{{ route('2fa.recovery.rotate') }}" class="space-y-3">
                        @csrf
                        <div>
                            <label for="rotate_code" class="block text-xs font-medium text-slate-300 mb-1">Authenticator or recovery code to confirm</label>
                            <input
                                id="rotate_code"
                                type="text"
                                name="code"
                                maxlength="32"
                                required
                                placeholder="123456 or XXXX-XXXX"
                                class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm tracking-[0.08em] focus:outline-none focus:ring-2 focus:ring-cyan-500/50 focus:border-cyan-500"
                            />
                        </div>
                        <button type="submit" class="bg-cyan-600 hover:bg-cyan-500 text-white font-semibold px-5 py-2.5 rounded-lg transition text-sm">
                            Rotate Recovery Codes
                        </button>
                    </form>
                </div>

                {{-- Disable 2FA --}}
                <div class="p-4 rounded-xl bg-slate-900/60 border border-slate-700 space-y-3">
                    <p class="text-sm font-semibold text-white">Disable two-factor authentication</p>
                    <p class="text-xs text-slate-400">You will no longer be required to enter a code at login. Not recommended.</p>

                    <form method="POST" action="{{ route('2fa.setup.disable') }}" class="space-y-3">
                        @csrf
                        <div>
                            <label for="disable_code" class="block text-xs font-medium text-slate-300 mb-1">Enter your current authenticator code</label>
                            <input
                                id="disable_code"
                                type="text"
                                name="code"
                                inputmode="numeric"
                                pattern="[0-9]*"
                                maxlength="6"
                                required
                                placeholder="123456"
                                class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-lg tracking-[0.3em] text-center focus:outline-none focus:ring-2 focus:ring-red-500/50 focus:border-red-500"
                            />
                        </div>
                        <button type="submit" class="bg-red-600 hover:bg-red-500 text-white font-semibold px-5 py-2.5 rounded-lg transition text-sm">
                            Disable Two-Factor Authentication
                        </button>
                    </form>
                </div>

            </div>
        @endif

        <div class="text-xs text-slate-500 pt-2 border-t border-slate-700/50">
            After enabling, you will be asked for your authenticator code each time you log in.
        </div>
    </div>
</div>

</body>
</html>
