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
                        <option value="q_and_a" {{ old('campaign_type', $campaign->campaign_type?->value ?? $campaign->campaign_type) === 'q_and_a' ? 'selected' : '' }}>❓ Q&A</option>
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
                    <input type="url" name="media_url" id="videoUrlInput" value="{{ old('media_url', $campaign->media_url) }}"
                        class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition"
                        placeholder="https://youtube.com/watch?v=... or https://example.com/video.mp4" />
                    <p class="text-xs text-slate-500 mt-1">YouTube URL or direct link to MP4/WebM video</p>
                    @error('media_url')<p class="text-xs text-red-400 mt-1">{{ $message }}</p>@enderror
                </div>
                
                {{-- Video Preview Section --}}
                <div id="videoPreviewSection" class="{{ old('media_url', $campaign->media_url) ? '' : 'hidden' }}">
                    <div class="flex items-center justify-between mb-2">
                        <label class="block text-sm font-medium text-slate-300">Video Preview</label>
                        <button type="button" onclick="testVideoPreview()" 
                            class="text-xs text-emerald-400 hover:text-emerald-300 flex items-center gap-1 transition">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            Test Play
                        </button>
                    </div>
                    <div class="bg-black rounded-xl overflow-hidden border border-slate-700/50">
                        <div id="ytPreviewContainer" class="hidden w-full aspect-video"></div>
                        <video id="nativePreviewPlayer" class="hidden w-full aspect-video"
                            controls controlsList="nodownload" preload="metadata">
                            <source id="nativePreviewSource" src="" type="video/mp4">
                            Your browser does not support video preview.
                        </video>
                        <div id="previewPlaceholder" class="w-full aspect-video flex flex-col items-center justify-center gap-3 text-slate-500">
                            <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 10l4.553-2.069A1 1 0 0121 8.882v6.236a1 1 0 01-1.447.894L15 14M3 8a2 2 0 00-2 2v4a2 2 0 002 2h9a2 2 0 002-2v-4a2 2 0 00-2-2H3z"/>
                            </svg>
                            <p class="text-sm">Enter a valid video URL to preview</p>
                        </div>
                    </div>
                    <p class="text-xs text-slate-500 mt-2">
                        <svg class="w-3.5 h-3.5 inline-block -mt-0.5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        This is how voters will see your video. Test it to ensure proper playback.
                    </p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1.5">Video Duration (seconds)</label>
                    <input type="number" name="media_duration" value="{{ old('media_duration', $campaign->media_duration ?? config('u9itus.min_video_duration', 30)) }}"
                        min="{{ config('u9itus.min_video_duration', 30) }}"
                        max="{{ config('u9itus.max_video_duration', 300) }}"
                        class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition" />
                    <p class="text-xs text-slate-500 mt-1">System will auto-detect from video metadata if available ({{ config('u9itus.min_video_duration', 30) }}–{{ config('u9itus.max_video_duration', 300) }}s)</p>
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
                        <input type="number" name="total_budget" value="{{ old('total_budget', $campaign->total_budget) }}" min="{{ number_format((float) config('u9itus.revenue_per_view', 1.00) * 10, 2, '.', '') }}" step="0.01"
                            class="w-full bg-slate-900/60 border border-slate-700 rounded-lg pl-7 pr-4 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition" />
                    </div>
                </div>
            </div>
        </div>

        {{-- Targeting --}}
        <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-6 space-y-4">
            <h2 class="text-sm font-semibold text-slate-200 mb-4">Geographic Targeting <span class="text-slate-500 font-normal text-xs">(optional)</span></h2>

            <div>
                <label class="block text-sm font-medium text-slate-300 mb-2">Target States</label>
                <div class="relative">
                    <button type="button" id="statesDropdownBtn" 
                        class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-left text-sm text-white focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition flex items-center justify-between">
                        <span id="statesSelectedText" class="text-slate-400">Select states...</span>
                        <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <div id="statesDropdown" class="hidden absolute z-10 w-full mt-1 bg-slate-800 border border-slate-600 rounded-lg shadow-xl max-h-60 overflow-y-auto">
                        <div class="p-2 border-b border-slate-700 flex gap-2">
                            <button type="button" onclick="selectAllStates()" class="text-xs text-emerald-400 hover:text-emerald-300">Select All</button>
                            <button type="button" onclick="clearAllStates()" class="text-xs text-slate-400 hover:text-white">Clear All</button>
                        </div>
                        <div class="p-2 space-y-1">
                            @foreach($states as $abbr => $stateName)
                            <label class="flex items-center gap-2 px-2 py-1.5 hover:bg-slate-700/50 rounded cursor-pointer transition">
                                <input type="checkbox" name="target_states[]" value="{{ $abbr }}" 
                                    class="state-checkbox w-4 h-4 rounded border-slate-600 text-emerald-500 focus:ring-emerald-500 focus:ring-offset-slate-900"
                                    {{ is_array($campaign->target_states) && in_array($abbr, $campaign->target_states) ? 'checked' : '' }}>
                                <span class="text-sm text-slate-200">{{ $abbr }} - {{ $stateName }}</span>
                            </label>
                            @endforeach
                        </div>
                    </div>
                </div>
                <p class="text-xs text-slate-500 mt-1">Leave blank to target all states</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-300 mb-2">Target Cities</label>
                <div class="relative">
                    <button type="button" id="citiesDropdownBtn" 
                        class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-left text-sm text-white focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition flex items-center justify-between">
                        <span id="citiesSelectedText" class="text-slate-400">Select cities...</span>
                        <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <div id="citiesDropdown" class="hidden absolute z-10 w-full mt-1 bg-slate-800 border border-slate-600 rounded-lg shadow-xl max-h-60 overflow-y-auto">
                        <div class="p-2 border-b border-slate-700">
                            <input type="text" id="citySearch" placeholder="Search cities..." 
                                class="w-full bg-slate-900/60 border border-slate-700 rounded px-3 py-1.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                        </div>
                        <div class="p-2 border-b border-slate-700 flex gap-2">
                            <button type="button" onclick="selectAllCities()" class="text-xs text-emerald-400 hover:text-emerald-300">Select All</button>
                            <button type="button" onclick="clearAllCities()" class="text-xs text-slate-400 hover:text-white">Clear All</button>
                        </div>
                        <div class="p-2 space-y-1" id="citiesCheckboxList">
                            @php
                                $citiesByState = config('u9itus.cities_by_state', []);
                                $allCities = [];
                                foreach ($citiesByState as $stateAbbr => $cities) {
                                    foreach ($cities as $city) {
                                        $allCities[] = ['city' => $city, 'state' => $stateAbbr, 'display' => "$city, $stateAbbr"];
                                    }
                                }
                                // Sort alphabetically by display name
                                usort($allCities, fn($a, $b) => strcmp($a['display'], $b['display']));
                            @endphp
                            @foreach($allCities as $cityData)
                            <label class="city-option flex items-center gap-2 px-2 py-1.5 hover:bg-slate-700/50 rounded cursor-pointer transition" data-state="{{ $cityData['state'] }}">
                                <input type="checkbox" name="target_cities[]" value="{{ $cityData['display'] }}" 
                                    class="city-checkbox w-4 h-4 rounded border-slate-600 text-emerald-500 focus:ring-emerald-500 focus:ring-offset-slate-900"
                                    {{ is_array($campaign->target_cities) && in_array($cityData['display'], $campaign->target_cities) ? 'checked' : '' }}>
                                <span class="text-sm text-slate-200">{{ $cityData['display'] }}</span>
                            </label>
                            @endforeach
                        </div>
                    </div>
                </div>
                <p class="text-xs text-slate-500 mt-1">Select major cities or leave blank to target all areas</p>
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
    // Sync views ↔ budget on edit
    const revenuePerViewEdit = {{ (float) ($campaign->revenue_per_view ?? config('u9itus.revenue_per_view', 1.00)) }};
    const viewsEditInput  = document.querySelector('[name="total_views_requested"]');
    const budgetEditInput = document.querySelector('[name="total_budget"]');
    if (viewsEditInput && budgetEditInput) {
        viewsEditInput.addEventListener('input', () => {
            budgetEditInput.value = (parseFloat(viewsEditInput.value || 0) * revenuePerViewEdit).toFixed(2);
        });
        budgetEditInput.addEventListener('input', () => {
            viewsEditInput.value = Math.floor(parseFloat(budgetEditInput.value || 0) / revenuePerViewEdit);
        });
    }

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
    
    // ── Geographic Targeting Dropdowns ──────────────────────────────────
    // States dropdown
    const statesBtn = document.getElementById('statesDropdownBtn');
    const statesDropdown = document.getElementById('statesDropdown');
    const statesSelectedText = document.getElementById('statesSelectedText');
    const stateCheckboxes = document.querySelectorAll('.state-checkbox');

    statesBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        statesDropdown.classList.toggle('hidden');
        citiesDropdown.classList.add('hidden');
    });

    function updateStatesDisplay() {
        const checked = Array.from(stateCheckboxes).filter(cb => cb.checked);
        if (checked.length === 0) {
            statesSelectedText.textContent = 'Select states...';
            statesSelectedText.classList.add('text-slate-400');
        } else if (checked.length === stateCheckboxes.length) {
            statesSelectedText.textContent = 'All states selected';
            statesSelectedText.classList.remove('text-slate-400');
        } else {
            statesSelectedText.textContent = `${checked.length} state${checked.length > 1 ? 's' : ''} selected`;
            statesSelectedText.classList.remove('text-slate-400');
        }
    }

    stateCheckboxes.forEach(cb => cb.addEventListener('change', () => {
        updateStatesDisplay();
        filterCitiesByState();
    }));

    function selectAllStates() {
        stateCheckboxes.forEach(cb => cb.checked = true);
        updateStatesDisplay();
        filterCitiesByState();
    }

    function clearAllStates() {
        stateCheckboxes.forEach(cb => cb.checked = false);
        updateStatesDisplay();
        filterCitiesByState();
    }

    // Cities dropdown
    const citiesBtn = document.getElementById('citiesDropdownBtn');
    const citiesDropdown = document.getElementById('citiesDropdown');
    const citiesSelectedText = document.getElementById('citiesSelectedText');
    const cityCheckboxes = document.querySelectorAll('.city-checkbox');
    const citySearch = document.getElementById('citySearch');
    const cityOptions = document.querySelectorAll('.city-option');

    citiesBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        citiesDropdown.classList.toggle('hidden');
        statesDropdown.classList.add('hidden');
    });

    function filterCitiesByState() {
        const checkedStates = Array.from(stateCheckboxes)
            .filter(cb => cb.checked)
            .map(cb => cb.value);
        
        // If no states selected, show all cities
        if (checkedStates.length === 0) {
            cityOptions.forEach(option => {
                option.classList.remove('hidden');
            });
        } else {
            // Only show cities from selected states
            cityOptions.forEach(option => {
                const cityState = option.getAttribute('data-state');
                if (checkedStates.includes(cityState)) {
                    option.classList.remove('hidden');
                } else {
                    option.classList.add('hidden');
                }
            });
        }
        
        // Re-apply search filter if there's a search term
        const searchTerm = citySearch.value.toLowerCase();
        if (searchTerm) {
            cityOptions.forEach(option => {
                const text = option.textContent.toLowerCase();
                if (!option.classList.contains('hidden') && !text.includes(searchTerm)) {
                    option.classList.add('hidden');
                }
            });
        }
        
        updateCitiesDisplay();
    }

    function updateCitiesDisplay() {
        const checked = Array.from(cityCheckboxes).filter(cb => cb.checked);
        if (checked.length === 0) {
            citiesSelectedText.textContent = 'Select cities...';
            citiesSelectedText.classList.add('text-slate-400');
        } else if (checked.length === cityCheckboxes.length) {
            citiesSelectedText.textContent = 'All cities selected';
            citiesSelectedText.classList.remove('text-slate-400');
        } else {
            citiesSelectedText.textContent = `${checked.length} ${checked.length > 1 ? 'cities' : 'city'} selected`;
            citiesSelectedText.classList.remove('text-slate-400');
        }
    }

    cityCheckboxes.forEach(cb => cb.addEventListener('change', updateCitiesDisplay));

    function selectAllCities() {
        cityCheckboxes.forEach(cb => {
            if (!cb.closest('.city-option').classList.contains('hidden')) {
                cb.checked = true;
            }
        });
        updateCitiesDisplay();
    }

    function clearAllCities() {
        cityCheckboxes.forEach(cb => cb.checked = false);
        updateCitiesDisplay();
    }

    // City search functionality
    citySearch.addEventListener('input', (e) => {
        const searchTerm = e.target.value.toLowerCase();
        const checkedStates = Array.from(stateCheckboxes)
            .filter(cb => cb.checked)
            .map(cb => cb.value);
        
        cityOptions.forEach(option => {
            const text = option.textContent.toLowerCase();
            const cityState = option.getAttribute('data-state');
            
            // Show if: matches search AND (no states selected OR matches selected state)
            const matchesSearch = text.includes(searchTerm);
            const matchesState = checkedStates.length === 0 || checkedStates.includes(cityState);
            
            option.classList.toggle('hidden', !(matchesSearch && matchesState));
        });
    });

    // Close dropdowns when clicking outside
    document.addEventListener('click', () => {
        statesDropdown.classList.add('hidden');
        citiesDropdown.classList.add('hidden');
    });

    // Prevent dropdown close when clicking inside
    statesDropdown.addEventListener('click', (e) => e.stopPropagation());
    citiesDropdown.addEventListener('click', (e) => e.stopPropagation());

    // Initialize displays
    updateStatesDisplay();
    updateCitiesDisplay();
    
    // ── Video Preview Functionality ──────────────────────────────────────
    let ytPreviewPlayer = null;
    const videoUrlInput = document.getElementById('videoUrlInput');
    const previewSection = document.getElementById('videoPreviewSection');
    
    if (videoUrlInput && previewSection) {
        const ytContainer = document.getElementById('ytPreviewContainer');
        const nativePlayer = document.getElementById('nativePreviewPlayer');
        const nativeSource = document.getElementById('nativePreviewSource');
        const placeholder = document.getElementById('previewPlaceholder');

        // Extract YouTube video ID from various URL formats
        function extractYouTubeId(url) {
            if (!url) return null;
            const patterns = [
                /(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([a-zA-Z0-9_-]{11})/,
                /^([a-zA-Z0-9_-]{11})$/  // Direct ID
            ];
            for (const pattern of patterns) {
                const match = url.match(pattern);
                if (match) return match[1];
            }
            return null;
        }

        // Initialize YouTube player for preview
        function initYouTubePreview(videoId) {
            if (ytPreviewPlayer) {
                ytPreviewPlayer.destroy();
                ytPreviewPlayer = null;
            }
            nativePlayer.classList.add('hidden');
            ytContainer.classList.remove('hidden');
            placeholder.classList.add('hidden');

            if (!window.YT) {
                const tag = document.createElement('script');
                tag.src = 'https://www.youtube.com/iframe_api';
                const firstScriptTag = document.getElementsByTagName('script')[0];
                firstScriptTag.parentNode.insertBefore(tag, firstScriptTag);
                
                window.onYouTubeIframeAPIReady = () => createYTPlayer(videoId);
            } else {
                createYTPlayer(videoId);
            }
        }

        function createYTPlayer(videoId) {
            ytPreviewPlayer = new YT.Player('ytPreviewContainer', {
                height: '100%',
                width: '100%',
                videoId: videoId,
                playerVars: {
                    autoplay: 0,
                    controls: 1,
                    modestbranding: 1,
                    rel: 0
                }
            });
        }

        function initNativePreview(url) {
            if (ytPreviewPlayer) {
                ytPreviewPlayer.destroy();
                ytPreviewPlayer = null;
            }
            ytContainer.classList.add('hidden');
            nativePlayer.classList.remove('hidden');
            placeholder.classList.add('hidden');
            
            nativeSource.src = url;
            nativePlayer.load();
        }

        videoUrlInput.addEventListener('input', () => {
            const url = videoUrlInput.value.trim();
            
            if (!url) {
                previewSection.classList.add('hidden');
                return;
            }
            
            previewSection.classList.remove('hidden');
            const youtubeId = extractYouTubeId(url);
            
            if (youtubeId) {
                initYouTubePreview(youtubeId);
            } else if (url.startsWith('http')) {
                initNativePreview(url);
            } else {
                if (ytPreviewPlayer) ytPreviewPlayer.destroy();
                ytContainer.classList.add('hidden');
                nativePlayer.classList.add('hidden');
                placeholder.classList.remove('hidden');
            }
        });

        // Test preview button
        window.testVideoPreview = function() {
            if (ytPreviewPlayer && ytPreviewPlayer.playVideo) {
                ytPreviewPlayer.playVideo();
            } else if (!nativePlayer.classList.contains('hidden')) {
                nativePlayer.play();
            }
        };

        // Trigger preview on page load if URL present
        window.addEventListener('DOMContentLoaded', () => {
            if (videoUrlInput.value) {
                videoUrlInput.dispatchEvent(new Event('input'));
            }
        });
    }
</script>
@endpush
