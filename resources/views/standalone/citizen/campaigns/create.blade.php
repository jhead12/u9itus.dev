@extends('standalone.layouts.dashboard')

@section('title', 'New Campaign')
@section('page-title', 'Create Campaign')

@php
    $allowMovUploads = preg_match('/\b(iPhone|iPad|iPod)\b/i', request()->userAgent() ?? '') === 1;
    $videoAcceptTypes = $allowMovUploads ? 'video/mp4,video/webm,video/quicktime' : 'video/mp4,video/webm';
@endphp

@section('content')
<div class="max-w-2xl" x-data="{
    adType: '{{ old('citizen_ad_type', 'local_business') }}',
    campaignType: '{{ old('campaign_type', 'video') }}',
    citizenRate: {{ $citizenRate }},
    ballotIssueRate: {{ $ballotIssueRate }},
    repeatViews: {{ old('allow_repeat_views') ? 'true' : 'false' }},
    get currentRate() { return this.adType === 'ballot_issue' ? this.ballotIssueRate : this.citizenRate; }
}">

    <div class="mb-6">
        <a href="{{ route('citizen.campaigns.index') }}" class="text-sm text-slate-400 hover:text-white transition">← Back to campaigns</a>
    </div>

    @if($errors->any())
    <div class="mb-6 rounded-xl border border-red-500/30 bg-red-500/10 p-4">
        <p class="text-sm font-medium text-red-300">Please fix the following before submitting:</p>
        <ul class="mt-2 list-disc pl-5 text-xs text-red-200 space-y-1">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <form method="POST" action="{{ route('citizen.campaigns.store') }}" enctype="multipart/form-data" class="space-y-6">
        @csrf

        {{-- Campaign Details --}}
        <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-6 space-y-4">
            <h2 class="text-sm font-semibold text-slate-200">Campaign Details</h2>

            <div>
                <label class="block text-sm font-medium text-slate-300 mb-1.5">Title <span class="text-red-400">*</span></label>
                <input type="text" name="title" value="{{ old('title') }}" required maxlength="255"
                    class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500 transition"
                    placeholder="e.g. Grand Opening — Maple Street Bakery" />
                @error('title')<p class="text-xs text-red-400 mt-1">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-300 mb-1.5">Message Summary</label>
                <textarea name="message_summary" rows="3" maxlength="2000"
                    class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500 transition resize-none"
                    placeholder="A brief description of your message...">{{ old('message_summary') }}</textarea>
            </div>

            {{-- Call to Action (optional) --}}
            <div>
                <label class="block text-sm font-medium text-slate-300 mb-1.5">Call to Action Link <span class="text-slate-500 font-normal">(optional)</span></label>
                <p class="text-xs text-slate-500 mb-2">Show a button on the watch page linking voters to your site, store, petition, or event.</p>
                <input type="url" name="call_to_action_url" value="{{ old('call_to_action_url') }}" maxlength="2048"
                    class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500 transition"
                    placeholder="https://your-site.com/offer">
                <div class="mt-2">
                    <label for="cta-label" class="block text-xs text-slate-400 mb-1">Button label</label>
                    <input id="cta-label" type="text" name="call_to_action_label" value="{{ old('call_to_action_label') }}" maxlength="60"
                        class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500 transition"
                        placeholder="Learn More">
                </div>
            </div>

            {{-- Ad Type --}}
            <div>
                <label class="block text-sm font-medium text-slate-300 mb-2">Ad Type <span class="text-red-400">*</span></label>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    @foreach($adTypes as $type)
                    @php
                        $labels = [
                            'local_business'       => ['Local Business', 'Promote your business or store'],
                            'community_notice'     => ['Community Notice', 'Events, announcements, public info'],
                            'general_announcement' => ['General Announcement', 'Other local messages'],
                            'ballot_issue'         => ['Ballot Issue', 'PAC required · $1.00/view · admin review'],
                        ];
                        [$label, $desc] = $labels[$type->value] ?? [$type->value, ''];
                    @endphp
                    <label class="relative flex items-start gap-3 cursor-pointer rounded-lg border p-3 transition"
                           :class="adType === '{{ $type->value }}' ? 'border-amber-500 bg-amber-500/10' : 'border-slate-700 bg-slate-900/30 hover:border-slate-600'">
                        <input type="radio" name="citizen_ad_type" value="{{ $type->value }}"
                               x-model="adType"
                               class="mt-0.5 accent-amber-500"
                               {{ old('citizen_ad_type', 'local_business') === $type->value ? 'checked' : '' }} />
                        <div>
                            <div class="text-sm font-medium text-white">{{ $label }}</div>
                            <div class="text-xs text-slate-400 mt-0.5">{{ $desc }}</div>
                        </div>
                    </label>
                    @endforeach
                </div>
                @error('citizen_ad_type')<p class="text-xs text-red-400 mt-1">{{ $message }}</p>@enderror
            </div>

            {{-- PAC Registration (ballot_issue only) --}}
            <div x-show="adType === 'ballot_issue'" x-cloak>
                <label class="block text-sm font-medium text-slate-300 mb-1.5">PAC Registration ID <span class="text-red-400">*</span></label>
                <input type="text" name="pac_registration_id" value="{{ old('pac_registration_id') }}"
                    maxlength="50"
                    class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500 transition"
                    placeholder="e.g. C00123456 or state PAC ID" />
                <p class="text-xs text-slate-500 mt-1">Required for ballot-issue campaigns. Your PAC ID will be visible to admin reviewers.</p>
                @error('pac_registration_id')<p class="text-xs text-red-400 mt-1">{{ $message }}</p>@enderror
            </div>
        </div>

        {{-- Campaign Type & Media --}}
        <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-6 space-y-4">
            <h2 class="text-sm font-semibold text-slate-200">Content Type</h2>

            <div>
                <label class="block text-sm font-medium text-slate-300 mb-1.5">Campaign Type <span class="text-red-400">*</span></label>
                <div class="flex gap-3">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" name="campaign_type" value="video" x-model="campaignType"
                               {{ old('campaign_type', 'video') === 'video' ? 'checked' : '' }}
                               class="accent-amber-500" />
                        <span class="text-sm text-slate-200">🎬 Video</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" name="campaign_type" value="live_feed" x-model="campaignType"
                               {{ old('campaign_type') === 'live_feed' ? 'checked' : '' }}
                               class="accent-amber-500" />
                        <span class="text-sm text-slate-200">📡 Live Feed</span>
                    </label>
                </div>
                @error('campaign_type')<p class="text-xs text-red-400 mt-1">{{ $message }}</p>@enderror
            </div>

            {{-- Video URL or file --}}
            <div x-show="campaignType === 'video'">
                <label class="block text-sm font-medium text-slate-300 mb-1.5">Video URL</label>
                <input type="url" name="media_url" value="{{ old('media_url') }}"
                    class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500 transition"
                    placeholder="https://www.youtube.com/watch?v=..." />
                <p class="text-xs text-slate-500 mt-1">Paste a YouTube, Vimeo, or direct video URL — or upload a file below.</p>
                @error('media_url')<p class="text-xs text-red-400 mt-1">{{ $message }}</p>@enderror

                <div class="mt-3">
                    <label class="block text-sm font-medium text-slate-300 mb-1.5">— or upload a video file</label>
                    <input type="file" name="video" accept="{{ $videoAcceptTypes }}"
                        class="block w-full text-sm text-slate-400 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-amber-500/20 file:text-amber-300 file:text-xs file:font-medium hover:file:bg-amber-500/30 cursor-pointer" />
                    @error('video')<p class="text-xs text-red-400 mt-1">{{ $message }}</p>@enderror
                </div>
            </div>

            {{-- Live feed --}}
            <div x-show="campaignType === 'live_feed'" x-cloak class="space-y-3">
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1.5">Live Feed URL <span class="text-red-400">*</span></label>
                    <input type="url" name="live_feed_url" value="{{ old('live_feed_url') }}"
                        class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500 transition"
                        placeholder="https://stream.example.com/live" />
                    @error('live_feed_url')<p class="text-xs text-red-400 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1.5">Scheduled Start <span class="text-red-400">*</span></label>
                    <input type="datetime-local" name="live_scheduled_at" value="{{ old('live_scheduled_at') }}"
                        class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500 transition" />
                    @error('live_scheduled_at')<p class="text-xs text-red-400 mt-1">{{ $message }}</p>@enderror
                </div>
            </div>
        </div>

        {{-- Targeting --}}
        <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-6 space-y-4">
            <h2 class="text-sm font-semibold text-slate-200">Local Targeting</h2>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1.5">Target ZIP Code <span class="text-red-400">*</span></label>
                    <input type="text" name="target_zip" value="{{ old('target_zip', $citizen->zip) }}"
                        maxlength="5" pattern="[0-9]{5}"
                        class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500 transition"
                        placeholder="90210" />
                    @error('target_zip')<p class="text-xs text-red-400 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1.5">Radius (miles)</label>
                    <input type="number" name="target_zip_radius" value="{{ old('target_zip_radius', 10) }}"
                        min="1" max="100"
                        class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500 transition" />
                    @error('target_zip_radius')<p class="text-xs text-red-400 mt-1">{{ $message }}</p>@enderror
                </div>
            </div>
        </div>

        {{-- Budget --}}
        <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-6 space-y-4">
            <h2 class="text-sm font-semibold text-slate-200">Budget</h2>

            <div class="rounded-lg bg-amber-500/5 border border-amber-500/20 px-4 py-3 text-sm text-amber-300">
                Rate: <strong x-text="'$' + currentRate.toFixed(2) + '/view'"></strong>
                · Voters earn <strong>$0.50/view</strong>
                · Minimum 10 views
                <span x-show="adType === 'ballot_issue'" x-cloak class="ml-2 text-xs text-amber-200">(Ballot issue — requires admin approval)</span>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1.5">Views Requested <span class="text-red-400">*</span></label>
                    <input type="number" name="total_views_requested" value="{{ old('total_views_requested', 100) }}"
                        min="10" required
                        class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500 transition" />
                    @error('total_views_requested')<p class="text-xs text-red-400 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1.5">Daily View Cap</label>
                    <input type="number" name="daily_view_cap" value="{{ old('daily_view_cap', 500) }}"
                        min="10" max="5000" placeholder="500"
                        class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500 transition"
                        :disabled="adType === 'ballot_issue'" />
                    <p class="text-xs text-slate-500 mt-1" x-show="adType === 'ballot_issue'" x-cloak>Uncapped for ballot-issue campaigns.</p>
                    @error('daily_view_cap')<p class="text-xs text-red-400 mt-1">{{ $message }}</p>@enderror
                </div>
            </div>

            {{-- Budget is computed server-side from views × rate; send total_budget so validation has a value --}}
            <input type="hidden" name="total_budget" id="totalBudgetInput"
                   value="{{ old('total_budget', 75) }}" />
        </div>

        {{-- Repeat Viewing --}}
        <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-6 space-y-4">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 class="text-sm font-semibold text-slate-200">Repeat Viewing</h2>
                    <p class="text-xs text-slate-500 mt-1">Let the same voter watch this video more than once. Only the first qualifying view pays the voter — re-watches are free for the sponsor.</p>
                </div>
                <label class="relative inline-flex items-center cursor-pointer shrink-0">
                    <input type="checkbox" name="allow_repeat_views" value="1" class="sr-only peer"
                        {{ old('allow_repeat_views') ? 'checked' : '' }}
                        x-model="repeatViews">
                    <div class="w-11 h-6 bg-slate-700 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-amber-500/50 rounded-full peer transition peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-amber-500"></div>
                    <span class="ml-3 text-sm text-slate-300">Allow repeat views</span>
                </label>
            </div>

            <div x-show="repeatViews" x-cloak class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1.5">Max views per voter</label>
                    <input type="number" name="max_views_per_voter" value="{{ old('max_views_per_voter', 3) }}"
                        min="1" max="10"
                        class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500 transition" />
                    @error('max_views_per_voter')<p class="text-xs text-red-400 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1.5">Cooldown (hours)</label>
                    <input type="number" name="repeat_view_cooldown_hours" value="{{ old('repeat_view_cooldown_hours', 24) }}"
                        min="0" max="720"
                        class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500 transition" />
                    <p class="text-xs text-slate-500 mt-1">0 = no wait between re-watches.</p>
                    @error('repeat_view_cooldown_hours')<p class="text-xs text-red-400 mt-1">{{ $message }}</p>@enderror
                </div>
            </div>
        </div>

        {{-- Submit --}}
        <div class="flex gap-3">
            <button type="submit"
                class="flex-1 bg-amber-500 hover:bg-amber-400 text-slate-900 font-semibold rounded-lg px-6 py-3 text-sm transition-colors">
                Create Campaign
            </button>
            <a href="{{ route('citizen.campaigns.index') }}"
               class="px-5 py-3 text-sm text-slate-400 hover:text-white border border-slate-700 hover:border-slate-500 rounded-lg transition">
                Cancel
            </a>
        </div>
    </form>
</div>

@push('scripts')
<script>
    // Keep total_budget hidden input in sync so server-side min validation has a value to check.
    // The real computation is always done server-side; this just passes something.
    const views = document.querySelector('[name="total_views_requested"]');
    const budgetInput = document.getElementById('totalBudgetInput');
    const updateBudget = () => {
        const rate = document.querySelector('[name="citizen_ad_type"]:checked')?.value === 'ballot_issue'
            ? {{ $ballotIssueRate }}
            : {{ $citizenRate }};
        budgetInput.value = (parseInt(views?.value || '0', 10) * rate).toFixed(2);
    };
    document.addEventListener('change', updateBudget);
    document.addEventListener('input', updateBudget);
    updateBudget();
</script>
@endpush

@endsection
