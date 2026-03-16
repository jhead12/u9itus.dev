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

        {{-- Videos & Appearances --}}
        <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-6"
             x-data="{
                videos: {{ json_encode(old('video_links', $politician?->video_links ?? [])) }},
                addVideo() {
                    this.videos.push({ url: '', title: '' });
                },
                removeVideo(i) {
                    this.videos.splice(i, 1);
                }
             }">
            <h2 class="text-sm font-semibold text-slate-200 mb-1">Videos &amp; Appearances</h2>
            <p class="text-xs text-slate-500 mb-4">Add YouTube or C-SPAN video links to feature on your public profile. Voters will see these when they visit your page.</p>

            <div class="space-y-3">
                <template x-for="(video, i) in videos" :key="i">
                    <div class="flex gap-2 items-start">
                        <div class="flex-1 grid grid-cols-1 sm:grid-cols-2 gap-2">
                            <input type="url"
                                :name="`video_links[${i}][url]`"
                                x-model="video.url"
                                placeholder="https://www.youtube.com/watch?v=... or https://www.c-span.org/video/..."
                                class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition" />
                            <input type="text"
                                :name="`video_links[${i}][title]`"
                                x-model="video.title"
                                placeholder="Label (optional)"
                                class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition" />
                        </div>
                        <button type="button" @click="removeVideo(i)"
                            class="mt-0.5 text-slate-500 hover:text-red-400 transition text-lg leading-none">✕</button>
                    </div>
                </template>
            </div>

            <button type="button" @click="addVideo()"
                class="mt-3 inline-flex items-center gap-1.5 text-xs font-medium text-emerald-400 hover:text-emerald-300 transition">
                + Add Video Link
            </button>
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
                <dd class="{{ $politician?->kyc_status === 'approved' ? 'text-emerald-400' : ($politician?->kyc_status === 'rejected' ? 'text-red-400' : 'text-yellow-400') }}">
                    {{ ucfirst($politician?->kyc_status ?? 'pending') }}
                </dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-slate-500">Member since</dt>
                <dd class="text-slate-400">{{ auth()->user()->created_at?->format('F Y') }}</dd>
            </div>
        </dl>
    </div>

    {{-- KYC (Know Your Customer) Identity Document Upload --}}
    <div class="bg-slate-800/50 border {{ auth()->user()->kyc_status === 'approved' ? 'border-emerald-500/30' : (auth()->user()->kyc_status === 'rejected' ? 'border-red-500/30' : 'border-yellow-500/30') }} rounded-xl p-6 space-y-4">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h2 class="text-sm font-semibold text-white flex items-center gap-2">
                    <svg class="w-4 h-4 text-yellow-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"/>
                    </svg>
                    KYC Identity Verification
                </h2>
                <p class="text-xs text-slate-500 mt-0.5">Know Your Customer — submit a government-issued ID so your campaigns can be approved</p>
            </div>
            @if(auth()->user()->kyc_status === 'approved')
            <span class="shrink-0 text-xs bg-emerald-900/40 border border-emerald-700/40 text-emerald-400 rounded-full px-3 py-1 font-medium">✓ Approved</span>
            @elseif(auth()->user()->kyc_status === 'rejected')
            <span class="shrink-0 text-xs bg-red-900/30 border border-red-700/30 text-red-400 rounded-full px-3 py-1 font-medium">✗ Rejected</span>
            @else
            <span class="shrink-0 text-xs bg-yellow-900/20 border border-yellow-700/30 text-yellow-400 rounded-full px-3 py-1 font-medium">⏳ Pending</span>
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

        {{-- Rejection reason --}}
        @if(auth()->user()->kyc_status === 'rejected' && auth()->user()->kyc_rejection_reason)
        <div class="bg-red-900/20 border border-red-700/30 rounded-lg px-4 py-3">
            <p class="text-xs text-red-400 font-semibold mb-1">Rejection reason:</p>
            <p class="text-sm text-red-300">{{ auth()->user()->kyc_rejection_reason }}</p>
            <p class="text-xs text-slate-500 mt-2">Please re-upload a clearer document to try again.</p>
        </div>
        @endif

        {{-- Current document --}}
        @if(auth()->user()->kyc_document_path)
        <div class="flex items-center justify-between bg-slate-900/50 border border-slate-700/50 rounded-lg px-4 py-3">
            <div class="flex items-center gap-2 text-sm text-slate-300 min-w-0">
                <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <span class="truncate">{{ basename(auth()->user()->kyc_document_path) }}</span>
            </div>
            <a href="{{ route('politician.kyc.view') }}" target="_blank"
               class="shrink-0 text-xs text-emerald-400 hover:text-emerald-300 transition ml-3">
                View →
            </a>
        </div>
        @endif

        {{-- Upload form --}}
        @if(auth()->user()->kyc_status !== 'approved')
        <form action="{{ route('politician.kyc.upload') }}" method="POST" enctype="multipart/form-data" class="space-y-3">
            @csrf
            <div>
                <label class="block text-xs text-slate-400 font-medium mb-1.5">
                    {{ auth()->user()->kyc_document_path ? 'Replace document' : 'Upload government-issued ID' }}
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

</div>
@endsection
