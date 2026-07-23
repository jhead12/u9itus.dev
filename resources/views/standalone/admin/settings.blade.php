@extends('standalone.layouts.dashboard')

@section('title', 'System Settings')
@section('page-title', 'System Settings')

@section('content')
<div class="max-w-3xl mx-auto space-y-8">

    @if(session('success'))
        <div class="p-3 rounded-lg bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm">
            {{ session('success') }}
        </div>
    @endif

    @if(session('warning'))
        <div class="p-3 rounded-lg bg-amber-500/10 border border-amber-500/30 text-amber-400 text-sm">
            {{ session('warning') }}
        </div>
    @endif

    {{-- ── Section header ─────────────────────────────────────────────── --}}
    <div>
        <h2 class="text-lg font-semibold text-white">System Settings</h2>
        <p class="mt-1 text-sm text-slate-400">Manage your account security and platform-wide configuration.</p>
    </div>

    {{-- ── Admin Security Policy ───────────────────────────────────────── --}}
    <div class="bg-slate-800/60 border border-slate-700/50 rounded-2xl overflow-hidden">

        <div class="px-6 py-4 border-b border-slate-700/50 flex items-center gap-3">
            <div class="w-9 h-9 rounded-lg bg-cyan-500/10 border border-cyan-500/20 flex items-center justify-center text-cyan-400">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <div>
                <p class="text-sm font-semibold text-white">Admin Two-Factor Policy</p>
                <p class="text-xs text-slate-400">Global switch that controls whether every admin must pass TOTP at login.</p>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.settings.security') }}" class="px-6 py-6 space-y-5">
            @csrf
            @method('PUT')

            <input type="hidden" name="admin_2fa_enforced" value="0" />
            {{-- Carry current registration_open so this form doesn't reset it --}}
            <input type="hidden" name="registration_open" value="{{ $registrationOpen ? '1' : '0' }}" />

            <label class="flex items-start gap-3 cursor-pointer">
                <input
                    type="checkbox"
                    name="admin_2fa_enforced"
                    value="1"
                    {{ !empty($adminTwoFactorEnforced) ? 'checked' : '' }}
                    class="mt-1 h-4 w-4 rounded border-slate-600 bg-slate-900 text-cyan-500 focus:ring-cyan-500/50"
                />
                <span>
                    <span class="block text-sm font-medium text-slate-200">Require authenticator code for all admin logins</span>
                    <span class="block mt-1 text-xs text-slate-500">When enabled, admins without setup are redirected to complete enrollment before accessing admin dashboard pages.</span>
                </span>
            </label>

            @if (!empty($adminTwoFactorEnforced))
                {{-- SEC-6: current password is required to turn enforcement OFF --}}
                <div class="pt-2">
                    <label for="disable_2fa_current_password" class="block text-sm font-medium text-slate-300 mb-1.5">
                        Current password <span class="text-slate-500 font-normal">(required to disable 2FA enforcement)</span>
                    </label>
                    <input
                        type="password"
                        id="disable_2fa_current_password"
                        name="current_password"
                        autocomplete="current-password"
                        class="w-full rounded-lg border border-slate-600 bg-slate-900 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-cyan-500/50 @error('current_password') border-red-500 @enderror"
                    />
                    @error('current_password')
                        <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                    @enderror
                </div>
            @endif

            <div class="flex items-center justify-between pt-4 border-t border-slate-700/50">
                <a href="{{ route('admin.2fa.setup') }}" class="text-sm text-cyan-400 hover:text-cyan-300">Manage my authenticator setup</a>
                <button type="submit"
                    class="bg-cyan-600 hover:bg-cyan-500 text-white font-semibold rounded-lg px-5 py-2.5 text-sm transition-colors focus:outline-none focus:ring-2 focus:ring-cyan-500/50">
                    Save Security Policy
                </button>
            </div>
        </form>
    </div>

    {{-- ── Registration Access ─────────────────────────────────────────── --}}
    <div class="bg-slate-800/60 border border-slate-700/50 rounded-2xl overflow-hidden">

        <div class="px-6 py-4 border-b border-slate-700/50 flex items-center gap-3">
            <div class="w-9 h-9 rounded-lg bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
                </svg>
            </div>
            <div>
                <p class="text-sm font-semibold text-white">Registration Access</p>
                <p class="text-xs text-slate-400">Temporarily close signups without any code changes.</p>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.settings.security') }}" class="px-6 py-6 space-y-5">
            @csrf
            @method('PUT')

            {{-- Re-send the 2FA value so the shared handler doesn't reset it --}}
            <input type="hidden" name="admin_2fa_enforced" value="{{ $adminTwoFactorEnforced ? '1' : '0' }}" />
            <input type="hidden" name="registration_open" value="0" />

            <label class="flex items-start gap-3 cursor-pointer">
                <input
                    type="checkbox"
                    name="registration_open"
                    value="1"
                    {{ !empty($registrationOpen) ? 'checked' : '' }}
                    class="mt-1 h-4 w-4 rounded border-slate-600 bg-slate-900 text-emerald-500 focus:ring-emerald-500/50"
                />
                <span>
                    <span class="block text-sm font-medium text-slate-200">Allow new user registrations</span>
                    <span class="block mt-1 text-xs text-slate-500">When unchecked, all registration pages return 404 and sign-up links are hidden site-wide. Existing users can still log in.</span>
                </span>
            </label>

            @if(!$registrationOpen)
                <div class="flex items-center gap-2 px-4 py-2.5 rounded-lg bg-amber-500/10 border border-amber-500/30">
                    <svg class="w-4 h-4 text-amber-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                    </svg>
                    <p class="text-xs text-amber-400">Registration is currently <strong>closed</strong>. New users cannot sign up.</p>
                </div>
            @endif

            <div class="flex justify-end pt-4 border-t border-slate-700/50">
                <button type="submit"
                    class="bg-emerald-600 hover:bg-emerald-500 text-white font-semibold rounded-lg px-5 py-2.5 text-sm transition-colors focus:outline-none focus:ring-2 focus:ring-emerald-500/50">
                    Save Registration Policy
                </button>
            </div>
        </form>
    </div>

    {{-- ── Video Duration Limits ───────────────────────────────────────── --}}
    <div class="bg-slate-800/60 border border-slate-700/50 rounded-2xl overflow-hidden">

        <div class="px-6 py-4 border-b border-slate-700/50 flex items-center gap-3">
            <div class="w-9 h-9 rounded-lg bg-pink-500/10 border border-pink-500/20 flex items-center justify-center text-pink-400">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                </svg>
            </div>
            <div>
                <p class="text-sm font-semibold text-white">Video Duration Limits</p>
                <p class="text-xs text-slate-400">Set global campaign video bounds used by upload validation and campaign forms.</p>
            </div>
        </div>

        <div class="px-6 py-6 space-y-4">
            <form method="POST" action="{{ route('admin.platform-settings.update') }}" class="space-y-2">
                @csrf
                <input type="hidden" name="key" value="min_video_duration" />
                <input type="hidden" name="category" value="video" />
                <input type="hidden" name="description" value="Updated from admin system settings" />

                <label for="min_video_duration" class="block text-sm font-medium text-slate-300 mb-1.5">
                    Minimum Video Duration (seconds)
                </label>
                <div class="flex flex-col sm:flex-row gap-3">
                    <input
                        id="min_video_duration"
                        type="number"
                        name="value"
                        min="10"
                        max="180"
                        step="1"
                        value="{{ (int) \App\Services\PlatformSettingsService::get('min_video_duration') }}"
                        class="flex-1 bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-pink-500/50 focus:border-pink-500 transition"
                    />
                    <button type="submit"
                        class="bg-pink-600 hover:bg-pink-500 text-white font-semibold rounded-lg px-5 py-2.5 text-sm transition-colors focus:outline-none focus:ring-2 focus:ring-pink-500/50">
                        Save Minimum
                    </button>
                </div>
            </form>

            <form method="POST" action="{{ route('admin.platform-settings.update') }}" class="space-y-2 pt-3 border-t border-slate-700/50">
                @csrf
                <input type="hidden" name="key" value="max_video_duration" />
                <input type="hidden" name="category" value="video" />
                <input type="hidden" name="description" value="Updated from admin system settings" />

                <label for="max_video_duration" class="block text-sm font-medium text-slate-300 mb-1.5">
                    Maximum Video Duration (seconds)
                </label>
                <div class="flex flex-col sm:flex-row gap-3">
                    <input
                        id="max_video_duration"
                        type="number"
                        name="value"
                        min="10"
                        max="180"
                        step="1"
                        value="{{ (int) \App\Services\PlatformSettingsService::get('max_video_duration') }}"
                        class="flex-1 bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-pink-500/50 focus:border-pink-500 transition"
                    />
                    <button type="submit"
                        class="bg-slate-700 hover:bg-slate-600 text-white font-semibold rounded-lg px-5 py-2.5 text-sm transition-colors focus:outline-none focus:ring-2 focus:ring-slate-500/50">
                        Save Maximum
                    </button>
                </div>
            </form>

            <p class="text-xs text-slate-500">Allowed range is 10 to 180 seconds. These settings apply to create/edit forms and upload duration checks.</p>
        </div>
    </div>

    {{-- ── Change Password ──────────────────────────────────────────────── --}}
    <div class="bg-slate-800/60 border border-slate-700/50 rounded-2xl overflow-hidden">

        <div class="px-6 py-4 border-b border-slate-700/50 flex items-center gap-3">
            <div class="w-9 h-9 rounded-lg bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-amber-400">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                </svg>
            </div>
            <div>
                <p class="text-sm font-semibold text-white">Change Password</p>
                <p class="text-xs text-slate-400">Update your admin account password for enhanced security.</p>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.settings.password') }}" class="px-6 py-6 space-y-5">
            @csrf
            @method('PUT')

            @if(session('password_success'))
                <div class="p-3 rounded-lg bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm">
                    {{ session('password_success') }}
                </div>
            @endif

            @if($errors->updatePassword->any())
                <div class="p-3 rounded-lg bg-red-500/10 border border-red-500/30 text-red-400 text-sm space-y-1">
                    <ul class="list-disc list-inside">
                        @foreach($errors->updatePassword->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div>
                <label for="current_password" class="block text-sm font-medium text-slate-300 mb-1.5">
                    Current password <span class="text-red-400">*</span>
                </label>
                <x-password-input
                    id="current_password"
                    name="current_password"
                    required
                    autocomplete="current-password"
                    class="w-full pr-16 bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500 transition"
                    placeholder="Enter your current password"
                />
                <p class="mt-1 text-xs text-slate-500">Verify your identity before changing your password.</p>
            </div>

            <div>
                <label for="new_password" class="block text-sm font-medium text-slate-300 mb-1.5">
                    New password <span class="text-red-400">*</span>
                </label>
                <x-password-input
                    id="new_password"
                    name="new_password"
                    required
                    autocomplete="new-password"
                    class="w-full pr-16 bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500 transition"
                    placeholder="Enter new password (min. 8 characters)"
                />
            </div>

            <div>
                <label for="new_password_confirmation" class="block text-sm font-medium text-slate-300 mb-1.5">
                    Confirm new password <span class="text-red-400">*</span>
                </label>
                <x-password-input
                    id="new_password_confirmation"
                    name="new_password_confirmation"
                    required
                    autocomplete="new-password"
                    class="w-full pr-16 bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500 transition"
                    placeholder="Confirm new password"
                />
            </div>

            <div class="flex items-center justify-between pt-4 border-t border-slate-700/50">
                <p class="text-xs text-slate-500">Password must be at least 8 characters long.</p>
                <button type="submit"
                    class="bg-amber-500 hover:bg-amber-400 text-slate-900 font-semibold rounded-lg px-5 py-2.5 text-sm transition-colors focus:outline-none focus:ring-2 focus:ring-amber-500/50">
                    Update Password
                </button>
            </div>
        </form>

    </div>

    {{-- ── SMTP / Email Settings ───────────────────────────────────────── --}}
    <div class="bg-slate-800/60 border border-slate-700/50 rounded-2xl overflow-hidden">

        <div class="px-6 py-4 border-b border-slate-700/50 flex items-center gap-3">
            <div class="w-9 h-9 rounded-lg bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                </svg>
            </div>
            <div>
                <p class="text-sm font-semibold text-white">Email / SMTP Configuration</p>
                <p class="text-xs text-slate-400">Controls all outgoing platform emails (welcome, verification, notifications).</p>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.settings.update') }}" class="px-6 py-6 space-y-5">
            @csrf
            @method('PUT')

            {{-- Mailer type --}}
            <div>
                <label for="MAIL_MAILER" class="block text-sm font-medium text-slate-300 mb-1.5">
                    Mail driver <span class="text-red-400">*</span>
                </label>
                <select id="MAIL_MAILER" name="MAIL_MAILER"
                    class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition"
                    onchange="toggleMailerFields(this.value)">
                    @foreach(['mailgun' => 'Mailgun (API — recommended)', 'smtp' => 'SMTP relay', 'sendmail' => 'Sendmail', 'log' => 'Log (dev only)', 'array' => 'Array (testing)'] as $val => $label)
                        <option value="{{ $val }}" {{ ($smtp['MAIL_MAILER'] ?? 'mailgun') === $val ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-slate-500">Use <code>Mailgun (API)</code> for the fastest, most reliable delivery. Switch to <code>SMTP</code> for any other provider.</p>
            </div>

            {{-- Mailgun API fields --}}
            <div id="mailgun-fields" class="space-y-4 p-4 rounded-xl bg-indigo-500/5 border border-indigo-500/20">
                <p class="text-xs font-semibold text-indigo-400 uppercase tracking-wider">Mailgun API credentials</p>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="MAILGUN_DOMAIN" class="block text-sm font-medium text-slate-300 mb-1.5">
                            Mailgun domain <span class="text-red-400">*</span>
                        </label>
                        <input id="MAILGUN_DOMAIN" type="text" name="MAILGUN_DOMAIN"
                            value="{{ old('MAILGUN_DOMAIN', $smtp['MAILGUN_DOMAIN'] ?? '') }}"
                            placeholder="mg.yourdomain.com"
                            class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500 transition" />
                        <p class="mt-1 text-xs text-slate-500">The sending domain registered in your Mailgun account.</p>
                    </div>
                    <div>
                        <label for="MAILGUN_ENDPOINT" class="block text-sm font-medium text-slate-300 mb-1.5">Region endpoint</label>
                        <select id="MAILGUN_ENDPOINT" name="MAILGUN_ENDPOINT"
                            class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500 transition">
                            <option value="api.mailgun.net"  {{ ($smtp['MAILGUN_ENDPOINT'] ?? 'api.mailgun.net') === 'api.mailgun.net'    ? 'selected' : '' }}>US — api.mailgun.net</option>
                            <option value="api.eu.mailgun.net" {{ ($smtp['MAILGUN_ENDPOINT'] ?? '') === 'api.eu.mailgun.net' ? 'selected' : '' }}>EU — api.eu.mailgun.net</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label for="MAILGUN_SECRET" class="block text-sm font-medium text-slate-300 mb-1.5">
                        Mailgun API key (Private key) <span class="text-red-400">*</span>
                    </label>
                    <div class="relative">
                        <input id="MAILGUN_SECRET" type="password" name="MAILGUN_SECRET"
                            value="{{ old('MAILGUN_SECRET', $smtp['MAILGUN_SECRET'] ?? '') }}"
                            placeholder="key-••••••••••••••••••••••••••••••••"
                            autocomplete="new-password"
                            class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500 transition pr-10" />
                        <button type="button" onclick="togglePasswordVisibility('MAILGUN_SECRET')"
                            class="absolute inset-y-0 right-0 px-3 text-slate-400 hover:text-slate-200">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                        </button>
                    </div>
                    <p class="mt-1 text-xs text-slate-500">Found in Mailgun → Account → API Keys → Private API key.</p>
                </div>
            </div>

            {{-- SMTP fields --}}
            <div id="smtp-fields" class="space-y-5">

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="sm:col-span-2">
                        <label for="MAIL_HOST" class="block text-sm font-medium text-slate-300 mb-1.5">
                            SMTP host <span class="text-red-400">*</span>
                        </label>
                        <input id="MAIL_HOST" type="text" name="MAIL_HOST"
                            value="{{ old('MAIL_HOST', $smtp['MAIL_HOST'] ?? '') }}"
                            placeholder="smtp.sendgrid.net"
                            class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition" />
                    </div>
                    <div>
                        <label for="MAIL_PORT" class="block text-sm font-medium text-slate-300 mb-1.5">
                            Port <span class="text-red-400">*</span>
                        </label>
                        <input id="MAIL_PORT" type="number" name="MAIL_PORT"
                            value="{{ old('MAIL_PORT', $smtp['MAIL_PORT'] ?? '587') }}"
                            placeholder="587"
                            class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition" />
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="MAIL_USERNAME" class="block text-sm font-medium text-slate-300 mb-1.5">SMTP username</label>
                        <input id="MAIL_USERNAME" type="text" name="MAIL_USERNAME"
                            value="{{ old('MAIL_USERNAME', $smtp['MAIL_USERNAME'] ?? '') }}"
                            placeholder="apikey or your@email.com"
                            autocomplete="off"
                            class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition" />
                    </div>
                    <div>
                        <label for="MAIL_PASSWORD" class="block text-sm font-medium text-slate-300 mb-1.5">SMTP password / API key</label>
                        <div class="relative">
                            <input id="MAIL_PASSWORD" type="password" name="MAIL_PASSWORD"
                                value="{{ old('MAIL_PASSWORD', $smtp['MAIL_PASSWORD'] ?? '') }}"
                                placeholder="••••••••••••"
                                autocomplete="new-password"
                                class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition pr-10" />
                            <button type="button" onclick="togglePasswordVisibility('MAIL_PASSWORD')"
                                class="absolute inset-y-0 right-0 px-3 text-slate-400 hover:text-slate-200">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <div>
                    <label for="MAIL_ENCRYPTION" class="block text-sm font-medium text-slate-300 mb-1.5">Encryption</label>
                    <select id="MAIL_ENCRYPTION" name="MAIL_ENCRYPTION"
                        class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition">
                        @foreach(['' => 'None', 'tls' => 'TLS (recommended for port 587)', 'ssl' => 'SSL (port 465)', 'starttls' => 'STARTTLS'] as $val => $label)
                            <option value="{{ $val }}" {{ ($smtp['MAIL_ENCRYPTION'] ?? 'tls') === $val ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>

            </div>  {{-- /smtp-fields --}}

            {{-- From address block (always visible) --}}
            <div class="pt-4 border-t border-slate-700/50 grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="MAIL_FROM_ADDRESS" class="block text-sm font-medium text-slate-300 mb-1.5">
                        From address <span class="text-red-400">*</span>
                    </label>
                    <input id="MAIL_FROM_ADDRESS" type="email" name="MAIL_FROM_ADDRESS"
                        value="{{ old('MAIL_FROM_ADDRESS', $smtp['MAIL_FROM_ADDRESS'] ?? '') }}"
                        placeholder="no-reply@u9itus.com"
                        class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition" />
                </div>
                <div>
                    <label for="MAIL_FROM_NAME" class="block text-sm font-medium text-slate-300 mb-1.5">
                        From name <span class="text-red-400">*</span>
                    </label>
                    <input id="MAIL_FROM_NAME" type="text" name="MAIL_FROM_NAME"
                        value="{{ old('MAIL_FROM_NAME', $smtp['MAIL_FROM_NAME'] ?? config('app.name')) }}"
                        placeholder="U9itus"
                        class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition" />
                </div>
            </div>

            {{-- Quick-reference presets --}}
            <div class="pt-4 border-t border-slate-700/50">
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-3">Provider presets</p>
                <div class="flex flex-wrap gap-2">
                    @foreach([
                        ['label' => 'SendGrid',  'host' => 'smtp.sendgrid.net',         'port' => '587', 'enc' => 'tls',  'user' => 'apikey'],
                        ['label' => 'Mailgun',   'host' => 'smtp.mailgun.org',           'port' => '587', 'enc' => 'tls',  'user' => ''],
                        ['label' => 'Postmark',  'host' => 'smtp.postmarkapp.com',       'port' => '587', 'enc' => 'tls',  'user' => ''],
                        ['label' => 'Resend',    'host' => 'smtp.resend.com',            'port' => '465', 'enc' => 'ssl',  'user' => 'resend'],
                        ['label' => 'Gmail',     'host' => 'smtp.gmail.com',             'port' => '587', 'enc' => 'tls',  'user' => ''],
                        ['label' => 'Brevo',     'host' => 'smtp-relay.brevo.com',       'port' => '587', 'enc' => 'tls',  'user' => ''],
                        ['label' => 'AWS SES',   'host' => 'email-smtp.us-east-1.amazonaws.com', 'port' => '587', 'enc' => 'tls', 'user' => ''],
                    ] as $p)
                        <button type="button"
                            onclick="applyPreset('{{ $p['host'] }}', '{{ $p['port'] }}', '{{ $p['enc'] }}', '{{ $p['user'] }}')"
                            class="px-3 py-1.5 rounded-lg bg-slate-700/50 border border-slate-600/50 text-xs text-slate-300 hover:bg-slate-700 hover:text-white transition">
                            {{ $p['label'] }}
                        </button>
                    @endforeach
                </div>
                <p class="mt-2 text-xs text-slate-500">Clicking a preset fills in the host, port, and encryption — you still need to enter your credentials.</p>
            </div>

            {{-- Actions --}}
            <div class="flex items-center gap-3 pt-4 border-t border-slate-700/50">
                <button type="submit"
                    class="inline-flex items-center gap-2 bg-emerald-500 hover:bg-emerald-400 text-white font-semibold px-5 py-2.5 rounded-lg text-sm transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    Save Settings
                </button>

                <button type="button" onclick="sendTestEmail()"
                    class="inline-flex items-center gap-2 bg-slate-700 hover:bg-slate-600 text-slate-200 font-medium px-5 py-2.5 rounded-lg text-sm transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                    Send Test Email
                </button>

                <span id="test-status" class="text-sm text-slate-400 hidden"></span>
            </div>

        </form>
    </div>

    {{-- ── Common provider docs ────────────────────────────────────────── --}}
    <div class="bg-slate-800/40 border border-slate-700/30 rounded-xl p-5">
        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-3">Quick reference</p>
        <div class="overflow-x-auto">
            <table class="w-full text-xs text-slate-400">
                <thead>
                    <tr class="border-b border-slate-700/40">
                        <th class="text-left py-2 pr-4 font-medium text-slate-300">Provider</th>
                        <th class="text-left py-2 pr-4 font-medium text-slate-300">Host</th>
                        <th class="text-left py-2 pr-4 font-medium text-slate-300">Port</th>
                        <th class="text-left py-2 pr-4 font-medium text-slate-300">Encryption</th>
                        <th class="text-left py-2 font-medium text-slate-300">Username</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700/20">
                    <tr><td class="py-1.5 pr-4 text-slate-300">SendGrid</td><td class="pr-4">smtp.sendgrid.net</td><td class="pr-4">587</td><td class="pr-4">TLS</td><td>apikey</td></tr>
                    <tr><td class="py-1.5 pr-4 text-slate-300">Mailgun</td><td class="pr-4">smtp.mailgun.org</td><td class="pr-4">587</td><td class="pr-4">TLS</td><td>your-mailgun-user</td></tr>
                    <tr><td class="py-1.5 pr-4 text-slate-300">Postmark</td><td class="pr-4">smtp.postmarkapp.com</td><td class="pr-4">587</td><td class="pr-4">TLS</td><td>POSTMARK_API_TOKEN</td></tr>
                    <tr><td class="py-1.5 pr-4 text-slate-300">Resend</td><td class="pr-4">smtp.resend.com</td><td class="pr-4">465</td><td class="pr-4">SSL</td><td>resend</td></tr>
                    <tr><td class="py-1.5 pr-4 text-slate-300">Gmail</td><td class="pr-4">smtp.gmail.com</td><td class="pr-4">587</td><td class="pr-4">TLS</td><td>your@gmail.com</td></tr>
                    <tr><td class="py-1.5 pr-4 text-slate-300">Brevo</td><td class="pr-4">smtp-relay.brevo.com</td><td class="pr-4">587</td><td class="pr-4">TLS</td><td>your@email.com</td></tr>
                    <tr><td class="py-1.5 pr-4 text-slate-300">AWS SES</td><td class="pr-4">email-smtp.us-east-1.amazonaws.com</td><td class="pr-4">587</td><td class="pr-4">TLS</td><td>SMTP access key ID</td></tr>
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
function toggleMailerFields(val) {
    document.getElementById('mailgun-fields').style.display = val === 'mailgun' ? 'block' : 'none';
    document.getElementById('smtp-fields').style.display    = val === 'smtp'    ? 'block' : 'none';
}

function togglePasswordVisibility(id) {
    const el = document.getElementById(id);
    el.type = el.type === 'password' ? 'text' : 'password';
}

function applyPreset(host, port, enc, user) {
    document.getElementById('MAIL_HOST').value = host;
    document.getElementById('MAIL_PORT').value = port;
    document.getElementById('MAIL_ENCRYPTION').value = enc;
    if (user) document.getElementById('MAIL_USERNAME').value = user;
    const mailerEl = document.getElementById('MAIL_MAILER');
    mailerEl.value = 'smtp';
    toggleMailerFields('smtp');
}

function sendTestEmail() {
    const status = document.getElementById('test-status');
    status.textContent = 'Sending…';
    status.classList.remove('hidden', 'text-red-400', 'text-emerald-400');
    status.classList.add('text-slate-400');

    fetch('{{ route('admin.settings.test-email') }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
        },
    })
    .then(r => r.json())
    .then(data => {
        status.classList.remove('hidden', 'text-slate-400');
        if (data.success) {
            status.textContent = '✓ ' + data.message;
            status.classList.add('text-emerald-400');
        } else {
            status.textContent = '✗ ' + data.message;
            status.classList.add('text-red-400');
        }
    })
    .catch(() => {
        status.classList.remove('hidden', 'text-slate-400');
        status.textContent = '✗ Request failed';
        status.classList.add('text-red-400');
    });
}

// Init visibility on load
document.addEventListener('DOMContentLoaded', () => {
    toggleMailerFields(document.getElementById('MAIL_MAILER').value);
});
</script>
@endpush
