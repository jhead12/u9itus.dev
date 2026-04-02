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
        $campaignTypeRaw = (string) ($campaign->getRawOriginal('campaign_type') ?? '');
        $campaignType = in_array($campaignTypeRaw, ['video', 'q_and_a', 'live_feed'], true) ? $campaignTypeRaw : 'video';
        $qaItems = is_array($campaign->qa_items ?? null) ? $campaign->qa_items : [];
        $surveyPayload = is_array($campaign->engagement_survey ?? null) ? $campaign->engagement_survey : [];
        $surveyOptions = is_array($surveyPayload['options'] ?? null) ? $surveyPayload['options'] : [];
        $campaignTopicIds = is_array($campaignTopicIds ?? null) ? $campaignTopicIds : [];
        $campaignTargetStates = is_array($campaign->target_states ?? null) ? $campaign->target_states : [];
        $campaignTargetCities = is_array($campaign->target_cities ?? null) ? $campaign->target_cities : [];

        $liveScheduledAtValue = old('live_scheduled_at');
        if (blank($liveScheduledAtValue)) {
            $liveScheduledAtRaw = (string) ($campaign->getRawOriginal('live_scheduled_at') ?? '');
            if ($liveScheduledAtRaw !== '') {
                try {
                    $liveScheduledAtValue = \Illuminate\Support\Carbon::parse($liveScheduledAtRaw)->format('Y-m-d\\TH:i');
                } catch (\Throwable $e) {
                    $liveScheduledAtValue = '';
                }
            }
        }

        $scheduledStartAtValue = old('scheduled_start_at');
        if (blank($scheduledStartAtValue)) {
            $scheduledStartAtRaw = (string) ($campaign->getRawOriginal('scheduled_start_at') ?? '');
            if ($scheduledStartAtRaw !== '') {
                try {
                    $scheduledStartAtValue = \Illuminate\Support\Carbon::parse($scheduledStartAtRaw)->format('Y-m-d\\TH:i');
                } catch (\Throwable $e) {
                    $scheduledStartAtValue = '';
                }
            }
        }

        $scheduledEndAtValue = old('scheduled_end_at');
        if (blank($scheduledEndAtValue)) {
            $scheduledEndAtRaw = (string) ($campaign->getRawOriginal('scheduled_end_at') ?? '');
            if ($scheduledEndAtRaw !== '') {
                try {
                    $scheduledEndAtValue = \Illuminate\Support\Carbon::parse($scheduledEndAtRaw)->format('Y-m-d\\TH:i');
                } catch (\Throwable $e) {
                    $scheduledEndAtValue = '';
                }
            }
        }

        $allowMovUploads = preg_match('/\b(iPhone|iPad|iPod)\b/i', request()->userAgent() ?? '') === 1;
        $videoAcceptTypes = $allowMovUploads ? 'video/mp4,video/webm,video/quicktime' : 'video/mp4,video/webm';
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

    <form method="POST" action="{{ route('admin.campaigns.update', $campaign) }}" enctype="multipart/form-data" class="space-y-6" id="adminEditCampaignForm">
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
                        <option value="video" {{ old('campaign_type', $campaignType) === 'video' ? 'selected' : '' }}>🎬 Video</option>
                        <option value="q_and_a" {{ old('campaign_type', $campaignType) === 'q_and_a' ? 'selected' : '' }}>❓ Q&A</option>
                        <option value="live_feed" {{ old('campaign_type', $campaignType) === 'live_feed' ? 'selected' : '' }}>📡 Live Feed</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1.5">Governance Level <span class="text-red-400">*</span></label>
                    <select name="governance_level" required
                        class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition">
                        <option value="" disabled {{ old('governance_level', $campaign->governance_level) ? '' : 'selected' }}>Select governance level</option>
                        @foreach($governanceLevels as $value => $label)
                            <option value="{{ $value }}" {{ old('governance_level', $campaign->governance_level) === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('governance_level')<p class="text-xs text-red-400 mt-1">{{ $message }}</p>@enderror
                </div>
            </div>

@php $editType = old('campaign_type', $campaignType); @endphp

            {{-- Video fields --}}
            <div id="videoFields" class="{{ $editType === 'live_feed' ? 'hidden' : '' }} space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2.5">Media Type</label>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="relative flex items-center group cursor-pointer">
                            <input type="radio" name="media_type" value="youtube" {{ old('media_type', $campaign->media_type) === 'youtube' ? 'checked' : '' }}
                                class="sr-only peer">
                            <div class="flex-1 bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-3 peer-checked:border-emerald-500 peer-checked:bg-emerald-500/10 transition">
                                <p class="text-sm font-medium text-slate-200">🎬 YouTube</p>
                                <p class="text-xs text-slate-500 mt-0.5">YouTube video link</p>
                            </div>
                        </label>
                        <label class="relative flex items-center group cursor-pointer">
                            <input type="radio" name="media_type" value="vimeo" {{ old('media_type', $campaign->media_type) === 'vimeo' ? 'checked' : '' }}
                                class="sr-only peer">
                            <div class="flex-1 bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-3 peer-checked:border-emerald-500 peer-checked:bg-emerald-500/10 transition">
                                <p class="text-sm font-medium text-slate-200">🎥 Vimeo</p>
                                <p class="text-xs text-slate-500 mt-0.5">Vimeo video link</p>
                            </div>
                        </label>
                        <label class="relative flex items-center group cursor-pointer">
                            <input type="radio" name="media_type" value="direct_file" {{ old('media_type', $campaign->media_type) === 'direct_file' ? 'checked' : '' }}
                                class="sr-only peer">
                            <div class="flex-1 bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-3 peer-checked:border-emerald-500 peer-checked:bg-emerald-500/10 transition">
                                <p class="text-sm font-medium text-slate-200">📁 Direct File</p>
                                <p class="text-xs text-slate-500 mt-0.5">MP4 or WebM URL</p>
                            </div>
                        </label>
                        <label class="relative flex items-center group cursor-pointer">
                            <input type="radio" name="media_type" value="s3_cloudfront" {{ old('media_type', $campaign->media_type) === 's3_cloudfront' ? 'checked' : '' }}
                                class="sr-only peer">
                            <div class="flex-1 bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-3 peer-checked:border-emerald-500 peer-checked:bg-emerald-500/10 transition">
                                <p class="text-sm font-medium text-slate-200">☁️ S3/CloudFront</p>
                                <p class="text-xs text-slate-500 mt-0.5">S3 CloudFront URL</p>
                            </div>
                        </label>
                        <label class="relative flex items-center group cursor-pointer">
                            <input type="radio" name="media_type" value="hls_stream" {{ old('media_type', $campaign->media_type) === 'hls_stream' ? 'checked' : '' }}
                                class="sr-only peer">
                            <div class="flex-1 bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-3 peer-checked:border-emerald-500 peer-checked:bg-emerald-500/10 transition">
                                <p class="text-sm font-medium text-slate-200">📺 HLS Stream</p>
                                <p class="text-xs text-slate-500 mt-0.5">.m3u8 live or VOD playlist</p>
                            </div>
                        </label>
                    </div>
                </div>

                <div id="mediaUrlField">
                    <label id="mediaUrlLabel" class="block text-sm font-medium text-slate-300 mb-1.5">Video URL <span class="text-red-400">*</span></label>
                    <input type="url" name="media_url" id="videoUrlInput" value="{{ old('media_url', $campaign->media_url) }}"
                        class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition"
                        placeholder="https://youtube.com/watch?v=... or https://example.com/video.mp4" />
                    <p id="mediaUrlHelp" class="text-xs text-slate-500 mt-1">YouTube URL or direct link to MP4/WebM video</p>
                    @error('media_url')<p class="text-xs text-red-400 mt-1">{{ $message }}</p>@enderror
                </div>

                <div id="uploadVideoField">
                    <label id="uploadVideoLabel" class="block text-sm font-medium text-slate-300 mb-1.5">Upload New Video File</label>
                    <input type="file" name="video" id="videoFileInput" accept="{{ $videoAcceptTypes }}"
                        class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm file:mr-3 file:rounded file:border-0 file:bg-emerald-500 file:px-3 file:py-1.5 file:text-slate-900 file:font-medium hover:file:bg-emerald-400" />
                    <div class="mt-2">
                        <p id="uploadVideoHelp" class="text-xs text-slate-500">Optional alternative to URL (max {{ config('u9itus.max_video_size_mb', 1024) }}MB). Uploading a file replaces the current video URL.</p>
                        <p class="text-xs text-amber-600/80 mt-2 bg-amber-950/30 border border-amber-800/40 rounded px-2 py-1.5">💡 <strong>Tip:</strong> For best upload reliability with large files, use H.264-encoded MP4 format. Files larger than 100 MB may take longer to process.</p>
                    </div>
                    @error('video')<p class="text-xs text-red-400 mt-1">{{ $message }}</p>@enderror
                </div>

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
                    <p class="text-xs text-slate-500 mt-2">This is how voters will see your video. Test it to ensure proper playback.</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1.5">Video Duration (seconds)</label>
                    @php
                        $minVideoDuration = max(10, min(180, (int) config('u9itus.min_video_duration', 10)));
                        $maxVideoDuration = max($minVideoDuration, min(180, (int) config('u9itus.max_video_duration', 180)));
                    @endphp
                    <input type="number" name="media_duration" value="{{ old('media_duration', $campaign->media_duration ?? $minVideoDuration) }}"
                        min="{{ $minVideoDuration }}"
                        max="{{ $maxVideoDuration }}"
                        class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition"
                        placeholder="e.g. 60" />
                    <p class="text-xs text-slate-500 mt-1">System will auto-detect from video metadata if available ({{ $minVideoDuration }}–{{ $maxVideoDuration }}s)</p>
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
                        value="{{ $liveScheduledAtValue }}"
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

        {{-- Sprint 3: Topics & Q&A Section --}}
        <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-6 space-y-6">
            <div>
                <h2 class="text-sm font-semibold text-slate-200 mb-1">Virtual Town Hall Content <span class="text-slate-500 font-normal text-xs">(optional)</span></h2>
                <p class="text-xs text-slate-500 mt-1">Add topics, Q&A pairs, engagement surveys, and media customization to create an interactive town hall experience.</p>
            </div>

            <div class="border-t border-slate-700/50 pt-6">
                <label class="block text-sm font-medium text-slate-300 mb-2.5">Campaign Topics <span class="text-slate-500 font-normal text-xs">(max 5)</span></label>
                <div class="relative">
                    <button type="button" id="topicsDropdownBtn"
                        class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-left text-sm text-white focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition flex items-center justify-between gap-2">
                        <span id="topicsSelectedText" class="text-slate-400">Select topics...</span>
                        <svg class="w-4 h-4 text-slate-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <div id="topicsDropdown" class="hidden absolute z-10 w-full mt-1 bg-slate-800 border border-slate-600 rounded-lg shadow-xl max-h-60 overflow-y-auto">
                        <div class="p-3 border-b border-slate-700 space-y-3">
                            @foreach(($topics ?? collect()) as $topic)
                            <label class="flex items-center gap-2 px-2 py-1.5 hover:bg-slate-700/50 rounded cursor-pointer transition">
                                <input type="checkbox" name="topic_ids[]" value="{{ $topic->id }}"
                                    class="topic-checkbox w-4 h-4 rounded border-slate-600 text-emerald-500 focus:ring-emerald-500 focus:ring-offset-slate-900"
                                    {{ in_array($topic->id, $campaignTopicIds) || (is_array(old('topic_ids')) && in_array($topic->id, old('topic_ids'))) ? 'checked' : '' }}>
                                <span class="text-lg mr-2">{{ $topic->icon }}</span>
                                <span class="text-sm text-slate-200">{{ $topic->name }}</span>
                            </label>
                            @endforeach
                        </div>
                    </div>
                </div>
                <div id="selectedTopicsDisplay" class="mt-3 flex flex-wrap gap-2"></div>
                <p class="text-xs text-slate-500 mt-2">Help voters discover your campaign by assigning relevant topics</p>
            </div>

            <div class="border-t border-slate-700/50 pt-6">
                <label class="block text-sm font-medium text-slate-300 mb-2.5">Politician's Opening Statement <span class="text-slate-500 font-normal text-xs">(max 1000 chars)</span></label>
                <textarea name="intro_text" maxlength="1000" rows="3"
                    class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition resize-none"
                    placeholder="Start your town hall with a brief introduction or statement...">{{ old('intro_text', $campaign->intro_text) }}</textarea>
                <p class="text-xs text-slate-500 mt-1"><span id="introCharCount">{{ strlen(old('intro_text', $campaign->intro_text ?? '')) }}</span>/1000 characters</p>
            </div>

            <div class="border-t border-slate-700/50 pt-6">
                <div class="flex items-center justify-between mb-3">
                    <label class="block text-sm font-medium text-slate-300">Questions & Answers</label>
                    <button type="button" id="addQABtn" class="text-xs text-emerald-400 hover:text-emerald-300 flex items-center gap-1 transition">Add Q&A Pair</button>
                </div>
                <div id="qaItemsContainer" class="space-y-3">
                    @forelse($qaItems as $index => $item)
                    <div class="qa-item bg-slate-900/60 border border-slate-700 rounded-lg overflow-hidden">
                        <button type="button" class="w-full qa-toggle px-4 py-3 flex items-center justify-between hover:bg-slate-700/50 transition text-left">
                            <span class="text-sm font-medium text-slate-200" id="qa-label-{{ $index }}">Q{{ $index + 1 }}: {{ \Illuminate\Support\Str::limit($item['question'] ?? '', 50) }}</span>
                            <svg class="w-4 h-4 text-slate-400 transition-transform qa-chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div class="qa-content hidden px-4 pb-3 space-y-2 border-t border-slate-700">
                            <input type="hidden" name="qa_items[{{ $index }}][question]" value="{{ $item['question'] ?? '' }}">
                            <input type="hidden" name="qa_items[{{ $index }}][answer]" value="{{ $item['answer'] ?? '' }}">
                            <div>
                                <label class="block text-xs font-medium text-slate-300 mb-1">Question</label>
                                <textarea class="w-full bg-slate-800/60 border border-slate-600 rounded px-3 py-2 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-emerald-500 resize-none qa-question" rows="2">{{ $item['question'] ?? '' }}</textarea>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-300 mb-1">Answer</label>
                                <textarea class="w-full bg-slate-800/60 border border-slate-600 rounded px-3 py-2 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-emerald-500 resize-none qa-answer" rows="3">{{ $item['answer'] ?? '' }}</textarea>
                            </div>
                            <button type="button" class="text-xs text-red-400 hover:text-red-300 flex items-center gap-1 mt-2 qa-remove">Remove</button>
                        </div>
                    </div>
                    @empty
                    <p class="text-xs text-slate-500 text-center py-3">No Q&A pairs yet. Click "Add Q&A Pair" to get started.</p>
                    @endforelse
                </div>
            </div>

            <div class="border-t border-slate-700/50 pt-6">
                <label class="block text-sm font-medium text-slate-300 mb-2.5">Post-View Survey <span class="text-slate-500 font-normal text-xs">(optional)</span></label>
                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-1.5">Survey Question</label>
                        <input type="text" name="engagement_survey[question]" maxlength="200"
                            value="{{ old('engagement_survey.question', $surveyPayload['question'] ?? '') }}"
                            class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition"
                            placeholder="e.g., Do you support this proposal?" />
                    </div>
                    <div id="surveyOptionsContainer">
                        <label class="block text-xs font-medium text-slate-300 mb-1.5">Answer Options <span class="text-slate-500 font-normal">(minimum 2)</span></label>
                        <div class="space-y-2">
                            @for($i = 0; $i < max(2, count($surveyOptions)); $i++)
                                @php $option = $surveyOptions[$i] ?? null; @endphp
                            <div class="flex gap-2 items-end">
                                <div class="flex-1">
                                    <input type="text" name="engagement_survey[options][{{ $i }}][text]" value="{{ $option['text'] ?? '' }}" maxlength="100"
                                        class="w-full bg-slate-900/60 border border-slate-700 rounded px-3 py-2 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-emerald-500 transition survey-option-text"
                                        placeholder="Option {{ $i + 1 }}" />
                                </div>
                                <input type="hidden" name="engagement_survey[options][{{ $i }}][value]" value="{{ $option['value'] ?? chr(65 + $i) }}">
                                <button type="button" class="text-xs text-slate-400 hover:text-red-400 transition remove-survey-option {{ $i < 2 ? 'invisible' : '' }}">Remove</button>
                            </div>
                            @endfor
                        </div>
                        <button type="button" id="addSurveyOptionBtn" class="mt-2 text-xs text-emerald-400 hover:text-emerald-300 flex items-center gap-1 transition">Add Option</button>
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
                                    {{ in_array($abbr, $campaignTargetStates) ? 'checked' : '' }}>
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
                                usort($allCities, fn($a, $b) => strcmp($a['display'], $b['display']));
                            @endphp
                            @foreach($allCities as $cityData)
                            <label class="city-option flex items-center gap-2 px-2 py-1.5 hover:bg-slate-700/50 rounded cursor-pointer transition" data-state="{{ $cityData['state'] }}">
                                <input type="checkbox" name="target_cities[]" value="{{ $cityData['display'] }}"
                                    class="city-checkbox w-4 h-4 rounded border-slate-600 text-emerald-500 focus:ring-emerald-500 focus:ring-offset-slate-900"
                                    {{ in_array($cityData['display'], $campaignTargetCities) ? 'checked' : '' }}>
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
                    <input type="datetime-local" name="scheduled_start_at" value="{{ $scheduledStartAtValue }}"
                        class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1.5">End Date &amp; Time</label>
                    <input type="datetime-local" name="scheduled_end_at" value="{{ $scheduledEndAtValue }}"
                        class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition" />
                </div>
            </div>
        </div>

        {{-- Repeat Viewing --}}
        <div class="bg-gradient-to-br from-purple-500/10 to-blue-500/10 border-2 border-purple-500/30 rounded-xl p-6 space-y-4 relative overflow-hidden">
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
                    <input type="checkbox" name="allow_repeat_views" id="allowRepeatViewsEdit" value="1" {{ old('allow_repeat_views', $campaign->allow_repeat_views) ? 'checked' : '' }} class="sr-only peer">
                    <div class="w-14 h-7 bg-slate-700 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-purple-500/50 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-6 after:w-6 after:transition-all peer-checked:bg-purple-500 shadow-lg"></div>
                </label>
            </div>

            <div id="repeatViewingOptionsEdit" class="{{ old('allow_repeat_views', $campaign->allow_repeat_views) ? '' : 'hidden' }} space-y-4 pt-2 border-t border-slate-700/40">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1.5">Cooldown Period (hours)</label>
                        <input type="number" name="repeat_view_cooldown_hours" value="{{ old('repeat_view_cooldown_hours', $campaign->repeat_view_cooldown_hours ?? 24) }}" min="1" max="720" step="1"
                            class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition" />
                        <p class="text-xs text-slate-500 mt-1">Min. hours between re-watches (1–720)</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1.5">Max Views Per Voter</label>
                        <input type="number" name="max_views_per_voter" value="{{ old('max_views_per_voter', $campaign->max_views_per_voter ?? 2) }}" min="2" max="10" step="1"
                            class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition" />
                        <p class="text-xs text-slate-500 mt-1">Lifetime cap per voter (2–10)</p>
                    </div>
                </div>
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
        syncMediaSourceUI();
    });

    const allowRepeatEdit = document.getElementById('allowRepeatViewsEdit');
    const repeatOptionsEdit = document.getElementById('repeatViewingOptionsEdit');
    if (allowRepeatEdit && repeatOptionsEdit) {
        allowRepeatEdit.addEventListener('change', () => {
            repeatOptionsEdit.classList.toggle('hidden', !allowRepeatEdit.checked);
        });
    }

    const statesBtn = document.getElementById('statesDropdownBtn');
    const statesDropdown = document.getElementById('statesDropdown');
    const statesSelectedText = document.getElementById('statesSelectedText');
    const stateCheckboxes = document.querySelectorAll('.state-checkbox');

    const citiesBtn = document.getElementById('citiesDropdownBtn');
    const citiesDropdown = document.getElementById('citiesDropdown');
    const citiesSelectedText = document.getElementById('citiesSelectedText');
    const cityCheckboxes = document.querySelectorAll('.city-checkbox');
    const citySearch = document.getElementById('citySearch');
    const cityOptions = document.querySelectorAll('.city-option');

    if (statesBtn && statesDropdown) {
        statesBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            statesDropdown.classList.toggle('hidden');
            citiesDropdown?.classList.add('hidden');
        });
    }

    function updateStatesDisplay() {
        if (!statesSelectedText) return;
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

    function filterCitiesByState() {
        const checkedStates = Array.from(stateCheckboxes)
            .filter(cb => cb.checked)
            .map(cb => cb.value);

        if (checkedStates.length === 0) {
            cityOptions.forEach(option => option.classList.remove('hidden'));
        } else {
            cityOptions.forEach(option => {
                const cityState = option.getAttribute('data-state');
                option.classList.toggle('hidden', !checkedStates.includes(cityState));
            });
        }

        const searchTerm = (citySearch?.value || '').toLowerCase();
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
        if (!citiesSelectedText) return;
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

    window.selectAllStates = function() {
        stateCheckboxes.forEach(cb => cb.checked = true);
        updateStatesDisplay();
        filterCitiesByState();
    };

    window.clearAllStates = function() {
        stateCheckboxes.forEach(cb => cb.checked = false);
        updateStatesDisplay();
        filterCitiesByState();
    };

    window.selectAllCities = function() {
        cityCheckboxes.forEach(cb => {
            if (!cb.closest('.city-option').classList.contains('hidden')) {
                cb.checked = true;
            }
        });
        updateCitiesDisplay();
    };

    window.clearAllCities = function() {
        cityCheckboxes.forEach(cb => cb.checked = false);
        updateCitiesDisplay();
    };

    stateCheckboxes.forEach(cb => cb.addEventListener('change', () => {
        updateStatesDisplay();
        filterCitiesByState();
    }));

    if (citiesBtn && citiesDropdown) {
        citiesBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            citiesDropdown.classList.toggle('hidden');
            statesDropdown?.classList.add('hidden');
        });
    }

    cityCheckboxes.forEach(cb => cb.addEventListener('change', updateCitiesDisplay));

    if (citySearch) {
        citySearch.addEventListener('input', (e) => {
            const searchTerm = e.target.value.toLowerCase();
            const checkedStates = Array.from(stateCheckboxes)
                .filter(cb => cb.checked)
                .map(cb => cb.value);

            cityOptions.forEach(option => {
                const text = option.textContent.toLowerCase();
                const cityState = option.getAttribute('data-state');
                const matchesSearch = text.includes(searchTerm);
                const matchesState = checkedStates.length === 0 || checkedStates.includes(cityState);
                option.classList.toggle('hidden', !(matchesSearch && matchesState));
            });
        });
    }

    document.addEventListener('click', () => {
        statesDropdown?.classList.add('hidden');
        citiesDropdown?.classList.add('hidden');
    });

    statesDropdown?.addEventListener('click', (e) => e.stopPropagation());
    citiesDropdown?.addEventListener('click', (e) => e.stopPropagation());
    updateStatesDisplay();
    updateCitiesDisplay();

    const mediaTypeInputs = Array.from(document.querySelectorAll('input[name="media_type"]'));
    const mediaUrlField = document.getElementById('mediaUrlField');
    const mediaUrlLabel = document.getElementById('mediaUrlLabel');
    const mediaUrlHelp = document.getElementById('mediaUrlHelp');
    const uploadVideoField = document.getElementById('uploadVideoField');
    const uploadVideoLabel = document.getElementById('uploadVideoLabel');
    const uploadVideoHelp = document.getElementById('uploadVideoHelp');

    function getSelectedMediaType() {
        return mediaTypeInputs.find((input) => input.checked)?.value || 'youtube';
    }

    let ytPreviewPlayer = null;
    const videoUrlInput = document.getElementById('videoUrlInput');
    const previewSection = document.getElementById('videoPreviewSection');

    function syncMediaSourceUI() {
        if (!videoUrlInput || !mediaUrlLabel || !mediaUrlHelp || !uploadVideoField || !uploadVideoLabel || !uploadVideoHelp || !mediaUrlField) {
            return;
        }

        if (document.getElementById('campaignType').value === 'live_feed') {
            return;
        }

        const mediaType = getSelectedMediaType();
        const isDirectFile = mediaType === 'direct_file';
        const videoFileInput = document.getElementById('videoFileInput');

        mediaUrlField.classList.remove('hidden');
        uploadVideoField.classList.toggle('hidden', !isDirectFile);
        videoUrlInput.disabled = false;
        if (videoFileInput) {
            videoFileInput.disabled = !isDirectFile;
        }

        if (mediaType === 'youtube') {
            mediaUrlLabel.innerHTML = 'YouTube URL <span class="text-red-400">*</span>';
            videoUrlInput.placeholder = 'https://youtube.com/watch?v=...';
            mediaUrlHelp.textContent = 'Paste a YouTube watch or share URL.';
        } else if (mediaType === 'vimeo') {
            mediaUrlLabel.innerHTML = 'Vimeo URL <span class="text-red-400">*</span>';
            videoUrlInput.placeholder = 'https://vimeo.com/...';
            mediaUrlHelp.textContent = 'Paste a Vimeo video URL.';
        } else if (mediaType === 'hls_stream') {
            mediaUrlLabel.innerHTML = 'HLS Playlist URL <span class="text-red-400">*</span>';
            videoUrlInput.placeholder = 'https://example.com/stream.m3u8';
            mediaUrlHelp.textContent = 'Paste an HLS playlist URL ending in .m3u8.';
        } else if (mediaType === 's3_cloudfront') {
            mediaUrlLabel.innerHTML = 'CloudFront or S3 URL <span class="text-red-400">*</span>';
            videoUrlInput.placeholder = 'https://cdn.example.com/video.mp4';
            mediaUrlHelp.textContent = 'Paste a public CloudFront or S3 asset URL.';
        } else {
            mediaUrlLabel.innerHTML = 'Direct File URL <span class="text-slate-500 font-normal">(optional)</span>';
            videoUrlInput.placeholder = 'https://example.com/video.mp4';
            mediaUrlHelp.textContent = 'Optional direct file URL. Or upload a file below.';
        }

        uploadVideoLabel.textContent = 'Upload New Video File';
        uploadVideoHelp.textContent = 'Choose MP4, WebM, or MOV on iOS. Upload takes priority over URL.';

        syncVideoPreview();
    }

    function extractYouTubeId(url) {
        if (!url) return null;
        const patterns = [
            /(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([a-zA-Z0-9_-]{11})/,
            /^([a-zA-Z0-9_-]{11})$/,
        ];
        for (const pattern of patterns) {
            const match = url.match(pattern);
            if (match) return match[1];
        }
        return null;
    }

    function resetVideoPreview() {
        const ytContainer = document.getElementById('ytPreviewContainer');
        const nativePlayer = document.getElementById('nativePreviewPlayer');
        const placeholder = document.getElementById('previewPlaceholder');
        if (ytPreviewPlayer) {
            ytPreviewPlayer.destroy();
            ytPreviewPlayer = null;
        }
        ytContainer?.classList.add('hidden');
        nativePlayer?.classList.add('hidden');
        placeholder?.classList.remove('hidden');
    }

    function createYTPlayer(videoId) {
        ytPreviewPlayer = new YT.Player('ytPreviewContainer', {
            height: '100%',
            width: '100%',
            videoId,
            playerVars: { autoplay: 0, controls: 1, modestbranding: 1, rel: 0 },
        });
    }

    function initYouTubePreview(videoId) {
        const ytContainer = document.getElementById('ytPreviewContainer');
        const nativePlayer = document.getElementById('nativePreviewPlayer');
        const placeholder = document.getElementById('previewPlaceholder');
        if (ytPreviewPlayer) {
            ytPreviewPlayer.destroy();
            ytPreviewPlayer = null;
        }
        nativePlayer?.classList.add('hidden');
        ytContainer?.classList.remove('hidden');
        placeholder?.classList.add('hidden');

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

    function initNativePreview(url) {
        const ytContainer = document.getElementById('ytPreviewContainer');
        const nativePlayer = document.getElementById('nativePreviewPlayer');
        const nativeSource = document.getElementById('nativePreviewSource');
        const placeholder = document.getElementById('previewPlaceholder');
        if (ytPreviewPlayer) {
            ytPreviewPlayer.destroy();
            ytPreviewPlayer = null;
        }
        ytContainer?.classList.add('hidden');
        nativePlayer?.classList.remove('hidden');
        placeholder?.classList.add('hidden');
        if (nativeSource && nativePlayer) {
            nativeSource.src = url;
            nativePlayer.load();
        }
    }

    function syncVideoPreview() {
        if (!videoUrlInput || !previewSection) {
            return;
        }

        const mediaType = getSelectedMediaType();
        const isDirectFile = mediaType === 'direct_file';
        const url = videoUrlInput.value.trim();
        const videoFileInput = document.getElementById('videoFileInput');
        const file = videoFileInput?.files && videoFileInput.files[0];

        if (isDirectFile && file) {
            previewSection.classList.remove('hidden');
            initNativePreview(URL.createObjectURL(file));
            return;
        }

        if (!url) {
            previewSection.classList.add('hidden');
            return;
        }

        previewSection.classList.remove('hidden');

        if (mediaType === 'youtube') {
            const youtubeId = extractYouTubeId(url);
            if (youtubeId) {
                initYouTubePreview(youtubeId);
            } else {
                resetVideoPreview();
            }
            return;
        }

        if (url.startsWith('http')) {
            initNativePreview(url);
        } else {
            resetVideoPreview();
        }
    }

    const videoFileInput = document.getElementById('videoFileInput');
    if (videoFileInput) {
        videoFileInput.addEventListener('change', () => {
            if (videoUrlInput && videoFileInput.files && videoFileInput.files[0]) {
                videoUrlInput.value = '';
            }
            syncVideoPreview();
        });
    }

    if (videoUrlInput) {
        videoUrlInput.addEventListener('input', () => {
            if (videoFileInput && videoFileInput.files && videoFileInput.files.length > 0) {
                videoFileInput.value = '';
            }
            syncVideoPreview();
        });
    }

    mediaTypeInputs.forEach((input) => input.addEventListener('change', syncMediaSourceUI));

    window.testVideoPreview = function() {
        const nativePlayer = document.getElementById('nativePreviewPlayer');
        if (ytPreviewPlayer && ytPreviewPlayer.playVideo) {
            ytPreviewPlayer.playVideo();
        } else if (nativePlayer && !nativePlayer.classList.contains('hidden')) {
            nativePlayer.play();
        }
    };

    const introText = document.querySelector('textarea[name="intro_text"]');
    const introCharCount = document.getElementById('introCharCount');
    if (introText && introCharCount) {
        introText.addEventListener('input', () => {
            introCharCount.textContent = introText.value.length;
        });
        introCharCount.textContent = introText.value.length;
    }

    const topicsBtn = document.getElementById('topicsDropdownBtn');
    const topicsDropdown = document.getElementById('topicsDropdown');
    const topicsSelectedText = document.getElementById('topicsSelectedText');
    const topicCheckboxes = document.querySelectorAll('.topic-checkbox');
    const selectedTopicsDisplay = document.getElementById('selectedTopicsDisplay');
    const MAX_TOPICS = 5;

    function updateTopicsDisplay() {
        if (!topicsSelectedText || !selectedTopicsDisplay) return;
        const checked = Array.from(topicCheckboxes).filter(cb => cb.checked);
        if (checked.length > MAX_TOPICS) {
            return;
        }
        if (checked.length === 0) {
            topicsSelectedText.textContent = 'Select topics...';
            topicsSelectedText.classList.add('text-slate-400');
        } else {
            topicsSelectedText.textContent = checked.length + ' topic' + (checked.length > 1 ? 's' : '') + ' selected';
            topicsSelectedText.classList.remove('text-slate-400');
        }

        selectedTopicsDisplay.innerHTML = checked.map(cb => {
            const label = cb.closest('label').textContent.trim();
            const [icon, ...nameParts] = label.split(' ');
            const name = nameParts.join(' ');
            return `<div class="inline-flex items-center gap-2 bg-emerald-500/20 border border-emerald-500/40 rounded-full px-3 py-1.5 text-xs text-emerald-300"><span class="text-sm">${icon}</span><span>${name}</span></div>`;
        }).join('');
    }

    if (topicsBtn && topicsDropdown) {
        topicsBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            topicsDropdown.classList.toggle('hidden');
        });
        topicsDropdown.addEventListener('click', (e) => e.stopPropagation());
    }

    topicCheckboxes.forEach(cb => cb.addEventListener('change', updateTopicsDisplay));

    document.addEventListener('click', (e) => {
        if (!e.target.closest('#topicsDropdownBtn') && !e.target.closest('#topicsDropdown')) {
            topicsDropdown?.classList.add('hidden');
        }
    });
    updateTopicsDisplay();

    const addQABtn = document.getElementById('addQABtn');
    const qaContainer = document.getElementById('qaItemsContainer');
    let qaIndex = document.querySelectorAll('.qa-item').length;
    const editCampaignForm = document.getElementById('adminEditCampaignForm');

    function escapeHtml(text) {
        const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return text.replace(/[&<>"']/g, m => map[m]);
    }

    function renderQAFieldsFromDOM() {
        const items = document.querySelectorAll('.qa-item');
        let html = '';
        items.forEach((item, idx) => {
            const question = item.querySelector('.qa-question')?.value || '';
            const answer = item.querySelector('.qa-answer')?.value || '';
            html += `<input type="hidden" name="qa_items[${idx}][question]" value="${escapeHtml(question)}">`;
            html += `<input type="hidden" name="qa_items[${idx}][answer]" value="${escapeHtml(answer)}">`;
        });

        let hiddenContainer = document.getElementById('qa-hidden-fields');
        if (!hiddenContainer) {
            hiddenContainer = document.createElement('div');
            hiddenContainer.id = 'qa-hidden-fields';
            hiddenContainer.style.display = 'none';
            editCampaignForm?.appendChild(hiddenContainer);
        }
        hiddenContainer.innerHTML = html;
    }

    function createQAItem(index) {
        const div = document.createElement('div');
        div.className = 'qa-item bg-slate-900/60 border border-slate-700 rounded-lg overflow-hidden';
        div.innerHTML = `
            <button type="button" class="qa-toggle w-full px-4 py-3 flex items-center justify-between hover:bg-slate-700/50 transition text-left">
                <span class="text-sm font-medium text-slate-200" id="qa-label-${index}">New Q&A Pair</span>
                <svg class="w-4 h-4 text-slate-400 transition-transform qa-chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>
            <div class="qa-content hidden px-4 pb-3 space-y-2 border-t border-slate-700">
                <div>
                    <label class="block text-xs font-medium text-slate-300 mb-1">Question</label>
                    <textarea class="w-full bg-slate-800/60 border border-slate-600 rounded px-3 py-2 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-emerald-500 resize-none qa-question" rows="2" placeholder="Enter question..."></textarea>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-300 mb-1">Answer</label>
                    <textarea class="w-full bg-slate-800/60 border border-slate-600 rounded px-3 py-2 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-emerald-500 resize-none qa-answer" rows="3" placeholder="Enter answer..."></textarea>
                </div>
                <button type="button" class="text-xs text-red-400 hover:text-red-300 flex items-center gap-1 mt-2 qa-remove">Remove</button>
            </div>
        `;

        const toggle = div.querySelector('.qa-toggle');
        const content = div.querySelector('.qa-content');
        const chevron = div.querySelector('.qa-chevron');
        const removeBtn = div.querySelector('.qa-remove');
        const questionField = div.querySelector('.qa-question');
        const label = div.querySelector(`#qa-label-${index}`);

        toggle.addEventListener('click', () => {
            content.classList.toggle('hidden');
            chevron.classList.toggle('rotate-180');
        });

        questionField.addEventListener('input', () => {
            const q = questionField.value || 'New Q&A Pair';
            label.textContent = `Q${index + 1}: ` + (q.length > 50 ? q.substring(0, 50) + '...' : q);
            renderQAFieldsFromDOM();
        });

        div.querySelector('.qa-answer').addEventListener('input', renderQAFieldsFromDOM);

        removeBtn.addEventListener('click', () => {
            div.remove();
            renderQAFieldsFromDOM();
        });

        return div;
    }

    if (addQABtn) {
        addQABtn.addEventListener('click', () => {
            const newItem = createQAItem(qaIndex++);
            qaContainer.querySelector('p.text-xs')?.remove();
            qaContainer.appendChild(newItem);
            newItem.querySelector('.qa-toggle').click();
            newItem.querySelector('.qa-question').focus();
            renderQAFieldsFromDOM();
        });
    }

    document.querySelectorAll('.qa-toggle').forEach(toggle => {
        toggle.addEventListener('click', () => {
            const content = toggle.nextElementSibling;
            const chevron = toggle.querySelector('.qa-chevron');
            content.classList.toggle('hidden');
            chevron.classList.toggle('rotate-180');
        });
    });
    document.querySelectorAll('.qa-remove').forEach(btn => btn.addEventListener('click', () => {
        btn.closest('.qa-item').remove();
        renderQAFieldsFromDOM();
    }));
    document.querySelectorAll('.qa-question, .qa-answer').forEach(field => field.addEventListener('input', renderQAFieldsFromDOM));

    const addSurveyOptionBtn = document.getElementById('addSurveyOptionBtn');
    const surveyOptionsContainer = document.getElementById('surveyOptionsContainer');
    if (addSurveyOptionBtn && surveyOptionsContainer) {
        addSurveyOptionBtn.addEventListener('click', () => {
            const optionCount = surveyOptionsContainer.querySelectorAll('.flex.gap-2').length;
            const charCode = 65 + optionCount;
            const div = document.createElement('div');
            div.className = 'flex gap-2 items-end';
            div.innerHTML = `
                <div class="flex-1">
                    <input type="text" name="engagement_survey[options][${optionCount}][text]" maxlength="100"
                        class="w-full bg-slate-900/60 border border-slate-700 rounded px-3 py-2 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-emerald-500 transition survey-option-text"
                        placeholder="Option ${optionCount + 1}" />
                </div>
                <input type="hidden" name="engagement_survey[options][${optionCount}][value]" value="${String.fromCharCode(charCode)}">
                <button type="button" class="text-xs text-slate-400 hover:text-red-400 transition remove-survey-option">Remove</button>
            `;
            surveyOptionsContainer.querySelector(':scope > div')?.appendChild(div);
            div.querySelector('.remove-survey-option').addEventListener('click', () => div.remove());
        });
    }
    document.querySelectorAll('.remove-survey-option').forEach(btn => btn.addEventListener('click', () => {
        btn.closest('.flex.gap-2')?.remove();
    }));

    editCampaignForm?.addEventListener('submit', () => {
        renderQAFieldsFromDOM();
    });

    window.addEventListener('DOMContentLoaded', () => {
        syncMediaSourceUI();
        filterCitiesByState();
        renderQAFieldsFromDOM();
    });
</script>
@endpush
