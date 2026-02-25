@extends('standalone.layouts.dashboard')

@section('title', 'New Campaign')
@section('page-title', 'Create Campaign')

@section('content')
<div class="max-w-2xl">

    <div class="mb-6">
        <a href="{{ route('politician.campaigns.index') }}" class="text-sm text-slate-400 hover:text-white transition">← Back to campaigns</a>
    </div>

    <form method="POST" action="{{ route('politician.campaigns.store') }}" class="space-y-6" id="campaignForm">
        @csrf

        {{-- Basic Info --}}
        <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-6 space-y-4">
            <h2 class="text-sm font-semibold text-slate-200 mb-4">Campaign Details</h2>

            <div>
                <label class="block text-sm font-medium text-slate-300 mb-1.5">Campaign Title <span class="text-red-400">*</span></label>
                <input type="text" name="title" value="{{ old('title') }}" required maxlength="255"
                    class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition"
                    placeholder="e.g. Vote Yes on Proposition 1" />
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-300 mb-1.5">Message Summary</label>
                <textarea name="message_summary" rows="3" maxlength="2000"
                    class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition resize-none"
                    placeholder="A short description of your campaign message...">{{ old('message_summary') }}</textarea>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1.5">Campaign Type <span class="text-red-400">*</span></label>
                    <select name="campaign_type" required id="campaignType"
                        class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition">
                        <option value="video" {{ old('campaign_type', 'video') === 'video' ? 'selected' : '' }}>🎬 Video</option>
                        <option value="live_feed" {{ old('campaign_type') === 'live_feed' ? 'selected' : '' }}>📡 Live Feed</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1.5">Governance Level</label>
                    <select name="governance_level"
                        class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition">
                        <option value="">— Any —</option>
                        @foreach($governanceLevels as $value => $label)
                            <option value="{{ $value }}" {{ old('governance_level') === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- Video fields (shown when campaign_type = video, which is the default) --}}
            <div id="videoFields" class="{{ old('campaign_type', 'video') === 'live_feed' ? 'hidden' : '' }} space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1.5">Video URL <span class="text-red-400">*</span></label>
                    <input type="url" name="media_url" value="{{ old('media_url') }}"
                        class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition"
                        placeholder="https://example.com/your-video.mp4" />
                    <p class="text-xs text-slate-500 mt-1">Direct link to an MP4, WebM, or hosted video URL</p>
                    @error('media_url')<p class="text-xs text-red-400 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1.5">Video Duration (seconds) <span class="text-red-400">*</span></label>
                    <input type="number" name="media_duration" value="{{ old('media_duration') }}"
                        min="{{ config('u9itus.min_video_duration', 30) }}"
                        max="{{ config('u9itus.max_video_duration', 300) }}"
                        class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition"
                        placeholder="e.g. 60" />
                    <p class="text-xs text-slate-500 mt-1">Between {{ config('u9itus.min_video_duration', 30) }}–{{ config('u9itus.max_video_duration', 300) }} seconds</p>
                    @error('media_duration')<p class="text-xs text-red-400 mt-1">{{ $message }}</p>@enderror
                </div>
            </div>

            {{-- Live feed fields --}}
            <div id="liveFeedFields" class="{{ old('campaign_type') === 'live_feed' ? '' : 'hidden' }} space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1.5">Live Stream URL <span class="text-red-400">*</span></label>
                    <input type="url" name="live_feed_url" value="{{ old('live_feed_url') }}"
                        class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition"
                        placeholder="https://stream.example.com/live/..." />
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1.5">Scheduled Start Time <span class="text-red-400">*</span></label>
                    <input type="datetime-local" name="live_scheduled_at" value="{{ old('live_scheduled_at') }}"
                        class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition" />
                </div>
            </div>
        </div>

        {{-- Budget --}}
        <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-6 space-y-4">
            <h2 class="text-sm font-semibold text-slate-200 mb-1">Budget & Views</h2>
            <p class="text-xs text-slate-500 mb-4">Rate: <span class="text-emerald-400 font-medium">${{ number_format($revenuePerView, 2) }}</span> per completed view</p>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1.5">Views Requested <span class="text-red-400">*</span></label>
                    <input type="number" name="total_views_requested" id="viewsInput" value="{{ old('total_views_requested', 100) }}" min="10" step="10" required
                        class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition" />
                    <p class="text-xs text-slate-500 mt-1">Minimum 10 views</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1.5">Total Budget (USD) <span class="text-red-400">*</span></label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">$</span>
                        <input type="number" name="total_budget" id="budgetInput" value="{{ old('total_budget', number_format(100 * $revenuePerView, 2)) }}"
                            min="6" step="0.01" required
                            class="w-full bg-slate-900/60 border border-slate-700 rounded-lg pl-7 pr-4 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition" />
                    </div>
                </div>
            </div>
        </div>

        {{-- Targeting --}}
        <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-6 space-y-4">
            <h2 class="text-sm font-semibold text-slate-200 mb-4">Geographic Targeting <span class="text-slate-500 font-normal text-xs">(optional)</span></h2>

            <div>
                <label class="block text-sm font-medium text-slate-300 mb-1.5">Target States</label>
                <input type="text" name="target_states_raw" id="targetStates"
                    value="{{ old('target_states') ? implode(', ', old('target_states')) : '' }}"
                    class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition"
                    placeholder="e.g. CA, TX, FL (comma-separated 2-letter codes)" />
                <p class="text-xs text-slate-500 mt-1">Leave blank to target all states</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-300 mb-1.5">Target Cities <span class="text-slate-500 font-normal text-xs">(optional)</span></label>
                <input type="text" name="target_cities_raw"
                    value="{{ old('target_cities') ? implode(', ', old('target_cities')) : '' }}"
                    class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition"
                    placeholder="e.g. Los Angeles, Dallas (comma-separated)" />
            </div>
        </div>

        {{-- Campaign Window --}}
        <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-6 space-y-4">
            <div>
                <h2 class="text-sm font-semibold text-slate-200 mb-1">Campaign Window <span class="text-slate-500 font-normal text-xs">(optional)</span></h2>
                <p class="text-xs text-slate-500 mb-4">Leave blank to start immediately when approved. Dates are inclusive — the campaign activates at <em>Start</em> and auto-pauses at <em>End</em>.</p>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1.5">Start Date &amp; Time</label>
                    <input type="datetime-local" name="scheduled_start_at"
                        value="{{ old('scheduled_start_at') }}"
                        class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition" />
                    <p class="text-xs text-slate-500 mt-1">Campaign activates at this time</p>
                    @error('scheduled_start_at')<p class="text-xs text-red-400 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1.5">End Date &amp; Time</label>
                    <input type="datetime-local" name="scheduled_end_at"
                        value="{{ old('scheduled_end_at') }}"
                        class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition" />
                    <p class="text-xs text-slate-500 mt-1">Campaign auto-pauses at this time</p>
                    @error('scheduled_end_at')<p class="text-xs text-red-400 mt-1">{{ $message }}</p>@enderror
                </div>
            </div>
        </div>

        {{-- Repeat Viewing --}}
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-sm font-semibold text-slate-200">Repeat Viewing</h2>
                    <p class="text-xs text-slate-500 mt-0.5">Allow the same voter to watch this ad more than once and earn each time</p>
                </div>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="hidden" name="allow_repeat_views" value="0">
                    <input type="checkbox" name="allow_repeat_views" id="allowRepeatViews" value="1"
                        {{ old('allow_repeat_views') ? 'checked' : '' }}
                        class="sr-only peer">
                    <div class="w-11 h-6 bg-slate-700 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-emerald-500/50 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-500"></div>
                </label>
            </div>

            <div id="repeatViewingOptions" class="hidden space-y-4 pt-2 border-t border-slate-700/40">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1.5">Cooldown Period (hours)</label>
                        <input type="number" name="repeat_view_cooldown_hours" value="{{ old('repeat_view_cooldown_hours', 24) }}"
                            min="1" max="720" step="1"
                            class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition" />
                        <p class="text-xs text-slate-500 mt-1">Min. hours between re-watches (1–720)</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1.5">Max Views Per Voter</label>
                        <input type="number" name="max_views_per_voter" value="{{ old('max_views_per_voter', 2) }}"
                            min="2" max="10" step="1"
                            class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition" />
                        <p class="text-xs text-slate-500 mt-1">Lifetime cap per voter (2–10)</p>
                    </div>
                </div>
                <p class="text-xs text-amber-400/80 bg-amber-500/10 rounded-lg px-3 py-2">
                    Each repeat view costs <strong>${{ number_format($revenuePerView, 2) }}</strong> and pays the voter <strong>$0.25</strong> — ensure your budget covers the additional views.
                </p>
            </div>
        </div>

        {{-- Submit --}}
        <div class="flex gap-3">
            <button type="submit"
                class="flex-1 bg-emerald-500 hover:bg-emerald-400 text-slate-900 font-semibold rounded-lg px-6 py-2.5 text-sm transition-colors focus:outline-none focus:ring-2 focus:ring-emerald-500/50">
                Create Campaign (Draft)
            </button>
            <a href="{{ route('politician.campaigns.index') }}"
               class="px-6 py-2.5 text-sm font-medium text-slate-400 hover:text-white bg-slate-700/50 hover:bg-slate-700 rounded-lg transition">
                Cancel
            </a>
        </div>
    </form>

</div>
@endsection

@push('scripts')
<script>
const revenuePerView = {{ $revenuePerView }};
const viewsInput  = document.getElementById('viewsInput');
const budgetInput = document.getElementById('budgetInput');
const campaignType = document.getElementById('campaignType');
const liveFeedFields = document.getElementById('liveFeedFields');

// Sync views ↔ budget
viewsInput.addEventListener('input', () => {
    budgetInput.value = (parseFloat(viewsInput.value || 0) * revenuePerView).toFixed(2);
});
budgetInput.addEventListener('input', () => {
    viewsInput.value = Math.floor(parseFloat(budgetInput.value || 0) / revenuePerView);
});

// Show/hide video vs live feed fields
const videoFields = document.getElementById('videoFields');
campaignType.addEventListener('change', () => {
    const isLive = campaignType.value === 'live_feed';
    liveFeedFields.classList.toggle('hidden', !isLive);
    videoFields.classList.toggle('hidden', isLive);
});

// Repeat Viewing toggle
const allowRepeatCheckbox = document.getElementById('allowRepeatViews');
const repeatOptions = document.getElementById('repeatViewingOptions');
function syncRepeatPanel() {
    repeatOptions.classList.toggle('hidden', !allowRepeatCheckbox.checked);
}
allowRepeatCheckbox.addEventListener('change', syncRepeatPanel);
syncRepeatPanel();

// Convert comma-separated text to array inputs before submit
document.getElementById('campaignForm').addEventListener('submit', function(e) {
    const statesRaw = document.getElementById('targetStates').value;
    const states = statesRaw.split(',').map(s => s.trim().toUpperCase()).filter(Boolean);
    states.forEach(s => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'target_states[]';
        input.value = s;
        this.appendChild(input);
    });
});
</script>
@endpush
