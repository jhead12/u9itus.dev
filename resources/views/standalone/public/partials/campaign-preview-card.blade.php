@php
    $_ytId  = null;
    $_mUrl  = $campaign->media_url ?? '';
    $_isDirectVideo = preg_match('/\.(mp4|webm|ogg)(\?.*)?$/i', $_mUrl) === 1;
    if (preg_match('/youtu\.be\/([a-zA-Z0-9_-]+)/', $_mUrl, $_m)) {
        $_ytId = $_m[1];
    } elseif (preg_match('/[?&]v=([a-zA-Z0-9_-]+)/', $_mUrl, $_m)) {
        $_ytId = $_m[1];
    } elseif (preg_match('/\/embed\/([a-zA-Z0-9_-]+)/', $_mUrl, $_m)) {
        $_ytId = $_m[1];
    }

    $statusValue = $campaign->status?->value ?? (string) $campaign->status;
    $statusMap = [
        'active' => ['label' => 'Running', 'classes' => 'bg-emerald-500/15 text-emerald-300 border-emerald-500/20'],
        'scheduled' => ['label' => 'Scheduled', 'classes' => 'bg-blue-500/15 text-blue-300 border-blue-500/20'],
        'paused' => ['label' => 'Paused', 'classes' => 'bg-amber-500/15 text-amber-300 border-amber-500/20'],
        'completed' => ['label' => 'Completed', 'classes' => 'bg-slate-500/15 text-slate-300 border-slate-500/20'],
        'cancelled' => ['label' => 'Cancelled', 'classes' => 'bg-rose-500/15 text-rose-300 border-rose-500/20'],
    ];
    $statusUi = $statusMap[$statusValue] ?? ['label' => ucfirst(str_replace('_', ' ', $statusValue)), 'classes' => 'bg-slate-500/15 text-slate-300 border-slate-500/20'];

    $updateLabel = 'Updated ' . $campaign->updated_at?->diffForHumans();
    if ($statusValue === 'completed' && $campaign->completed_at) {
        $updateLabel = 'Completed ' . $campaign->completed_at->diffForHumans();
    } elseif ($statusValue === 'scheduled' && $campaign->scheduled_start_at) {
        $updateLabel = 'Starts ' . $campaign->scheduled_start_at->diffForHumans();
    } elseif ($statusValue === 'active' && $campaign->started_at) {
        $updateLabel = 'Running since ' . $campaign->started_at->diffForHumans();
    }
@endphp

<article class="bg-slate-800/40 border border-slate-700/40 rounded-xl overflow-hidden hover:border-slate-500 transition group">
    <div class="relative aspect-video bg-black">
        @if($_ytId)
            <iframe
                src="https://www.youtube-nocookie.com/embed/{{ $_ytId }}?rel=0&modestbranding=1&color=white&iv_load_policy=3"
                title="{{ e($campaign->title) }}"
                class="w-full h-full"
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                allowfullscreen
                loading="lazy"
            ></iframe>
        @elseif($_isDirectVideo)
            <video class="w-full h-full object-cover" controls preload="metadata" playsinline>
                <source src="{{ $_mUrl }}">
                Your browser does not support this video format.
            </video>
        @elseif($campaign->thumbnail_url)
            <img src="{{ $campaign->thumbnail_url }}" alt="{{ $campaign->title }}"
                 class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" />
            <div class="absolute inset-0 flex flex-col items-center justify-center bg-black/50">
                <div class="w-14 h-14 rounded-full bg-white/10 border-2 border-white/40 flex items-center justify-center">
                    <svg class="w-7 h-7 text-white ml-0.5" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                </div>
                <span class="mt-3 text-xs text-white/70 bg-black/40 px-3 py-1 rounded-full">Campaign preview</span>
            </div>
        @else
            <div class="w-full h-full flex flex-col items-center justify-center bg-slate-900/60">
                <svg class="w-12 h-12 text-slate-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                          d="M15 10l4.553-2.853A1 1 0 0121 8.004v7.992a1 1 0 01-1.447.857L15 14M3 8a2 2 0 012-2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8z"/>
                </svg>
                <span class="text-xs text-slate-500">Campaign video or update preview</span>
            </div>
        @endif

        <div class="absolute top-2 left-2 inline-flex items-center gap-1 text-[11px] font-semibold px-2.5 py-1 rounded-full border {{ $statusUi['classes'] }} z-10">
            {{ $statusUi['label'] }}
        </div>
        <div class="absolute top-2 right-2 bg-slate-900/85 text-white text-xs font-bold px-2 py-0.5 rounded-full shadow-lg pointer-events-none select-none z-10 border border-white/10">
            Public Preview
        </div>
    </div>

    <div class="p-4">
        <div class="flex items-start justify-between gap-3 mb-2">
            <h3 class="font-semibold text-white text-sm line-clamp-2">{{ $campaign->title }}</h3>
            <span class="text-[11px] text-slate-500 whitespace-nowrap">{{ $updateLabel }}</span>
        </div>

        @if($campaign->message_summary)
            <p class="text-xs text-slate-400 line-clamp-3 mb-3">{{ $campaign->message_summary }}</p>
        @endif

        <div class="flex items-center justify-between gap-3 text-xs text-slate-500 mb-3">
            <span>{{ number_format($campaign->views_completed ?? 0) }} views</span>
            @if($campaign->total_views_requested)
                <span>{{ number_format(max(0, $campaign->total_views_requested - $campaign->views_completed)) }} remaining</span>
            @endif
        </div>

        <a href="{{ auth()->check() ? route('dashboard') : route('register.voter') }}"
           class="p13-btn-primary inline-flex items-center justify-center gap-1.5 text-xs font-semibold px-3 py-2 rounded-lg transition w-full">
            <svg class="w-3.5 h-3.5 flex-shrink-0" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
            {{ auth()->check() ? 'Open dashboard to continue' : 'Create free account for full access' }}
        </a>
    </div>
</article>