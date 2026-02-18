@extends('layouts.app')

@section('title', 'Watch: ' . $campaign->title)

@section('content')
<div class="max-w-3xl mx-auto px-4 py-8">

    {{-- Campaign Header --}}
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-white">{{ $campaign->title }}</h1>
        <p class="text-slate-400 mt-1 text-sm">
            From <span class="text-emerald-400">{{ $campaign->politician->full_name ?? 'Unknown' }}</span>
            &middot; {{ $campaign->politician->political_office ?? '' }}
        </p>
    </div>

    {{-- Earn Banner --}}
    <div class="bg-emerald-900/40 border border-emerald-500/30 rounded-xl px-5 py-3 mb-6 flex items-center gap-3">
        <svg class="w-5 h-5 text-emerald-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <span class="text-emerald-300 text-sm font-medium">
            Earn <strong class="text-emerald-400">${{ number_format($payout, 2) }}</strong>
            for watching at least {{ $mustWatch }}% of this message
        </span>
    </div>

    {{-- Video Player --}}
    <div class="relative bg-black rounded-2xl overflow-hidden shadow-2xl" id="player-wrapper">
        <video
            id="ad-video"
            class="w-full aspect-video"
            controlsList="nodownload nofullscreen"
            disablePictureInPicture
            playsinline
            preload="metadata"
        >
            @if($campaign->media_url)
                <source src="{{ $campaign->media_url }}" type="video/mp4">
            @endif
            Your browser does not support HTML5 video.
        </video>

        {{-- Overlay before play --}}
        <div id="play-overlay" class="absolute inset-0 flex flex-col items-center justify-center bg-black/60 cursor-pointer">
            <div class="w-20 h-20 rounded-full bg-emerald-500/20 border-2 border-emerald-400 flex items-center justify-center mb-4">
                <svg class="w-10 h-10 text-emerald-400 ml-1" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M8 5v14l11-7z"/>
                </svg>
            </div>
            <p class="text-white font-semibold">Click to Play &amp; Earn</p>
            <p class="text-slate-400 text-sm mt-1">{{ $duration }}s video &middot; must watch {{ $mustWatch }}%</p>
        </div>

        {{-- Progress bar --}}
        <div id="progress-track" class="absolute bottom-0 left-0 right-0 h-1 bg-slate-700">
            <div id="progress-bar" class="h-full bg-emerald-500 transition-all duration-500" style="width:0%"></div>
        </div>
    </div>

    {{-- Status messages --}}
    <div id="status-msg" class="mt-5 hidden text-center py-4 px-6 rounded-xl"></div>

</div>

<meta name="watch-token" content="{{ $adToken->token }}">
<meta name="csrf-token" content="{{ csrf_token() }}">

@push('scripts')
<script>
(function () {
    const video       = document.getElementById('ad-video');
    const overlay     = document.getElementById('play-overlay');
    const progressBar = document.getElementById('progress-bar');
    const statusMsg   = document.getElementById('status-msg');
    const token       = document.querySelector('meta[name="watch-token"]').content;
    const csrf        = document.querySelector('meta[name="csrf-token"]').content;
    const duration    = {{ $duration ?? 0 }};
    const mustWatch   = {{ $mustWatch ?? 100 }};

    let sessionId     = null;
    let heartbeatTimer = null;
    let completed     = false;

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

    // Click overlay → start session
    overlay.addEventListener('click', async () => {
        overlay.style.display = 'none';
        try {
            const res = await post('{{ route("voter.watch.start", ["token" => "__T__"]) }}'.replace('__T__', token), {});
            if (res.error) { showStatus(res.error, 'error'); return; }
            sessionId = res.session_id;
            video.play();
            startHeartbeat();
        } catch (e) {
            showStatus('Could not start session. Please try again.', 'error');
        }
    });

    // Heartbeat every 5 s
    function startHeartbeat() {
        heartbeatTimer = setInterval(async () => {
            if (!sessionId || completed) return;
            const watched = Math.floor(video.currentTime);
            await post(`/voter/session/${sessionId}/progress`, { seconds_watched: watched });
            const pct = duration > 0 ? Math.min(100, (watched / duration) * 100) : 0;
            progressBar.style.width = pct + '%';
        }, 5000);
    }

    // Video ended → mark complete
    video.addEventListener('ended', async () => {
        if (!sessionId || completed) return;
        completed = true;
        clearInterval(heartbeatTimer);
        const total = Math.floor(video.duration);
        try {
            const res = await post(`/voter/session/${sessionId}/complete`, { total_seconds_watched: total });
            if (res.qualified) {
                showStatus(`🎉 You earned ${{ '$' }}${parseFloat(res.payout_earned).toFixed(2)}! Payment is being processed.`, 'success');
            } else {
                showStatus('Video ended — watch at least {{ $mustWatch }}% to earn a payout.', 'info');
            }
        } catch (e) {
            showStatus('Error recording completion. Contact support.', 'error');
        }
    });

    // Prevent skipping forward
    let lastTime = 0;
    video.addEventListener('timeupdate', () => {
        if (video.currentTime > lastTime + 2) {
            video.currentTime = lastTime;
        } else {
            lastTime = video.currentTime;
        }
    });
})();
</script>
@endpush
@endsection
