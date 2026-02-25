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
        <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-emerald-600 to-teal-700 flex items-center justify-center text-white text-2xl font-bold shrink-0 shadow-lg">
            {{ strtoupper(substr($user->name ?? 'V', 0, 1)) }}
        </div>
        <div class="min-w-0">
            <p class="text-white text-lg font-semibold truncate">{{ $user->name }}</p>
            <p class="text-slate-400 text-sm truncate">{{ $user->email }}</p>
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

    {{-- KYC (Know Your Customer) Identity Document Upload --}}
    <div class="bg-slate-800/50 border {{ $user->kyc_status === 'approved' ? 'border-emerald-500/30' : ($user->kyc_status === 'rejected' ? 'border-red-500/30' : 'border-yellow-500/30') }} rounded-2xl p-6 space-y-4">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h2 class="text-base font-semibold text-white flex items-center gap-2">
                    <svg class="w-4 h-4 text-yellow-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"/>
                    </svg>
                    KYC Identity Verification
                </h2>
                <p class="text-xs text-slate-500 mt-0.5">Know Your Customer — upload a government-issued ID to unlock payouts</p>
            </div>
            @if($user->kyc_status === 'approved')
            <span class="shrink-0 inline-flex items-center gap-1 text-xs bg-emerald-900/40 border border-emerald-700/40 text-emerald-400 rounded-full px-3 py-1 font-medium">
                ✓ Approved
            </span>
            @elseif($user->kyc_status === 'rejected')
            <span class="shrink-0 inline-flex items-center gap-1 text-xs bg-red-900/30 border border-red-700/30 text-red-400 rounded-full px-3 py-1 font-medium">
                ✗ Rejected
            </span>
            @else
            <span class="shrink-0 inline-flex items-center gap-1 text-xs bg-yellow-900/20 border border-yellow-700/30 text-yellow-400 rounded-full px-3 py-1 font-medium">
                ⏳ Pending Review
            </span>
            @endif
        </div>

        @if(session('kyc_success'))
        <div class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm rounded-lg px-4 py-3">
            {{ session('kyc_success') }}
        </div>
        @endif

        @if($errors->has('kyc_document'))
        <div class="bg-red-500/10 border border-red-500/30 text-red-400 text-sm rounded-lg px-4 py-3">
            {{ $errors->first('kyc_document') }}
        </div>
        @endif

        {{-- Show rejection reason --}}
        @if($user->kyc_status === 'rejected' && $user->kyc_rejection_reason)
        <div class="bg-red-900/20 border border-red-700/30 rounded-lg px-4 py-3">
            <p class="text-xs text-red-400 font-semibold mb-1">Rejection reason:</p>
            <p class="text-sm text-red-300">{{ $user->kyc_rejection_reason }}</p>
            <p class="text-xs text-slate-500 mt-2">Please re-upload a clearer document to try again.</p>
        </div>
        @endif

        {{-- Current document --}}
        @if($user->kyc_document_path)
        <div class="flex items-center justify-between bg-slate-900/50 border border-slate-700/50 rounded-lg px-4 py-3">
            <div class="flex items-center gap-2 text-sm text-slate-300 min-w-0">
                <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <span class="truncate">{{ basename($user->kyc_document_path) }}</span>
            </div>
            <a href="{{ Storage::disk('public')->url($user->kyc_document_path) }}" target="_blank"
               class="shrink-0 text-xs text-emerald-400 hover:text-emerald-300 transition ml-3">
                View →
            </a>
        </div>
        @endif

        {{-- Upload form --}}
        @if($user->kyc_status !== 'approved')
        <form action="{{ route('voter.kyc.upload') }}" method="POST" enctype="multipart/form-data" class="space-y-3">
            @csrf
            <div>
                <label class="block text-xs text-slate-400 font-medium mb-1.5">
                    {{ $user->kyc_document_path ? 'Replace document' : 'Upload government-issued ID' }}
                </label>
                <input type="file" name="kyc_document" accept=".jpg,.jpeg,.png,.pdf"
                       class="block w-full text-sm text-slate-400
                              file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0
                              file:text-xs file:font-semibold file:bg-slate-700 file:text-slate-200
                              hover:file:bg-slate-600 file:transition file:cursor-pointer cursor-pointer">
                <p class="text-xs text-slate-600 mt-1.5">Accepted: JPG, PNG, PDF — max 5 MB. Passport, driver's licence, or national ID.</p>
            </div>
            <button type="submit"
                    class="px-5 py-2 rounded-lg bg-yellow-500 hover:bg-yellow-400 text-slate-900 text-xs font-bold transition">
                Upload Document
            </button>
        </form>
        @endif
    </div>

    @else
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-2xl p-10 text-center">
        <p class="text-slate-400">No voter profile found. Contact support.</p>
    </div>
    @endif

</div>
@endsection
