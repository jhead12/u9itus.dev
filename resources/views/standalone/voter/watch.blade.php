@extends('layouts.voter')

@section('title', 'Watch: ' . $campaign->title)

@section('content')
<div class="max-w-3xl mx-auto px-4 sm:px-6 py-8">


    {{-- Top Navigation --}}
    <div class="mb-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <a href="{{ route('voter.dashboard') }}" class="inline-flex items-center gap-1.5 text-slate-400 hover:text-emerald-400 text-sm transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            Back to Dashboard
        </a>
        @if(!empty($nextAdToken) && !empty($nextCampaign) && isset($nextAdToken->token))
            <a href="{{ route('voter.watch', $nextAdToken->token) }}"
               class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-sm font-semibold transition focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-slate-800 focus:ring-emerald-500"
               title="Go to next available video">
                Next Video
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </a>
        @endif
    </div>

    {{-- Campaign Header --}}
    <div class="mb-5 p-5 bg-slate-800/60 border border-slate-700/60 rounded-2xl">
        <h1 class="text-xl font-bold text-white leading-snug">{{ $campaign->title }}</h1>
        <p class="text-slate-400 mt-1.5 text-sm">
            <span class="text-slate-500">Sponsored by</span>
            <span class="text-emerald-400 font-medium">{{ $campaign->politician->full_name ?? 'Unknown' }}</span>
            @if($campaign->politician->political_office ?? false)
                <span class="text-slate-600">&middot;</span>
                <span class="text-slate-400">{{ $campaign->politician->political_office }}</span>
                {{-- "About This Office" tooltip trigger --}}
                <button
                    type="button"
                    id="office-info-btn"
                    aria-label="Learn about this office"
                    class="inline-flex items-center justify-center w-4 h-4 rounded-full bg-slate-700 hover:bg-emerald-700 text-slate-300 hover:text-white transition ml-1 align-middle"
                    onclick="openOfficeInfoModal('{{ $campaign->politician->uuid }}')"
                >
                    <svg class="w-2.5 h-2.5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                    </svg>
                </button>
            @endif
        </p>
    </div>

    {{-- ── Office Info Modal ────────────────────────────────────────────── --}}
    <div id="office-info-modal"
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm"
         style="display:none!important"
         role="dialog"
         aria-modal="true"
         aria-labelledby="office-modal-title">
        <div class="relative w-full max-w-lg bg-slate-900 border border-slate-700/70 rounded-2xl shadow-2xl overflow-hidden">

            {{-- Header --}}
            <div class="flex items-start justify-between p-5 border-b border-slate-700/60">
                <div>
                    <p class="text-[10px] uppercase tracking-[0.2em] font-semibold text-emerald-400/80 mb-1">About This Office</p>
                    <h2 id="office-modal-title" class="text-lg font-bold text-white" id="om-office-title">Loading…</h2>
                    <p class="text-sm text-slate-400 mt-0.5" id="om-jurisdiction"></p>
                </div>
                <button
                    type="button"
                    onclick="closeOfficeInfoModal()"
                    class="text-slate-500 hover:text-white transition ml-4 mt-0.5 shrink-0"
                    aria-label="Close">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            {{-- Body --}}
            <div class="p-5 max-h-[70vh] overflow-y-auto space-y-5 text-sm">

                {{-- Loading state --}}
                <div id="om-loading" class="flex items-center justify-center py-8 text-slate-400 gap-3">
                    <div class="h-5 w-5 rounded-full border-2 border-slate-500 border-t-emerald-400 animate-spin"></div>
                    <span>Loading office information…</span>
                </div>

                {{-- Error state --}}
                <div id="om-error" class="hidden py-6 text-center text-slate-400">
                    <p class="text-slate-300 font-medium">No information available yet</p>
                    <p class="text-xs mt-1 text-slate-500">Civic data for this office hasn't been added to the platform yet.</p>
                </div>

                {{-- Content --}}
                <div id="om-content" class="hidden space-y-5">

                    {{-- Quick stats row --}}
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                        <div id="om-stat-level" class="hidden rounded-xl bg-slate-800/70 border border-slate-700/50 px-3 py-2.5">
                            <p class="text-[10px] uppercase tracking-wide text-slate-500 mb-0.5">Level</p>
                            <p class="text-white font-semibold" id="om-governance-level"></p>
                        </div>
                        <div id="om-stat-salary" class="hidden rounded-xl bg-slate-800/70 border border-slate-700/50 px-3 py-2.5">
                            <p class="text-[10px] uppercase tracking-wide text-slate-500 mb-0.5">Annual Salary</p>
                            <p class="text-emerald-300 font-semibold" id="om-salary"></p>
                        </div>
                        <div id="om-stat-term" class="hidden rounded-xl bg-slate-800/70 border border-slate-700/50 px-3 py-2.5">
                            <p class="text-[10px] uppercase tracking-wide text-slate-500 mb-0.5">Term Length</p>
                            <p class="text-white font-semibold" id="om-term"></p>
                        </div>
                        <div id="om-stat-how" class="hidden rounded-xl bg-slate-800/70 border border-slate-700/50 px-3 py-2.5 col-span-2 sm:col-span-1">
                            <p class="text-[10px] uppercase tracking-wide text-slate-500 mb-0.5">How Selected</p>
                            <p class="text-white font-semibold capitalize" id="om-how-elected"></p>
                        </div>
                    </div>

                    {{-- Role summary --}}
                    <div id="om-summary-block" class="hidden">
                        <p class="text-[10px] uppercase tracking-wide text-slate-500 mb-1.5">What Does This Official Do?</p>
                        <p class="text-slate-200 leading-relaxed" id="om-role-summary"></p>
                    </div>

                    {{-- Community impact --}}
                    <div id="om-impact-block" class="hidden">
                        <p class="text-[10px] uppercase tracking-wide text-slate-500 mb-1.5">How It Affects Your Life</p>
                        <p class="text-slate-200 leading-relaxed" id="om-community-impact"></p>
                    </div>

                    {{-- Key duties --}}
                    <div id="om-duties-block" class="hidden">
                        <p class="text-[10px] uppercase tracking-wide text-slate-500 mb-2">Key Responsibilities</p>
                        <ul id="om-key-duties" class="space-y-1.5 text-slate-200"></ul>
                    </div>

                    {{-- Powers & limits --}}
                    <div id="om-powers-block" class="hidden">
                        <p class="text-[10px] uppercase tracking-wide text-slate-500 mb-2">Powers &amp; Limits</p>
                        <ul id="om-powers-and-limits" class="space-y-1.5 text-slate-300"></ul>
                    </div>

                    {{-- Salary note + source link --}}
                    <div id="om-footer-block" class="hidden border-t border-slate-700/50 pt-4 space-y-1">
                        <p class="text-xs text-slate-500" id="om-salary-note"></p>
                        <a id="om-source-url" href="#" target="_blank" rel="noopener noreferrer"
                           class="text-xs text-emerald-400/80 hover:text-emerald-300 underline underline-offset-2">
                            View official source ↗
                        </a>
                    </div>

                    {{-- Verified badge --}}
                    <div id="om-verified-badge" class="hidden flex items-center gap-2 text-xs text-emerald-300/70">
                        <svg class="w-3.5 h-3.5 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                        Civic data verified by U9itus team
                    </div>

                </div>
            </div>

            {{-- Footer disclaimer --}}
            <div class="px-5 py-3 bg-slate-950/40 border-t border-slate-800/60">
                <p class="text-xs text-slate-500 text-center">
                    This information is provided to help you understand what your elected officials do.
                    It does not represent U9itus's endorsement of any candidate.
                </p>
            </div>
        </div>
    </div>

    @if(!empty($campaign->video_blurb))
    <div class="mb-5 p-4 bg-slate-800/45 border border-slate-700/60 rounded-2xl">
        <p class="text-[11px] uppercase tracking-wide text-slate-500 mb-2">About This Video</p>
        <div class="prose prose-invert prose-sm max-w-none text-slate-200 [&_a]:text-emerald-400 [&_a:hover]:text-emerald-300 [&_img]:max-w-full [&_img]:h-auto [&_img]:rounded-lg">
            {!! $campaign->video_blurb !!}
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

        if ($isHlsUrl) {
            $nativeSourceType = 'application/x-mpegURL';
        } elseif (preg_match('/\.(webm)(\?.*)?$/i', $mediaUrl)) {
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
        } elseif (! empty($vimeoId)) {
            // Fallback for legacy campaigns missing media_type.
            $playerMode = 'vimeo';
        } elseif (! empty($videoId)) {
            $playerMode = 'youtube';
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
                @if($campaign->media_url)
                    <source data-src="{{ $campaign->media_url }}" type="{{ $playerMode === 'hls' ? 'application/x-mpegURL' : $nativeSourceType }}">
                @endif
                <track kind="captions" srclang="en" label="English captions" src="data:text/vtt,WEBVTT" default>
                <track kind="descriptions" srclang="en" label="English descriptions" src="data:text/vtt,WEBVTT">
                Your browser does not support HTML5 video.
            </video>
        @endif

        {{-- Fraud Prevention: Transparent blocker prevents seeking/interaction with video controls --}}
        <div id="control-blocker" class="hidden absolute inset-0 z-10" style="pointer-events: auto; cursor: not-allowed;"></div>

        {{-- Overlay before play --}}
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

        {{-- Live timer overlay --}}
        <div id="watch-timer" class="absolute top-3 right-3 px-2.5 py-1 rounded-md bg-slate-900/75 border border-slate-600/70 text-[11px] text-slate-200 font-medium tracking-wide">
            0:00 / {{ floor(($duration ?? 0) / 60) }}:{{ str_pad((string)(($duration ?? 0) % 60), 2, '0', STR_PAD_LEFT) }}
        </div>

        {{-- Buffering state overlay --}}
        <div id="player-buffer-indicator" class="hidden absolute inset-0 z-20 pointer-events-none bg-black/45 flex flex-col items-center justify-center">
            <div class="h-10 w-10 rounded-full border-2 border-emerald-300/40 border-t-emerald-400 animate-spin"></div>
            <p id="player-buffer-label" class="mt-3 text-xs text-emerald-200">Loading video…</p>
        </div>
    </div>

    <div id="stream-controls" class="mt-3 hidden items-center gap-2">
        <button
            id="low-bitrate-toggle"
            type="button"
            class="inline-flex items-center gap-1.5 rounded-lg border border-slate-600 bg-slate-900/60 px-3 py-1.5 text-xs font-medium text-slate-200 hover:border-emerald-500/50 hover:text-white transition"
            aria-pressed="false">
            Data Saver: Off
        </button>
        <span id="stream-mode-hint" class="text-xs text-slate-400"></span>
    </div>

    <div x-data="{
        reportModal: false,
        messageModal: false,
        questionFeedOpen: false,
        submitting: false,
        toastOpen: false,
        toastMessage: '',
        toastKind: 'success',
        toastTimer: null,
        showToast(message, kind = 'success') {
            this.toastMessage = message;
            this.toastKind = kind;
            this.toastOpen = true;
            if (this.toastTimer) {
                clearTimeout(this.toastTimer);
            }
            this.toastTimer = setTimeout(() => {
                this.toastOpen = false;
            }, 3600);
        }
    }">
        <output x-show="toastOpen"
            x-transition:enter="transition ease-out duration-250"
            x-transition:enter-start="opacity-0 translate-y-2"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed right-4 bottom-4 z-[70] w-[min(92vw,420px)] rounded-xl border px-4 py-3 shadow-2xl"
            :class="toastKind === 'success'
                ? 'border-emerald-400/35 bg-slate-900/95'
                : 'border-rose-400/35 bg-slate-900/95'"
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

        {{-- Status messages --}}
        <div id="status-msg" class="mt-5 hidden text-center py-4 px-6 rounded-2xl"></div>

        {{-- Replay CTA (shown after completion) --}}
        <div id="replay-wrap" class="mt-3 hidden text-center">
            <button id="replay-btn" type="button"
                class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-slate-800/70 hover:bg-slate-700/70 border border-slate-600 text-slate-200 hover:text-white text-sm font-medium transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0A8.003 8.003 0 014.582 15"/>
                </svg>
                Replay Video
            </button>
        </div>

        {{-- Ask Campaign Owner CTA --}}
        <div class="mt-5 rounded-2xl border border-emerald-500/30 bg-gradient-to-r from-emerald-900/35 via-teal-900/25 to-slate-800/90 p-5 shadow-lg shadow-emerald-950/20">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="max-w-2xl">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.24em] text-emerald-300/80">Have a Question?</p>
                    <h2 class="mt-2 text-lg font-semibold text-white">Ask the politician's campaign about this message.</h2>
                    <p class="mt-1.5 text-sm leading-relaxed text-slate-300">Get clarification on the ad, the candidate's position, or what they plan to do if elected.</p>
                </div>

                <button id="ask-question-open-btn" @click="messageModal = true" type="button"
                    class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-emerald-400/40 bg-emerald-500 px-5 py-3 text-sm font-semibold text-slate-950 shadow-md shadow-emerald-900/30 transition hover:bg-emerald-400 hover:shadow-lg hover:shadow-emerald-900/40 focus:outline-none focus:ring-2 focus:ring-emerald-300/60 sm:w-auto sm:min-w-[230px]">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
                    </svg>
                    Ask the Politician
                </button>
            </div>

            <p class="mt-3 text-xs text-emerald-100/75">Your question is sent to this ad's campaign owner or team, not platform support.</p>
        </div>

        @if(($recentPublicQuestions ?? collect())->isNotEmpty())
        @php
            $publicQaUrl = ($campaign->politician?->slug && $campaign->politician?->page_published)
                ? route('politician.public.show', $campaign->politician->slug)
                : null;
        @endphp
        <div class="mt-4 rounded-2xl border border-slate-700/60 bg-slate-800/55 p-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">Recent Voter Q&amp;A</p>
                    <p class="mt-1 text-sm text-slate-300">See what other voters asked without turning this page into a full discussion board.</p>
                </div>

                <button @click="questionFeedOpen = !questionFeedOpen" type="button"
                    class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-600 bg-slate-900/60 px-4 py-2 text-sm font-medium text-slate-200 transition hover:border-emerald-400/40 hover:text-white sm:min-w-[220px]">
                    <svg class="h-4 w-4 transition-transform" :class="questionFeedOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                    <span x-text="questionFeedOpen ? 'Hide voter Q&A' : 'See what voters asked'"></span>
                </button>
            </div>

            <div x-show="questionFeedOpen"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 -translate-y-1"
                x-transition:enter-end="opacity-100 translate-y-0"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 translate-y-0"
                x-transition:leave-end="opacity-0 -translate-y-1"
                class="mt-4 space-y-3"
                style="display: none;">
                @foreach($recentPublicQuestions as $entry)
                <article class="rounded-xl border border-slate-700/50 bg-slate-900/45 p-4">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="text-[11px] uppercase tracking-wide text-slate-500">Voter Question</p>
                        <p class="text-xs text-slate-500">{{ $entry->public_alias ?: 'Verified Voter' }}</p>
                    </div>
                    <p class="mt-2 text-sm leading-relaxed text-slate-100">{{ $entry->body }}</p>

                    @if($entry->hasReference())
                        <div class="mt-3 rounded-lg border border-sky-500/20 bg-sky-500/10 px-3 py-2">
                            <p class="text-[11px] uppercase tracking-wide text-sky-300">Referenced Clip</p>
                            <a href="{{ $entry->reference_url }}" target="_blank" rel="noopener"
                               class="mt-1 inline-block text-xs text-sky-100 hover:text-white underline break-all">{{ $entry->referencePlatformLabel() }} Link ↗</a>
                            @if($entry->referenceTimeRangeLabel())
                                <p class="mt-1 text-[11px] text-sky-200/90">Time: {{ $entry->referenceTimeRangeLabel() }}</p>
                            @endif
                        </div>
                    @endif

                    <div class="mt-3 rounded-lg border border-emerald-500/20 bg-emerald-500/10 px-3 py-2.5">
                        <p class="text-[11px] uppercase tracking-wide text-emerald-300">Official Campaign Response</p>
                        <p class="mt-1 text-sm leading-relaxed text-emerald-100 whitespace-pre-line">{{ $entry->campaign_reply ?: $entry->admin_notes }}</p>
                    </div>
                </article>
                @endforeach

                <a href="{{ route('voter.watch.questions', $adToken->token) }}"
                    target="_blank" rel="noopener"
                    class="inline-flex items-center gap-2 text-sm font-medium text-emerald-300 transition hover:text-emerald-200">
                    View the full campaign Q&amp;A
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                    </svg>
                </a>
            </div>
        </div>
        @endif

    @php
        $engagementSurvey = is_array($campaign->engagement_survey ?? null) ? $campaign->engagement_survey : null;
        $surveyOptions = collect($engagementSurvey['options'] ?? [])->filter(function ($option) {
            return is_array($option)
                && filled($option['text'] ?? null)
                && filled($option['value'] ?? null);
        })->values();
    @endphp

    @if($engagementSurvey && filled($engagementSurvey['question'] ?? null) && $surveyOptions->count() >= 2)
    <div id="engagement-survey-panel" class="hidden mt-5 bg-slate-800/70 border border-slate-700/60 rounded-2xl p-5">
        <div class="flex items-start justify-between gap-3 mb-3">
            <div>
                <p class="text-xs uppercase tracking-wider text-slate-500 font-semibold">Post-View Survey</p>
                <h3 class="text-white font-semibold mt-1">{{ $engagementSurvey['question'] }}</h3>
            </div>
            <span id="survey-badge" class="text-[11px] px-2 py-1 rounded-full bg-slate-700/70 text-slate-300">Optional</span>
        </div>

        <div id="survey-options" class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
            @foreach($surveyOptions as $option)
            <button type="button"
                class="survey-option-btn text-left px-3.5 py-2.5 rounded-xl border border-slate-600 bg-slate-900/60 text-slate-300 hover:border-emerald-500/50 hover:text-white transition"
                data-value="{{ $option['value'] }}">
                {{ $option['text'] }}
            </button>
            @endforeach
        </div>

        <div class="mt-3">
            <label for="survey-response-text" class="block text-xs text-slate-500 mb-1">Optional note</label>
            <textarea id="survey-response-text" rows="2" maxlength="400"
                class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/40 focus:border-emerald-500 transition"
                placeholder="Share additional feedback..."></textarea>
        </div>

        <div class="mt-3 flex items-center gap-2">
            <button id="survey-submit-btn" type="button" disabled
                class="px-4 py-2 rounded-lg bg-emerald-600/60 text-white text-sm font-semibold disabled:opacity-40 disabled:cursor-not-allowed hover:bg-emerald-500 transition">
                Submit Response
            </button>
            <button id="survey-skip-btn" type="button"
                class="px-3 py-2 rounded-lg bg-slate-700/70 text-slate-300 text-sm hover:text-white hover:bg-slate-600 transition">
                Skip
            </button>
            <span id="survey-status-msg" class="text-xs text-slate-400"></span>
        </div>
    </div>
    @endif

    {{-- ── About the Candidate ──────────────────────────────────── --}}
    @php $pol = $campaign->politician; @endphp
    @if($pol)
    <div x-data="{ bioOpen: false }" class="mt-5 bg-slate-800/60 border border-slate-700/60 rounded-2xl overflow-hidden">

        {{-- Header row: avatar + key facts --}}
        <div class="flex items-start gap-4 p-5">
            {{-- Avatar --}}
            @if($pol->profile_photo_url)
                <img src="{{ $pol->profile_photo_url }}"
                     alt="{{ $pol->full_name }}"
                     class="w-14 h-14 rounded-full ring-2 ring-slate-600 object-cover shrink-0">
            @else
                <div class="w-14 h-14 rounded-full bg-slate-700 border border-slate-600 flex items-center justify-center shrink-0">
                    <span class="text-lg font-bold text-slate-300 select-none">
                        {{ strtoupper(mb_substr($pol->full_name ?? 'P', 0, 2)) }}
                    </span>
                </div>
            @endif

            {{-- Name / office / location --}}
            <div class="flex-1 min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <h2 class="text-base font-bold text-white leading-tight truncate">{{ $pol->full_name }}</h2>

                    @if($pol->verified_official)
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-900/50 text-emerald-300 border border-emerald-500/30">
                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                            Verified Official
                        </span>
                    @endif

                    @if($pol->party_affiliation)
                        @php
                            $partyColors = [
                                'Democrat'     => 'bg-blue-900/50 text-blue-300 border-blue-500/30',
                                'Democratic'   => 'bg-blue-900/50 text-blue-300 border-blue-500/30',
                                'Republican'   => 'bg-red-900/50 text-red-300 border-red-500/30',
                                'Independent'  => 'bg-purple-900/50 text-purple-300 border-purple-500/30',
                                'Libertarian'  => 'bg-yellow-900/50 text-yellow-300 border-yellow-500/30',
                                'Green'        => 'bg-green-900/50 text-green-300 border-green-500/30',
                            ];
                            $partyColor = $partyColors[$pol->party_affiliation]
                                       ?? 'bg-slate-700/60 text-slate-300 border-slate-600/40';
                        @endphp
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium border {{ $partyColor }}">
                            {{ $pol->party_affiliation }}
                        </span>
                    @endif
                </div>

                @if($pol->political_office)
                    <p class="text-slate-300 text-sm mt-0.5">{{ $pol->political_office }}</p>
                @endif

                @php
                    $location = collect([$pol->district, $pol->city, $pol->state])->filter()->implode(', ');
                @endphp
                @if($location)
                    <p class="text-slate-500 text-xs mt-0.5 flex items-center gap-1">
                        <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        {{ $location }}
                    </p>
                @endif
            </div>
        </div>

        {{-- Bio --}}
        @if($pol->bio)
        <div class="px-5 pb-4 border-t border-slate-700/40 pt-4">
            <p class="text-slate-400 text-sm leading-relaxed" :class="bioOpen ? '' : 'line-clamp-3'">
                {{ $pol->bio }}
            </p>
            @if(mb_strlen($pol->bio) > 180)
                <button @click="bioOpen = !bioOpen"
                    class="mt-2 text-xs text-emerald-400 hover:text-emerald-300 font-medium transition">
                    <span x-text="bioOpen ? 'Show less ↑' : 'Read more ↓'">Read more ↓</span>
                </button>
            @endif
        </div>
        @endif

        {{-- Research & Transparency Links --}}
        <div class="border-t border-slate-700/40 px-5 py-4">
            <p class="text-xs font-semibold text-slate-500 uppercase tracking-widest mb-3">Research This Candidate</p>
            <div class="flex flex-wrap gap-2">

                {{-- U9itus public profile --}}
                @if($pol->slug && $pol->page_published)
                <a href="{{ route('politician.public.show', $pol->slug) }}"
                   target="_blank" rel="noopener"
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-emerald-900/30 hover:bg-emerald-900/50 border border-emerald-500/30 text-emerald-300 text-xs font-medium transition">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                    Full Profile
                </a>
                @endif

                {{-- Wikipedia --}}
                <a href="https://en.wikipedia.org/wiki/Special:Search?search={{ urlencode($pol->full_name) }}"
                   target="_blank" rel="noopener noreferrer"
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-700/60 hover:bg-slate-700 border border-slate-600/60 text-slate-300 hover:text-white text-xs font-medium transition">
                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12.09 13.119c-.936 1.932-2.217 4.548-2.853 5.728-.616 1.074-1.127.931-1.532.029-1.406-3.321-4.293-9.144-5.651-12.409-.251-.601-.441-.987-.619-1.139-.181-.15-.554-.24-1.122-.271C.103 5.033 0 4.982 0 4.898v-.455l.052-.045c.924-.005 5.401 0 5.401 0l.051.045v.434c0 .084-.103.129-.209.141-.585.03-.993.115-.993.395 0 .175.336.895.465 1.201 1.252 3.073 3.444 8.075 4.273 9.972.219.468.308.437.558.02.421-.707 1.189-2.385 1.189-2.385l-1.676-3.898c-.48-.991-.791-1.595-.912-1.805a1.54 1.54 0 00-.457-.503c-.19-.12-.516-.221-.979-.306-.083-.017-.126-.045-.126-.13v-.518l.051-.045c1.568-.005 3.752 0 3.752 0l.05.045v.506c0 .07-.057.117-.169.143-.363.056-.615.138-.715.213-.094.076-.139.171-.139.279 0 .146.129.635.387 1.196l.896 1.918c.316-.609.562-1.077.72-1.403a5.545 5.545 0 00.409-1.045c0-.109-.049-.199-.145-.273-.098-.075-.359-.155-.785-.232-.12-.019-.179-.069-.179-.134v-.496l.05-.045c1.405-.005 2.989 0 2.989 0l.052.045v.48c0 .077-.066.123-.196.143-.482.054-.832.262-1.049.508-.164.19-.432.537-.804 1.217-.107.193-.398.745-.876 1.656l1.931 4.268c.151.291.261.437.329.437.065 0 .195-.154.39-.506l3.079-5.973c.181-.35.272-.606.272-.806a.635.635 0 00-.227-.497c-.151-.121-.43-.206-.833-.25-.082-.01-.123-.054-.123-.136v-.496l.052-.044c1.277-.005 2.604 0 2.604 0l.05.044v.488c0 .074-.057.118-.175.134-.485.055-.863.292-1.135.714-.108.17-.428.727-1.249 2.167l-3.593 6.479z"/>
                    </svg>
                    Wikipedia
                </a>

                {{-- Ballotpedia: use stored ID for direct page, else fall back to name search --}}
                @if($pol->show_ballotpedia_data ?? true)
                @php
                    $ballotpediaUrl = $pol->ballotpedia_id
                        ? 'https://ballotpedia.org/' . rawurlencode($pol->ballotpedia_id)
                        : 'https://ballotpedia.org/wiki/index.php?search=' . urlencode($pol->full_name);
                @endphp
                <a href="{{ $ballotpediaUrl }}"
                   target="_blank" rel="noopener noreferrer"
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-700/60 hover:bg-slate-700 border border-slate-600/60 text-slate-300 hover:text-white text-xs font-medium transition">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    Ballotpedia
                </a>
                @endif

                {{-- VoteSmart: voting record --}}
                @if($pol->votesmart_id && ($pol->show_votesmart_data ?? true))
                <a href="https://votesmart.org/candidate/{{ (int) $pol->votesmart_id }}/key-votes"
                   target="_blank" rel="noopener noreferrer"
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-700/60 hover:bg-slate-700 border border-slate-600/60 text-slate-300 hover:text-white text-xs font-medium transition">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                    </svg>
                    Voting Record
                </a>
                @endif

                {{-- OpenSecrets: campaign finance --}}
                @if($pol->opensecrets_id && ($pol->show_opensecrets_data ?? true))
                <a href="https://www.opensecrets.org/politicians/summary?cid={{ urlencode($pol->opensecrets_id) }}"
                   target="_blank" rel="noopener noreferrer"
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-700/60 hover:bg-slate-700 border border-slate-600/60 text-slate-300 hover:text-white text-xs font-medium transition">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Campaign Finance
                </a>
                @endif

                {{-- FEC: use stored candidate ID for direct record, else fall back to name search --}}
                @if($pol->show_fec_data ?? true)
                @php
                    $fecUrl = $pol->fec_candidate_id
                        ? 'https://www.fec.gov/data/candidate/' . urlencode($pol->fec_candidate_id) . '/'
                        : 'https://www.fec.gov/data/candidates/?q=' . urlencode($pol->full_name);
                @endphp
                <a href="{{ $fecUrl }}"
                   target="_blank" rel="noopener noreferrer"
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-700/60 hover:bg-slate-700 border border-slate-600/60 text-slate-300 hover:text-white text-xs font-medium transition">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/>
                    </svg>
                    FEC Filings
                </a>
                @endif

                {{-- Official website --}}
                @if($pol->website_url)
                <a href="{{ $pol->website_url }}"
                   target="_blank" rel="noopener noreferrer"
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-700/60 hover:bg-slate-700 border border-slate-600/60 text-slate-300 hover:text-white text-xs font-medium transition">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9"/>
                    </svg>
                    Official Website
                </a>
                @endif

            </div>
        </div>
    </div>
    @endif
    {{-- ── /About the Candidate ─────────────────────────────────── --}}

    {{-- Report Actions --}}
    <div class="mt-5">
        {{-- Action Buttons --}}
        <div class="flex items-center justify-center gap-3">
            <button @click="reportModal = true" type="button"
                class="inline-flex items-center gap-2 px-4 py-2 bg-slate-800/60 hover:bg-slate-700/60 border border-slate-700/60 rounded-lg text-slate-300 hover:text-white text-sm transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                Report Issue
            </button>
        </div>
        <p class="mt-2 text-center text-xs text-slate-500">
            Report Issue contacts platform support.
        </p>

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
            
            <div class="bg-slate-800 border border-slate-700 rounded-2xl shadow-2xl max-w-md w-full p-6"
                @click.stop>
                
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

                <form id="message-form" @submit.prevent="
                    if (!submitting) {
                        submitting = true;
                        const formData = new FormData($event.target);
                        fetch('{{ route('voter.watch.report-issue', $adToken->token) }}', {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                'Accept': 'application/json'
                            },
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
                    <input type="hidden" name="view_session_uuid" :value="window.sessionId || ''">

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
                            class="flex-1 px-4 py-2 bg-slate-700/60 hover:bg-slate-600/60 border border-slate-600 rounded-lg text-slate-300 hover:text-white text-sm transition">
                            Cancel
                        </button>
                        <button type="submit" :disabled="submitting"
                            class="flex-1 px-4 py-2 bg-amber-600 hover:bg-amber-500 disabled:opacity-50 disabled:cursor-not-allowed rounded-lg text-white font-medium text-sm transition">
                            <span x-show="!submitting">Submit Report</span>
                            <span x-show="submitting">Submitting...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Ask Campaign Owner Question Composer (non-blocking) --}}
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

            <div class="bg-slate-800/95 border border-slate-700 rounded-2xl shadow-2xl w-full p-6 backdrop-blur"
                @click.stop>
                
                <div class="flex items-start justify-between mb-4">
                    <div>
                        <h3 class="text-lg font-bold text-white">Ask the Campaign Owner</h3>
                        <p class="text-sm text-slate-400 mt-0.5">This message goes to the campaign owner/team for this ad, not platform support and not necessarily the featured politician.</p>
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
                        fetch('{{ route('voter.watch.ask-question', $adToken->token) }}', {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                'Accept': 'application/json'
                            },
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
                    <input type="hidden" name="view_session_uuid" :value="window.sessionId || ''">

                    <div class="mb-5">
                        <label for="message-body" class="block text-sm font-medium text-slate-300 mb-2">Your Question for the Campaign Owner *</label>
                        <textarea name="body" id="message-body" rows="5" maxlength="1000" required
                            placeholder="Ask the campaign owner a question about this ad or campaign..."
                            class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition resize-none"></textarea>
                        <p class="text-xs text-slate-500 mt-1">Questions may be reviewed before public posting. Public posts use an anonymous voter alias.</p>
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
                                    <input id="reference-start-seconds" type="number" name="reference_start_seconds" min="0" max="86400"
                                        placeholder="0"
                                        class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition">
                                </div>
                                <div>
                                    <label for="reference-end-seconds" class="block text-xs font-medium text-slate-400 mb-1">End (seconds)</label>
                                    <input id="reference-end-seconds" type="number" name="reference_end_seconds" min="0" max="86400"
                                        placeholder="60"
                                        class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition">
                                </div>
                            </div>

                            <div>
                                <label for="reference-note" class="block text-xs font-medium text-slate-400 mb-1">What part are you asking about? (optional)</label>
                                <input id="reference-note" type="text" name="reference_note" maxlength="280"
                                    placeholder="Example: Claim about school funding around 00:42"
                                    class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition">
                            </div>

                            <p class="text-xs text-slate-500">Supported references: YouTube, Facebook, Instagram, TikTok, and X/Twitter.</p>
                        </div>
                    </details>

                    <div class="flex gap-3">
                        <button type="button" @click="messageModal = false"
                            class="flex-1 px-4 py-2 bg-slate-700/60 hover:bg-slate-600/60 border border-slate-600 rounded-lg text-slate-300 hover:text-white text-sm transition">
                            Cancel
                        </button>
                        <button type="submit" :disabled="submitting"
                            class="flex-1 px-4 py-2 bg-emerald-600 hover:bg-emerald-500 disabled:opacity-50 disabled:cursor-not-allowed rounded-lg text-white font-medium text-sm transition">
                            <span x-show="!submitting">Send to Campaign Owner</span>
                            <span x-show="submitting">Sending...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Disclaimer --}}
    <p class="mt-6 text-xs text-slate-600 text-center">This political advertisement was paid for by the campaign of {{ $campaign->politician->full_name ?? 'the sponsoring campaign' }}. Earnings are credited to your wallet upon verified completion and processed in your next batch payout.</p>

    </div>

</div>

<meta name="watch-token" content="{{ $adToken->token }}">
<meta name="csrf-token" content="{{ csrf_token() }}">

@push('scripts')
<script>
(function () {
    const overlay     = document.getElementById('play-overlay');
    const progressBar = document.getElementById('progress-bar');
    const timerText   = document.getElementById('watch-timer');
    const statusMsg   = document.getElementById('status-msg');
    const token       = document.querySelector('meta[name="watch-token"]').content;
    const csrf        = document.querySelector('meta[name="csrf-token"]').content;
    let duration        = {{ $duration ?? 0 }};
    const initialDuration = {{ $duration ?? 0 }};
    const mustWatch     = {{ $mustWatch ?? 100 }};
    const playerMode    = '{{ $playerMode }}';
    const isYouTube     = playerMode === 'youtube';
    const isVimeo       = playerMode === 'vimeo';
    const isHls         = playerMode === 'hls';
    const videoId       = '{{ $videoId ?? '' }}';
    const vimeoId       = '{{ $vimeoId ?? '' }}';
    const mediaStreamUrl = @json($campaign->media_url ?? null);
    const surveyPayload = @json($engagementSurvey);
    const dashboardUrl  = '{{ route('voter.dashboard') }}';
    const replayWrap    = document.getElementById('replay-wrap');
    const replayBtn     = document.getElementById('replay-btn');
    const bufferIndicator = document.getElementById('player-buffer-indicator');
    const bufferLabel   = document.getElementById('player-buffer-label');
    const streamControls = document.getElementById('stream-controls');
    const lowBitrateToggle = document.getElementById('low-bitrate-toggle');
    const streamModeHint = document.getElementById('stream-mode-hint');
    const networkInfo = navigator.connection || navigator.mozConnection || navigator.webkitConnection || null;
    const constrainedNetwork = Boolean(networkInfo && (networkInfo.saveData || ['slow-2g', '2g', '3g'].includes(networkInfo.effectiveType)));
    const lowDataStorageKey = 'u9itus:player:low-data-mode';

    let sessionId      = null;
    let heartbeatTimer = null;
    let antiSkipTimer  = null;
    let uiTimer        = null;
    let completed      = false;
    let lastTime       = 0;
    let ytPlayer       = null;
    let vimeoPlayer    = null;
    let hlsPlayer      = null;
    let hlsFatalRecoveryAttempts = 0;
    let vimeoCurrentTime = 0;
    let vimeoLastTime = 0;
    let surveySubmitted = false;
    let selectedSurveyValue = null;
    let bufferingShownByScript = false;
    let lowDataModeEnabled = constrainedNetwork;
    let hlsAppliedLowDataMode = false;

    const surveyPanel = document.getElementById('engagement-survey-panel');
    const surveySubmitBtn = document.getElementById('survey-submit-btn');
    const surveySkipBtn = document.getElementById('survey-skip-btn');
    const surveyStatusMsg = document.getElementById('survey-status-msg');
    const surveyResponseText = document.getElementById('survey-response-text');
    const surveyBadge = document.getElementById('survey-badge');
    const surveyOptionButtons = Array.from(document.querySelectorAll('.survey-option-btn'));
    const durationHint = document.getElementById('duration-hint');
    const askQuestionOpenButton = document.getElementById('ask-question-open-btn');
    const questionForm = document.getElementById('message-form');

    /* ── helpers ─────────────────────────────────────────────────── */
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

    function loadLowDataPreference() {
        try {
            const stored = window.localStorage.getItem(lowDataStorageKey);
            if (stored === 'on') {
                lowDataModeEnabled = true;
                return;
            }

            if (stored === 'off') {
                lowDataModeEnabled = false;
                return;
            }
        } catch (_) {
            // Ignore storage failures and fall back to network-based default.
        }

        lowDataModeEnabled = constrainedNetwork;
    }

    function persistLowDataPreference() {
        try {
            window.localStorage.setItem(lowDataStorageKey, lowDataModeEnabled ? 'on' : 'off');
        } catch (_) {
            // Ignore storage failures; playback behavior still updates in memory.
        }
    }

    function setBufferingState(visible, label = 'Buffering video…') {
        if (!bufferIndicator) return;

        if (bufferLabel) {
            bufferLabel.textContent = label;
        }

        if (visible) {
            bufferingShownByScript = true;
            bufferIndicator.classList.remove('hidden');
        } else if (bufferingShownByScript) {
            bufferIndicator.classList.add('hidden');
            bufferingShownByScript = false;
        }
    }

    function updateLowBitrateUi() {
        if (!lowBitrateToggle) return;

        lowBitrateToggle.textContent = `Data Saver: ${lowDataModeEnabled ? 'On' : 'Off'}`;
        lowBitrateToggle.setAttribute('aria-pressed', lowDataModeEnabled ? 'true' : 'false');
        lowBitrateToggle.classList.toggle('border-emerald-500/60', lowDataModeEnabled);
        lowBitrateToggle.classList.toggle('text-emerald-300', lowDataModeEnabled);
        lowBitrateToggle.classList.toggle('bg-emerald-500/10', lowDataModeEnabled);
    }

    loadLowDataPreference();

    function applyHlsBitratePolicy() {
        if (!hlsPlayer) {
            return;
        }

        const levels = Array.isArray(hlsPlayer.levels) ? hlsPlayer.levels : [];
        if (!levels.length) {
            return;
        }

        hlsAppliedLowDataMode = lowDataModeEnabled;
        const lowCapBitrate = 800000;

        if (lowDataModeEnabled) {
            let capIndex = 0;
            for (let i = 0; i < levels.length; i += 1) {
                if ((levels[i]?.bitrate || 0) <= lowCapBitrate) {
                    capIndex = i;
                }
            }

            hlsPlayer.autoLevelCapping = capIndex;
            hlsPlayer.nextAutoLevel = capIndex;
            hlsPlayer.startLevel = Math.min(capIndex, 1);
            hlsPlayer.loadLevel = capIndex;
            if (streamModeHint) {
                streamModeHint.textContent = 'Using lower bitrate for smoother playback on constrained networks.';
            }
            return;
        }

        hlsPlayer.autoLevelCapping = -1;
        hlsPlayer.nextAutoLevel = -1;
        hlsPlayer.startLevel = -1;
        if (streamModeHint) {
            streamModeHint.textContent = 'Adaptive quality is balancing visual quality and stability.';
        }
    }

    function bindVideoBufferEvents(video) {
        if (!video || video.dataset.bufferEventsBound === '1') {
            return;
        }

        video.dataset.bufferEventsBound = '1';

        video.addEventListener('loadstart', () => setBufferingState(true, 'Loading video…'));
        video.addEventListener('waiting', () => setBufferingState(true, 'Buffering video…'));
        video.addEventListener('stalled', () => setBufferingState(true, 'Network is slow. Rebuffering…'));
        video.addEventListener('canplay', () => setBufferingState(false));
        video.addEventListener('canplaythrough', () => setBufferingState(false));
        video.addEventListener('playing', () => setBufferingState(false));
        video.addEventListener('ended', () => setBufferingState(false));
        video.addEventListener('error', () => setBufferingState(false));
    }

    function applyQuestionPrefillFromQuery() {
        if (!questionForm) {
            return;
        }

        const params = new URLSearchParams(window.location.search);
        const prefill = {
            body: params.get('question') || params.get('q') || '',
            referenceUrl: params.get('reference_url') || params.get('ref_url') || '',
            start: params.get('reference_start') || params.get('ref_start') || '',
            end: params.get('reference_end') || params.get('ref_end') || '',
            note: params.get('reference_note') || params.get('ref_note') || '',
        };

        const bodyField = questionForm.querySelector('textarea[name="body"]');
        const referenceUrlField = questionForm.querySelector('input[name="reference_url"]');
        const startField = questionForm.querySelector('input[name="reference_start_seconds"]');
        const endField = questionForm.querySelector('input[name="reference_end_seconds"]');
        const noteField = questionForm.querySelector('input[name="reference_note"]');

        if (bodyField && prefill.body) bodyField.value = prefill.body;
        if (referenceUrlField && prefill.referenceUrl) referenceUrlField.value = prefill.referenceUrl;
        if (startField && prefill.start) startField.value = prefill.start;
        if (endField && prefill.end) endField.value = prefill.end;
        if (noteField && prefill.note) noteField.value = prefill.note;

        const shouldAutoOpen = Boolean(prefill.body || prefill.referenceUrl || prefill.note);
        if (shouldAutoOpen && askQuestionOpenButton) {
            setTimeout(() => {
                askQuestionOpenButton.click();
            }, 0);
        }
    }

    applyQuestionPrefillFromQuery();

    function hydrateNativeVideoSource(video) {
        if (!video || video.dataset.sourceHydrated === '1') {
            return;
        }

        const deferredSources = video.querySelectorAll('source[data-src]');
        if (!deferredSources.length) {
            return;
        }

        deferredSources.forEach((source) => {
            source.src = source.dataset.src || '';
            source.removeAttribute('data-src');
        });

        video.dataset.sourceHydrated = '1';
        video.load();
    }

    function formatTime(seconds) {
        const safe = Math.max(0, Math.floor(seconds || 0));
        const mins = Math.floor(safe / 60);
        const secs = safe % 60;
        return `${mins}:${String(secs).padStart(2, '0')}`;
    }

    function setEffectiveDuration(seconds) {
        const parsed = Math.max(0, Math.floor(seconds || 0));
        if (parsed <= 0) return;
        if (duration === parsed) return;

        duration = parsed;

        if (durationHint) {
            durationHint.innerHTML = `${duration}s video &middot; must watch {{ $mustWatch }}%`;
        }

        updateProgressUi(getCurrentPlaybackSeconds());
    }

    function getCurrentPlaybackSeconds() {
        if (isYouTube && ytPlayer && typeof ytPlayer.getCurrentTime === 'function') {
            return ytPlayer.getCurrentTime() || 0;
        }

        if (isVimeo) {
            return vimeoCurrentTime || 0;
        }

        const video = document.getElementById('ad-video');
        return video?.currentTime || 0;
    }

    function updateProgressUi(currentSeconds) {
        const watched = Math.max(0, Math.floor(currentSeconds || 0));
        const clamped = duration > 0 ? Math.min(duration, watched) : watched;
        const pct = duration > 0 ? Math.min(100, (clamped / duration) * 100) : 0;
        progressBar.style.width = pct + '%';
        if (timerText) {
            timerText.textContent = `${formatTime(clamped)} / ${formatTime(duration)}`;
        }
    }

    function startUiTimer(getCurrentTime) {
        if (uiTimer) return;
        uiTimer = setInterval(() => {
            if (completed) return;
            updateProgressUi(getCurrentTime());
        }, 250);
    }

    function stopUiTimer() {
        if (!uiTimer) return;
        clearInterval(uiTimer);
        uiTimer = null;
    }

    function showReplayButton() {
        if (replayWrap) {
            replayWrap.classList.remove('hidden');
        }
    }

    async function replayFromStart() {
        try {
            if (isYouTube && ytPlayer) {
                ytPlayer.seekTo(0, true);
                ytPlayer.playVideo();
                return;
            }

            if (isVimeo && vimeoPlayer) {
                await vimeoPlayer.setCurrentTime(0);
                await vimeoPlayer.play();
                return;
            }

            const video = document.getElementById('ad-video');
            if (video) {
                video.currentTime = 0;
                await video.play();
            }
        } catch (_) {
            showStatus('Replay could not start. Please refresh and try again.', 'error');
        }
    }

    function updateSurveySelectionUi() {
        surveyOptionButtons.forEach((btn) => {
            const isSelected = btn.dataset.value === selectedSurveyValue;
            btn.classList.toggle('border-emerald-500', isSelected);
            btn.classList.toggle('bg-emerald-500/10', isSelected);
            btn.classList.toggle('text-white', isSelected);
        });
        if (surveySubmitBtn) {
            surveySubmitBtn.disabled = !selectedSurveyValue || surveySubmitted;
        }
    }

    function revealSurveyPanel() {
        if (!surveyPanel || surveySubmitted) return;
        if (!surveyPayload || !Array.isArray(surveyPayload.options) || surveyPayload.options.length < 2) return;
        surveyPanel.classList.remove('hidden');
        surveyStatusMsg.textContent = 'Pick an option, then submit.';
    }

    async function submitSurveyResponse() {
        if (!sessionId || !selectedSurveyValue || surveySubmitted) return;

        surveySubmitBtn.disabled = true;
        surveyStatusMsg.textContent = 'Submitting...';

        try {
            const res = await post(`/voter/session/${sessionId}/survey`, {
                response_value: selectedSurveyValue,
                response_text: surveyResponseText?.value || null,
            });

            if (res.error) {
                surveyStatusMsg.textContent = res.error;
                updateSurveySelectionUi();
                return;
            }

            surveySubmitted = true;
            if (surveyBadge) {
                surveyBadge.textContent = 'Submitted';
                surveyBadge.className = 'text-[11px] px-2 py-1 rounded-full bg-emerald-900/50 text-emerald-300 border border-emerald-500/40';
            }
            surveyStatusMsg.textContent = res.message || 'Response submitted.';
            surveyOptionButtons.forEach((btn) => btn.disabled = true);
            if (surveyResponseText) surveyResponseText.disabled = true;
            if (surveySubmitBtn) surveySubmitBtn.disabled = true;
            if (surveySkipBtn) surveySkipBtn.disabled = true;
        } catch (e) {
            surveyStatusMsg.textContent = 'Could not submit right now. Please try again.';
            updateSurveySelectionUi();
        }
    }

    function startHeartbeat(getCurrentTime) {
        if (heartbeatTimer) return;
        heartbeatTimer = setInterval(async () => {
            if (!sessionId || completed) return;
            const watched = Math.floor(getCurrentTime());
            updateProgressUi(watched);
            try {
                const res = await post(`/voter/session/${sessionId}/progress`, {
                    seconds_watched: watched,
                    media_duration_seconds: duration > 0 ? duration : initialDuration,
                });
                // Update progress bar from server-reported percentage if available, else calculate locally
                const pct = res.watched_pct !== undefined
                    ? Math.min(100, res.watched_pct)
                    : (duration > 0 ? Math.min(100, (watched / duration) * 100) : 0);
                progressBar.style.width = pct + '%';

                // Server auto-completed the session because threshold was reached
                if (res.auto_completed && !completed) {
                    completed = true;
                    clearInterval(heartbeatTimer);
                    clearInterval(antiSkipTimer);
                    stopUiTimer();
                    updateProgressUi(duration);
                    revealSurveyPanel();
                    showReplayButton();
                    if (res.qualified) {
                        showStatus(`\u{1F389} You earned $${parseFloat(res.payout_earned).toFixed(2)}! Payment is being processed.`, 'success');
                        statusMsg.innerHTML += ` <a href="${dashboardUrl}" class="underline text-emerald-400 ml-2">View earnings \u2192</a>`;
                        statusMsg.innerHTML += ' <span class="text-slate-300 ml-2">Replay available below.</span>';
                    } else {
                        showStatus('You watched enough \u2014 but did not meet the full qualifying threshold. No payout this time.', 'info');
                    }
                }
            } catch (_) { /* silent — next tick will retry */ }
        }, 5000);
    }

    async function handleVideoEnded(actualPlaybackSeconds) {
        // If heartbeat already qualified and credited the session, just show a tidy message
        if (completed) {
            showStatus('\u2713 Video finished \u2014 earnings already credited to your wallet.', 'success');
            return;
        }
        if (!sessionId) return;
        completed = true;
        clearInterval(heartbeatTimer);
        clearInterval(antiSkipTimer);
        stopUiTimer();
        // Use actual playback time if provided, fallback to the server-side duration
        const total = Math.floor(actualPlaybackSeconds > 0 ? actualPlaybackSeconds : duration);
        updateProgressUi(total);
        showReplayButton();
        try {
            const baseUrl = '{{ url("/voter/session") }}';
            const res = await post(`${baseUrl}/${sessionId}/complete`, {
                total_seconds_watched: total,
                media_duration_seconds: duration > 0 ? duration : initialDuration,
            });
            revealSurveyPanel();
            if (res.already_completed) {
                // Heartbeat beat us to it — earnings already recorded
                showStatus('\u2713 Video finished \u2014 earnings already credited to your wallet.', 'success');
                statusMsg.innerHTML += ' <a href="{{ route("voter.dashboard") }}" class="underline text-emerald-400 ml-2">View earnings →</a>';
                statusMsg.innerHTML += ' <span class="text-slate-300 ml-2">Replay available below.</span>';
            } else if (res.qualified) {
                showStatus(`\u{1F389} You earned $${parseFloat(res.payout_earned).toFixed(2)}! Payment is being processed.`, 'success');
                statusMsg.innerHTML += ' <a href="{{ route("voter.dashboard") }}" class="underline text-emerald-400 ml-2">View earnings →</a>';
                statusMsg.innerHTML += ' <span class="text-slate-300 ml-2">Replay available below.</span>';
            } else {
                showStatus('Video ended \u2014 watch at least {{ $mustWatch }}% to earn a payout.', 'info');
            }
        } catch (e) {
            showStatus('Error recording completion. Contact support.', 'error');
        }
    }

    /* ── YouTube IFrame API path ─────────────────────────────────── */
    if (isYouTube) {
        // YouTube calls this global when the API script loads
        window.onYouTubeIframeAPIReady = function () {
            ytPlayer = new YT.Player('yt-player-container', {
                height: '100%',
                width:  '100%',
                videoId: videoId,
                playerVars: {
                    enablejsapi:    1,
                    rel:            0,
                    fs:             0,       // disable fullscreen button
                    modestbranding: 1,
                    playsinline:    1,
                    controls:       0,       // 🔒 FRAUD PREVENTION: Hide all controls to prevent seeking
                    disablekb:      1,       // disable keyboard controls (spacebar, arrow keys)
                    iv_load_policy: 3,       // hide video annotations
                    origin:         window.location.origin,
                },
                events: {
                    onReady: function () {
                        if (ytPlayer && typeof ytPlayer.getDuration === 'function') {
                            const ytDuration = Math.floor(ytPlayer.getDuration() || 0);
                            if (ytDuration > 0) {
                                setEffectiveDuration(ytDuration);
                            }
                        }
                    },
                    onStateChange: function (e) {
                        if (e.data === YT.PlayerState.PLAYING) {
                            // Show control blocker to prevent any clicking on video
                            document.getElementById('control-blocker').classList.remove('hidden');
                            startHeartbeat(() => ytPlayer.getCurrentTime() || 0);
                            startUiTimer(() => ytPlayer.getCurrentTime() || 0);
                            // Anti-skip: poll every 500ms for aggressive detection
                            if (!antiSkipTimer) {
                                antiSkipTimer = setInterval(() => {
                                    if (!ytPlayer || completed) return;
                                    const t = ytPlayer.getCurrentTime() || 0;
                                    // If user somehow skips forward more than 1.5 seconds, rewind
                                    if (t > lastTime + 1.5) {
                                        console.warn('⚠️ Skip detected - rewinding');
                                        ytPlayer.seekTo(lastTime, true);
                                        ytPlayer.playVideo(); // ensure it continues playing
                                    } else {
                                        lastTime = t;
                                    }
                                }, 500);
                            }
                        } else if (e.data === YT.PlayerState.PAUSED) {
                            // Prevent manual pausing - auto-resume
                            if (!completed && ytPlayer) {
                                console.warn('⚠️ Pause detected - auto-resuming');
                                setTimeout(() => ytPlayer.playVideo(), 100);
                            }
                        } else if (e.data === YT.PlayerState.ENDED) {
                            handleVideoEnded(ytPlayer.getCurrentTime() || 0);
                        }
                    }
                }
            });
        };

        // Inject the YouTube IFrame API script
        var ytScript  = document.createElement('script');
        ytScript.src  = 'https://www.youtube.com/iframe_api';
        document.head.appendChild(ytScript);

        // Overlay click → start session then play
        overlay.addEventListener('click', async () => {
            overlay.style.display = 'none';
            try {
                const startUrl = '{{ url("/voter/watch") }}/' + encodeURIComponent(token) + '/start';
                const res = await post(startUrl, {});
                if (res.error) { showStatus(res.error, 'error'); overlay.style.display = ''; return; }
                sessionId = res.session_id;
                window.sessionId = sessionId; // Expose for report forms
                if (ytPlayer && typeof ytPlayer.playVideo === 'function') {
                    ytPlayer.playVideo();
                }
            } catch (e) {
                showStatus('Could not start session. Please try again.', 'error');
                overlay.style.display = '';
            }
        });
    }

    /* ── Vimeo Player API path ───────────────────────────────────── */
    function loadVimeoApi() {
        if (window.Vimeo && window.Vimeo.Player) {
            return Promise.resolve();
        }

        return new Promise((resolve, reject) => {
            const existing = document.querySelector('script[data-vimeo-api="1"]');
            if (existing) {
                existing.addEventListener('load', () => resolve(), { once: true });
                existing.addEventListener('error', () => reject(new Error('Vimeo API failed to load')), { once: true });
                return;
            }

            const script = document.createElement('script');
            script.src = 'https://player.vimeo.com/api/player.js';
            script.setAttribute('data-vimeo-api', '1');
            script.onload = () => resolve();
            script.onerror = () => reject(new Error('Vimeo API failed to load'));
            document.head.appendChild(script);
        });
    }

    async function initVimeoPlayer() {
        if (vimeoPlayer || !isVimeo || !vimeoId) return;

        await loadVimeoApi();
        vimeoPlayer = new window.Vimeo.Player('vimeo-player-container', {
            id: parseInt(vimeoId, 10),
            controls: false,
            byline: false,
            title: false,
            portrait: false,
            playsinline: true,
            dnt: true,
        });

        vimeoPlayer.on('timeupdate', async ({ seconds }) => {
            if (completed) return;
            vimeoCurrentTime = seconds || 0;

            // Anti-skip enforcement for Vimeo player.
            if (vimeoCurrentTime > vimeoLastTime + 1.5) {
                try {
                    await vimeoPlayer.setCurrentTime(vimeoLastTime);
                } catch (_) {
                    // Ignore transient seek errors from the player.
                }
            } else {
                vimeoLastTime = vimeoCurrentTime;
            }
        });

        const vimeoDuration = await vimeoPlayer.getDuration().catch(() => 0);
        if (vimeoDuration > 0) {
            setEffectiveDuration(vimeoDuration);
        }

        vimeoPlayer.on('pause', () => {
            if (!completed) {
                setTimeout(() => {
                    if (!completed && vimeoPlayer) {
                        vimeoPlayer.play().catch(() => {});
                    }
                }, 100);
            }
        });

        vimeoPlayer.on('ended', async () => {
            const played = await vimeoPlayer.getCurrentTime().catch(() => vimeoCurrentTime || duration);
            handleVideoEnded(played || duration);
        });
    }

    if (isVimeo) {
        initVimeoPlayer().catch(() => {
            showStatus('Could not load Vimeo player. Please refresh and try again.', 'error');
        });

        overlay.addEventListener('click', async () => {
            overlay.style.display = 'none';
            try {
                const startUrl = '{{ url("/voter/watch") }}/' + encodeURIComponent(token) + '/start';
                const res = await post(startUrl, {});
                if (res.error) { showStatus(res.error, 'error'); overlay.style.display = ''; return; }
                sessionId = res.session_id;
                window.sessionId = sessionId;
                document.getElementById('control-blocker').classList.remove('hidden');

                await initVimeoPlayer();
                if (vimeoPlayer) {
                    vimeoPlayer.play().catch(() => {});
                }

                startHeartbeat(() => vimeoCurrentTime || 0);
                startUiTimer(() => vimeoCurrentTime || 0);
            } catch (e) {
                showStatus('Could not start session. Please try again.', 'error');
                overlay.style.display = '';
            }
        });
    }

    /* ── HLS player helpers ─────────────────────────────────────── */
    function loadHlsApi() {
        if (window.Hls) {
            return Promise.resolve();
        }

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
        if (!isHls || !video || !mediaStreamUrl) return;

        bindVideoBufferEvents(video);

        if (video.canPlayType('application/vnd.apple.mpegurl')) {
            video.src = mediaStreamUrl;
            if (streamModeHint) {
                streamModeHint.textContent = lowDataModeEnabled
                    ? 'Browser-native HLS detected. Data Saver can be limited by browser support.'
                    : 'Browser-native HLS detected.';
            }
            return;
        }

        await loadHlsApi();
        if (!window.Hls || !window.Hls.isSupported()) {
            throw new Error('HLS playback not supported by this browser');
        }

        if (hlsPlayer) {
            hlsPlayer.destroy();
        }

        hlsFatalRecoveryAttempts = 0;
        hlsPlayer = new window.Hls({
            enableWorker: true,
            lowLatencyMode: false,
            backBufferLength: 30,
            maxBufferLength: 20,
            maxMaxBufferLength: 40,
            maxBufferHole: 0.5,
            highBufferWatchdogPeriod: 2,
            nudgeMaxRetry: 8,
            manifestLoadingTimeOut: 15000,
            levelLoadingTimeOut: 15000,
            fragLoadingTimeOut: 20000,
            capLevelToPlayerSize: true,
            testBandwidth: true,
            abrEwmaFastLive: 3,
            abrEwmaSlowLive: 9,
            abrEwmaFastVoD: 3,
            abrEwmaSlowVoD: 9,
            startLevel: lowDataModeEnabled ? 0 : -1,
        });

        hlsPlayer.on(window.Hls.Events.MANIFEST_PARSED, function () {
            applyHlsBitratePolicy();
        });

        hlsPlayer.on(window.Hls.Events.LEVEL_SWITCHED, function (_event, data) {
            if (!streamModeHint || !data || typeof data.level !== 'number') {
                return;
            }

            const currentLevel = hlsPlayer.levels?.[data.level];
            if (currentLevel?.height) {
                const dataSaverText = hlsAppliedLowDataMode ? 'Data Saver active' : 'Adaptive mode';
                streamModeHint.textContent = `${dataSaverText} • ${currentLevel.height}p`;
            }
        });

        hlsPlayer.on(window.Hls.Events.ERROR, function (_event, data) {
            if (!data?.fatal) {
                return;
            }

            if (data.type === window.Hls.ErrorTypes.NETWORK_ERROR && hlsFatalRecoveryAttempts < 3) {
                hlsFatalRecoveryAttempts += 1;
                setBufferingState(true, 'Network issue detected. Retrying stream…');
                hlsPlayer.startLoad();
                return;
            }

            if (data.type === window.Hls.ErrorTypes.MEDIA_ERROR && hlsFatalRecoveryAttempts < 3) {
                hlsFatalRecoveryAttempts += 1;
                setBufferingState(true, 'Recovering media playback…');
                hlsPlayer.recoverMediaError();
                return;
            }

            setBufferingState(false);
            showStatus('Video stream interrupted. Please refresh and try again.', 'error');
        });

        hlsPlayer.loadSource(mediaStreamUrl);
        hlsPlayer.attachMedia(video);
    }

    /* ── Native HTML5 video path ─────────────────────────────────── */
    if (playerMode === 'native' || playerMode === 'hls') {
        const video = document.getElementById('ad-video');
        let nativeLastTime = 0;

        bindVideoBufferEvents(video);

        if (streamControls && isHls) {
            streamControls.classList.remove('hidden');
            streamControls.classList.add('flex');
            if (constrainedNetwork && streamModeHint) {
                streamModeHint.textContent = 'Constrained network detected. Data Saver enabled by default.';
            }
            updateLowBitrateUi();
            lowBitrateToggle?.addEventListener('click', () => {
                lowDataModeEnabled = !lowDataModeEnabled;
                persistLowDataPreference();
                updateLowBitrateUi();
                applyHlsBitratePolicy();
            });
        }

        video.addEventListener('error', function() {
            showStatus('Video could not be loaded. Please refresh. If this continues, report the issue so we can repair the media link.', 'error');
        });

        video.addEventListener('loadedmetadata', function() {
            if (Number.isFinite(video.duration) && video.duration > 0) {
                setEffectiveDuration(video.duration);
            }
        });

        // Prevent seeking on native video
        video.addEventListener('seeking', function() {
            if (!completed && nativeLastTime > 0) {
                const delta = Math.abs(this.currentTime - nativeLastTime);
                if (delta > 1.5) {
                    console.warn('⚠️ Seek detected - blocking');
                    this.currentTime = nativeLastTime;
                }
            }
        });

        // Track time and prevent skipping
        video.addEventListener('timeupdate', function() {
            if (!completed) {
                nativeLastTime = this.currentTime;
            }
        });

        // Prevent pausing
        video.addEventListener('pause', function() {
            if (!completed && video.currentTime > 0 && video.currentTime < video.duration - 1) {
                console.warn('⚠️ Pause detected - auto-resuming');
                setTimeout(() => video.play(), 100);
            }
        });

        overlay.addEventListener('click', async () => {
            overlay.style.display = 'none';
            setBufferingState(true, 'Starting stream…');
            try {
                const startUrl = '{{ url("/voter/watch") }}/' + encodeURIComponent(token) + '/start';
                const res = await post(startUrl, {});
                if (res.error) {
                    showStatus(res.error, 'error');
                    overlay.style.display = '';
                    setBufferingState(false);
                    return;
                }
                sessionId = res.session_id;
                window.sessionId = sessionId; // Expose for report forms
                // Show control blocker
                document.getElementById('control-blocker').classList.remove('hidden');
                if (isHls) {
                    await initHls(video);
                } else {
                    hydrateNativeVideoSource(video);
                }
                video.play();
                startHeartbeat(() => video.currentTime || 0);
                startUiTimer(() => video.currentTime || 0);
            } catch (e) {
                showStatus('Could not start session. Please try again.', 'error');
                overlay.style.display = '';
                setBufferingState(false);
            }
        });

        video.addEventListener('ended', () => handleVideoEnded(video.currentTime || 0));

        // Prevent skipping forward
        video.addEventListener('timeupdate', () => {
            if (video.currentTime > nativeLastTime + 2) {
                video.currentTime = nativeLastTime;
            } else {
                nativeLastTime = video.currentTime;
            }
        });
    }

    surveyOptionButtons.forEach((btn) => {
        btn.addEventListener('click', () => {
            if (surveySubmitted) return;
            selectedSurveyValue = btn.dataset.value || null;
            updateSurveySelectionUi();
        });
    });

    if (surveySubmitBtn) {
        surveySubmitBtn.addEventListener('click', submitSurveyResponse);
    }

    if (surveySkipBtn) {
        surveySkipBtn.addEventListener('click', () => {
            if (!surveyPanel || surveySubmitted) return;
            surveyPanel.classList.add('hidden');
        });
    }

    if (replayBtn) {
        replayBtn.addEventListener('click', replayFromStart);
    }
})();
</script>

{{-- ── Office Info Modal JS ─────────────────────────────────────────────── --}}
<script>
(function () {
    const modal       = document.getElementById('office-info-modal');
    const loadingEl   = document.getElementById('om-loading');
    const errorEl     = document.getElementById('om-error');
    const contentEl   = document.getElementById('om-content');

    function show(el)  { el.style.removeProperty('display'); el.classList.remove('hidden'); }
    function hide(el)  { el.classList.add('hidden'); }

    function setText(id, value) {
        const el = document.getElementById(id);
        if (el) el.textContent = value;
    }

    function setList(listId, wrapperId, items) {
        const wrapper = document.getElementById(wrapperId);
        const list    = document.getElementById(listId);
        if (! wrapper || ! list || ! Array.isArray(items) || items.length === 0) return;
        list.innerHTML = items
            .map(item => `<li class="flex items-start gap-2"><span class="text-emerald-400 mt-0.5 shrink-0">▸</span><span>${escHtml(item)}</span></li>`)
            .join('');
        show(wrapper);
    }

    function escHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function populateModal(data) {
        const p  = data.politician;
        const op = data.office_profile;

        // Header
        setText('om-office-title', op.office_title || p.political_office || 'Unknown Office');
        const jurisdictionParts = [op.jurisdiction, p.state].filter(Boolean);
        setText('om-jurisdiction', jurisdictionParts.join(' · ') || '');

        // Stats
        if (op.governance_level) {
            setText('om-governance-level', op.governance_level.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()));
            show(document.getElementById('om-stat-level'));
        }
        if (op.salary_range) {
            setText('om-salary', op.salary_range + ' / yr');
            show(document.getElementById('om-stat-salary'));
        }
        if (op.term_length_years) {
            setText('om-term', op.term_length_years + (op.term_length_years === 1 ? ' year' : ' years'));
            show(document.getElementById('om-stat-term'));
        }
        if (op.how_elected_or_appointed) {
            setText('om-how-elected', op.how_elected_or_appointed);
            show(document.getElementById('om-stat-how'));
        }

        // Text blocks
        if (op.role_summary) {
            setText('om-role-summary', op.role_summary);
            show(document.getElementById('om-summary-block'));
        }
        if (op.community_impact) {
            setText('om-community-impact', op.community_impact);
            show(document.getElementById('om-impact-block'));
        }

        // Lists
        setList('om-key-duties', 'om-duties-block', op.key_duties);
        setList('om-powers-and-limits', 'om-powers-block', op.powers_and_limits);

        // Footer / source
        const footerBlock = document.getElementById('om-footer-block');
        const hasSalaryNote = Boolean(op.salary_source_note);
        const hasSourceUrl  = Boolean(op.source_url);
        if (hasSalaryNote || hasSourceUrl) {
            if (hasSalaryNote) setText('om-salary-note', 'Salary source: ' + op.salary_source_note);
            if (hasSourceUrl) {
                const link = document.getElementById('om-source-url');
                if (link) { link.href = op.source_url; show(link); }
            }
            show(footerBlock);
        }

        // Verified
        if (op.is_verified) {
            show(document.getElementById('om-verified-badge'));
        }

        hide(loadingEl);
        show(contentEl);
    }

    // Fetch the office profile from the public API
    let _cache = null;

    window.openOfficeInfoModal = function (politicianUuid) {
        // Reset state
        hide(contentEl);
        hide(errorEl);
        show(loadingEl);
        modal.style.removeProperty('display');
        modal.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');

        if (_cache) {
            populateModal(_cache);
            return;
        }

        fetch('/api/v1/politicians/' + encodeURIComponent(politicianUuid) + '/office-profile', {
            headers: { 'Accept': 'application/json' },
        })
        .then(function (res) {
            if (! res.ok) throw new Error('not_found');
            return res.json();
        })
        .then(function (data) {
            _cache = data;
            populateModal(data);
        })
        .catch(function () {
            hide(loadingEl);
            show(errorEl);
        });
    };

    window.closeOfficeInfoModal = function () {
        modal.style.setProperty('display', 'none', 'important');
        document.body.classList.remove('overflow-hidden');
    };

    // Close on backdrop click
    modal.addEventListener('click', function (e) {
        if (e.target === modal) closeOfficeInfoModal();
    });

    // Close on Escape
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && ! modal.style.display.includes('none') === false) {
            closeOfficeInfoModal();
        }
    });
})();
</script>
@endpush
@endsection
