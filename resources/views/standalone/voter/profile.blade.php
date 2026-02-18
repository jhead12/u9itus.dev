@extends('layouts.app')

@section('title', 'My Profile')

@section('content')
<div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">

    <h1 class="text-3xl font-bold text-white">My Profile</h1>

    @if(session('success'))
    <div class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm rounded-lg px-4 py-3">
        {{ session('success') }}
    </div>
    @endif

    @if($errors->any())
    <div class="bg-red-500/10 border border-red-500/30 text-red-400 text-sm rounded-lg px-4 py-3">
        <ul class="list-disc list-inside space-y-1">
            @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    @if($voter)
    <form action="{{ route('voter.profile.update') }}" method="POST">
        @csrf
        @method('PUT')

        {{-- Read-only account info --}}
        <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-6 mb-6">
            <h2 class="text-lg font-semibold text-white mb-4">Account Information</h2>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1">Email</label>
                    <input type="text" disabled
                        value="{{ $user->email }}"
                        class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-slate-400 text-sm cursor-not-allowed"
                    >
                    <p class="text-slate-500 text-xs mt-1">Email cannot be changed here. Contact support.</p>
                </div>
            </div>
        </div>

        {{-- Editable voter details --}}
        <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-6">
            <h2 class="text-lg font-semibold text-white mb-4">Voter Details</h2>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1" for="full_name">Full Name</label>
                    <input
                        id="full_name" name="full_name" type="text"
                        value="{{ old('full_name', $voter->full_name ?? '') }}"
                        class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
                        required
                    >
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1" for="phone">Phone</label>
                    <input
                        id="phone" name="phone" type="tel"
                        value="{{ old('phone', $voter->phone ?? '') }}"
                        placeholder="(555) 555-5555"
                        class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
                    >
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1" for="state">State</label>
                        <input
                            id="state" name="state" type="text" maxlength="2"
                            value="{{ old('state', $voter->state ?? '') }}"
                            placeholder="CA"
                            class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
                        >
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1" for="zip_code">ZIP Code</label>
                        <input
                            id="zip_code" name="zip_code" type="text" maxlength="10"
                            value="{{ old('zip_code', $voter->zip_code ?? '') }}"
                            placeholder="90210"
                            class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
                        >
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1" for="city">City</label>
                    <input
                        id="city" name="city" type="text"
                        value="{{ old('city', $voter->city ?? '') }}"
                        class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
                    >
                </div>
            </div>
        </div>

        <div class="mt-6 flex justify-end">
            <button type="submit" class="bg-emerald-600 hover:bg-emerald-500 text-white px-6 py-2.5 rounded-lg font-medium transition">
                Update Profile
            </button>
        </div>
    </form>

    @else
    <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-10 text-center">
        <p class="text-slate-400">No voter profile found.</p>
    </div>
    @endif

</div>
@endsection
