@extends('layouts.voter')

@section('title', 'Watch: ' . $campaign->title)

@section('content')
<div class="max-w-3xl mx-auto px-4 sm:px-6 py-8">

    {{-- Top Navigation --}}
    <div class="mb-5 flex items-center justify-between">
        <a href="{{ route('voter.ad-room') }}" class="inline-flex items-center gap-1.5 text-slate-400 hover:text-emerald-400 text-sm transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            Back to Ad Room
        </a>
    </div>

    {{-- Campaign Header --}}
    <div class="mb-5 p-5 bg-slate-800/60 border border-slate-700/60 rounded-2xl">
        <h1 class="text-xl font-bold text-white leading-snug">{{ $campaign->title }}</h1>
        <p class="text-slate-400 mt-1.5 text-sm">
            <span class="text-slate-500">Sponsored by</span>
            <span class="text-amber-400 font-medium">{{ $campaign->citizen->business_name ?? $campaign->citizen->full_name ?? 'Community Sponsor' }}</span>
            @if(!empty($campaign->citizen->website_url))
                <span class="text-slate-600">&middot;</span>
                <a href="{{ $campaign->citizen->website_url }}" target="_blank" rel="noopener noreferrer nofollow" class="text-emerald-400 hover:text-emerald-300 underline">
                    Visit site
                </a>
            @endif
        </p>
        @if(!empty($campaign->citizen->bio))
        <p class="text-slate-400 text-sm mt-3 leading-relaxed">{{ $campaign->citizen->bio }}</p>
        @endif
    </div>

    @if(!empty($campaign->video_blurb) || !empty($campaign->message_summary))
    <div class="mb-5 p-4 bg-slate-800/45 border border-slate-700/60 rounded-2xl">
        <p class="text-[11px] uppercase tracking-wide text-slate-500 mb-2">About This Video</p>
        <div class="prose prose-invert prose-sm max-w-none text-slate-200 [&_a]:text-emerald-400 [&_a:hover]:text-emerald-300 [&_img]:max-w-full [&_img]:h-auto [&_img]:rounded-lg">
            @if(!empty($campaign->video_blurb))
                {!! $campaign->video_blurb !!}
            @else
                {{ $campaign->message_summary }}
            @endif
        </div>
    </div>
    @endif

    {{-- Earn Banner --}}
    <div class="bg-emerald-900/30 border border-emerald-500/25 rounded-2xl px-5 py-3.5 mb-5 flex items-center gap-3">
        <div class="w-8 h-8 rounded-full bg-emerald-500/20 flex items-center justify-center shrink-0">
            <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
        </div>
        <div>
            <p class="text-emerald-300 text-sm font-medium">
                Earn <strong class="text-emerald-400 text-base">${{ number_format($payout, 2) }}</strong>
                for watching at least <strong class="text-white">{{ $mustWatch }}%</strong> of this message
            </p>
            <p class="text-slate-500 text-xs mt-0.5">Do not skip — rewards require continuous viewing</p>
        </div>
    </div>

    {{-- Video Player --}}
    @php
        $videoId  = null;
        $vimeoId  = null;
        $mediaUrl = $campaign->media_url ?? '';
        $mediaType = (string) ($campaign->media_type ?? 'youtube');
        $isHlsUrl = preg_match('/\.m3u8(\?.*)?$/i', $mediaUrl) === 1;
        $nativeSourceType = 'video/mp4';

        if (preg_match('/\.(webm)(\?.*)?$/i', $mediaUrl)) {
            $nativeSourceType = 'video/webm';
        } elseif (preg_match('/\.(mov|qt)(\?.*)?$/i', $mediaUrl)) {
            $nativeSourceType = 'video/quicktime';
        }

        if (preg_match('/youtu\.be\/([a-zA-Z0-9_-]+)/', $mediaUrl, $_m))         { $videoId = $_m[1]; }
        elseif (preg_match('/[?&]v=([a-zA-Z0-9_-]+)/', $mediaUrl, $_m))         { $videoId = $_m[1]; }
        elseif (preg_match('/\/embed\/([a-zA-Z0-9_-]+)/', $mediaUrl, $_m))     { $videoId = $_m[1]; }
        if (preg_match('/vimeo\.com\/(?:video\/)?(\d+)/', $mediaUrl, $_m))   { $vimeoId = $_m[1]; }

        $playerMode = 'native';
        if ($mediaType === 'youtube' && ! empty($videoId)) {
            $playerMode = 'youtube';
        } elseif ($mediaType === 'vimeo' && ! empty($vimeoId)) {
            $playerMode = 'vimeo';
        } elseif ($mediaType === 'hls_stream' && ! empty($mediaUrl)) {
            $playerMode = 'hls';
        } elseif ($isHlsUrl) {
            $playerMode = 'hls';
        } elseif (! empty($videoId)) {
            $playerMode = 'youtube';
        } elseif (! empty($vimeoId)) {
            $playerMode = 'vimeo';
        }
    @endphp

    <div class="relative bg-black rounded-2xl overflow-hidden shadow-2xl ring-1 ring-slate-700/50" id="player-wrapper">
        @if($playerMode === 'youtube')
            <div id="yt-player-container" class="w-full aspect-video"></div>
        @elseif($playerMode === 'vimeo')
            <div id="vimeo-player-container" class="w-full aspect-video"></div>
        @else
            <video
                id="ad-video"
                class="w-full aspect-video"
                controlsList="nodownload nofullscreen noplaybackrate"
                disablePictureInPicture
                disableRemotePlayback
                playsinline
                preload="none"
                oncontextmenu="return false;"
            >
                @if($campaign->media_url && $playerMode !== 'hls')
                    <source src="{{ $campaign->media_url }}" type="{{ $nativeSourceType }}">
                @endif
                Your browser does not support HTML5 video.
            </video>
        @endif

        {{-- Fraud Prevention: blocker overlay prevents direct interaction --}}
        <div id="control-blocker" class="hidden absolute inset-0 z-10" style="pointer-events: auto; cursor: not-allowed;"></div>

        {{-- Play overlay --}}
        <div id="play-overlay" class="absolute inset-0 flex flex-col items-center justify-center bg-black/60 cursor-pointer">
            <div class="w-20 h-20 rounded-full bg-emerald-500/20 border-2 border-emerald-400 flex items-center justify-center mb-4">
                <svg class="w-10 h-10 text-emerald-400 ml-1" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M8 5v14l11-7z"/>
                </svg>
            </div>
            <p class="text-white font-semibold">Click to Play &amp; Earn</p>
            <p id="duration-hint" class="text-slate-400 text-sm mt-1">{{ $duration }}s video &middot; must watch {{ $mustWatch }}%</p>
        </div>

        {{-- Progress bar --}}
        <div id="progress-track" class="absolute bottom-0 left-0 right-0 h-1 bg-slate-700">
            <div id="progress-bar" class="h-full bg-emerald-500 transition-all duration-500" style="width:0%"></div>
        </div>
    </div>

    {{-- Status message --}}
    <div id="status-msg" class="mt-5 hidden text-center py-4 px-6 rounded-2xl"></div>

    @if($campaign->allow_repeat_views)
    {{-- Watch again (only for repeat-enabled campaigns) --}}
    <div id="rewatch-wrap" class="mt-3 hidden text-center">
        <button id="rewatch-btn" type="button"
            class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-slate-800/70 hover:bg-slate-700/70 border border-slate-600 text-slate-200 hover:text-white text-sm font-medium transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0A8.003 8.003 0 014.582 15"/>
            </svg>
            Watch Again
        </button>
        <p class="mt-1.5 text-xs text-slate-500">Re-watches are recorded but only the first qualifying view earns a payout.</p>
    </div>
    @endif

    <div x-data="{
        reportModal: false,
        messageModal: false,
        submitting: false,
        toastOpen: false,
        toastMessage: '',
        toastKind: 'success',
        toastTimer: null,
        showToast(message, kind = 'success') {
            this.toastMessage = message;
            this.toastKind = kind;
            this.toastOpen = true;
            if (this.toastTimer) clearTimeout(this.toastTimer);
            this.toastTimer = setTimeout(() => { this.toastOpen = false; }, 3600);
        }
    }">
        {{-- Toast --}}
        <output x-show="toastOpen"
            x-transition:enter="transition ease-out duration-250"
            x-transition:enter-start="opacity-0 translate-y-2"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed right-4 bottom-4 z-[70] w-[min(92vw,420px)] rounded-xl border px-4 py-3 shadow-2xl"
            :class="toastKind === 'success' ? 'border-emerald-400/35 bg-slate-900/95' : 'border-rose-400/35 bg-slate-900/95'"
            aria-live="polite"
            style="display: none;">
            <div class="flex items-start gap-3">
                <div class="mt-0.5 flex-shrink-0" :class="toastKind === 'success' ? 'text-emerald-300' : 'text-rose-300'">
                    <svg x-show="toastKind === 'success'" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display: none;">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <svg x-show="toastKind !== 'success'" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display: none;">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div class="flex-1">
                    <p class="text-sm font-medium text-white" x-text="toastKind === 'success' ? 'Update' : 'Something went wrong'"></p>
                    <p class="mt-0.5 text-sm text-slate-300 leading-relaxed" x-text="toastMessage"></p>
                </div>
                <button type="button" @click="toastOpen = false" class="text-slate-500 hover:text-white transition" aria-label="Dismiss notification">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </output>

        {{-- Call to Action button (per-campaign) --}}
        @if(!empty($campaign->call_to_action_url))
        @php $ctaLabel = !empty($campaign->call_to_action_label) ? $campaign->call_to_action_label : 'Learn More'; @endphp
        <div class="mt-5 rounded-2xl border border-emerald-500/30 bg-gradient-to-r from-emerald-900/35 via-teal-900/25 to-slate-800/90 p-5 shadow-lg shadow-emerald-950/20">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="max-w-2xl">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.24em] text-emerald-300/80">Take Action</p>
                    <h2 class="mt-2 text-lg font-semibold text-white">{{ $ctaLabel }}</h2>
                    <p class="mt-1.5 text-sm leading-relaxed text-slate-300 break-all">{{ $campaign->call_to_action_url }}</p>
                </div>
                <a href="{{ $campaign->call_to_action_url }}" target="_blank" rel="noopener noreferrer nofollow"
                    class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-emerald-400/40 bg-emerald-500 px-5 py-3 text-sm font-semibold text-slate-950 shadow-md shadow-emerald-900/30 transition hover:bg-emerald-400 hover:shadow-lg hover:shadow-emerald-900/40 focus:outline-none focus:ring-2 focus:ring-emerald-300/60 sm:w-auto sm:min-w-[200px]">
                    {{ $ctaLabel }}
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                    </svg>
                </a>
            </div>
        </div>
        @endif

        {{-- Ask the Creator CTA --}}
        <div class="mt-5 rounded-2xl border border-emerald-500/30 bg-gradient-to-r from-emerald-900/35 via-teal-900/25 to-slate-800/90 p-5 shadow-lg shadow-emerald-950/20">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="max-w-2xl">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.24em] text-emerald-300/80">Have a Question?</p>
                    <h2 class="mt-2 text-lg font-semibold text-white">Ask this sponsor about the message.</h2>
                    <p class="mt-1.5 text-sm leading-relaxed text-slate-300">Get clarification on the ad, the offer, or what the sponsor wants you to know.</p>
                </div>

                <button @click="messageModal = true" type="button"
                    class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-emerald-400/40 bg-emerald-500 px-5 py-3 text-sm font-semibold text-slate-950 shadow-md shadow-emerald-900/30 transition hover:bg-emerald-400 hover:shadow-lg hover:shadow-emerald-900/40 focus:outline-none focus:ring-2 focus:ring-emerald-300/60 sm:w-auto sm:min-w-[230px]">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
                    </svg>
                    Ask the Creator
                </button>
            </div>

            <p class="mt-3 text-xs text-emerald-100/75">Your question is sent to this ad's sponsor, not platform support.</p>
        </div>

        {{-- Report Actions --}}
        <div class="mt-5">
            <div class="flex items-center justify-center gap-3">
                <button @click="reportModal = true" type="button"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-slate-800/60 hover:bg-slate-700/60 border border-slate-700/60 rounded-lg text-slate-300 hover:text-white text-sm transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    Report Issue
                </button>
            </div>
            <p class="mt-2 text-center text-xs text-slate-500">Report Issue contacts platform support.</p>

            {{-- Report Issue Modal --}}
            <div x-show="reportModal"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                class="fixed inset-0 z-50 flex items-center justify-center px-4 bg-black/60 backdrop-blur-sm"
                @click.self="reportModal = false"
                style="display: none;">

                <div class="bg-slate-800 border border-slate-700 rounded-2xl shadow-2xl max-w-md w-full p-6" @click.stop>

                    <div class="flex items-start justify-between mb-4">
                        <div>
                            <h3 class="text-lg font-bold text-white">Report Issue</h3>
                            <p class="text-sm text-slate-400 mt-0.5">Help us improve quality</p>
                        </div>
                        <button @click="reportModal = false" class="text-slate-500 hover:text-slate-300 transition">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <form @submit.prevent="
                        if (!submitting) {
                            submitting = true;
                            const formData = new FormData($event.target);
                            fetch('{{ route('voter.citizen-campaigns.report-issue', $campaign) }}', {
                                method: 'POST',
                                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' },
                                body: formData
                            })
                            .then(r => r.json())
                            .then(data => {
                                if (data.success) {
                                    showToast(data.message || 'Report submitted successfully!');
                                    reportModal = false;
                                    $event.target.reset();
                                } else {
                                    showToast(data.message || 'Failed to submit report. Please try again.', 'error');
                                }
                            })
                            .catch(() => showToast('Failed to submit report. Please try again.', 'error'))
                            .finally(() => submitting = false);
                        }
                    ">
                        <div class="mb-4">
                            <label for="issue-category" class="block text-sm font-medium text-slate-300 mb-2">Issue Category *</label>
                            <select name="issue_category" id="issue-category" required
                                class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500 transition">
                                <option value="">Select a category...</option>
                                <option value="video_not_playing">Video Not Playing</option>
                                <option value="incorrect_info">Incorrect Information</option>
                                <option value="offensive_content">Offensive Content</option>
                                <option value="other">Other</option>
                            </select>
                        </div>

                        <div class="mb-5">
                            <label for="issue-body" class="block text-sm font-medium text-slate-300 mb-2">Description (optional)</label>
                            <textarea name="body" id="issue-body" rows="3" maxlength="1000"
                                placeholder="Provide additional details about the issue..."
                                class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500 transition resize-none"></textarea>
                        </div>

                        <div class="flex gap-3">
                            <button type="button" @click="reportModal = false"
                                class="flex-1 px-4 py-2 bg-slate-700/60 hover:bg-slate-600/60 border border-slate-600 rounded-lg text-slate-300 hover:text-white text-sm transition">Cancel</button>
                            <button type="submit" :disabled="submitting"
                                class="flex-1 px-4 py-2 bg-amber-600 hover:bg-amber-500 disabled:opacity-50 disabled:cursor-not-allowed rounded-lg text-white font-medium text-sm transition">
                                <span x-show="!submitting">Submit Report</span>
                                <span x-show="submitting">Submitting...</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Ask the Creator composer modal --}}
            <div x-show="messageModal"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 translate-y-2"
                x-transition:enter-end="opacity-100 translate-y-0"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 translate-y-0"
                x-transition:leave-end="opacity-0 translate-y-2"
                @keydown.escape.window="messageModal = false"
                class="fixed inset-x-0 bottom-0 z-50 px-4 pb-4 sm:inset-auto sm:right-4 sm:bottom-4 sm:w-[min(92vw,560px)]"
                style="display: none;">

                <div class="bg-slate-800/95 border border-slate-700 rounded-2xl shadow-2xl w-full p-6 backdrop-blur" @click.stop>

                    <div class="flex items-start justify-between mb-4">
                        <div>
                            <h3 class="text-lg font-bold text-white">Ask the Creator</h3>
                            <p class="text-sm text-slate-400 mt-0.5">This message goes to the sponsor of this ad, not platform support.</p>
                            <p class="text-xs text-emerald-300/90 mt-1">Your video can keep playing while you write.</p>
                        </div>
                        <button @click="messageModal = false" class="text-slate-500 hover:text-slate-300 transition">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <form @submit.prevent="
                        if (!submitting) {
                            submitting = true;
                            const formData = new FormData($event.target);
                            fetch('{{ route('voter.citizen-campaigns.ask-question', $campaign) }}', {
                                method: 'POST',
                                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' },
                                body: formData
                            })
                            .then(r => r.json())
                            .then(data => {
                                if (data.success) {
                                    showToast(data.message || 'Question sent successfully!');
                                    messageModal = false;
                                    $event.target.reset();
                                } else {
                                    showToast(data.message || 'Failed to send question. Please try again.', 'error');
                                }
                            })
                            .catch(() => showToast('Failed to send question. Please try again.', 'error'))
                            .finally(() => submitting = false);
                        }
                    ">
                        <div class="mb-5">
                            <label for="message-body" class="block text-sm font-medium text-slate-300 mb-2">Your Question for the Creator *</label>
                            <textarea name="body" id="message-body" rows="5" maxlength="1000" required
                                placeholder="Ask the sponsor a question about this ad..."
                                class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition resize-none"></textarea>
                        </div>

                        <details class="mb-5 rounded-lg border border-slate-700/70 bg-slate-900/35 px-3 py-2">
                            <summary class="cursor-pointer list-none text-sm font-medium text-slate-300 flex items-center justify-between">
                                Add optional social/video reference
                                <span class="text-xs text-slate-500">Optional</span>
                            </summary>
                            <div class="mt-3 space-y-3">
                                <div>
                                    <label for="reference-url" class="block text-xs font-medium text-slate-400 mb-1">Reference URL</label>
                                    <input id="reference-url" type="url" name="reference_url" maxlength="2048"
                                        placeholder="https://youtube.com/... or TikTok/Facebook/Instagram/X link"
                                        class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition">
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div>
                                        <label for="reference-start-seconds" class="block text-xs font-medium text-slate-400 mb-1">Start (seconds)</label>
                                        <input id="reference-start-seconds" type="number" name="reference_start_seconds" min="0" max="86400" placeholder="0"
                                            class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition">
                                    </div>
                                    <div>
                                        <label for="reference-end-seconds" class="block text-xs font-medium text-slate-400 mb-1">End (seconds)</label>
                                        <input id="reference-end-seconds" type="number" name="reference_end_seconds" min="0" max="86400" placeholder="60"
                                            class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition">
                                    </div>
                                </div>
                                <div>
                                    <label for="reference-note" class="block text-xs font-medium text-slate-400 mb-1">What part are you asking about? (optional)</label>
                                    <input id="reference-note" type="text" name="reference_note" maxlength="280"
                                        placeholder="Example: Claim about pricing around 00:42"
                                        class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition">
                                </div>
                            </div>
                        </details>

                        <div class="flex gap-3">
                            <button type="button" @click="messageModal = false"
                                class="flex-1 px-4 py-2 bg-slate-700/60 hover:bg-slate-600/60 border border-slate-600 rounded-lg text-slate-300 hover:text-white text-sm transition">Cancel</button>
                            <button type="submit" :disabled="submitting"
                                class="flex-1 px-4 py-2 bg-emerald-600 hover:bg-emerald-500 disabled:opacity-50 disabled:cursor-not-allowed rounded-lg text-white font-medium text-sm transition">
                                <span x-show="!submitting">Send to Creator</span>
                                <span x-show="submitting">Sending...</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    {{-- Disclaimer --}}
    <p class="mt-6 text-xs text-slate-600 text-center">
        This community advertisement was paid for by the sponsoring citizen. Earnings are credited to your wallet upon verified completion and processed in your next batch payout.
    </p>

</div>

<meta name="campaign-complete-url" content="{{ route('voter.citizen-campaigns.complete', $campaign) }}">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="ad-room-url" content="{{ route('voter.ad-room') }}">

@push('scripts')
<script>
(function () {
    const overlay     = document.getElementById('play-overlay');
    const progressBar = document.getElementById('progress-bar');
    const statusMsg   = document.getElementById('status-msg');
    const completeUrl = document.querySelector('meta[name="campaign-complete-url"]').content;
    const csrf        = document.querySelector('meta[name="csrf-token"]').content;
    const adRoomUrl   = document.querySelector('meta[name="ad-room-url"]').content;
    const duration    = {{ $duration ?? 0 }};
    const mustWatch   = {{ $mustWatch ?? 100 }};
    const playerMode  = '{{ $playerMode }}';
    const isYouTube   = playerMode === 'youtube';
    const isVimeo     = playerMode === 'vimeo';
    const isHls       = playerMode === 'hls';
    const videoId     = '{{ $videoId ?? '' }}';
    const vimeoId     = '{{ $vimeoId ?? '' }}';
    const mediaUrl    = @json($campaign->media_url ?? '');

    let completed = false;
    let ytPlayer  = null;
    let vimeoPlayer = null;
    let lastTime  = 0;

    const rewatchWrap = document.getElementById('rewatch-wrap');
    const rewatchBtn  = document.getElementById('rewatch-btn');

    function showStatus(msg, type = 'info') {
        const colours = {
            info:    'bg-slate-700/50 text-slate-300',
            success: 'bg-emerald-900/50 border border-emerald-500/40 text-emerald-300',
            error:   'bg-red-900/50 border border-red-500/40 text-red-300',
        };
        statusMsg.className = 'mt-5 text-center py-4 px-6 rounded-xl ' + (colours[type] ?? colours.info);
        statusMsg.textContent = msg;
        statusMsg.classList.remove('hidden');
    }

    function post(url, data) {
        return fetch(url, {
            method:  'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
            body:    JSON.stringify(data),
        }).then(r => r.json());
    }

    function updateProgress(currentSeconds) {
        const watched = Math.max(0, Math.floor(currentSeconds || 0));
        const pct = duration > 0 ? Math.min(100, (watched / duration) * 100) : 0;
        progressBar.style.width = pct + '%';
    }

    function showRewatchButton() {
        if (rewatchWrap) rewatchWrap.classList.remove('hidden');
    }

    // Reset the player + state so the voter can re-watch. Repeat completions
    // are recorded by the server but pay nothing (is_repeat path).
    function resetForRewatch() {
        completed = false;
        lastTime = 0;
        progressBar.style.width = '0%';
        statusMsg.classList.add('hidden');
        statusMsg.innerHTML = '';
        document.getElementById('control-blocker').classList.add('hidden');
        if (rewatchWrap) rewatchWrap.classList.add('hidden');
    }

    function playFromStart() {
        overlay.style.display = 'none';
        document.getElementById('control-blocker').classList.remove('hidden');
        if (isYouTube && ytPlayer && typeof ytPlayer.seekTo === 'function') {
            ytPlayer.seekTo(0, true);
            ytPlayer.playVideo();
        } else if (isVimeo && vimeoPlayer) {
            vimeoPlayer.setCurrentTime(0).then(() => vimeoPlayer.play().catch(() => {})).catch(() => {});
        } else {
            const video = document.getElementById('ad-video');
            if (video) { video.currentTime = 0; video.play().catch(() => {}); }
        }
    }

    if (rewatchBtn) {
        rewatchBtn.addEventListener('click', () => {
            resetForRewatch();
            playFromStart();
        });
    }

    function handleCompletion(playbackSeconds) {
        if (completed) return;
        completed = true;
        const total = Math.floor(playbackSeconds > 0 ? playbackSeconds : duration);
        updateProgress(total);
        showStatus('Recording completion…', 'info');

        post(completeUrl, {
            total_seconds_watched: total,
            media_duration_seconds: duration > 0 ? duration : total,
        })
        .then(res => {
            if (res.error) {
                showStatus(res.error, 'error');
            } else if (res.qualified) {
                showStatus(`🎉 You earned $${parseFloat(res.payout_earned).toFixed(2)}! Credited to pending earnings.`, 'success');
                statusMsg.innerHTML += ` <a href="${adRoomUrl}" class="underline text-emerald-400 ml-2">Watch more →</a>`;
                showRewatchButton();
            } else if (res.is_repeat) {
                showStatus('Re-watch recorded. Only the first qualifying view earns a payout — thanks for watching again.', 'info');
                statusMsg.innerHTML += ` <a href="${adRoomUrl}" class="underline text-emerald-400 ml-2">Watch more →</a>`;
                showRewatchButton();
            } else {
                showStatus('Video ended — you did not meet the qualifying threshold this time.', 'info');
            }
        })
        .catch(() => showStatus('Error recording completion. Please try again or contact support.', 'error'));
    }

    /* ── YouTube path ─────────────────────────────────────────────── */
    if (isYouTube) {
        window.onYouTubeIframeAPIReady = function () {
            ytPlayer = new YT.Player('yt-player-container', {
                height: '100%',
                width:  '100%',
                videoId: videoId,
                playerVars: {
                    enablejsapi: 1, rel: 0, fs: 0, modestbranding: 1, playsinline: 1,
                    controls: 0, disablekb: 1, iv_load_policy: 3,
                    origin: window.location.origin,
                },
                events: {
                    onStateChange: function (e) {
                        if (e.data === YT.PlayerState.PLAYING) {
                            document.getElementById('control-blocker').classList.remove('hidden');
                            setInterval(() => {
                                if (!completed && ytPlayer && typeof ytPlayer.getCurrentTime === 'function') {
                                    updateProgress(ytPlayer.getCurrentTime());
                                }
                            }, 1000);
                        } else if (e.data === YT.PlayerState.PAUSED) {
                            if (!completed && ytPlayer) setTimeout(() => ytPlayer.playVideo(), 100);
                        } else if (e.data === YT.PlayerState.ENDED) {
                            handleCompletion(ytPlayer.getCurrentTime() || 0);
                        }
                    }
                }
            });
        };

        var ytScript = document.createElement('script');
        ytScript.src = 'https://www.youtube.com/iframe_api';
        document.head.appendChild(ytScript);

        overlay.addEventListener('click', () => {
            overlay.style.display = 'none';
            if (ytPlayer && typeof ytPlayer.playVideo === 'function') {
                ytPlayer.playVideo();
            }
        });
    }

    /* ── Vimeo path ───────────────────────────────────────────────── */
    function loadVimeoApi() {
        if (window.Vimeo && window.Vimeo.Player) return Promise.resolve();
        return new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = 'https://player.vimeo.com/api/player.js';
            script.onload = resolve;
            script.onerror = reject;
            document.head.appendChild(script);
        });
    }

    if (isVimeo) {
        loadVimeoApi().then(() => {
            vimeoPlayer = new window.Vimeo.Player('vimeo-player-container', {
                id: parseInt(vimeoId, 10),
                controls: false, byline: false, title: false, portrait: false, playsinline: true, dnt: true,
            });

            let currentTime = 0;
            vimeoPlayer.on('timeupdate', ({ seconds }) => { currentTime = seconds || 0; updateProgress(currentTime); });
            vimeoPlayer.on('pause', () => { if (!completed) vimeoPlayer.play().catch(() => {}); });
            vimeoPlayer.on('ended', () => handleCompletion(currentTime || duration));
        }).catch(() => showStatus('Could not load Vimeo player.', 'error'));

        overlay.addEventListener('click', () => {
            overlay.style.display = 'none';
            document.getElementById('control-blocker').classList.remove('hidden');
            if (vimeoPlayer) vimeoPlayer.play().catch(() => {});
        });
    }

    /* ── HLS player helper ────────────────────────────────────────── */
    function loadHlsApi() {
        if (window.Hls) return Promise.resolve();
        return new Promise((resolve, reject) => {
            const existing = document.querySelector('script[data-hls-api="1"]');
            if (existing) {
                existing.addEventListener('load', () => resolve(), { once: true });
                existing.addEventListener('error', () => reject(new Error('HLS API failed to load')), { once: true });
                return;
            }
            const script = document.createElement('script');
            script.src = 'https://cdn.jsdelivr.net/npm/hls.js@1.5.15/dist/hls.min.js';
            script.setAttribute('data-hls-api', '1');
            script.onload = () => resolve();
            script.onerror = () => reject(new Error('HLS API failed to load'));
            document.head.appendChild(script);
        });
    }

    async function initHls(video) {
        if (video.canPlayType('application/vnd.apple.mpegurl')) {
            video.src = mediaUrl;
            return;
        }

        await loadHlsApi();
        if (!window.Hls || !window.Hls.isSupported()) {
            showStatus('This browser cannot play this video stream.', 'error');
            return;
        }

        const hlsPlayer = new window.Hls();
        hlsPlayer.on(window.Hls.Events.ERROR, function (_event, data) {
            if (data?.fatal) {
                showStatus('Video stream interrupted. Please refresh and try again.', 'error');
            }
        });
        hlsPlayer.loadSource(mediaUrl);
        hlsPlayer.attachMedia(video);
    }

    /* ── Native HTML5 / HLS video path ────────────────────────────── */
    if (playerMode === 'native' || isHls) {
        const video = document.getElementById('ad-video');
        let hlsReady = false;

        video.addEventListener('seeking', function () {
            if (!completed && lastTime > 0 && Math.abs(this.currentTime - lastTime) > 1.5) {
                this.currentTime = lastTime;
            }
        });

        video.addEventListener('timeupdate', function () {
            if (!completed) {
                lastTime = this.currentTime;
                updateProgress(this.currentTime);
            }
        });

        video.addEventListener('pause', function () {
            if (!completed && this.currentTime > 0 && this.currentTime < (this.duration || duration) - 1) {
                setTimeout(() => video.play(), 100);
            }
        });

        video.addEventListener('ended', () => handleCompletion(video.currentTime || 0));

        overlay.addEventListener('click', async () => {
            overlay.style.display = 'none';
            document.getElementById('control-blocker').classList.remove('hidden');

            if (isHls && !hlsReady) {
                hlsReady = true;
                await initHls(video).catch(() => showStatus('Could not load video stream.', 'error'));
            }

            video.play();
        });
    }
})();
</script>
@endpush
@endsection
