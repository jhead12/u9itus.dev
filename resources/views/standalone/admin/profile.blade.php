@extends('standalone.layouts.dashboard')

@section('title', 'My Profile')
@section('page-title', 'My Profile')

@section('content')
<div class="max-w-2xl mx-auto space-y-8">

    @if(session('success'))
        <div class="p-3 rounded-lg bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm">
            {{ session('success') }}
        </div>
    @endif

    {{-- ── Section header ────────────────────────────────────────────── --}}
    <div>
        <h2 class="text-lg font-semibold text-white">Account Settings</h2>
        <p class="mt-1 text-sm text-slate-400">Update your admin name, email address, and password.</p>
    </div>

    {{-- ── Avatar / identity card ─────────────────────────────────────── --}}
    <div class="bg-slate-800/60 border border-slate-700/50 rounded-2xl px-6 py-5 flex items-center gap-5">
        <img src="{{ $user->avatar_url }}"
             alt="{{ $user->name }}"
             class="w-16 h-16 rounded-full object-cover shrink-0 border border-emerald-500/30"
             onerror="this.onerror=null;this.src='https://www.gravatar.com/avatar/00000000000000000000000000000000?d=mp&s=128'">
        <div class="min-w-0">
            <p class="text-base font-semibold text-white truncate">{{ $user->name }}</p>
            <p class="text-sm text-slate-400 truncate">{{ $user->email }}</p>
            <span class="mt-1 inline-flex items-center gap-1.5 text-xs font-medium text-red-400 bg-red-500/10 border border-red-500/20 rounded-full px-2.5 py-0.5">
                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/></svg>
                Administrator
            </span>
        </div>
    </div>

    <div class="bg-slate-800/60 border border-slate-700/50 rounded-2xl px-6 py-5 flex items-center justify-between gap-4">
        <div>
            <p class="text-sm font-semibold text-white">Authenticator Security</p>
            <p class="text-xs text-slate-400 mt-1">
                Status: {{ $user->hasAdminTwoFactorEnabled() ? 'Enabled' : 'Disabled' }}
                @if(!empty($adminTwoFactorEnforced))
                    · Global policy is currently enabled
                @endif
            </p>
        </div>
        <a href="{{ route('admin.2fa.setup') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-cyan-600 hover:bg-cyan-500 text-white text-sm font-medium rounded-lg transition">
            Manage 2FA
        </a>
    </div>

    {{-- ── Profile update form ─────────────────────────────────────────── --}}
    <div class="bg-slate-800/60 border border-slate-700/50 rounded-2xl overflow-hidden">

        <div class="px-6 py-4 border-b border-slate-700/50 flex items-center gap-3">
            <div class="w-9 h-9 rounded-lg bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                </svg>
            </div>
            <div>
                <p class="text-sm font-semibold text-white">Personal Information</p>
                <p class="text-xs text-slate-400">Update your display name and email address.</p>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.profile.update') }}" class="px-6 py-6 space-y-5">
            @csrf
            @method('PUT')

            {{-- Name --}}
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="first_name" class="block text-sm font-medium text-slate-300 mb-1.5">
                        First name <span class="text-red-400">*</span>
                    </label>
                    <input id="first_name" type="text" name="first_name"
                        value="{{ old('first_name', $user->first_name) }}"
                        placeholder="First name"
                        required
                        class="w-full bg-slate-900/60 border {{ $errors->has('first_name') ? 'border-red-500' : 'border-slate-700' }} rounded-lg px-4 py-2.5 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition" />
                    @error('first_name')
                        <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="last_name" class="block text-sm font-medium text-slate-300 mb-1.5">
                        Last name <span class="text-red-400">*</span>
                    </label>
                    <input id="last_name" type="text" name="last_name"
                        value="{{ old('last_name', $user->last_name) }}"
                        placeholder="Last name"
                        required
                        class="w-full bg-slate-900/60 border {{ $errors->has('last_name') ? 'border-red-500' : 'border-slate-700' }} rounded-lg px-4 py-2.5 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition" />
                    @error('last_name')
                        <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- Email --}}
            <div>
                <label for="email" class="block text-sm font-medium text-slate-300 mb-1.5">
                    Email address <span class="text-red-400">*</span>
                </label>
                <input id="email" type="email" name="email"
                    value="{{ old('email', $user->email) }}"
                    placeholder="admin@example.com"
                    required
                    class="w-full bg-slate-900/60 border {{ $errors->has('email') ? 'border-red-500' : 'border-slate-700' }} rounded-lg px-4 py-2.5 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition" />
                @error('email')
                    <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div class="pt-2">
                <button type="submit"
                    class="inline-flex items-center gap-2 px-5 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white text-sm font-medium rounded-lg transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Save Changes
                </button>
            </div>
        </form>
    </div>

    {{-- ── Change password form ─────────────────────────────────────────── --}}
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
                <p class="text-xs text-slate-400">Leave blank to keep your current password.</p>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.profile.update') }}" class="px-6 py-6 space-y-5">
            @csrf
            @method('PUT')

            {{-- Carry current name/email so the controller doesn't blank them out --}}
            <input type="hidden" name="name" value="{{ $user->name }}">
            <input type="hidden" name="email" value="{{ $user->email }}">

            {{-- Current password --}}
            <div>
                <label for="current_password" class="block text-sm font-medium text-slate-300 mb-1.5">
                    Current password <span class="text-red-400">*</span>
                </label>
                <input id="current_password" type="password" name="current_password"
                    placeholder="••••••••"
                    class="w-full bg-slate-900/60 border {{ $errors->has('current_password') ? 'border-red-500' : 'border-slate-700' }} rounded-lg px-4 py-2.5 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500 transition" />
                @error('current_password')
                    <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                @enderror
            </div>

            {{-- New password --}}
            <div>
                <label for="password" class="block text-sm font-medium text-slate-300 mb-1.5">New password</label>
                <input id="password" type="password" name="password"
                    placeholder="Min. 8 characters"
                    class="w-full bg-slate-900/60 border {{ $errors->has('password') ? 'border-red-500' : 'border-slate-700' }} rounded-lg px-4 py-2.5 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500 transition" />
                @error('password')
                    <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                @enderror
            </div>

            {{-- Confirm password --}}
            <div>
                <label for="password_confirmation" class="block text-sm font-medium text-slate-300 mb-1.5">Confirm new password</label>
                <input id="password_confirmation" type="password" name="password_confirmation"
                    placeholder="Repeat new password"
                    class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500 transition" />
            </div>

            <div class="pt-2">
                <button type="submit"
                    class="inline-flex items-center gap-2 px-5 py-2.5 bg-amber-600 hover:bg-amber-500 text-white text-sm font-medium rounded-lg transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                    </svg>
                    Update Password
                </button>
            </div>
        </form>
    </div>

    {{-- ── Account metadata ────────────────────────────────────────────── --}}
    <div class="bg-slate-800/30 border border-slate-700/30 rounded-xl px-6 py-4 text-xs text-slate-500 flex flex-wrap gap-x-6 gap-y-1">
        <span>Account created: <span class="text-slate-400">{{ $user->created_at?->format('M j, Y') ?? 'Unavailable' }}</span></span>
        <span>Last updated: <span class="text-slate-400">{{ $user->updated_at?->format('M j, Y g:i A') ?? 'Unavailable' }}</span></span>
        <span>Role: <span class="text-red-400 font-medium">admin</span></span>
    </div>

</div>
@endsection
