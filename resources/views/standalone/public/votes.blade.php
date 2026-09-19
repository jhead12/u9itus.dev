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
            <p class="text-sm font-bold text-white truncate">Voting Record</p>
        </div>
    </div>
</header>

<main class="max-w-5xl mx-auto px-4 sm:px-6 py-8 space-y-6">
    @php
        $pct = fn (?float $n) => $n === null ? '—' : rtrim(rtrim(number_format($n, 1), '0'), '.').'%';
        $filters = ['all' => 'All votes', 'yea' => 'Yea', 'nay' => 'Nay', 'not_voting' => 'Not voting'];
    @endphp

    <p class="text-sm text-slate-300">
        {{ $summary['chamber'] === 'senate' ? 'Senate' : 'House' }} roll-call votes in the {{ $summary['congress'] }}th Congress:
        {{ number_format($summary['yea']) }} yea, {{ number_format($summary['nay']) }} nay,
        {{ $pct($summary['missed_pct']) }} missed
        @if($summary['party_line_pct'] !== null), with their party's majority {{ $pct($summary['party_line_pct']) }} of the time @endif.
    </p>

    <nav class="flex flex-wrap gap-2" aria-label="Filter votes">
        @foreach($filters as $key => $label)
            <a href="{{ route('politician.public.votes', $politician->slug) }}{{ $key === 'all' ? '' : '?vote='.$key }}"
               class="text-xs font-semibold px-3 py-1.5 rounded-full border transition {{ $filter === $key ? 'bg-slate-700 border-slate-500 text-white' : 'border-slate-700 text-slate-400 hover:text-white' }}">
                {{ $label }}
            </a>
        @endforeach
    </nav>

    <div class="bg-slate-800/40 border border-slate-700/40 rounded-xl px-5">
        @if($votes->isEmpty())
            <p class="py-6 text-sm text-slate-400">No votes match this filter.</p>
        @else
            <ul class="divide-y divide-slate-700/40">
                @foreach($votes as $vote)
                    @include('standalone.public.partials.vote-row', ['vote' => $vote])
                @endforeach
            </ul>
        @endif
    </div>

    @if($votes->hasPages())
        <div class="flex items-center justify-between text-sm">
            @if($votes->onFirstPage())
                <span class="text-slate-600">← Newer</span>
            @else
                <a href="{{ $votes->previousPageUrl() }}" class="p13-link" style="color:var(--p13-accent,#f59e0b)">← Newer</a>
            @endif
            <span class="text-slate-500">Page {{ $votes->currentPage() }} of {{ $votes->lastPage() }}</span>
            @if($votes->hasMorePages())
                <a href="{{ $votes->nextPageUrl() }}" style="color:var(--p13-accent,#f59e0b)">Older →</a>
            @else
                <span class="text-slate-600">Older →</span>
            @endif
        </div>
    @endif

    <p class="text-xs text-slate-500">
        Source: the U.S. {{ $summary['chamber'] === 'senate' ? 'Senate' : 'House Clerk' }} roll-call records. “Not voting” includes every roll call the member did not vote on, whatever the reason.
    </p>
</main>
</body>
</html>
