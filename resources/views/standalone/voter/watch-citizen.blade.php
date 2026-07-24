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
        </p>
    </div>

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
                @if($campaign->media_url)
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
    const videoId     = '{{ $videoId ?? '' }}';
    const vimeoId     = '{{ $vimeoId ?? '' }}';

    let completed = false;
    let ytPlayer  = null;
    let vimeoPlayer = null;
    let lastTime  = 0;

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
            if (res.qualified) {
                showStatus(`🎉 You earned $${parseFloat(res.payout_earned).toFixed(2)}! Credited to pending earnings.`, 'success');
                statusMsg.innerHTML += ` <a href="${adRoomUrl}" class="underline text-emerald-400 ml-2">Watch more →</a>`;
            } else if (res.error) {
                showStatus(res.error, 'error');
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

    /* ── Native HTML5 video path ──────────────────────────────────── */
    if (playerMode === 'native') {
        const video = document.getElementById('ad-video');

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

        overlay.addEventListener('click', () => {
            overlay.style.display = 'none';
            document.getElementById('control-blocker').classList.remove('hidden');
            video.play();
        });
    }
})();
</script>
@endpush
@endsection
