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
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 class="text-sm font-semibold text-slate-200 mb-1">Budget & Views</h2>
                    <p class="text-xs text-slate-500">Rate: <span class="text-emerald-400 font-medium">${{ number_format($revenuePerView, 2) }}</span> per completed view</p>
                </div>
                <div class="text-right shrink-0">
                    <p class="text-xs text-slate-500 uppercase tracking-wide font-medium">Your Credit Balance</p>
                    <p class="text-lg font-bold {{ $creditBalance > 0 ? 'text-emerald-400' : 'text-red-400' }} mt-0.5">
                        ${{ number_format($creditBalance, 2) }}
                    </p>
                    @if($creditBalance <= 0)
                        <a href="{{ route('politician.billing') }}" class="text-xs text-blue-400 hover:text-blue-300">Add credits →</a>
                    @endif
                </div>
            </div>

            {{-- Live balance warning (shown by JS when budget > balance) --}}
            <div id="balanceWarning" class="hidden rounded-lg px-4 py-3 bg-amber-500/10 border border-amber-500/30 text-sm text-amber-300 flex items-start gap-2">
                <svg class="w-4 h-4 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                <span>Your campaign budget exceeds your credit balance. You can save this as a draft, but you'll need to <a href="{{ route('politician.billing') }}" class="underline hover:text-amber-200">add credits</a> before submitting for review.</span>
            </div>

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
                    <input type="checkbox" name="allow_repeat_views" id="allowRepeatViews" value="1"
                        {{ old('allow_repeat_views') ? 'checked' : '' }}
                        class="sr-only peer">
                    <div class="w-14 h-7 bg-slate-700 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-purple-500/50 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-6 after:w-6 after:transition-all peer-checked:bg-purple-500 shadow-lg"></div>
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
const revenuePerView  = {{ $revenuePerView }};
const creditBalance   = {{ $creditBalance }};
const viewsInput      = document.getElementById('viewsInput');
const budgetInput     = document.getElementById('budgetInput');
const campaignType    = document.getElementById('campaignType');
const liveFeedFields  = document.getElementById('liveFeedFields');
const balanceWarning  = document.getElementById('balanceWarning');

function syncBalanceWarning() {
    const budget = parseFloat(budgetInput.value || 0);
    balanceWarning.classList.toggle('hidden', budget <= creditBalance);
}

// Sync views ↔ budget
viewsInput.addEventListener('input', () => {
    budgetInput.value = (parseFloat(viewsInput.value || 0) * revenuePerView).toFixed(2);
    syncBalanceWarning();
});
budgetInput.addEventListener('input', () => {
    viewsInput.value = Math.floor(parseFloat(budgetInput.value || 0) / revenuePerView);
    syncBalanceWarning();
});

// Run on load in case old() repopulates the fields
syncBalanceWarning();

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
