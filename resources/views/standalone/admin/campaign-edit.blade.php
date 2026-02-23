@extends('standalone.layouts.dashboard')

@section('title', 'Edit Campaign')
@section('page-title', 'Edit Campaign')

@section('content')
<div class="max-w-2xl">

    <div class="mb-6">
        <a href="{{ route('admin.campaigns.pending') }}" class="text-sm text-slate-400 hover:text-white transition">← Back to pending campaigns</a>
    </div>

    {{-- Campaign owner info --}}
    <div class="mb-6 bg-slate-800/50 border border-slate-700/50 rounded-xl px-5 py-4 flex items-center gap-3">
        <div class="w-8 h-8 rounded-full bg-emerald-500/20 flex items-center justify-center shrink-0">
            <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
            </svg>
        </div>
        <div>
            <p class="text-sm font-semibold text-white">{{ $campaign->politician?->full_name ?? '—' }}</p>
            <p class="text-xs text-slate-400">{{ $campaign->politician?->user?->email }}</p>
        </div>
    </div>

    @if($errors->any())
    <div class="mb-4 bg-red-500/10 border border-red-500/30 rounded-lg px-4 py-3 space-y-1">
        @foreach($errors->all() as $error)
            <p class="text-xs text-red-400">{{ $error }}</p>
        @endforeach
    </div>
    @endif

    <form method="POST" action="{{ route('admin.campaigns.update', $campaign) }}" class="space-y-6" id="adminEditCampaignForm">
        @csrf @method('PUT')

        {{-- Basic Info --}}
        <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-6 space-y-4">
            <h2 class="text-sm font-semibold text-slate-200 mb-4">Campaign Details</h2>

            <div>
                <label class="block text-sm font-medium text-slate-300 mb-1.5">Campaign Title <span class="text-red-400">*</span></label>
                <input type="text" name="title" value="{{ old('title', $campaign->title) }}" required maxlength="255"
                    class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition" />
                @error('title')<p class="text-xs text-red-400 mt-1">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-300 mb-1.5">Message Summary</label>
                <textarea name="message_summary" rows="3" maxlength="2000"
                    class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition resize-none">{{ old('message_summary', $campaign->message_summary) }}</textarea>
                @error('message_summary')<p class="text-xs text-red-400 mt-1">{{ $message }}</p>@enderror
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1.5">Campaign Type</label>
                    <select name="campaign_type" id="campaignType"
                        class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition">
                        <option value="video" {{ old('campaign_type', $campaign->campaign_type?->value ?? $campaign->campaign_type) === 'video' ? 'selected' : '' }}>🎬 Video</option>
                        <option value="live_feed" {{ old('campaign_type', $campaign->campaign_type?->value ?? $campaign->campaign_type) === 'live_feed' ? 'selected' : '' }}>📡 Live Feed</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1.5">Governance Level</label>
                    <select name="governance_level"
                        class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition">
                        <option value="">— Any —</option>
                        @foreach($governanceLevels as $value => $label)
                            <option value="{{ $value }}" {{ old('governance_level', $campaign->governance_level) === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

@php
    $editType = old('campaign_type', $campaign->campaign_type?->value ?? $campaign->campaign_type);
@endphp

            {{-- Video fields --}}
            <div id="videoFields" class="{{ $editType === 'live_feed' ? 'hidden' : '' }} space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1.5">Video URL</label>
                    <input type="url" name="media_url" value="{{ old('media_url', $campaign->media_url) }}"
                        class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition"
                        placeholder="https://example.com/your-video.mp4" />
                    @error('media_url')<p class="text-xs text-red-400 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1.5">Video Duration (seconds)</label>
                    <input type="number" name="media_duration" value="{{ old('media_duration', $campaign->media_duration) }}" min="1"
                        class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition"
                        placeholder="e.g. 60" />
                    @error('media_duration')<p class="text-xs text-red-400 mt-1">{{ $message }}</p>@enderror
                </div>
            </div>

            {{-- Live feed fields --}}
            <div id="liveFeedFields" class="{{ $editType === 'live_feed' ? '' : 'hidden' }} space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1.5">Live Stream URL</label>
                    <input type="url" name="live_feed_url" value="{{ old('live_feed_url', $campaign->live_feed_url) }}"
                        class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition" />
                    @error('live_feed_url')<p class="text-xs text-red-400 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1.5">Scheduled Start</label>
                    <input type="datetime-local" name="live_scheduled_at"
                        value="{{ old('live_scheduled_at', $campaign->live_scheduled_at?->format('Y-m-d\TH:i')) }}"
                        class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition" />
                </div>
            </div>
        </div>

        {{-- Budget --}}
        <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-6 space-y-4">
            <h2 class="text-sm font-semibold text-slate-200 mb-4">Budget & Views</h2>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1.5">Views Requested</label>
                    <input type="number" name="total_views_requested" value="{{ old('total_views_requested', $campaign->total_views_requested) }}" min="0" step="10"
                        class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition" />
                    @error('total_views_requested')<p class="text-xs text-red-400 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1.5">Total Budget (USD)</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">$</span>
                        <input type="number" name="total_budget" value="{{ old('total_budget', $campaign->total_budget) }}" min="0" step="0.01"
                            class="w-full bg-slate-900/60 border border-slate-700 rounded-lg pl-7 pr-4 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition" />
                    </div>
                    @error('total_budget')<p class="text-xs text-red-400 mt-1">{{ $message }}</p>@enderror
                </div>
            </div>
        </div>

        {{-- Targeting --}}
        <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-6 space-y-4">
            <h2 class="text-sm font-semibold text-slate-200 mb-4">Geographic Targeting</h2>
            <div>
                <label class="block text-sm font-medium text-slate-300 mb-1.5">Target States</label>
                <input type="text" name="target_states_raw"
                    value="{{ old('target_states_raw', $campaign->target_states ? implode(', ', $campaign->target_states) : '') }}"
                    class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition"
                    placeholder="CA, TX, FL..." />
                <p class="text-xs text-slate-500 mt-1">Comma-separated 2-letter state codes. Leave blank to target all states.</p>
            </div>
        </div>

        {{-- Admin-only: Status Controls --}}
        <div class="bg-amber-500/5 border border-amber-500/20 rounded-xl p-6 space-y-4">
            <h2 class="text-sm font-semibold text-amber-400 mb-1">Admin Controls</h2>
            <p class="text-xs text-slate-500 mb-4">These fields are only visible and editable by administrators.</p>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1.5">Campaign Status</label>
                    <select name="status"
                        class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500 transition">
                        @foreach(['draft', 'pending_approval', 'active', 'paused', 'completed', 'cancelled'] as $s)
                            <option value="{{ $s }}" {{ old('status', $campaign->status?->value ?? $campaign->status) === $s ? 'selected' : '' }}>
                                {{ ucwords(str_replace('_', ' ', $s)) }}
                            </option>
                        @endforeach
                    </select>
                    @error('status')<p class="text-xs text-red-400 mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1.5">Approval Status</label>
                    <select name="approval_status"
                        class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500 transition">
                        @foreach(['pending', 'approved', 'rejected'] as $as)
                            <option value="{{ $as }}" {{ old('approval_status', $campaign->approval_status?->value ?? $campaign->approval_status) === $as ? 'selected' : '' }}>
                                {{ ucfirst($as) }}
                            </option>
                        @endforeach
                    </select>
                    @error('approval_status')<p class="text-xs text-red-400 mt-1">{{ $message }}</p>@enderror
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-300 mb-1.5">Rejection Reason <span class="text-slate-500 font-normal">(only shown when rejected)</span></label>
                <input type="text" name="rejection_reason" maxlength="500"
                    value="{{ old('rejection_reason', $campaign->rejection_reason) }}"
                    class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500 transition"
                    placeholder="Reason for rejection..." />
                @error('rejection_reason')<p class="text-xs text-red-400 mt-1">{{ $message }}</p>@enderror
            </div>
        </div>

        <div class="flex gap-3">
            <button type="submit"
                class="flex-1 bg-emerald-500 hover:bg-emerald-400 text-slate-900 font-semibold rounded-lg px-6 py-2.5 text-sm transition-colors">
                Save Changes
            </button>
            <a href="{{ route('admin.campaigns.pending') }}"
               class="px-6 py-2.5 text-sm font-medium text-slate-400 hover:text-white bg-slate-700/50 hover:bg-slate-700 rounded-lg transition">
                Cancel
            </a>
        </div>
    </form>

</div>
@endsection

@push('scripts')
<script>
    document.getElementById('campaignType').addEventListener('change', function() {
        const isLive = this.value === 'live_feed';
        document.getElementById('liveFeedFields').classList.toggle('hidden', !isLive);
        document.getElementById('videoFields').classList.toggle('hidden', isLive);
    });

    // Convert comma-separated states to target_states[] before submit
    document.getElementById('adminEditCampaignForm').addEventListener('submit', function () {
        const raw = this.querySelector('[name="target_states_raw"]')?.value || '';
        raw.split(',').map(s => s.trim().toUpperCase()).filter(Boolean).forEach(s => {
            const input = document.createElement('input');
            input.type = 'hidden'; input.name = 'target_states[]'; input.value = s;
            this.appendChild(input);
        });
    });
</script>
@endpush
