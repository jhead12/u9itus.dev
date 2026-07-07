@extends('layouts.voter')

@section('title', 'Politicians I Follow')

@section('content')
<div class="max-w-4xl mx-auto py-8 px-4 sm:px-6 lg:px-8">

    <div class="mb-6">
        <h1 class="text-2xl font-bold text-white">Politicians I Follow</h1>
        <p class="mt-1 text-sm text-gray-400">Candidates and officials you've bookmarked for quick access.</p>
    </div>

    @if($favorites->isEmpty())
        <div class="rounded-xl border border-gray-700 bg-gray-800/50 p-12 text-center">
            <div class="text-4xl mb-3">⭐</div>
            <h3 class="text-lg font-medium text-white mb-1">No favorites yet</h3>
            <p class="text-sm text-gray-400">Browse the <a href="{{ route('politicians.directory') }}" class="text-indigo-400 hover:text-indigo-300 underline">politician directory</a> and tap "Follow" on any profile.</p>
        </div>
    @else
        <div class="grid gap-4 sm:grid-cols-2">
            @foreach($favorites as $politician)
                <div class="rounded-xl border border-gray-700 bg-gray-800/50 p-5 flex items-start gap-4">

                    {{-- Avatar --}}
                    <div class="flex-shrink-0">
                        @if($politician->profile_photo_url)
                            <img src="{{ $politician->profile_photo_url }}" alt="{{ $politician->full_name }}"
                                 class="h-12 w-12 rounded-full object-cover">
                        @else
                            <div class="h-12 w-12 rounded-full bg-indigo-600 flex items-center justify-center text-white font-bold text-lg">
                                {{ strtoupper(substr($politician->full_name, 0, 1)) }}
                            </div>
                        @endif
                    </div>

                    {{-- Info --}}
                    <div class="flex-1 min-w-0">
                        <p class="text-white font-semibold truncate">{{ $politician->full_name }}</p>
                        <p class="text-xs text-gray-400 mt-0.5">{{ $politician->political_office }}{{ $politician->state ? ', '.$politician->state : '' }}</p>

                        {{-- Badges --}}
                        @if($politician->publicBadges->isNotEmpty())
                            <div class="flex flex-wrap gap-1.5 mt-2">
                                @foreach($politician->publicBadges->take(4) as $badge)
                                    <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium text-white"
                                          style="background-color: {{ $badge->topic->badge_color ?? '#6366f1' }}">
                                        {{ $badge->topic->icon ?? '' }} {{ $badge->topic->name }}
                                    </span>
                                @endforeach
                            </div>
                        @endif

                        {{-- Actions --}}
                        <div class="flex items-center gap-3 mt-3">
                            @if($politician->slug)
                                <a href="{{ route('politician.public.show', $politician->slug) }}"
                                   class="text-xs text-indigo-400 hover:text-indigo-300">View profile →</a>
                            @endif

                            <form method="POST" action="{{ route('voter.favorites.destroy', $politician->id) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                        class="text-xs text-red-400 hover:text-red-300"
                                        onclick="return confirm('Unfollow {{ addslashes($politician->full_name) }}?')">
                                    Unfollow
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-6">
            {{ $favorites->links() }}
        </div>
    @endif

    {{-- ── Saved Articles ──────────────────────────────────────────────── --}}
    <div id="saved-articles" class="mt-10">
        <h2 class="text-xl font-bold text-white mb-1">Saved Articles</h2>
        <p class="text-sm text-slate-400 mb-6">News articles you\'ve bookmarked.</p>

        @if(isset($savedArticles) && $savedArticles->isNotEmpty())
        <div class="space-y-3">
            @foreach($savedArticles as $article)
            <div class="flex gap-4 bg-slate-800/50 border border-slate-700/50 rounded-xl p-4 items-start">
                @if($article->image_url)
                <img src="{{ $article->image_url }}" alt=""
                     class="w-16 h-14 object-cover rounded-lg shrink-0 bg-slate-700"
                     loading="lazy" onerror="this.style.display='none'">
                @else
                <div class="w-16 h-14 rounded-lg shrink-0 flex items-center justify-center text-xl bg-slate-700/60">📰</div>
                @endif
                <div class="min-w-0 flex-1">
                    <a href="{{ $article->source_url }}" target="_blank" rel="noopener noreferrer"
                       class="text-sm font-medium text-slate-200 hover:text-white line-clamp-2 leading-snug transition">
                        {{ $article->headline }}
                    </a>
                    <p class="mt-1 text-xs text-slate-500">
                        @if($article->source_name)<span class="text-slate-400">{{ $article->source_name }}</span> · @endif
                        {{ $article->published_at?->format('M j, Y') }}
                        @if($article->politician)
                         · <a href="{{ route('politician.public.show', $article->politician->slug) }}"
                               class="text-emerald-400 hover:text-emerald-300">{{ $article->politician->full_name }}</a>
                        @endif
                    </p>
                </div>
                <form method="POST" action="{{ route('voter.articles.unsave', $article->id) }}" class="shrink-0">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="text-slate-600 hover:text-red-400 transition p-1" title="Remove">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </form>
            </div>
            @endforeach
        </div>
        <div class="mt-4">{{ $savedArticles->links() }}</div>
        @else
        <div class="rounded-xl border border-slate-700 bg-slate-800/40 p-10 text-center">
            <div class="text-3xl mb-3">🤍</div>
            <p class="text-sm text-slate-400">No saved articles yet. Tap the heart icon on any news article to save it here.</p>
        </div>
        @endif
    </div>

</div>
@endsection
