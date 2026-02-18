@extends('layouts.voter')

@section('title', 'Watch: ' . $campaign->title)

@section('content')
<div class="max-w-3xl mx-auto px-4 sm:px-6 py-8">

    {{-- Breadcrumb --}}
    <div class="mb-5">
        <a href="{{ route('voter.dashboard') }}" class="inline-flex items-center gap-1.5 text-slate-400 hover:text-emerald-400 text-sm transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            Back to Dashboard
        </a>
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
            @endif
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
    <div class="relative bg-black rounded-2xl overflow-hidden shadow-2xl ring-1 ring-slate-700/50" id="player-wrapper">
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
    <div id="status-msg" class="mt-5 hidden text-center py-4 px-6 rounded-2xl"></div>

    {{-- Disclaimer --}}
    <p class="mt-6 text-xs text-slate-600 text-center">This political advertisement was paid for by the campaign of {{ $campaign->politician->full_name ?? 'the sponsoring campaign' }}. Earnings are credited to your wallet upon verified completion and processed in your next batch payout.</p>

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
            const startUrl = '{{ url("/voter/watch") }}/' + encodeURIComponent(token) + '/start';
            const res = await post(startUrl, {});
            if (res.error) { showStatus(res.error, 'error'); overlay.style.display = ''; return; }
            sessionId = res.session_id;
            video.play();
            startHeartbeat();
        } catch (e) {
            showStatus('Could not start session. Please try again.', 'error');
            overlay.style.display = '';
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
            const baseUrl = '{{ url("/voter/session") }}';
            const res = await post(`${baseUrl}/${sessionId}/complete`, { total_seconds_watched: total });
            if (res.qualified) {
                showStatus(`\u{1F389} You earned $${parseFloat(res.payout_earned).toFixed(2)}! Payment is being processed.`, 'success');
                statusMsg.innerHTML += ' <a href="{{ route("voter.dashboard") }}" class="underline text-emerald-400 ml-2">View earnings →</a>';
            } else {
                showStatus('Video ended \u2014 watch at least {{ $mustWatch }}% to earn a payout.', 'info');
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
