<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $ogTitle }}</title>
    @include('standalone.partials.seo-head', ['seoTitle' => $ogTitle, 'seoDescription' => $ogDescription, 'seoCanonical' => $ogUrl])

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
    </style>
</head>
<body class="bg-style-{{ $page->background_style }} min-h-screen antialiased text-slate-100">

<header class="border-b border-slate-700/50 bg-slate-900/80 backdrop-blur-sm sticky top-0 z-40">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 py-3 flex items-center gap-3">
        <a href="{{ route('politician.public.show', $politician->slug) }}" class="text-slate-400 hover:text-white transition flex-shrink-0" aria-label="Back to profile">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
        </a>
        <div class="min-w-0">
            <p class="text-xs text-slate-400 truncate">{{ $politician->full_name }}</p>
            <p class="text-sm font-bold text-white truncate">Floor Speeches</p>
        </div>
    </div>
</header>

<main class="max-w-5xl mx-auto px-4 sm:px-6 py-8 space-y-6">
    <form method="GET" action="{{ route('politician.public.speeches', $politician->slug) }}" role="search" class="flex flex-col sm:flex-row gap-2">
        <label for="speech-search" class="sr-only">Search {{ $politician->full_name }}'s speeches</label>
        <input id="speech-search" type="search" name="q" value="{{ $q }}" maxlength="100"
               placeholder="Search what they said, e.g. tariffs, Medicaid, border"
               class="flex-1 rounded-lg bg-slate-800 border border-slate-700 px-4 py-2.5 text-sm text-white placeholder-slate-500 focus:border-slate-500 focus:outline-none">
        @if($topic !== '')<input type="hidden" name="topic" value="{{ $topic }}">@endif
        <button type="submit" class="rounded-lg px-5 py-2.5 text-sm font-semibold text-slate-900" style="background:var(--p13-accent,#f59e0b)">Search</button>
    </form>

    @if($topics->isNotEmpty())
        <nav class="flex flex-wrap gap-2" aria-label="Filter speeches by issue">
            <a href="{{ route('politician.public.speeches', array_filter(['slug' => $politician->slug, 'q' => $q])) }}"
               class="text-xs font-semibold px-3 py-1.5 rounded-full border transition {{ $topic === '' ? 'bg-slate-700 border-slate-500 text-white' : 'border-slate-700 text-slate-400 hover:text-white' }}"
               @if($topic === '') aria-current="true" @endif>All issues</a>
            @foreach($topics as $t)
                <a href="{{ route('politician.public.speeches', array_filter(['slug' => $politician->slug, 'q' => $q, 'topic' => $t->slug])) }}"
                   class="text-xs font-semibold px-3 py-1.5 rounded-full border transition {{ $topic === $t->slug ? 'bg-slate-700 border-slate-500 text-white' : 'border-slate-700 text-slate-400 hover:text-white' }}"
                   @if($topic === $t->slug) aria-current="true" @endif>@if($t->icon)<span aria-hidden="true">{{ $t->icon }}</span> @endif{{ $t->name }}</a>
            @endforeach
        </nav>
    @endif

    <p class="text-sm text-slate-400" aria-live="polite">
        {{ number_format($speeches->total()) }} {{ Str::plural('speech', $speeches->total()) }}@if($q !== '') mentioning “{{ $q }}”@endif
    </p>

    @if($speeches->isEmpty())
        <p class="bg-slate-800/40 border border-slate-700/40 rounded-xl p-6 text-sm text-slate-400">No speeches match. Try a different word or issue.</p>
    @else
        <div class="space-y-3">
            @foreach($speeches as $speech)
                @include('standalone.public.partials.floor-speech-card', ['speech' => $speech])
            @endforeach
        </div>
    @endif

    @if($speeches->hasPages())
        <div class="flex items-center justify-between text-sm">
            @if($speeches->onFirstPage())
                <span class="text-slate-600">← Newer</span>
            @else
                <a href="{{ $speeches->previousPageUrl() }}" style="color:var(--p13-accent,#f59e0b)">← Newer</a>
            @endif
            <span class="text-slate-500">Page {{ $speeches->currentPage() }} of {{ $speeches->lastPage() }}</span>
            @if($speeches->hasMorePages())
                <a href="{{ $speeches->nextPageUrl() }}" style="color:var(--p13-accent,#f59e0b)">Older →</a>
            @else
                <span class="text-slate-600">Older →</span>
            @endif
        </div>
    @endif

    <p class="text-xs text-slate-500">
        Source: the <a href="https://www.govinfo.gov/app/collection/crec" target="_blank" rel="noopener" class="underline">Congressional Record</a> via GovInfo. Debates are split by speaker, so each entry holds only {{ $politician->full_name }}'s own words. Issue, position and quote are generated automatically from the text; quotes are verified word-for-word. “Watch that day on C-SPAN” opens C-SPAN's video of the full floor session for that date.
    </p>
</main>
</body>
</html>
