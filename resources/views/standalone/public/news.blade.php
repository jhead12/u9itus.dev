<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $ogTitle }}</title>
    @include('standalone.partials.seo-head')

    <meta property="og:type"        content="website">
    <meta property="og:url"         content="{{ $ogUrl }}">
    <meta property="og:title"       content="{{ $ogTitle }}">
    <meta property="og:description" content="{{ $ogDescription }}">
    <meta name="twitter:card"        content="summary">
    <meta name="twitter:title"       content="{{ $ogTitle }}">
    <meta name="twitter:description" content="{{ $ogDescription }}">
    <meta name="description"         content="{{ $ogDescription }}">
    <link rel="canonical"            href="{{ $ogUrl }}">
    <meta name="csrf-token"          content="{{ csrf_token() }}">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700,800&display=swap" rel="stylesheet" />

    @if(file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
    @endif

    <style>
        :root { {{ $page->cssVariables() }} }
        * { font-family: 'Inter', sans-serif; }
        .bg-style-dark     { background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); }
        .bg-style-light    { background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%); color: #1e293b; }
        .bg-style-gradient { background: linear-gradient(135deg, var(--p13-primary, #1e40af) 0%, #0f172a 60%); }
        .bg-style-image    { background-color: #0f172a; }
        .breaking-badge { background: var(--p13-accent, #f59e0b); color: #0f172a; }
        .accent-bar     { background: var(--p13-accent, #f59e0b); }
        .p13-link       { color: var(--p13-accent, #f59e0b); }
        .p13-link:hover { opacity: .8; }
    </style>
</head>
<body class="bg-style-{{ $page->background_style }} min-h-screen antialiased text-slate-100">

{{-- ═══════════════════════ MASTHEAD ═══════════════════════ --}}
<header class="border-b border-slate-700/50 bg-slate-900/80 backdrop-blur-sm sticky top-0 z-40">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 py-3 flex items-center justify-between gap-4">
        <div class="flex items-center gap-3 min-w-0">
            <a href="{{ route('politician.public.show', $politician->slug) }}"
               class="text-slate-400 hover:text-white transition flex-shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
            </a>
            <div class="min-w-0">
                <p class="text-xs text-slate-400 truncate">{{ $politician->full_name }}</p>
                <p class="text-sm font-bold text-white truncate">In the News</p>
            </div>
        </div>

        {{-- TIME / TOPIC mode toggle --}}
        <div class="flex items-center bg-slate-800 rounded-lg p-0.5 gap-0.5 flex-shrink-0">
            <a href="{{ route('politician.public.news', $politician->slug) }}?mode=time{{ $q ? '&q='.urlencode($q) : '' }}"
               class="text-xs font-semibold px-3 py-1.5 rounded-md transition
                      {{ $mode === 'time' ? 'bg-slate-700 text-white' : 'text-slate-400 hover:text-white' }}">
                Time
            </a>
            <a href="{{ route('politician.public.news', $politician->slug) }}?mode=topic{{ $q ? '&q='.urlencode($q) : '' }}"
               class="text-xs font-semibold px-3 py-1.5 rounded-md transition
                      {{ $mode === 'topic' ? 'bg-slate-700 text-white' : 'text-slate-400 hover:text-white' }}">
                Topic
            </a>
        </div>
    </div>
</header>

<main class="max-w-5xl mx-auto px-4 sm:px-6 py-8 space-y-10">

    {{-- ═══════════════════ FILTER TOOLBAR ═══════════════════ --}}
    <form method="GET" action="{{ route('politician.public.news', $politician->slug) }}"
          class="flex flex-wrap gap-3 items-end">
        <input type="hidden" name="mode" value="{{ $mode }}">

        {{-- Search --}}
        <div class="flex-1 min-w-48">
            <label class="block text-xs text-slate-400 mb-1">Search</label>
            <input type="text" name="q" value="{{ $q }}" placeholder="keyword…"
                   class="w-full bg-slate-800/60 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white
                          focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition">
        </div>

        {{-- Sort --}}
        <div>
            <label class="block text-xs text-slate-400 mb-1">Sort</label>
            <select name="sort"
                    class="bg-slate-800/60 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white
                           focus:outline-none focus:ring-2 focus:ring-emerald-500/50 transition">
                <option value="newest" {{ $sort === 'newest' ? 'selected' : '' }}>Newest first</option>
                <option value="oldest" {{ $sort === 'oldest' ? 'selected' : '' }}>Oldest first</option>
                <option value="source" {{ $sort === 'source'  ? 'selected' : '' }}>By source</option>
            </select>
        </div>

        {{-- Date range --}}
        <div>
            <label class="block text-xs text-slate-400 mb-1">From</label>
            <input type="date" name="from" value="{{ $from }}"
                   class="bg-slate-800/60 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white
                          focus:outline-none focus:ring-2 focus:ring-emerald-500/50 transition">
        </div>
        <div>
            <label class="block text-xs text-slate-400 mb-1">To</label>
            <input type="date" name="to" value="{{ $to }}"
                   class="bg-slate-800/60 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white
                          focus:outline-none focus:ring-2 focus:ring-emerald-500/50 transition">
        </div>

        <button type="submit"
                class="px-4 py-2 rounded-lg text-sm font-semibold text-slate-900 transition"
                style="background:var(--p13-accent,#f59e0b)">
            Filter
        </button>

        @if($q || $from || $to || $sources)
        <a href="{{ route('politician.public.news', $politician->slug) }}?mode={{ $mode }}"
           class="px-4 py-2 rounded-lg text-sm text-slate-400 hover:text-white border border-slate-700 hover:border-slate-500 transition">
            Clear
        </a>
        @endif
    </form>

    {{-- ══════════════ TOPIC MODE — source pills ══════════════ --}}
    @if($mode === 'topic' && count($allProviders) > 0)
    <div class="flex flex-wrap gap-2">
        <a href="{{ route('politician.public.news', $politician->slug) }}?mode=topic{{ $sort !== 'newest' ? '&sort='.$sort : '' }}{{ $q ? '&q='.urlencode($q) : '' }}"
           class="inline-flex items-center gap-1.5 text-xs font-semibold border rounded-full px-3 py-1 transition
                  {{ ! $sources ? 'text-white border-emerald-500' : 'text-slate-400 border-slate-700 hover:border-slate-500 hover:text-white' }}"
           style="{{ ! $sources ? 'background:var(--p13-primary,#1e40af)' : '' }}">
            All sources
        </a>
        @foreach($allProviders as $pid)
        @php
            $sm      = $sourceMap[$pid] ?? ['label' => $pid, 'icon' => '📰'];
            $active  = in_array($pid, $sources, true);
            $toggled = $active
                ? array_values(array_filter($sources, fn($s) => $s !== $pid))
                : array_merge($sources, [$pid]);
            $srcParam = implode(',', $toggled);
        @endphp
        <a href="{{ route('politician.public.news', $politician->slug) }}?mode=topic{{ $srcParam ? '&source='.urlencode($srcParam) : '' }}{{ $sort !== 'newest' ? '&sort='.$sort : '' }}{{ $q ? '&q='.urlencode($q) : '' }}"
           class="inline-flex items-center gap-1.5 text-xs font-semibold border rounded-full px-3 py-1 transition
                  {{ $active ? 'text-white border-emerald-500' : 'text-slate-400 border-slate-700 hover:border-slate-500 hover:text-white' }}"
           style="{{ $active ? 'background:var(--p13-primary,#1e40af)' : '' }}">
            <span>{{ $sm['icon'] }}</span>
            <span>{{ $sm['label'] }}</span>
        </a>
        @endforeach
    </div>
    @endif

    {{-- ══════════════════ RESULT COUNT ══════════════════════ --}}
    <div class="flex items-center justify-between">
        <p class="text-xs text-slate-500">
            {{ number_format($articles->total()) }} article{{ $articles->total() !== 1 ? 's' : '' }}
            @if($q) matching <span class="text-slate-300 font-medium">"{{ $q }}"</span>@endif
        </p>
        <p class="text-xs text-slate-600">Page {{ $articles->currentPage() }} of {{ $articles->lastPage() }}</p>
    </div>

    {{-- ══════════════ BREAKING NOW (page 1, TIME mode, no filters) ══════════════ --}}
    @if($breakingNow->isNotEmpty())
    <section>
        <div class="flex items-center gap-2 mb-4">
            <span class="w-1 h-5 rounded-full accent-bar inline-block"></span>
            <h2 class="text-sm font-bold uppercase tracking-widest text-slate-300">Breaking Now</h2>
        </div>
        <div class="grid sm:grid-cols-2 gap-4">
            @foreach($breakingNow as $article)
            <a href="{{ $article->source_url }}" target="_blank" rel="noopener noreferrer"
               class="group block rounded-2xl overflow-hidden border border-slate-700/50 hover:border-slate-500
                      bg-slate-800/40 transition">
                @if($article->image_url)
                <div class="relative w-full h-44 bg-slate-700 overflow-hidden">
                    <img src="{{ $article->image_url }}" alt=""
                         class="w-full h-full object-cover group-hover:scale-105 transition duration-500"
                         loading="lazy" onerror="this.parentElement.style.display='none'">
                    <div class="absolute inset-0 bg-gradient-to-t from-slate-900/80 to-transparent"></div>
                </div>
                @endif
                <div class="p-4">
                    <p class="text-base font-semibold text-white group-hover:text-emerald-300 line-clamp-2 leading-snug transition">
                        {{ $article->headline }}
                    </p>
                    <p class="mt-1.5 text-xs text-slate-500">
                        {{ $article->source_name ? $article->source_name . ' · ' : '' }}{{ $article->published_at?->diffForHumans() }}
                    </p>
                    @if($article->topic_key)
                    <p class="mt-1.5">
                        <span class="inline-flex items-center rounded-full border border-emerald-600/40 bg-emerald-900/20 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-emerald-300">
                            {{ str_replace('-', ' ', $article->topic_key) }}
                        </span>
                    </p>
                    @endif
                    @if($article->snippet)
                    <p class="mt-1.5 text-xs text-slate-400 line-clamp-2">{{ $article->snippet }}</p>
                    @endif                    @auth
                    <button class="article-save-btn mt-2 inline-flex items-center gap-1 text-xs text-slate-500 hover:text-rose-400 transition"
                            data-article-id="{{ $article->id }}"
                            data-saved="{{ in_array($article->id, $savedArticleIds) ? '1' : '0' }}"
                            aria-label="Save article">
                        <svg class="w-4 h-4" fill="{{ in_array($article->id, $savedArticleIds) ? 'currentColor' : 'none' }}" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                        </svg>
                        <span>{{ in_array($article->id, $savedArticleIds) ? 'Saved' : 'Save' }}</span>
                    </button>
                    @endauth                </div>
            </a>
            @endforeach
        </div>
    </section>
    @endif

    {{-- ══════════════════ TIME MODE — date-grouped archive ══════════════════ --}}
    @if($mode === 'time' && $grouped->isNotEmpty())
    <section class="space-y-8">
        <div class="flex items-center gap-2 mb-2">
            <span class="w-1 h-5 rounded-full accent-bar inline-block"></span>
            <h2 class="text-sm font-bold uppercase tracking-widest text-slate-300">The Archive</h2>
        </div>
        @foreach($grouped as $dateLabel => $dayArticles)
        <div>
            <div class="flex items-center gap-3 mb-3">
                <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ $dateLabel }}</h3>
                <span class="text-xs text-slate-600">· {{ $dayArticles->count() }} {{ $dayArticles->count() === 1 ? 'story' : 'stories' }}</span>
                <div class="flex-1 h-px bg-slate-700/60"></div>
            </div>
            <div class="space-y-3">
                @foreach($dayArticles as $article)
                <a href="{{ $article->source_url }}" target="_blank" rel="noopener noreferrer"
                   class="group flex gap-4 bg-slate-800/40 border border-slate-700/40 hover:border-slate-600/60
                          rounded-xl p-4 transition">
                    @if($article->image_url)
                    <img src="{{ $article->image_url }}" alt=""
                         class="w-20 h-16 object-cover rounded-lg flex-shrink-0 bg-slate-700"
                         loading="lazy" onerror="this.style.display='none'">
                    @else
                    <div class="w-20 h-16 rounded-lg flex-shrink-0 flex items-center justify-center text-2xl"
                         style="background:linear-gradient(135deg,var(--p13-primary,#1e40af)33,var(--p13-accent,#f59e0b)22)">
                        📰
                    </div>
                    @endif
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-slate-200 group-hover:text-white line-clamp-2 leading-snug transition">
                            {{ $article->headline }}
                        </p>
                        <p class="mt-1 text-xs text-slate-500 flex items-center gap-1.5 flex-wrap">
                            @if($article->source_name)
                                <span class="font-medium text-slate-400">{{ $article->source_name }}</span>
                                <span>·</span>
                            @endif
                            <span>{{ $article->published_at?->diffForHumans() }}</span>
                            @if($article->topic_key)
                                <span>·</span>
                                <span class="inline-flex items-center rounded-full border border-emerald-600/40 bg-emerald-900/20 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-emerald-300">
                                    {{ str_replace('-', ' ', $article->topic_key) }}
                                </span>
                            @endif
                        </p>
                        @if($article->snippet)
                        <p class="mt-1 text-xs text-slate-500 line-clamp-1">{{ $article->snippet }}</p>
                        @endif
                        @auth
                        <button class="article-save-btn mt-1 inline-flex items-center gap-1 text-xs text-slate-500 hover:text-rose-400 transition"
                                data-article-id="{{ $article->id }}"
                                data-saved="{{ in_array($article->id, $savedArticleIds) ? '1' : '0' }}"
                                aria-label="Save article">
                            <svg class="w-3.5 h-3.5" fill="{{ in_array($article->id, $savedArticleIds) ? 'currentColor' : 'none' }}" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                            </svg>
                            <span>{{ in_array($article->id, $savedArticleIds) ? 'Saved' : 'Save' }}</span>
                        </button>
                        @endauth
                    </div>
                    <span class="text-slate-600 group-hover:text-slate-400 flex-shrink-0 self-center transition text-lg">↗</span>
                </a>
                @endforeach
            </div>
        </div>
        @endforeach
    </section>
    @endif

    {{-- ══════════════════ TOPIC MODE — flat grid ══════════════════ --}}
    @if($mode === 'topic')
    <section class="space-y-3">
        @forelse($articles as $article)
        <a href="{{ $article->source_url }}" target="_blank" rel="noopener noreferrer"
           class="group flex gap-4 bg-slate-800/40 border border-slate-700/40 hover:border-slate-600/60
                  rounded-xl p-4 transition">
            @if($article->image_url)
            <img src="{{ $article->image_url }}" alt=""
                 class="w-20 h-16 object-cover rounded-lg flex-shrink-0 bg-slate-700"
                 loading="lazy" onerror="this.style.display='none'">
            @else
            <div class="w-20 h-16 rounded-lg flex-shrink-0 flex items-center justify-center text-2xl"
                 style="background:linear-gradient(135deg,var(--p13-primary,#1e40af)33,var(--p13-accent,#f59e0b)22)">
                📰
            </div>
            @endif
            <div class="min-w-0 flex-1">
                <p class="text-sm font-medium text-slate-200 group-hover:text-white line-clamp-2 leading-snug transition">
                    {{ $article->headline }}
                </p>
                <p class="mt-1 text-xs text-slate-500 flex items-center gap-1.5 flex-wrap">
                    @if($article->source_name)
                        <span class="inline-flex items-center gap-1 text-slate-400 font-medium">
                            {{ ($sourceMap[$article->provider] ?? ['icon' => '📰'])['icon'] }}
                            {{ $article->source_name }}
                        </span>
                        <span>·</span>
                    @endif
                    <span>{{ $article->published_at?->format('M j, Y') }}</span>
                    @if($article->topic_key)
                        <span>·</span>
                        <span class="inline-flex items-center rounded-full border border-emerald-600/40 bg-emerald-900/20 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-emerald-300">
                            {{ str_replace('-', ' ', $article->topic_key) }}
                        </span>
                    @endif
                </p>
                @if($article->snippet)
                <p class="mt-1 text-xs text-slate-500 line-clamp-1">{{ $article->snippet }}</p>
                @endif
                @auth
                <button class="article-save-btn mt-1 inline-flex items-center gap-1 text-xs text-slate-500 hover:text-rose-400 transition"
                        data-article-id="{{ $article->id }}"
                        data-saved="{{ in_array($article->id, $savedArticleIds) ? '1' : '0' }}"
                        aria-label="Save article">
                    <svg class="w-3.5 h-3.5" fill="{{ in_array($article->id, $savedArticleIds) ? 'currentColor' : 'none' }}" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                    </svg>
                    <span>{{ in_array($article->id, $savedArticleIds) ? 'Saved' : 'Save' }}</span>
                </button>
                @endauth
            </div>
            <span class="text-slate-600 group-hover:text-slate-400 flex-shrink-0 self-center transition text-lg">↗</span>
        </a>
        @empty
        <div class="text-center py-16 bg-slate-800/30 border border-slate-700/40 rounded-2xl">
            <p class="text-slate-400 text-sm mb-2">No articles match your filters.</p>
            <a href="{{ route('politician.public.news', $politician->slug) }}?mode={{ $mode }}"
               class="text-xs p13-link font-medium">Clear filters</a>
        </div>
        @endforelse
    </section>
    @endif

    {{-- ═══════════════════ PAGINATION ═══════════════════ --}}
    @if($articles->hasPages())
    <nav class="flex items-center justify-between pt-4 border-t border-slate-700/50">
        @if($articles->onFirstPage())
        <span class="text-sm text-slate-600">← Previous</span>
        @else
        <a href="{{ $articles->previousPageUrl() }}" class="text-sm text-slate-400 hover:text-white transition">← Previous</a>
        @endif

        <span class="text-xs text-slate-500">
            {{ $articles->firstItem() }}–{{ $articles->lastItem() }} of {{ number_format($articles->total()) }}
        </span>

        @if($articles->hasMorePages())
        <a href="{{ $articles->nextPageUrl() }}" class="text-sm text-slate-400 hover:text-white transition">Next →</a>
        @else
        <span class="text-sm text-slate-600">Next →</span>
        @endif
    </nav>
    @endif

    {{-- ═══════════════════ BACK LINK ═══════════════════ --}}
    <div class="pt-2">
        <a href="{{ route('politician.public.show', $politician->slug) }}"
           class="inline-flex items-center gap-2 text-sm text-slate-400 hover:text-white transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Back to {{ $politician->full_name }}'s profile
        </a>
    </div>

</main>

<footer class="mt-16 border-t border-slate-700/50 py-6 text-center text-xs text-slate-600">
    © {{ date('Y') }} {{ config('app.name', 'U9itus') }}
    &nbsp;·&nbsp;
    <a href="{{ route('politician.public.show', $politician->slug) }}" class="hover:text-slate-400 transition">
        {{ $politician->full_name }} profile
    </a>
</footer>

@auth
<script>
document.addEventListener('click', function (e) {
    const btn = e.target.closest('.article-save-btn');
    if (!btn) return;
    e.preventDefault();
    e.stopPropagation();

    const articleId = btn.dataset.articleId;
    const saved     = btn.dataset.saved === '1';
    const method    = saved ? 'DELETE' : 'POST';
    const url       = `/voter/articles/${articleId}/save`;
    const svg       = btn.querySelector('svg');
    const label     = btn.querySelector('span');
    const csrf      = document.querySelector('meta[name="csrf-token"]')?.content
                   || '{{ csrf_token() }}';

    fetch(url, {
        method,
        headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
    })
    .then(r => r.json())
    .then(data => {
        const isSaved = data.saved;
        btn.dataset.saved = isSaved ? '1' : '0';
        svg.setAttribute('fill', isSaved ? 'currentColor' : 'none');
        btn.classList.toggle('text-rose-400', isSaved);
        btn.classList.toggle('text-slate-500', !isSaved);
        if (label) label.textContent = isSaved ? 'Saved' : 'Save';
    })
    .catch(() => {});
});
</script>
@endauth
</body>
</html>
