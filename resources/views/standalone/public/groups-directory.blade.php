<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Neighborhood Groups — {{ config('app.name', 'U9itus') }}</title>
    @include('standalone.partials.seo-head')
    <meta name="description" content="Find and join neighborhood groups on U9itus — local coalitions organizing around causes, ballot measures, and civic issues near you.">
    <link rel="canonical" href="{{ url('/groups') }}">
    <meta property="og:type"        content="website">
    <meta property="og:url"         content="{{ url('/groups') }}">
    <meta property="og:title"       content="Neighborhood Groups — {{ config('app.name', 'U9itus') }}">
    <meta property="og:description" content="Find and join neighborhood groups organizing around local causes and civic issues.">
    <meta name="twitter:card"       content="summary">
    <meta name="twitter:title"      content="Neighborhood Groups — {{ config('app.name', 'U9itus') }}">
    <meta name="twitter:description" content="Find and join neighborhood groups on U9itus.">

    {{-- Fonts --}}
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700,800&display=swap" rel="stylesheet" />

    {{-- Vite assets --}}
    @if(file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
    @endif

    <style>
        * { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-slate-950 min-h-screen antialiased">

    {{-- ── Top Nav Bar ── --}}
    <nav class="sticky top-0 z-40 bg-slate-900/80 backdrop-blur-md border-b border-slate-700/50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 flex items-center justify-between h-14">
            <a href="{{ url('/') }}" class="flex items-center space-x-1 text-lg font-bold hover:opacity-80 transition">
                <span class="text-white">U9</span><span class="text-emerald-400">itus</span>
            </a>
            <div class="flex items-center gap-3">
                <a href="{{ route('politicians.directory') }}" class="hidden sm:inline-block text-sm text-slate-300 hover:text-white transition">Browse Politicians</a>
                @auth
                    @if(auth()->user()->hasAnyRole(['voter', 'citizen']))
                    <a href="{{ route('groups.create') }}" class="bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-semibold px-4 py-2 rounded-lg transition">
                        Create a Group
                    </a>
                    @endif
                    <a href="{{ route('dashboard') }}" class="text-sm text-slate-300 hover:text-white transition">Dashboard</a>
                @else
                    <a href="{{ route('register.voter') }}" class="bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-semibold px-4 py-2 rounded-lg transition">
                        Create Free Account
                    </a>
                    <a href="{{ route('login') }}" class="text-sm text-slate-300 hover:text-white transition">Sign In</a>
                @endauth
            </div>
        </div>
    </nav>

    {{-- ── Header Section ── --}}
    <div class="bg-gradient-to-br from-emerald-900/20 via-slate-900/50 to-slate-950 border-b border-slate-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 py-12 sm:py-16">
            <div class="max-w-3xl">
                <h1 class="text-3xl sm:text-4xl font-bold text-white mb-3 flex items-center gap-3">
                    <span class="w-10 h-10 rounded-xl bg-emerald-500/20 border border-emerald-500/30 flex items-center justify-center flex-shrink-0">
                        <svg class="w-5.5 h-5.5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                    </span>
                    Neighborhood Groups
                </h1>
                <p class="text-slate-400 text-base leading-relaxed">
                    Find your neighbors organizing around the causes and issues you care about — or start
                    a group of your own. Membership is free and open to voters and citizens.
                </p>
            </div>
        </div>
    </div>

    {{-- ── Filter Bar ── --}}
    <div class="border-b border-slate-800 sticky top-14 z-30 bg-slate-900/90 backdrop-blur-md">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 py-4">
            <form method="GET" action="{{ route('groups.directory') }}#results" x-data="{ filtersOpen: false }" class="flex flex-col gap-3 sm:flex-row sm:flex-wrap">
                {{-- Search + mobile filters toggle --}}
                <div class="flex gap-2">
                    <div class="relative flex-1 min-w-0 sm:min-w-[220px]">
                        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-500 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        <input type="text" name="q" value="{{ request('q') }}"
                            placeholder="Search groups by name..."
                            class="w-full bg-slate-800 border border-slate-700 text-white placeholder-slate-500 rounded-lg pl-9 pr-4 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 transition"/>
                    </div>

                    <button type="button" @click="filtersOpen = !filtersOpen"
                        class="sm:hidden flex-shrink-0 inline-flex items-center gap-1.5 bg-slate-800 border border-slate-700 text-slate-300 rounded-lg px-3 py-2 text-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h18M6 8h12M10 12h4"/>
                        </svg>
                        Filters
                        <svg class="w-3.5 h-3.5 transition-transform" :class="filtersOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                </div>

                {{-- Remaining filters: collapsible on mobile, always visible sm+ --}}
                <div class="flex flex-col gap-3 sm:contents" :class="{ 'contents': filtersOpen, 'hidden': !filtersOpen }">
                    {{-- City Filter --}}
                    <div class="relative sm:min-w-[190px]">
                        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-500 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        <input type="text" name="city" value="{{ request('city') }}"
                            placeholder="City"
                            class="w-full bg-slate-800 border border-slate-700 text-white placeholder-slate-500 rounded-lg pl-9 pr-4 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 transition"/>
                    </div>

                    {{-- State Filter --}}
                    <div class="relative">
                        <select name="state"
                            class="w-full bg-slate-800 border border-slate-700 text-slate-300 rounded-lg pl-3 pr-8 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-emerald-500 appearance-none cursor-pointer">
                            <option value="">All States</option>
                            @foreach($states as $abbr => $name)
                            <option value="{{ $abbr }}" {{ request('state') === $abbr ? 'selected' : '' }}>{{ $abbr }} - {{ $name }}</option>
                            @endforeach
                        </select>
                        <svg class="absolute right-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-slate-500 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </div>

                    {{-- Scope Filter --}}
                    <div class="relative">
                        <select name="scope"
                            class="w-full bg-slate-800 border border-slate-700 text-slate-300 rounded-lg pl-3 pr-8 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-emerald-500 appearance-none cursor-pointer">
                            <option value="">All Scopes</option>
                            @foreach(\App\Models\NeighborhoodGroup::SCOPES as $scopeOption)
                            <option value="{{ $scopeOption }}" {{ request('scope') === $scopeOption ? 'selected' : '' }}>{{ $scopeOption }}</option>
                            @endforeach
                        </select>
                        <svg class="absolute right-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-slate-500 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </div>

                    {{-- Action Buttons --}}
                    <div class="flex items-center gap-3">
                        <button type="submit"
                            class="bg-emerald-600 hover:bg-emerald-500 text-white px-4 py-2 rounded-lg text-sm font-medium transition">
                            Filter
                        </button>

                        @if(request()->hasAny(['q', 'city', 'state', 'scope']))
                        <a href="{{ route('groups.directory') }}"
                           class="text-slate-400 hover:text-white text-sm flex items-center gap-1 px-3 py-2 transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                            Clear
                        </a>
                        @endif
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- ── Results Section ── --}}
    <div id="results" class="max-w-7xl mx-auto px-4 sm:px-6 py-8 scroll-mt-[220px] sm:scroll-mt-[140px]">

        {{-- Result count --}}
        <div class="flex items-center justify-between mb-6">
            <p class="text-slate-400 text-sm">
                {{ $groups->total() }} {{ Str::plural('group', $groups->total()) }} found
            </p>
        </div>

        {{-- Empty State --}}
        @if($groups->isEmpty())
        <div class="text-center py-20 bg-slate-800/40 border border-slate-700/60 rounded-2xl">
            <div class="w-14 h-14 rounded-2xl bg-slate-700/60 flex items-center justify-center mx-auto mb-4">
                <svg class="w-7 h-7 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
            </div>
            <h3 class="text-white font-semibold mb-1">No groups found</h3>
            <p class="text-slate-500 text-sm max-w-xs mx-auto">
                @if(request()->hasAny(['q', 'city', 'state', 'scope']))
                    No groups match your current filters. Try adjusting your search criteria.
                @else
                    No neighborhood groups have been created yet — be the first.
                @endif
            </p>
            @if(request()->hasAny(['q', 'city', 'state', 'scope']))
            <a href="{{ route('groups.directory') }}" class="inline-block mt-4 text-emerald-400 hover:text-emerald-300 text-sm transition">
                Clear filters →
            </a>
            @endif
        </div>
        @else

        {{-- Groups Grid --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
            @foreach($groups as $group)
            @php
                $groupHref = $group->scope
                    ? route('groups.public.show', ['group' => $group, 'scope' => $group->scopeUrlSegment()])
                    : route('groups.public.show', $group);
            @endphp
            <a href="{{ $groupHref }}"
               class="group flex flex-col bg-slate-800/50 border border-slate-700/60 hover:border-emerald-500/40 rounded-2xl overflow-hidden transition p-5">
                <div class="flex items-start justify-between gap-2 mb-1">
                    <h3 class="text-white font-semibold text-base group-hover:text-emerald-400 transition truncate">
                        {{ $group->name }}
                    </h3>
                    @if($group->scope)
                    <span class="flex-shrink-0 inline-flex items-center rounded-full border border-indigo-500/30 bg-indigo-500/10 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-indigo-300">
                        {{ $group->scope }}
                    </span>
                    @endif
                </div>

                @if($group->city || $group->state)
                <div class="flex items-center gap-1.5 text-xs mb-3">
                    <svg class="w-3 h-3 text-slate-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    <span class="text-slate-500 truncate">
                        {{ implode(', ', array_filter([$group->city, $group->state])) }}
                    </span>
                </div>
                @endif

                @if($group->description)
                <p class="text-slate-400 text-xs mb-3 line-clamp-2 leading-snug">
                    {{ $group->description }}
                </p>
                @endif

                <div class="mt-auto pt-3 border-t border-slate-700/50 flex items-center justify-between">
                    <span class="text-slate-400 text-xs flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        {{ $group->members_count }} {{ Str::plural('member', $group->members_count) }}
                    </span>
                    <span class="text-emerald-400 text-xs font-medium group-hover:text-emerald-300 flex items-center gap-1">
                        View Group
                        <svg class="w-3.5 h-3.5 group-hover:translate-x-0.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </span>
                </div>
            </a>
            @endforeach
        </div>

        {{-- Pagination --}}
        @if($groups->hasPages())
        <div class="mt-8">
            {{ $groups->withQueryString()->links('pagination::tailwind') }}
        </div>
        @endif
        @endif

    </div>

    {{-- ── Footer ── --}}
    <footer class="border-t border-slate-800 mt-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 py-8">
            <div class="flex flex-col sm:flex-row items-center justify-between gap-4 text-sm text-slate-500">
                <p>&copy; {{ date('Y') }} {{ config('app.name', 'U9itus') }}. All rights reserved.</p>
                <div class="flex items-center gap-4">
                    <a href="{{ url('/') }}" class="hover:text-white transition">Home</a>
                    <a href="{{ route('register.voter') }}" class="hover:text-white transition">Become a Voter</a>
                    <a href="{{ route('register.politician') }}" class="hover:text-white transition">Join as Politician</a>
                </div>
            </div>
        </div>
    </footer>

</body>
</html>
