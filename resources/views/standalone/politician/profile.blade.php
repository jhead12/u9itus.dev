@extends('standalone.layouts.dashboard')

@section('title', 'Profile & Settings')
@section('page-title', 'Profile & Settings')

@section('content')
<div class="max-w-2xl space-y-6">

    {{-- Politician Profile Form --}}
    <form method="POST" action="{{ route('politician.profile.update') }}" class="space-y-6">
        @csrf @method('PUT')

        {{-- Identity --}}
        <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-6 space-y-4">
            <h2 class="text-sm font-semibold text-slate-200 mb-4">Personal Information</h2>

            <div>
                <label class="block text-sm font-medium text-slate-300 mb-1.5">Full Name <span class="text-red-400">*</span></label>
                <input type="text" name="full_name" value="{{ old('full_name', $politician?->full_name) }}" required
                    class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition" />
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1.5">Political Office</label>
                    <input type="text" name="political_office" value="{{ old('political_office', $politician?->political_office) }}"
                        class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition"
                        placeholder="e.g. City Council Member" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1.5">Party Affiliation</label>
                    <input type="text" name="party_affiliation" value="{{ old('party_affiliation', $politician?->party_affiliation) }}"
                        class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition"
                        placeholder="e.g. Independent" />
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-300 mb-1.5">Bio</label>
                <textarea name="bio" rows="4" maxlength="2000"
                    class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition resize-none"
                    placeholder="Tell voters about yourself...">{{ old('bio', $politician?->bio) }}</textarea>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-300 mb-1.5">Website URL</label>
                <input type="url" name="website_url" value="{{ old('website_url', $politician?->website_url) }}"
                    class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition"
                    placeholder="https://yourwebsite.com" />
            </div>
        </div>

        {{-- Governance Info --}}
        <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-6 space-y-4">
            <h2 class="text-sm font-semibold text-slate-200 mb-4">Governance Details</h2>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1.5">Governance Level</label>
                    <select name="governance_level"
                        class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition">
                        <option value="">— Select —</option>
                        @foreach($governanceLevels as $value => $label)
                            <option value="{{ $value }}" {{ old('governance_level', $politician?->governance_level) === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1.5">District</label>
                    <input type="text" name="district" value="{{ old('district', $politician?->district) }}"
                        class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition"
                        placeholder="e.g. District 7" />
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1.5">State</label>
                    <select name="state"
                        class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition">
                        <option value="">— Select State —</option>
                        @foreach($states as $abbr => $name)
                            <option value="{{ $abbr }}" {{ old('state', $politician?->state) === $abbr ? 'selected' : '' }}>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1.5">City</label>
                    <input type="text" name="city" value="{{ old('city', $politician?->city) }}"
                        class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition" />
                </div>
            </div>
        </div>

        <button type="submit"
            class="w-full sm:w-auto bg-emerald-500 hover:bg-emerald-400 text-slate-900 font-semibold rounded-lg px-8 py-2.5 text-sm transition">
            Save Profile
        </button>
    </form>

    {{-- Account Info (read-only) --}}
    <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-6">
        <h2 class="text-sm font-semibold text-slate-200 mb-4">Account</h2>
        <dl class="space-y-3 text-sm">
            <div class="flex justify-between">
                <dt class="text-slate-500">Email</dt>
                <dd class="text-slate-200">{{ auth()->user()->email }}</dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-slate-500">Verified</dt>
                <dd class="{{ auth()->user()->hasVerifiedEmail() ? 'text-emerald-400' : 'text-yellow-400' }}">
                    {{ auth()->user()->hasVerifiedEmail() ? 'Yes' : 'Pending verification' }}
                </dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-slate-500">KYC Status</dt>
                <dd class="{{ $politician?->kyc_status === 'approved' ? 'text-emerald-400' : 'text-yellow-400' }}">
                    {{ ucfirst($politician?->kyc_status ?? 'pending') }}
                </dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-slate-500">Member since</dt>
                <dd class="text-slate-400">{{ auth()->user()->created_at?->format('F Y') }}</dd>
            </div>
        </dl>
    </div>

</div>
@endsection
