@extends('standalone.layouts.dashboard')

@section('title', 'New Campaign')
@section('page-title', 'Create Campaign')

@php
    $allowMovUploads = preg_match('/\b(iPhone|iPad|iPod)\b/i', request()->userAgent() ?? '') === 1;
    $videoAcceptTypes = $allowMovUploads ? 'video/mp4,video/webm,video/quicktime' : 'video/mp4,video/webm';
@endphp

@section('content')
<div class="max-w-2xl">

    <div class="mb-6">
        <a href="{{ route('politician.campaigns.index') }}" class="text-sm text-slate-400 hover:text-white transition">← Back to campaigns</a>
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

    <form method="POST" action="{{ route('politician.campaigns.store') }}" enctype="multipart/form-data" class="space-y-6" id="campaignForm">
        @csrf

        {{-- Basic Info --}}
        <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-6 space-y-4">
            <h2 class="text-sm font-semibold text-slate-200 mb-4">Campaign Details</h2>

            <div>
                <label class="block text-sm font-medium text-slate-300 mb-1.5">Campaign Title <span class="text-red-400">*</span></label>
                <input type="text" name="title" value="{{ old('title') }}" required maxlength="255"
                    class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition"
                    placeholder="e.g. Vote Yes on Proposition 1" />
                @error('title')<p class="text-xs text-red-400 mt-1">{{ $message }}</p>@enderror
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
                        <option value="q_and_a" {{ old('campaign_type') === 'q_and_a' ? 'selected' : '' }}>❓ Q&A</option>
                        <option value="live_feed" {{ old('campaign_type') === 'live_feed' ? 'selected' : '' }}>📡 Live Feed</option>
                    </select>
                    @error('campaign_type')<p class="text-xs text-red-400 mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1.5">Governance Level <span class="text-red-400">*</span></label>
                    <select name="governance_level" required
                        class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition">
                        <option value="" disabled {{ old('governance_level') ? '' : 'selected' }}>Select governance level</option>
                        @foreach($governanceLevels as $value => $label)
                            <option value="{{ $value }}" {{ old('governance_level') === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('governance_level')<p class="text-xs text-red-400 mt-1">{{ $message }}</p>@enderror
                </div>
            </div>

            {{-- Video fields (shown when campaign_type = video, which is the default) --}}
            <div id="videoFields" class="{{ old('campaign_type', 'video') === 'live_feed' ? 'hidden' : '' }} space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2.5">Media Type</label>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="relative flex items-center group cursor-pointer">
                            <input type="radio" name="media_type" value="youtube" {{ old('media_type', 'youtube') === 'youtube' ? 'checked' : '' }}
                                class="sr-only peer">
                            <div class="flex-1 bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-3 peer-checked:border-emerald-500 peer-checked:bg-emerald-500/10 transition">
                                <p class="text-sm font-medium text-slate-200">🎬 YouTube</p>
                                <p class="text-xs text-slate-500 mt-0.5">YouTube video link</p>
                            </div>
                        </label>
                        <label class="relative flex items-center group cursor-pointer">
                            <input type="radio" name="media_type" value="vimeo" {{ old('media_type') === 'vimeo' ? 'checked' : '' }}
                                class="sr-only peer">
                            <div class="flex-1 bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-3 peer-checked:border-emerald-500 peer-checked:bg-emerald-500/10 transition">
                                <p class="text-sm font-medium text-slate-200">🎥 Vimeo</p>
                                <p class="text-xs text-slate-500 mt-0.5">Vimeo video link</p>
                            </div>
                        </label>
                        <label class="relative flex items-center group cursor-pointer">
                            <input type="radio" name="media_type" value="direct_file" {{ old('media_type') === 'direct_file' ? 'checked' : '' }}
                                class="sr-only peer">
                            <div class="flex-1 bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-3 peer-checked:border-emerald-500 peer-checked:bg-emerald-500/10 transition">
                                <p class="text-sm font-medium text-slate-200">📁 Direct File</p>
                                <p class="text-xs text-slate-500 mt-0.5">MP4 or WebM URL</p>
                            </div>
                        </label>
                        <label class="relative flex items-center group cursor-pointer">
                            <input type="radio" name="media_type" value="s3_cloudfront" {{ old('media_type') === 's3_cloudfront' ? 'checked' : '' }}
                                class="sr-only peer">
                            <div class="flex-1 bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-3 peer-checked:border-emerald-500 peer-checked:bg-emerald-500/10 transition">
                                <p class="text-sm font-medium text-slate-200">☁️ S3/CloudFront</p>
                                <p class="text-xs text-slate-500 mt-0.5">S3 CloudFront URL</p>
                            </div>
                        </label>
                        <label class="relative flex items-center group cursor-pointer">
                            <input type="radio" name="media_type" value="hls_stream" {{ old('media_type') === 'hls_stream' ? 'checked' : '' }}
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
                    <input type="url" name="media_url" id="videoUrlInput" value="{{ old('media_url') }}"
                        class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition"
                        placeholder="https://youtube.com/watch?v=... or https://example.com/video.mp4" />
                    <p id="mediaUrlHelp" class="text-xs text-slate-500 mt-1">YouTube URL or direct link to MP4/WebM video</p>
                    @error('media_url')<p class="text-xs text-red-400 mt-1">{{ $message }}</p>@enderror
                </div>

                <div id="uploadVideoField">
                    <label id="uploadVideoLabel" class="block text-sm font-medium text-slate-300 mb-1.5">Upload Video File</label>
                    <input type="file" name="video" id="videoFileInput" accept="{{ $videoAcceptTypes }}"
                        class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm file:mr-3 file:rounded file:border-0 file:bg-emerald-500 file:px-3 file:py-1.5 file:text-slate-900 file:font-medium hover:file:bg-emerald-400" />
                    <div class="mt-2">
                        <p id="uploadVideoHelp" class="text-xs text-slate-500">Optional alternative to URL upload (max {{ config('u9itus.max_video_size_mb', 1024) }}MB). If both are provided, uploaded file takes priority.</p>
                        <p class="text-xs text-amber-600/80 mt-2 bg-amber-950/30 border border-amber-800/40 rounded px-2 py-1.5">💡 <strong>Tip:</strong> For best upload reliability with large files, use H.264-encoded MP4 format. Files larger than 100 MB may take longer to process.</p>
                    </div>
                    @error('video')<p class="text-xs text-red-400 mt-1">{{ $message }}</p>@enderror
                </div>
                
                {{-- Video Preview Section --}}
                <div id="videoPreviewSection" class="hidden">
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
                    <input type="number" name="media_duration" value="{{ old('media_duration', config('u9itus.min_video_duration', 30)) }}"
                        min="{{ config('u9itus.min_video_duration', 30) }}"
                        max="{{ config('u9itus.max_video_duration', 300) }}"
                        class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition" />
                    <p class="text-xs text-slate-500 mt-1">Defaults to {{ config('u9itus.min_video_duration', 30) }}s. System will auto-detect from video metadata if available ({{ config('u9itus.min_video_duration', 30) }}–{{ config('u9itus.max_video_duration', 300) }}s)</p>
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
                    @error('live_feed_url')<p class="text-xs text-red-400 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1.5">Scheduled Start Time <span class="text-red-400">*</span></label>
                    <input type="datetime-local" name="live_scheduled_at" value="{{ old('live_scheduled_at') }}"
                        class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition" />
                    @error('live_scheduled_at')<p class="text-xs text-red-400 mt-1">{{ $message }}</p>@enderror
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
                    @error('total_views_requested')<p class="text-xs text-red-400 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1.5">Total Budget (USD) <span class="text-red-400">*</span></label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">$</span>
                        <input type="number" name="total_budget" id="budgetInput" value="{{ old('total_budget', '100.00') }}"
                            min="{{ number_format($revenuePerView * 10, 2, '.', '') }}" step="0.01" required
                            class="w-full bg-slate-900/60 border border-slate-700 rounded-lg pl-7 pr-4 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition" />
                    </div>
                    @error('total_budget')<p class="text-xs text-red-400 mt-1">{{ $message }}</p>@enderror
                </div>
            </div>
        </div>

        {{-- Sprint 3: Topics & Q&A Section --}}
        <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-6 space-y-6">
            <div>
                <h2 class="text-sm font-semibold text-slate-200 mb-1">Virtual Town Hall Content <span class="text-slate-500 font-normal text-xs">(optional)</span></h2>
                <p class="text-xs text-slate-500 mt-1">Add topics, Q&A pairs, engagement surveys, and media customization to create an interactive town hall experience.</p>
            </div>

            {{-- Topics Multi-Select --}}
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
                                    {{ is_array(old('topic_ids')) && in_array($topic->id, old('topic_ids')) ? 'checked' : '' }}>
                                <span class="text-lg mr-2">{{ $topic->icon }}</span>
                                <span class="text-sm text-slate-200">{{ $topic->name }}</span>
                            </label>
                            @endforeach
                        </div>
                    </div>
                </div>
                <div id="selectedTopicsDisplay" class="mt-3 flex flex-wrap gap-2">
                    {{-- Selected topics will be displayed here --}}
                </div>
                <p class="text-xs text-slate-500 mt-2">Help voters discover your campaign by assigning relevant topics</p>
                @error('topic_ids')<p class="text-xs text-red-400 mt-1">{{ $message }}</p>@enderror
            </div>

            {{-- Intro Text --}}
            <div class="border-t border-slate-700/50 pt-6">
                <label class="block text-sm font-medium text-slate-300 mb-2.5">Politician's Opening Statement <span class="text-slate-500 font-normal text-xs">(max 1000 chars)</span></label>
                <textarea name="intro_text" maxlength="1000" rows="3"
                    class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition resize-none"
                    placeholder="Start your town hall with a brief introduction or statement...">{{ old('intro_text') }}</textarea>
                <p class="text-xs text-slate-500 mt-1"><span id="introCharCount">0</span>/1000 characters</p>
                @error('intro_text')<p class="text-xs text-red-400 mt-1">{{ $message }}</p>@enderror
            </div>

            {{-- Q&A Section --}}
            <div class="border-t border-slate-700/50 pt-6">
                <div class="flex items-center justify-between mb-3">
                    <label class="block text-sm font-medium text-slate-300">Questions & Answers</label>
                    <button type="button" id="addQABtn"
                        class="text-xs text-emerald-400 hover:text-emerald-300 flex items-center gap-1 transition">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        Add Q&A Pair
                    </button>
                </div>
                <div id="qaItemsContainer" class="space-y-3">
                    {{-- Q&A pairs will be added here dynamically --}}
                    @php
                        $existingQA = old('qa_items') ? (is_array(old('qa_items')) ? old('qa_items') : json_decode(old('qa_items'), true)) : [];
                    @endphp
                    @forelse($existingQA as $index => $item)
                    <div class="qa-item bg-slate-900/60 border border-slate-700 rounded-lg overflow-hidden">
                        <button type="button" class="w-full qa-toggle px-4 py-3 flex items-center justify-between hover:bg-slate-700/50 transition text-left">
                            <span class="text-sm font-medium text-slate-200" id="qa-label-{{ $index }}">
                                Q{{ $index + 1 }}: {{ \Illuminate\Support\Str::limit($item['question'] ?? '', 50) }}
                            </span>
                            <svg class="w-4 h-4 text-slate-400 transition-transform qa-chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>
                        <div class="qa-content hidden px-4 pb-3 space-y-2 border-t border-slate-700">
                            <input type="hidden" name="qa_items[{{ $index }}][question]" value="{{ $item['question'] ?? '' }}">
                            <input type="hidden" name="qa_items[{{ $index }}][answer]" value="{{ $item['answer'] ?? '' }}">
                            <div>
                                <label class="block text-xs font-medium text-slate-300 mb-1">Question</label>
                                <textarea class="w-full bg-slate-800/60 border border-slate-600 rounded px-3 py-2 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-emerald-500 resize-none qa-question" rows="2" placeholder="Enter question...">{{ $item['question'] ?? '' }}</textarea>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-300 mb-1">Answer</label>
                                <textarea class="w-full bg-slate-800/60 border border-slate-600 rounded px-3 py-2 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-emerald-500 resize-none qa-answer" rows="3" placeholder="Enter answer...">{{ $item['answer'] ?? '' }}</textarea>
                            </div>
                            <button type="button" class="text-xs text-red-400 hover:text-red-300 flex items-center gap-1 mt-2 qa-remove">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                                Remove
                            </button>
                        </div>
                    </div>
                    @empty
                    <p class="text-xs text-slate-500 text-center py-3">No Q&A pairs yet. Click "Add Q&A Pair" to get started.</p>
                    @endforelse
                </div>
                @error('qa_items')<p class="text-xs text-red-400 mt-2">{{ $message }}</p>@enderror
            </div>

            {{-- Engagement Survey Section --}}
            <div class="border-t border-slate-700/50 pt-6">
                <label class="block text-sm font-medium text-slate-300 mb-2.5">Post-View Survey <span class="text-slate-500 font-normal text-xs">(optional)</span></label>
                <p class="text-xs text-slate-500 mb-3">Ask voters a single question after they watch. Responses help with engagement metrics.</p>
                
                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-1.5">Survey Question</label>
                        <input type="text" name="engagement_survey[question]" maxlength="200"
                            value="{{ old('engagement_survey.question', old('engagement_survey.question')) }}"
                            class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition"
                            placeholder="e.g., Do you support this proposal?" />
                    </div>

                    <div id="surveyOptionsContainer">
                        <label class="block text-xs font-medium text-slate-300 mb-1.5">Answer Options <span class="text-slate-500 font-normal">(minimum 2)</span></label>
                        <div class="space-y-2">
                            @php
                                $existingSurvey = old('engagement_survey.options') ? (is_array(old('engagement_survey.options')) ? old('engagement_survey.options') : json_decode(old('engagement_survey.options'), true)) : [];
                            @endphp
                            @for($i = 0; $i < max(2, count($existingSurvey)); $i++)
                                @php $option = $existingSurvey[$i] ?? null; @endphp
                            <div class="flex gap-2 items-end">
                                <div class="flex-1">
                                    <input type="text" name="engagement_survey[options][{{ $i }}][text]"
                                        value="{{ $option['text'] ?? '' }}"
                                        maxlength="100"
                                        class="w-full bg-slate-900/60 border border-slate-700 rounded px-3 py-2 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-emerald-500 transition survey-option-text"
                                        placeholder="Option {{ $i + 1 }}" />
                                </div>
                                <input type="hidden" name="engagement_survey[options][{{ $i }}][value]" value="{{ $option['value'] ?? chr(65 + $i) }}">
                                <button type="button" class="text-xs text-slate-400 hover:text-red-400 transition remove-survey-option {{ $i < 2 ? 'invisible' : '' }}">
                                    Remove
                                </button>
                            </div>
                            @endfor
                        </div>
                        <button type="button" id="addSurveyOptionBtn"
                            class="mt-2 text-xs text-emerald-400 hover:text-emerald-300 flex items-center gap-1 transition">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                            Add Option
                        </button>
                    </div>
                </div>
                @error('engagement_survey')<p class="text-xs text-red-400 mt-2">{{ $message }}</p>@enderror
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
                                    {{ is_array(old('target_states')) && in_array($abbr, old('target_states')) ? 'checked' : '' }}>
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
                                    {{ is_array(old('target_cities')) && in_array($cityData['display'], old('target_cities')) ? 'checked' : '' }}>
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
                    Each repeat view costs <strong>${{ number_format($revenuePerView, 2) }}</strong> and pays the voter <strong>${{ number_format((float) \App\Services\PlatformSettingsService::get('viewer_payout_per_view', null, 0.25), 2) }}</strong> — ensure your budget covers the additional views.
                </p>
            </div>
        </div>

        {{-- Submit --}}
        <div class="flex gap-3">
            <button type="button" id="saveDraftBtn"
                class="px-6 py-2.5 text-sm font-medium text-emerald-400 hover:text-emerald-300 bg-emerald-500/10 hover:bg-emerald-500/20 border border-emerald-500/30 rounded-lg transition flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/>
                </svg>
                Save as Draft
            </button>
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

    {{-- Auto-save indicator --}}
    <div id="autoSaveIndicator" class="fixed bottom-4 right-4 bg-slate-800 border border-slate-700 text-slate-300 px-4 py-2 rounded-lg shadow-lg text-xs opacity-0 transition-opacity pointer-events-none">
        <span class="flex items-center gap-2">
            <svg class="w-3 h-3 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            Progress auto-saved
        </span>
    </div>

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
const campaignForm    = document.getElementById('campaignForm');
const saveDraftBtn    = document.getElementById('saveDraftBtn');
const autoSaveIndicator = document.getElementById('autoSaveIndicator');
const mediaTypeInputs = Array.from(document.querySelectorAll('input[name="media_type"]'));
const mediaUrlField = document.getElementById('mediaUrlField');
const mediaUrlLabel = document.getElementById('mediaUrlLabel');
const mediaUrlHelp = document.getElementById('mediaUrlHelp');
const uploadVideoField = document.getElementById('uploadVideoField');
const uploadVideoLabel = document.getElementById('uploadVideoLabel');
const uploadVideoHelp = document.getElementById('uploadVideoHelp');

// ── LocalStorage Draft Management ───────────────────────────────────
const DRAFT_KEY = 'campaign_draft_{{ Auth::user()->politician->id ?? "temp" }}';
let autoSaveTimeout;

// Save form data to localStorage
function saveDraftToLocalStorage() {
    const formData = new FormData(campaignForm);
    const data = {};
    for (const [key, value] of formData.entries()) {
        // Never persist framework/security fields in local drafts.
        if (key === '_token' || key === '_method') continue;

        if (key.includes('[]')) {
            // Handle array fields
            const baseKey = key.replace('[]', '');
            if (!data[baseKey]) data[baseKey] = [];
            data[baseKey].push(value);
        } else {
            data[key] = value;
        }
    }
    
    localStorage.setItem(DRAFT_KEY, JSON.stringify({
        data: data,
        timestamp: Date.now()
    }));
    
    // Show indicator
    autoSaveIndicator.style.opacity = '1';
    setTimeout(() => {
        autoSaveIndicator.style.opacity = '0';
    }, 2000);
}

// Restore form data from localStorage
function restoreDraftFromLocalStorage() {
    const saved = localStorage.getItem(DRAFT_KEY);
    if (!saved) return;
    
    try {
        const { data, timestamp } = JSON.parse(saved);
        
        // Check if draft is less than 7 days old
        const daysSaved = (Date.now() - timestamp) / (1000 * 60 * 60 * 24);
        if (daysSaved > 7) {
            localStorage.removeItem(DRAFT_KEY);
            return;
        }
        
        // Restore form fields
        for (const [key, value] of Object.entries(data)) {
            if (key === '_token' || key === '_method') continue;

            // Handle checkbox arrays (e.g., target_states, target_cities)
            if (Array.isArray(value)) {
                value.forEach(val => {
                    const checkbox = campaignForm.querySelector(`[name="${key}[]"][value="${val}"]`);
                    if (checkbox) checkbox.checked = true;
                });
            } else {
                const checkbox = campaignForm.querySelector(`input[type="checkbox"][name="${key}"]`);
                if (checkbox) {
                    checkbox.checked = value === '1' || value === 'true' || value === true;
                    continue;
                }

                const input = campaignForm.querySelector(`[name="${key}"]`);
                if (input) {
                    input.value = value;
                }
            }
        }
        
        // Trigger change events to update UI
        campaignType.dispatchEvent(new Event('change'));
        viewsInput.dispatchEvent(new Event('input'));
        if (document.getElementById('allowRepeatViews').checked) {
            syncRepeatPanel();
        }
        if (videoUrlInput.value) {
            videoUrlInput.dispatchEvent(new Event('input'));
        }
        
        // Update geographic dropdown displays
        updateStatesDisplay();
        updateCitiesDisplay();
        
        console.log('Draft restored from localStorage');
    } catch (error) {
        console.error('Failed to restore draft:', error);
        localStorage.removeItem(DRAFT_KEY);
    }
}

// Auto-save every 10 seconds when user is typing
function scheduleAutoSave() {
    clearTimeout(autoSaveTimeout);
    autoSaveTimeout = setTimeout(saveDraftToLocalStorage, 10000);
}

// Save Draft button handler
saveDraftBtn.addEventListener('click', function() {
    // Change form action to draft endpoint
    const originalAction = campaignForm.action;
    campaignForm.action = '{{ route('politician.campaigns.save-draft') }}';
    
    // Remove required attributes temporarily
    const requiredFields = campaignForm.querySelectorAll('[required]');
    requiredFields.forEach(field => field.removeAttribute('required'));
    
    // Submit form
    campaignForm.submit();
    
    // Clear localStorage after successful save
    localStorage.removeItem(DRAFT_KEY);
});

// Attach auto-save to form inputs
campaignForm.querySelectorAll('input, textarea, select').forEach(input => {
    input.addEventListener('input', scheduleAutoSave);
    input.addEventListener('change', scheduleAutoSave);
});

// Clear draft from localStorage on successful form submission
campaignForm.addEventListener('submit', function(e) {
    // Only clear if it's the main submit (not draft save)
    if (!this.action.includes('save-draft')) {
        localStorage.removeItem(DRAFT_KEY);
    }
});

// Restore draft on page load (only if no old() data)
window.addEventListener('DOMContentLoaded', () => {
    const hasOldData = {{ old('title') ? 'true' : 'false' }};
    if (!hasOldData) {
        restoreDraftFromLocalStorage();
    }
});

// ── End Draft Management ─────────────────────────────────────────────

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
    syncMediaSourceUI();
});

function getSelectedMediaType() {
    return mediaTypeInputs.find((input) => input.checked)?.value || 'youtube';
}

// Repeat Viewing toggle
const allowRepeatCheckbox = document.getElementById('allowRepeatViews');
const repeatOptions = document.getElementById('repeatViewingOptions');
function syncRepeatPanel() {
    repeatOptions.classList.toggle('hidden', !allowRepeatCheckbox.checked);
}
allowRepeatCheckbox.addEventListener('change', syncRepeatPanel);
syncRepeatPanel();

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
const ytContainer = document.getElementById('ytPreviewContainer');
const nativePlayer = document.getElementById('nativePreviewPlayer');
const nativeSource = document.getElementById('nativePreviewSource');
const placeholder = document.getElementById('previewPlaceholder');
const videoFileInput = document.getElementById('videoFileInput');

function resetVideoPreview() {
    if (ytPreviewPlayer) {
        ytPreviewPlayer.destroy();
        ytPreviewPlayer = null;
    }

    ytContainer.classList.add('hidden');
    nativePlayer.classList.add('hidden');
    placeholder.classList.remove('hidden');
}

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
    // Clean up existing players
    if (ytPreviewPlayer) {
        ytPreviewPlayer.destroy();
        ytPreviewPlayer = null;
    }
    nativePlayer.classList.add('hidden');
    ytContainer.classList.remove('hidden');
    placeholder.classList.add('hidden');

    // Load YouTube IFrame API if not already loaded
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

// Initialize native video player for direct URLs
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

function syncVideoPreview() {
    const mediaType = getSelectedMediaType();
    const isDirectFile = mediaType === 'direct_file';
    const url = videoUrlInput.value.trim();
    const file = videoFileInput.files && videoFileInput.files[0];

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

function syncMediaSourceUI() {
    if (campaignType.value === 'live_feed') {
        return;
    }

    const mediaType = getSelectedMediaType();
    const isDirectFile = mediaType === 'direct_file';

    mediaUrlField.classList.remove('hidden');
    uploadVideoField.classList.toggle('hidden', !isDirectFile);
    videoUrlInput.disabled = false;
    videoFileInput.disabled = !isDirectFile;

    if (mediaType === 'youtube') {
        mediaUrlLabel.innerHTML = 'YouTube URL <span class="text-red-400">*</span>';
        mediaUrlInput.placeholder = 'https://youtube.com/watch?v=...';
        mediaUrlHelp.textContent = 'Paste a YouTube watch or share URL.';
    } else if (mediaType === 'vimeo') {
        mediaUrlLabel.innerHTML = 'Vimeo URL <span class="text-red-400">*</span>';
        mediaUrlInput.placeholder = 'https://vimeo.com/...';
        mediaUrlHelp.textContent = 'Paste a Vimeo video URL.';
    } else if (mediaType === 'hls_stream') {
        mediaUrlLabel.innerHTML = 'HLS Playlist URL <span class="text-red-400">*</span>';
        mediaUrlInput.placeholder = 'https://example.com/stream.m3u8';
        mediaUrlHelp.textContent = 'Paste an HLS playlist URL ending in .m3u8.';
    } else if (mediaType === 's3_cloudfront') {
        mediaUrlLabel.innerHTML = 'CloudFront or S3 URL <span class="text-red-400">*</span>';
        mediaUrlInput.placeholder = 'https://cdn.example.com/video.mp4';
        mediaUrlHelp.textContent = 'Paste a public CloudFront or S3 asset URL.';
    } else {
        mediaUrlLabel.innerHTML = 'Direct File URL <span class="text-slate-500 font-normal">(optional)</span>';
        mediaUrlInput.placeholder = 'https://example.com/video.mp4';
        mediaUrlHelp.textContent = 'Optional direct file URL. Or upload a file below.';
    }

    uploadVideoLabel.textContent = 'Upload Video File';
    uploadVideoHelp.textContent = 'Choose MP4, WebM, or MOV on iOS. Upload takes priority over URL.';

    if (!isDirectFile && videoFileInput.files && videoFileInput.files.length > 0) {
        videoFileInput.value = '';
    }

    syncVideoPreview();
}

// Handle local file uploads preview
if (videoFileInput) {
    videoFileInput.addEventListener('change', () => {
        const file = videoFileInput.files && videoFileInput.files[0];
        if (!file) {
            return;
        }

        // Keep source selection unambiguous for both preview and submit.
        if (videoUrlInput) {
            videoUrlInput.value = '';
        }

        syncVideoPreview();
    });
}

// Handle video URL changes
videoUrlInput.addEventListener('input', () => {
    const url = videoUrlInput.value.trim();

    // Keep source selection unambiguous for both preview and submit.
    if (url && videoFileInput && videoFileInput.files && videoFileInput.files.length > 0) {
        videoFileInput.value = '';
    }

    syncVideoPreview();
});

mediaTypeInputs.forEach((input) => input.addEventListener('change', syncMediaSourceUI));

// Test preview button
function testVideoPreview() {
    if (ytPreviewPlayer && ytPreviewPlayer.playVideo) {
        ytPreviewPlayer.playVideo();
    } else if (!nativePlayer.classList.contains('hidden')) {
        nativePlayer.play();
    }
}

// Trigger preview on page load if URL already exists
window.addEventListener('DOMContentLoaded', () => {
    syncMediaSourceUI();
});

// ── Sprint 3: Q&A & Survey Management ──────────────────────────────

// Intro text character counter
const introText = document.querySelector('textarea[name="intro_text"]');
const introCharCount = document.getElementById('introCharCount');
if (introText) {
    introText.addEventListener('input', () => {
        introCharCount.textContent = introText.value.length;
    });
    // Initialize on page load
    introCharCount.textContent = introText.value.length;
}

// Topics dropdown management
const topicsBtn = document.getElementById('topicsDropdownBtn');
const topicsDropdown = document.getElementById('topicsDropdown');
const topicsSelectedText = document.getElementById('topicsSelectedText');
const topicCheckboxes = document.querySelectorAll('.topic-checkbox');
const selectedTopicsDisplay = document.getElementById('selectedTopicsDisplay');
const MAX_TOPICS = 5;

topicsBtn.addEventListener('click', (e) => {
    e.stopPropagation();
    topicsDropdown.classList.toggle('hidden');
});

function updateTopicsDisplay() {
    const checked = Array.from(topicCheckboxes).filter(cb => cb.checked);
    
    // Prevent selecting more than MAX_TOPICS
    if (checked.length > MAX_TOPICS) {
        event.target.checked = false;
        return;
    }
    
    if (checked.length === 0) {
        topicsSelectedText.textContent = 'Select topics...';
        topicsSelectedText.classList.add('text-slate-400');
    } else {
        topicsSelectedText.textContent = checked.length + ' topic' + (checked.length > 1 ? 's' : '') + ' selected';
        topicsSelectedText.classList.remove('text-slate-400');
    }
    
    // Display selected topics
    selectedTopicsDisplay.innerHTML = checked.map(cb => {
        const label = cb.closest('label').textContent.trim();
        const [icon, ...nameParts] = label.split(' ');
        const name = nameParts.join(' ');
        return `<div class="inline-flex items-center gap-2 bg-emerald-500/20 border border-emerald-500/40 rounded-full px-3 py-1.5 text-xs text-emerald-300">
            <span class="text-sm">${icon}</span>
            <span>${name}</span>
        </div>`;
    }).join('');
}

topicCheckboxes.forEach(cb => {
    cb.addEventListener('change', updateTopicsDisplay);
});

document.addEventListener('click', (e) => {
    if (!e.target.closest('#topicsDropdownBtn') && !e.target.closest('#topicsDropdown')) {
        topicsDropdown.classList.add('hidden');
    }
});

topicsDropdown.addEventListener('click', (e) => e.stopPropagation());
updateTopicsDisplay();

// Q&A Accordion Management
const addQABtn = document.getElementById('addQABtn');
const qaContainer = document.getElementById('qaItemsContainer');
let qaIndex = document.querySelectorAll('.qa-item').length;

function createQAItem(index) {
    const div = document.createElement('div');
    div.className = 'qa-item bg-slate-900/60 border border-slate-700 rounded-lg overflow-hidden';
    div.innerHTML = `
        <button type="button" class="qa-toggle w-full px-4 py-3 flex items-center justify-between hover:bg-slate-700/50 transition text-left">
            <span class="text-sm font-medium text-slate-200" id="qa-label-${index}">New Q&A Pair</span>
            <svg class="w-4 h-4 text-slate-400 transition-transform qa-chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
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
            <button type="button" class="text-xs text-red-400 hover:text-red-300 flex items-center gap-1 mt-2 qa-remove">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                </svg>
                Remove
            </button>
        </div>
    `;
    
    // Add event listeners
    const toggle = div.querySelector('.qa-toggle');
    const content = div.querySelector('.qa-content');
    const chevron = div.querySelector('.qa-chevron');
    const removeBtn = div.querySelector('.qa-remove');
    const questionField = div.querySelector('.qa-question');
    const answerField = div.querySelector('.qa-answer');
    const label = div.querySelector(`#qa-label-${index}`);
    
    toggle.addEventListener('click', () => {
        content.classList.toggle('hidden');
        chevron.classList.toggle('rotate-180');
    });
    
    // Update label when fields change
    const updateLabel = () => {
        const q = questionField.value || 'New Q&A Pair';
        label.textContent = `Q${index + 1}: ` + (q.length > 50 ? q.substring(0, 50) + '...' : q);
    };
    
    questionField.addEventListener('input', updateLabel);
    questionField.addEventListener('blur', updateLabel);
    
    // Remove button
    removeBtn.addEventListener('click', () => {
        div.remove();
        renderQAFieldsFromDOM();
    });
    
    return div;
}

function renderQAFieldsFromDOM() {
    // Regenerate hidden form fields from edit textareas
    const items = document.querySelectorAll('.qa-item');
    let html = '';
    
    items.forEach((item, idx) => {
        const question = item.querySelector('.qa-question').value;
        const answer = item.querySelector('.qa-answer').value;
        html += `<input type="hidden" name="qa_items[${idx}][question]" value="${escapeHtml(question)}">`;
        html += `<input type="hidden" name="qa_items[${idx}][answer]" value="${escapeHtml(answer)}">`;
    });
    
    // Find or create a container for hidden fields
    let hiddenContainer = document.getElementById('qa-hidden-fields');
    if (!hiddenContainer) {
        hiddenContainer = document.createElement('div');
        hiddenContainer.id = 'qa-hidden-fields';
        hiddenContainer.style.display = 'none';
        campaignForm.appendChild(hiddenContainer);
    }
    hiddenContainer.innerHTML = html;
}

function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, m => map[m]);
}

addQABtn.addEventListener('click', () => {
    const newItem = createQAItem(qaIndex);
    qaContainer.querySelector('p.text-xs') && qaContainer.querySelector('p.text-xs').remove();
    qaContainer.appendChild(newItem);
    qaIndex++;
    newItem.querySelector('.qa-toggle').click(); // Auto-open new item
    newItem.querySelector('.qa-question').focus();
    renderQAFieldsFromDOM();
});

// Initialize Q&A toggle buttons for existing items
document.querySelectorAll('.qa-toggle').forEach(toggle => {
    toggle.addEventListener('click', () => {
        const content = toggle.nextElementSibling;
        const chevron = toggle.querySelector('.qa-chevron');
        content.classList.toggle('hidden');
        chevron.classList.toggle('rotate-180');
    });
});

// Initialize Q&A remove buttons for existing items
document.querySelectorAll('.qa-remove').forEach(btn => {
    btn.addEventListener('click', () => {
        btn.closest('.qa-item').remove();
        renderQAFieldsFromDOM();
    });
});

// Track updates to existing Q&A items
document.querySelectorAll('.qa-question, .qa-answer').forEach(field => {
    field.addEventListener('input', () => {
        renderQAFieldsFromDOM();
        const label = field.closest('.qa-item').querySelector('[id^="qa-label-"]');
        if (field.classList.contains('qa-question') && label) {
            const idx = label.id.match(/\d+/)[0];
            const q = field.value || 'Q&A Pair';
            label.textContent = `Q${parseInt(idx) + 1}: ` + (q.length > 50 ? q.substring(0, 50) + '...' : q);
        }
    });
});

// Survey Option Management
const addSurveyOptionBtn = document.getElementById('addSurveyOptionBtn');
const surveyOptionsContainer = document.getElementById('surveyOptionsContainer');

if (addSurveyOptionBtn) {
    addSurveyOptionBtn.addEventListener('click', () => {
        const optionCount = surveyOptionsContainer.querySelectorAll('.flex.gap-2').length;
        const charCode = 65 + optionCount;
        
        const div = document.createElement('div');
        div.className = 'flex gap-2 items-end';
        div.innerHTML = `
            <div class="flex-1">
                <input type="text" name="engagement_survey[options][${optionCount}][text]"
                    maxlength="100"
                    class="w-full bg-slate-900/60 border border-slate-700 rounded px-3 py-2 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-emerald-500 transition survey-option-text"
                    placeholder="Option ${optionCount + 1}" />
            </div>
            <input type="hidden" name="engagement_survey[options][${optionCount}][value]" value="${String.fromCharCode(charCode)}">
            <button type="button" class="text-xs text-slate-400 hover:text-red-400 transition remove-survey-option">
                Remove
            </button>
        `;
        
        surveyOptionsContainer.querySelector('[id^="surveyOptionsContainer"] > div').appendChild(div);
        
        // Add remove listener
        div.querySelector('.remove-survey-option').addEventListener('click', () => {
            div.remove();
        });
    });
}

// Remove survey option buttons
document.querySelectorAll('.remove-survey-option').forEach(btn => {
    btn.addEventListener('click', () => {
        btn.closest('.flex.gap-2').remove();
    });
});

// Final form submission - sync Q&A fields from textarea values
campaignForm.addEventListener('submit', (e) => {
    renderQAFieldsFromDOM();
});
</script>
@endpush
