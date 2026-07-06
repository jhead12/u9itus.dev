@extends('layouts.voter')

@section('title', 'My Profile')

@section('content')
<div class="px-4 sm:px-6 lg:px-8 py-8 max-w-2xl mx-auto space-y-6">

    <div>
        <h1 class="text-2xl font-bold text-white">My Profile</h1>
        <p class="text-slate-400 text-sm mt-0.5">Manage your personal information</p>
    </div>

    @if($voter)

    {{-- Avatar / Identity card --}}
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-2xl p-6 flex items-center gap-5">
        <img src="{{ $user->avatar_url }}"
             alt="{{ $user->name ?? 'User' }}"
             class="w-16 h-16 rounded-2xl object-cover shadow-lg shrink-0"
             onerror="this.onerror=null;this.src='https://www.gravatar.com/avatar/00000000000000000000000000000000?d=mp&s=128'">
        <div class="min-w-0">
            <p class="text-white text-lg font-semibold truncate">{{ $user->name }}</p>
            <p class="text-slate-400 text-sm truncate">{{ $user->email }}</p>
            <a href="https://gravatar.com" target="_blank" rel="noopener" class="text-xs text-slate-500 hover:text-emerald-400 transition">Change profile photo at gravatar.com &rarr;</a>
            <div class="flex items-center gap-2 mt-1.5 flex-wrap">
                @if($voter->is_verified)
                <span class="inline-flex items-center gap-1 text-xs bg-emerald-900/40 border border-emerald-700/40 text-emerald-400 rounded-full px-2.5 py-0.5">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                    </svg>
                    Verified Voter
                </span>
                @else
                <span class="inline-flex items-center gap-1 text-xs bg-amber-900/30 border border-amber-700/30 text-amber-400 rounded-full px-2.5 py-0.5">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Unverified
                </span>
                @endif
                <span class="text-xs text-slate-500">
                    Trust score: <span class="text-slate-300 font-medium">{{ $voter->trust_score ?? 100 }}/100</span>
                </span>
            </div>
        </div>
    </div>

    <form action="{{ route('voter.profile.update') }}" method="POST" class="space-y-5">
        @csrf
        @method('PUT')

        {{-- Read-only account info --}}
        <div class="bg-slate-800/50 border border-slate-700/60 rounded-2xl overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-700/60">
                <h2 class="text-base font-semibold text-white">Account Information</h2>
                <p class="text-slate-500 text-xs mt-0.5">Contact support to change your email address</p>
            </div>
            <div class="px-6 py-5">
                <label class="block text-xs text-slate-500 mb-1.5 uppercase tracking-wide">Email</label>
                <input type="text" disabled
                    value="{{ $user->email }}"
                    class="w-full bg-slate-900/50 border border-slate-700 rounded-xl px-4 py-2.5 text-slate-500 text-sm cursor-not-allowed"
                >
            </div>
        </div>

        {{-- Editable voter details --}}
        <div class="bg-slate-800/50 border border-slate-700/60 rounded-2xl overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-700/60">
                <h2 class="text-base font-semibold text-white">Voter Details</h2>
            </div>
            <div class="px-6 py-5 space-y-5">
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1.5" for="full_name">Full Name</label>
                    <input
                        id="full_name" name="full_name" type="text"
                        value="{{ old('full_name', $voter->full_name ?? '') }}"
                        required
                        class="w-full bg-slate-900 border border-slate-600 rounded-xl px-4 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 transition"
                    >
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1.5" for="phone">Phone Number</label>
                    <input
                        id="phone" name="phone" type="tel"
                        value="{{ old('phone', $voter->phone ?? '') }}"
                        placeholder="(555) 555-5555"
                        class="w-full bg-slate-900 border border-slate-600 rounded-xl px-4 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 transition"
                    >
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1.5" for="city">City</label>
                    <input
                        id="city" name="city" type="text"
                        value="{{ old('city', $voter->city ?? '') }}"
                        placeholder="Los Angeles"
                        class="w-full bg-slate-900 border border-slate-600 rounded-xl px-4 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 transition"
                    >
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1.5" for="state">State</label>
                        <input
                            id="state" name="state" type="text" maxlength="2"
                            value="{{ old('state', $voter->state ?? '') }}"
                            placeholder="CA"
                            class="w-full bg-slate-900 border border-slate-600 rounded-xl px-4 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 transition uppercase"
                        >
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1.5" for="zip_code">ZIP Code</label>
                        <input
                            id="zip_code" name="zip_code" type="text" maxlength="10"
                            required inputmode="numeric" pattern="\d{5}(-\d{4})?"
                            value="{{ old('zip_code', $voter->zip_code ?? '') }}"
                            placeholder="90210"
                            class="w-full bg-slate-900 border border-slate-600 rounded-xl px-4 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 transition"
                        >
                    </div>
                </div>
            </div>
        </div>

        {{-- Voter Registration Status --}}
        <div class="bg-slate-800/50 border border-slate-700/60 rounded-2xl px-6 py-5 space-y-3">
            <h2 class="text-base font-semibold text-white flex items-center gap-2">
                🗳️ Voter Registration Status
            </h2>
            <p class="text-slate-400 text-xs">Let us know if you are registered to vote. Registered voters may receive additional targeted campaigns in their area.</p>
            <div class="flex flex-col gap-2">
                <label class="flex items-center gap-3 cursor-pointer group">
                    <input type="radio" name="is_registered_voter" value="1"
                           {{ old('is_registered_voter', $voter->is_registered_voter === true ? '1' : ($voter->is_registered_voter === false ? '0' : '')) === '1' ? 'checked' : '' }}
                           onchange="document.getElementById('profile_register_link').classList.add('hidden')"
                           class="w-4 h-4 text-emerald-500 border-slate-600 bg-slate-700 focus:ring-emerald-500/50">
                    <span class="text-slate-300 text-sm group-hover:text-white transition">Yes, I am registered to vote ✅</span>
                </label>
                <label class="flex items-center gap-3 cursor-pointer group">
                    <input type="radio" name="is_registered_voter" value="0"
                           {{ old('is_registered_voter', $voter->is_registered_voter === true ? '1' : ($voter->is_registered_voter === false ? '0' : '')) === '0' ? 'checked' : '' }}
                           onchange="document.getElementById('profile_register_link').classList.remove('hidden')"
                           class="w-4 h-4 text-red-500 border-slate-600 bg-slate-700 focus:ring-red-500/50">
                    <span class="text-slate-300 text-sm group-hover:text-white transition">No, I am not registered</span>
                </label>
                <label class="flex items-center gap-3 cursor-pointer group">
                    <input type="radio" name="is_registered_voter" value=""
                           {{ old('is_registered_voter', $voter->is_registered_voter === true ? '1' : ($voter->is_registered_voter === false ? '0' : '')) === '' ? 'checked' : '' }}
                           onchange="document.getElementById('profile_register_link').classList.remove('hidden')"
                           class="w-4 h-4 text-slate-400 border-slate-600 bg-slate-700 focus:ring-slate-400/50">
                    <span class="text-slate-300 text-sm group-hover:text-white transition">I'm not sure</span>
                </label>
            </div>
            <div id="profile_register_link" class="{{ !is_null($voter->is_registered_voter) && $voter->is_registered_voter === false || is_null($voter->is_registered_voter) ? '' : 'hidden' }} mt-1">
                <a href="https://vote.gov" target="_blank" rel="noopener noreferrer"
                   class="inline-flex items-center gap-1.5 bg-blue-600 hover:bg-blue-500 text-white text-xs font-semibold px-3 py-1.5 rounded-lg transition">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                    Register to vote at vote.gov
                </a>
            </div>
        </div>
        @if($voter->referral_code)
        <div class="bg-slate-800/50 border border-slate-700/60 rounded-2xl px-6 py-5 flex items-center justify-between gap-4">
            <div>
                <p class="text-xs text-slate-500 uppercase tracking-wide mb-1">Referral Code</p>
                <p class="text-emerald-400 font-bold font-mono text-lg tracking-widest">{{ $voter->referral_code }}</p>
            </div>
            <a href="{{ route('voter.referrals') }}"
               class="text-slate-400 hover:text-white text-sm transition shrink-0">
                View Referrals →
            </a>
        </div>
        @endif

        <div class="flex justify-end">
            <button type="submit" class="bg-emerald-600 hover:bg-emerald-500 text-white px-8 py-2.5 rounded-xl font-semibold transition text-sm">
                Save Changes
            </button>
        </div>
    </form>

    @include('standalone.voter.partials.authentic-user-verifier-banner')

    {{-- Identity Verification --}}
    @php
        $hasAuthenticUserVerifier = $voter->hasAuthenticUserVerifier();
        $hasLegacyKycRejection = $user->kyc_status === 'rejected';
    @endphp
    <div class="bg-slate-800/50 border {{ $hasAuthenticUserVerifier ? 'border-emerald-500/30' : ($hasLegacyKycRejection ? 'border-red-500/30' : 'border-slate-700/60') }} rounded-2xl p-6 space-y-4">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h2 class="text-base font-semibold text-white flex items-center gap-2">
                    <svg class="w-4 h-4 text-blue-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"/>
                    </svg>
                    Authentic User Verifier
                </h2>
                @if($hasAuthenticUserVerifier)
                <p class="text-xs text-slate-500 mt-0.5">Your identity is currently verified through Authentic User Verifier (powered by Stripe Connect)</p>
                @else
                <p class="text-xs text-slate-500 mt-0.5">Your identity is verified when you complete Authentic User Verifier (powered by Stripe Connect)</p>
                @endif
            </div>
            @if($hasAuthenticUserVerifier)
            <span class="shrink-0 inline-flex items-center gap-1 text-xs bg-emerald-900/40 border border-emerald-700/40 text-emerald-400 rounded-full px-3 py-1 font-medium">
                ✓ Verified
            </span>
            @elseif($hasLegacyKycRejection)
            <span class="shrink-0 inline-flex items-center gap-1 text-xs bg-red-900/30 border border-red-700/30 text-red-400 rounded-full px-3 py-1 font-medium">
                ✗ Needs Attention
            </span>
            @else
            <span class="shrink-0 inline-flex items-center gap-1 text-xs bg-slate-700/50 border border-slate-600/40 text-slate-400 rounded-full px-3 py-1 font-medium">
                Not Verified
            </span>
            @endif
        </div>

        {{-- Authentic User Verifier notice --}}
        <div class="bg-blue-500/5 border border-blue-500/20 rounded-lg px-4 py-4 space-y-2">
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 text-blue-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <p class="text-xs font-semibold text-blue-300">Authentic User Verifier</p>
            </div>
            <p class="text-xs text-slate-400">No manual document upload is required here. Authentic User Verifier is completed through Stripe Connect when you set up payout onboarding.</p>
            @if(!$hasAuthenticUserVerifier)
            <p class="text-xs text-slate-500 mt-1">To get verified, start Authentic User Verifier from your Earnings page.</p>
            @endif
        </div>

        {{-- Legacy KYC document (read-only, shown if one exists from the previous system) --}}
        @if($user->kyc_document_path)
        <div class="border border-slate-700/40 rounded-lg px-4 py-3 space-y-2">
            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Legacy ID Document</p>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2 text-sm text-slate-400 min-w-0">
                    <svg class="w-4 h-4 text-slate-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <span class="truncate text-xs">{{ basename($user->kyc_document_path) }}</span>
                </div>
                <a href="{{ route('voter.kyc.view') }}" target="_blank"
                   class="shrink-0 text-xs text-slate-400 hover:text-white transition ml-3">
                    View →
                </a>
            </div>
            <p class="text-xs text-slate-600 italic">Previously submitted document — read-only. New submissions are processed through Authentic User Verifier.</p>
        </div>
        @endif

        {{-- Show legacy rejection reason if present --}}
        @if($user->kyc_status === 'rejected' && $user->kyc_rejection_reason)
        <div class="bg-red-900/20 border border-red-700/30 rounded-lg px-4 py-3">
            <p class="text-xs text-red-400 font-semibold mb-1">Previous review note:</p>
            <p class="text-sm text-red-300">{{ $user->kyc_rejection_reason }}</p>
            <p class="text-xs text-slate-500 mt-2">To resolve this, complete Authentic User Verifier from the Earnings page.</p>
        </div>
        @endif
    </div>

    {{-- Password Change Section --}}
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-2xl overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-700/60">
            <h2 class="text-base font-semibold text-white">Change Password</h2>
            <p class="text-slate-500 text-xs mt-0.5">Update your account password</p>
        </div>

        @if(session('password_success'))
        <div class="mx-6 mt-5 bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm rounded-lg px-4 py-3">
            {{ session('password_success') }}
        </div>
        @endif

        @if($errors->has('current_password') || $errors->has('new_password'))
        <div class="mx-6 mt-5 bg-red-500/10 border border-red-500/30 text-red-400 text-sm rounded-lg px-4 py-3">
            @if($errors->has('current_password'))
                {{ $errors->first('current_password') }}
            @endif
            @if($errors->has('new_password'))
                {{ $errors->first('new_password') }}
            @endif
        </div>
        @endif

        <form action="{{ route('voter.profile.password.update') }}" method="POST" class="px-6 py-5 space-y-4">
            @csrf
            @method('PUT')

            <div>
                <label class="block text-sm font-medium text-slate-300 mb-1.5" for="current_password">Current Password</label>
                <input
                    id="current_password" name="current_password" type="password"
                    required
                    class="w-full bg-slate-900 border border-slate-600 rounded-xl px-4 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 transition"
                >
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-300 mb-1.5" for="new_password">New Password</label>
                <input
                    id="new_password" name="new_password" type="password"
                    required minlength="8"
                    class="w-full bg-slate-900 border border-slate-600 rounded-xl px-4 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 transition"
                >
                <p class="text-xs text-slate-500 mt-1">Must be at least 8 characters</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-300 mb-1.5" for="new_password_confirmation">Confirm New Password</label>
                <input
                    id="new_password_confirmation" name="new_password_confirmation" type="password"
                    required minlength="8"
                    class="w-full bg-slate-900 border border-slate-600 rounded-xl px-4 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 transition"
                >
            </div>

            <div class="flex justify-end pt-2">
                <button type="submit" class="bg-emerald-600 hover:bg-emerald-500 text-white px-8 py-2.5 rounded-xl font-semibold transition text-sm">
                    Update Password
                </button>
            </div>
        </form>
    </div>

    @else
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-2xl p-10 text-center">
        <p class="text-slate-400">No voter profile found. Contact support.</p>
    </div>
    @endif

</div>
@endsection
