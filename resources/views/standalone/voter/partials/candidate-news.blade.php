{{-- ========================================================================
     Candidate News Partial — 3-card grid of most recent local candidate news
     Scoped to the voter's state. Hidden entirely when no articles are available.
     ======================================================================== --}}
@if($candidateNews->isNotEmpty())
<div>
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-semibold text-white">Local Candidate News</h2>
        <span class="text-xs text-slate-500">Updates every 15 min</span>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        @foreach($candidateNews as $article)
        <div class="bg-slate-800/50 border border-slate-700/60 rounded-2xl overflow-hidden flex flex-col hover:border-slate-600/80 transition">

            {{-- Thumbnail --}}
            <a href="{{ $article->source_url }}"
               target="_blank"
               rel="noopener noreferrer"
               class="block shrink-0 overflow-hidden">
                @if($article->image_url)
                    <img src="{{ $article->image_url }}"
                         alt="{{ $article->headline }}"
                         loading="lazy"
                         class="w-full h-36 object-cover hover:scale-105 transition duration-300">
                @else
                    <div class="w-full h-36 bg-slate-700/60 flex items-center justify-center">
                        <svg class="w-8 h-8 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                  d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 12h6"/>
                        </svg>
                    </div>
                @endif
            </a>

            {{-- Content --}}
            <div class="p-4 flex flex-col flex-1">

                {{-- Source badge --}}
                @if($article->source_name)
                <span class="text-[10px] text-slate-500 uppercase tracking-wide font-medium mb-1.5">
                    {{ $article->source_name }}
                </span>
                @endif

                {{-- Headline → article link --}}
                <a href="{{ $article->source_url }}"
                   target="_blank"
                   rel="noopener noreferrer"
                   class="text-white font-semibold text-sm leading-snug hover:text-emerald-400 transition line-clamp-2 mb-2">
                    {{ $article->headline }}
                </a>

                {{-- Snippet --}}
                @if($article->snippet)
                <p class="text-slate-400 text-xs leading-relaxed line-clamp-2 mb-3 flex-1">
                    {{ $article->snippet }}
                </p>
                @else
                    <div class="flex-1"></div>
                @endif

                {{-- Footer: time + politician profile link --}}
                <div class="flex items-center justify-between text-xs mt-auto pt-2 border-t border-slate-700/50">
                    <span class="text-slate-500">
                        {{ $article->published_at?->diffForHumans() ?? '—' }}
                    </span>
                    @if($article->politician?->slug)
                    <a href="{{ route('politician.public.show', $article->politician->slug) }}"
                       class="text-blue-400 hover:text-blue-300 font-medium transition truncate max-w-[55%] text-right">
                        {{ $article->politician->full_name }} →
                    </a>
                    @elseif($article->candidate_name)
                    <span class="text-slate-500 truncate max-w-[55%] text-right">
                        {{ $article->candidate_name }}
                    </span>
                    @endif
                </div>

            </div>
        </div>
        @endforeach
    </div>
</div>
@endif
