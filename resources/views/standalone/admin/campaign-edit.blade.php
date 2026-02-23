@extends('standalone.layouts.dashboard')

@section('title', 'Edit Campaign')
@section('page-title', 'Edit Campaign')

@section('content')
<div class="max-w-2xl">

    <div class="mb-6 flex items-center gap-4">
        <a href="{{ route('admin.campaigns.pending') }}" class="text-sm text-slate-400 hover:text-white transition">← Back to pending campaigns</a>
        <a href="{{ route('admin.campaigns.audit', $campaign) }}" class="ml-auto text-xs text-slate-400 hover:text-white underline underline-offset-2">View full audit log →</a>
    </div>

    {{-- Campaign owner info + status ribbon --}}
    <div class="mb-6 bg-slate-800/50 border border-slate-700/50 rounded-xl px-5 py-4 flex items-center gap-3">
        <div class="w-8 h-8 rounded-full bg-emerald-500/20 flex items-center justify-center shrink-0">
            <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
            </svg>
        </div>
        <div class="flex-1">
            <p class="text-sm font-semibold text-white">{{ $campaign->politician?->full_name ?? '—' }}</p>
            <p class="text-xs text-slate-400">{{ $campaign->politician?->user?->email }}</p>
        </div>
        <div class="flex items-center gap-2 text-xs">
            <span class="px-2 py-1 rounded-full bg-slate-700 text-slate-300">
                {{ ucwords(str_replace('_', ' ', $campaign->status?->value ?? $campaign->status)) }}
            </span>
            <span class="px-2 py-1 rounded-full bg-slate-700 text-slate-300">
                {{ ucfirst($campaign->approval_status?->value ?? $campaign->approval_status) }}
            </span>
        </div>
    </div>

    {{-- Stop / Reactivate quick actions --}}
    @php
        $currentStatus = $campaign->status?->value ?? $campaign->status;
    @endphp
    <div class="mb-6 flex gap-3">
        @if(in_array($currentStatus, ['active', 'pending_approval']))
        <button type="button" onclick="document.getElementById('stop-modal').classList.remove('hidden')"
            class="flex items-center gap-1.5 px-4 py-2 rounded-lg bg-red-500/10 hover:bg-red-500/20 text-red-400 text-xs font-semibold border border-red-500/20 transition">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 10h6v4H9z"/>
            </svg>
            Stop Campaign
        </button>
        @endif
        @if($currentStatus === 'paused')
        <button type="button" onclick="document.getElementById('reactivate-modal').classList.remove('hidden')"
            class="flex items-center gap-1.5 px-4 py-2 rounded-lg bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-400 text-xs font-semibold border border-emerald-500/20 transition">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            Reactivate Campaign
        </button>
        @endif
    </div>

    {{-- Stop modal --}}
    <div id="stop-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/60">
        <div class="bg-slate-800 border border-slate-700 rounded-xl p-6 w-full max-w-md mx-4 space-y-4">
            <h3 class="text-sm font-semibold text-white">Stop Campaign</h3>
            <p class="text-xs text-slate-400">Stopping this campaign will pause it immediately. You must provide a reason (visible in the audit log).</p>
            <form method="POST" action="{{ route('admin.campaigns.stop', $campaign) }}" class="space-y-3">
                @csrf
                <textarea name="reason" rows="3" required placeholder="Reason for stopping (e.g. video not playing, incorrect targeting)…"
                    class="w-full bg-slate-900/70 border border-slate-600 rounded-lg px-3 py-2 text-sm text-slate-200 placeholder-slate-500 focus:outline-none focus:border-red-500/50 resize-none"></textarea>
                <div class="flex gap-2 justify-end">
                    <button type="button" onclick="document.getElementById('stop-modal').classList.add('hidden')"
                        class="px-4 py-1.5 text-xs text-slate-400 hover:text-white bg-slate-700 rounded-lg transition">Cancel</button>
                    <button type="submit"
                        class="px-4 py-1.5 text-xs font-semibold text-white bg-red-500 hover:bg-red-400 rounded-lg transition">Confirm Stop</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Reactivate modal --}}
    <div id="reactivate-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/60">
        <div class="bg-slate-800 border border-slate-700 rounded-xl p-6 w-full max-w-md mx-4 space-y-4">
            <h3 class="text-sm font-semibold text-white">Reactivate Campaign</h3>
            <form method="POST" action="{{ route('admin.campaigns.reactivate', $campaign) }}" class="space-y-3">
                @csrf
                <textarea name="reason" rows="2" placeholder="Optional note for the audit log…"
                    class="w-full bg-slate-900/70 border border-slate-600 rounded-lg px-3 py-2 text-sm text-slate-200 placeholder-slate-500 focus:outline-none focus:border-emerald-500/50 resize-none"></textarea>
                <div class="flex gap-2 justify-end">
                    <button type="button" onclick="document.getElementById('reactivate-modal').classList.add('hidden')"
                        class="px-4 py-1.5 text-xs text-slate-400 hover:text-white bg-slate-700 rounded-lg transition">Cancel</button>
                    <button type="submit"
                        class="px-4 py-1.5 text-xs font-semibold text-slate-900 bg-emerald-500 hover:bg-emerald-400 rounded-lg transition">Confirm Reactivate</button>
                </div>
            </form>
        </div>
    </div>

    @if(session('success'))
    <div class="mb-4 bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm rounded-lg px-4 py-3">
        {{ session('success') }}
    </div>
    @endif

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

@php $editType = old('campaign_type', $campaign->campaign_type?->value ?? $campaign->campaign_type); @endphp

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
                </div>
            </div>

            {{-- Live feed fields --}}
            <div id="liveFeedFields" class="{{ $editType === 'live_feed' ? '' : 'hidden' }} space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1.5">Live Stream URL</label>
                    <input type="url" name="live_feed_url" value="{{ old('live_feed_url', $campaign->live_feed_url) }}"
                        class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition" />
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
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1.5">Total Budget (USD)</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">$</span>
                        <input type="number" name="total_budget" value="{{ old('total_budget', $campaign->total_budget) }}" min="0" step="0.01"
                            class="w-full bg-slate-900/60 border border-slate-700 rounded-lg pl-7 pr-4 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition" />
                    </div>
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
                <p class="text-xs text-slate-500 mt-1">Comma-separated 2-letter state codes.</p>
            </div>
        </div>

        {{-- Admin-only: Status Controls --}}
        <div class="bg-amber-500/5 border border-amber-500/20 rounded-xl p-6 space-y-4">
            <h2 class="text-sm font-semibold text-amber-400 mb-1">Admin Controls</h2>
            <p class="text-xs text-slate-500 mb-4">Status overrides and approval management.</p>

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
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-300 mb-1.5">Rejection Reason</label>
                <input type="text" name="rejection_reason" maxlength="500"
                    value="{{ old('rejection_reason', $campaign->rejection_reason) }}"
                    class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500 transition"
                    placeholder="Reason for rejection..." />
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-300 mb-1.5">Reason for this edit <span class="text-slate-500 font-normal">(recorded in audit log)</span></label>
                <input type="text" name="edit_reason" maxlength="500"
                    value="{{ old('edit_reason') }}"
                    class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500 transition"
                    placeholder="e.g. Corrected video URL, fixed targeting region..." />
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

    {{-- ── Audit Log ──────────────────────────────────────────────────────── --}}
    @if($auditLogs->isNotEmpty())
    <div class="mt-10">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-sm font-semibold text-slate-200">Change History</h2>
            <a href="{{ route('admin.campaigns.audit', $campaign) }}" class="text-xs text-slate-400 hover:text-white underline underline-offset-2">Full log →</a>
        </div>
        <div class="space-y-3">
            @foreach($auditLogs->take(10) as $log)
            @php $color = $log->actionColor(); @endphp
            <div class="bg-slate-800/40 border border-slate-700/40 rounded-xl px-4 py-3">
                <div class="flex items-center gap-3 flex-wrap">
                    <span class="text-xs font-semibold px-2 py-0.5 rounded-full
                        @if($color === 'emerald') bg-emerald-500/15 text-emerald-400
                        @elseif($color === 'red')  bg-red-500/15 text-red-400
                        @elseif($color === 'amber') bg-amber-500/15 text-amber-400
                        @else bg-slate-700 text-slate-300 @endif">
                        {{ $log->actionLabel() }}
                    </span>
                    <span class="text-xs text-slate-400">by <span class="text-slate-200">{{ $log->admin?->name ?? 'Admin' }}</span></span>
                    <span class="text-xs text-slate-500 ml-auto">{{ $log->created_at->diffForHumans() }}</span>
                </div>
                @if($log->reason)
                <p class="text-xs text-slate-400 mt-2">{{ $log->reason }}</p>
                @endif
                @if($log->changes)
                <div class="mt-2 space-y-1">
                    @foreach($log->changes as $field => $change)
                    <p class="text-xs text-slate-500">
                        <span class="text-slate-300">{{ str_replace('_', ' ', $field) }}</span>:
                        <span class="line-through text-red-400/70">{{ is_array($change['old']) ? implode(', ', $change['old']) : $change['old'] }}</span>
                        →
                        <span class="text-emerald-400/80">{{ is_array($change['new']) ? implode(', ', $change['new']) : $change['new'] }}</span>
                    </p>
                    @endforeach
                </div>
                @endif
            </div>
            @endforeach
        </div>
    </div>
    @endif

</div>
@endsection

@push('scripts')
<script>
    document.getElementById('campaignType').addEventListener('change', function() {
        const isLive = this.value === 'live_feed';
        document.getElementById('liveFeedFields').classList.toggle('hidden', !isLive);
        document.getElementById('videoFields').classList.toggle('hidden', isLive);
    });

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
