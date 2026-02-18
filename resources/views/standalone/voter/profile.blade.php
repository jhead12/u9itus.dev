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

        {{-- Referral Code (read-only) --}}
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

    @else
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-2xl p-10 text-center">
        <p class="text-slate-400">No voter profile found. Contact support.</p>
    </div>
    @endif

</div>
@endsection
