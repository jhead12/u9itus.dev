@extends('standalone.layouts.dashboard')

@section('title', 'Edit Campaign')
@section('page-title', 'Edit Campaign')

@section('content')
<div class="max-w-2xl">

    <div class="mb-6">
        <a href="{{ route('politician.campaigns.show', $campaign) }}" class="text-sm text-slate-400 hover:text-white transition">← Back to campaign</a>
    </div>

    <form method="POST" action="{{ route('politician.campaigns.update', $campaign) }}" class="space-y-6" id="editCampaignForm">
        @csrf @method('PUT')

        {{-- Basic Info --}}
        <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-6 space-y-4">
            <h2 class="text-sm font-semibold text-slate-200 mb-4">Campaign Details</h2>

            <div>
                <label class="block text-sm font-medium text-slate-300 mb-1.5">Campaign Title <span class="text-red-400">*</span></label>
                <input type="text" name="title" value="{{ old('title', $campaign->title) }}" required maxlength="255"
                    class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition" />
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

@php
    $editType = old('campaign_type', $campaign->campaign_type?->value ?? $campaign->campaign_type);
@endphp

            {{-- Video fields --}}
            <div id="videoFields" class="{{ $editType === 'live_feed' ? 'hidden' : '' }} space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1.5">Video URL <span class="text-red-400">*</span></label>
                    <input type="url" name="media_url" value="{{ old('media_url', $campaign->media_url) }}"
                        class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition"
                        placeholder="https://example.com/your-video.mp4" />
                    @error('media_url')<p class="text-xs text-red-400 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1.5">Video Duration (seconds) <span class="text-red-400">*</span></label>
                    <input type="number" name="media_duration" value="{{ old('media_duration', $campaign->media_duration) }}"
                        min="{{ config('u9itus.min_video_duration', 30) }}"
                        max="{{ config('u9itus.max_video_duration', 300) }}"
                        class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition"
                        placeholder="e.g. 60" />
                    <p class="text-xs text-slate-500 mt-1">Between {{ config('u9itus.min_video_duration', 30) }}–{{ config('u9itus.max_video_duration', 300) }} seconds</p>
                    @error('media_duration')<p class="text-xs text-red-400 mt-1">{{ $message }}</p>@enderror
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
                    <input type="number" name="total_views_requested" value="{{ old('total_views_requested', $campaign->total_views_requested) }}" min="10" step="10"
                        class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1.5">Total Budget (USD)</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">$</span>
                        <input type="number" name="total_budget" value="{{ old('total_budget', $campaign->total_budget) }}" min="6" step="0.01"
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
                    value="{{ $campaign->target_states ? implode(', ', $campaign->target_states) : '' }}"
                    class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition"
                    placeholder="CA, TX, FL..." />
            </div>
        </div>

        {{-- Campaign Window --}}
        <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-6 space-y-4">
            <div>
                <h2 class="text-sm font-semibold text-slate-200 mb-1">Campaign Window <span class="text-slate-500 font-normal text-xs">(optional)</span></h2>
                <p class="text-xs text-slate-500 mb-4">Leave blank to run indefinitely. Changing these dates on an already-approved campaign takes effect on the next scheduler run (≤5 min).</p>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1.5">Start Date &amp; Time</label>
                    <input type="datetime-local" name="scheduled_start_at"
                        value="{{ old('scheduled_start_at', $campaign->scheduled_start_at?->format('Y-m-d\TH:i')) }}"
                        class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition" />
                    <p class="text-xs text-slate-500 mt-1">Campaign activates at this time</p>
                    @error('scheduled_start_at')<p class="text-xs text-red-400 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1.5">End Date &amp; Time</label>
                    <input type="datetime-local" name="scheduled_end_at"
                        value="{{ old('scheduled_end_at', $campaign->scheduled_end_at?->format('Y-m-d\TH:i')) }}"
                        class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition" />
                    <p class="text-xs text-slate-500 mt-1">Campaign auto-pauses at this time</p>
                    @error('scheduled_end_at')<p class="text-xs text-red-400 mt-1">{{ $message }}</p>@enderror
                </div>
            </div>
        </div>

        {{-- Repeat Viewing --}}
        <div class="bg-gradient-to-br from-purple-500/10 to-blue-500/10 border-2 border-purple-500/30 rounded-xl p-6 space-y-4 relative overflow-hidden">
            {{-- Eye-catching badge --}}
            <div class="absolute top-3 right-3">
                <span class="inline-flex items-center gap-1 bg-purple-500/20 border border-purple-400/40 text-purple-300 text-xs font-semibold px-2.5 py-1 rounded-full">
                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M10 12a2 2 0 100-4 2 2 0 000 4z"/>
                        <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"/>
                    </svg>
                    MAXIMIZE REACH
                </span>
            </div>

            <div class="flex items-start justify-between pr-32">
                <div class="flex items-start gap-3">
                    {{-- Icon --}}
                    <div class="flex-shrink-0 w-10 h-10 bg-purple-500/20 rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-base font-bold text-white">Repeat Viewing</h2>
                        <p class="text-sm text-slate-300 mt-1 leading-relaxed">Allow the same voter to watch this ad <strong class="text-purple-300">multiple times</strong> and earn each time.</p>
                        <p class="text-xs text-purple-200/70 mt-2 bg-purple-500/10 rounded-lg px-3 py-1.5 inline-block">
                            💡 <strong>Important:</strong> This significantly increases your ad's visibility and reinforces your message with repeat exposures.
                        </p>
                    </div>
                </div>
                <label class="relative inline-flex items-center cursor-pointer flex-shrink-0">
                    <input type="hidden" name="allow_repeat_views" value="0">
                    <input type="checkbox" name="allow_repeat_views" id="allowRepeatViewsEdit" value="1"
                        {{ old('allow_repeat_views', $campaign->allow_repeat_views) ? 'checked' : '' }}
                        class="sr-only peer">
                    <div class="w-14 h-7 bg-slate-700 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-purple-500/50 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-6 after:w-6 after:transition-all peer-checked:bg-purple-500 shadow-lg"></div>
                </label>
            </div>

            <div id="repeatViewingOptionsEdit" class="{{ old('allow_repeat_views', $campaign->allow_repeat_views) ? '' : 'hidden' }} space-y-4 pt-2 border-t border-slate-700/40">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1.5">Cooldown Period (hours)</label>
                        <input type="number" name="repeat_view_cooldown_hours"
                            value="{{ old('repeat_view_cooldown_hours', $campaign->repeat_view_cooldown_hours ?? 24) }}"
                            min="1" max="720" step="1"
                            class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition" />
                        <p class="text-xs text-slate-500 mt-1">Min. hours between re-watches (1–720)</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1.5">Max Views Per Voter</label>
                        <input type="number" name="max_views_per_voter"
                            value="{{ old('max_views_per_voter', $campaign->max_views_per_voter ?? 2) }}"
                            min="2" max="10" step="1"
                            class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition" />
                        <p class="text-xs text-slate-500 mt-1">Lifetime cap per voter (2–10)</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex gap-3">
            <button type="submit"
                class="flex-1 bg-emerald-500 hover:bg-emerald-400 text-slate-900 font-semibold rounded-lg px-6 py-2.5 text-sm transition-colors">
                Save Changes
            </button>
            <a href="{{ route('politician.campaigns.show', $campaign) }}"
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
    // Repeat Viewing toggle
    const allowRepeatEdit = document.getElementById('allowRepeatViewsEdit');
    const repeatOptionsEdit = document.getElementById('repeatViewingOptionsEdit');
    allowRepeatEdit.addEventListener('change', () => {
        repeatOptionsEdit.classList.toggle('hidden', !allowRepeatEdit.checked);
    });
    // Convert comma-separated states to target_states[] before submit
    document.getElementById('editCampaignForm').addEventListener('submit', function () {
        const raw = this.querySelector('[name="target_states_raw"]')?.value || '';
        raw.split(',').map(s => s.trim().toUpperCase()).filter(Boolean).forEach(s => {
            const input = document.createElement('input');
            input.type = 'hidden'; input.name = 'target_states[]'; input.value = s;
            this.appendChild(input);
        });
    });
</script>
@endpush
