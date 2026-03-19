@extends('standalone.layouts.dashboard')

@section('title', 'Admin 2FA Setup')
@section('page-title', 'Admin 2FA Setup')

@section('content')
<div class="max-w-3xl space-y-6">
    <div>
        <h2 class="text-lg font-semibold text-white">Authenticator App Setup</h2>
        <p class="mt-1 text-sm text-slate-400">Set up time-based one-time passcodes for your admin account.</p>
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

    @if(!empty($newRecoveryCodes))
        <div class="p-4 rounded-xl bg-indigo-500/10 border border-indigo-500/30 text-indigo-100 text-sm space-y-3">
            <div>
                <p class="font-semibold text-indigo-200">Save these recovery codes now</p>
                <p class="text-indigo-200/80 text-xs mt-1">These codes are shown once. Each code can be used a single time if you lose access to your authenticator app.</p>
            </div>
            <div class="grid grid-cols-2 gap-2">
                @foreach($newRecoveryCodes as $recoveryCode)
                    <div class="font-mono text-xs bg-slate-950/70 border border-indigo-500/20 rounded-md px-3 py-2 tracking-[0.1em]">
                        {{ $recoveryCode }}
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    @if($errors->any())
        <div class="p-3 rounded-lg bg-red-500/10 border border-red-500/30 text-red-300 text-sm">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="bg-slate-800/60 border border-slate-700/50 rounded-2xl p-6 space-y-5">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-semibold text-white">Current status</p>
                <p class="text-xs text-slate-400">{{ $isEnabled ? 'Enabled and confirmed' : 'Not enabled yet' }}</p>
            </div>
            <span class="px-3 py-1 rounded-full text-xs font-medium {{ $isEnabled ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30' : 'bg-slate-700 text-slate-300 border border-slate-600' }}">
                {{ $isEnabled ? 'Enabled' : 'Disabled' }}
            </span>
        </div>

        @if(!$isEnabled)
            <div class="p-4 rounded-xl bg-slate-900/60 border border-slate-700 space-y-3">
                <p class="text-sm text-slate-300">1. Scan this QR code with Google Authenticator/Authy.</p>
                @if(!empty($otpQrSvg))
                    <div class="bg-white rounded-lg border border-slate-700 p-3 inline-block">
                        {!! $otpQrSvg !!}
                    </div>
                @else
                    <div class="text-xs text-amber-300 bg-amber-500/10 border border-amber-500/30 rounded-lg px-3 py-2">
                        QR code preview is unavailable right now. Use the secret key below to add your account manually.
                    </div>
                @endif
                <div class="bg-slate-950 rounded-lg border border-slate-700 p-3">
                    <p class="text-xs text-slate-400">Manual setup secret key (fallback)</p>
                    <p class="text-emerald-300 font-mono tracking-[0.12em] text-sm mt-1 break-all">{{ $setupSecret }}</p>
                </div>
                <div class="bg-slate-950 rounded-lg border border-slate-700 p-3">
                    <p class="text-xs text-slate-400">If your app supports URI import, use this:</p>
                    <p class="text-xs text-slate-300 mt-1 break-all">{{ $otpAuthUrl }}</p>
                </div>
            </div>

            <form method="POST" action="{{ route('admin.2fa.setup.enable') }}" class="space-y-4">
                @csrf
                <div>
                    <label for="code" class="block text-sm font-medium text-slate-300 mb-1.5">2. Enter current 6-digit code</label>
                    <input
                        id="code"
                        type="text"
                        name="code"
                        inputmode="numeric"
                        pattern="[0-9]*"
                        maxlength="6"
                        required
                        placeholder="123456"
                        class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-lg tracking-[0.3em] text-center focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500"
                    />
                </div>

                <button type="submit" class="bg-emerald-600 hover:bg-emerald-500 text-white font-semibold px-5 py-2.5 rounded-lg transition">
                    Enable Admin 2FA
                </button>
            </form>
        @else
            <div class="space-y-4">
                <form method="POST" action="{{ route('admin.2fa.setup.recovery.rotate') }}" class="space-y-4 p-4 rounded-xl bg-slate-900/60 border border-slate-700">
                    @csrf
                    <p class="text-sm font-semibold text-white">Rotate recovery codes</p>
                    <p class="text-xs text-slate-400">Generate a brand new set of one-time codes. Your previous recovery codes will stop working immediately.</p>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="rotate_current_password" class="block text-sm font-medium text-slate-300 mb-1.5">Current password</label>
                            <x-password-input
                                id="rotate_current_password"
                                name="current_password"
                                required
                                autocomplete="current-password"
                                class="w-full pr-16 bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-cyan-500/50 focus:border-cyan-500 transition"
                                placeholder="Enter current password"
                            />
                        </div>
                        <div>
                            <label for="rotate_code" class="block text-sm font-medium text-slate-300 mb-1.5">Authenticator or recovery code</label>
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
                    </div>

                    <button type="submit" class="bg-cyan-600 hover:bg-cyan-500 text-white font-semibold px-5 py-2.5 rounded-lg transition">
                        Rotate Recovery Codes
                    </button>
                </form>

                <form method="POST" action="{{ route('admin.2fa.setup.disable') }}" class="space-y-4">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="current_password" class="block text-sm font-medium text-slate-300 mb-1.5">Current password</label>
                        <x-password-input
                            id="current_password"
                            name="current_password"
                            required
                            autocomplete="current-password"
                            class="w-full pr-16 bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-red-500/50 focus:border-red-500 transition"
                            placeholder="Enter current password"
                        />
                    </div>
                    <div>
                        <label for="disable_code" class="block text-sm font-medium text-slate-300 mb-1.5">Authenticator code</label>
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
                </div>

                <button type="submit" class="bg-red-600 hover:bg-red-500 text-white font-semibold px-5 py-2.5 rounded-lg transition">
                    Disable Admin 2FA
                </button>
                </form>
            </div>
        @endif
    </div>
</div>
@endsection
