<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Browse Politicians & Officials — {{ config('app.name', 'U9itus') }}</title>
    @include('standalone.partials.seo-head')
    <meta name="description" content="Research and learn about verified politicians and local governance officials on U9itus. View campaign profiles, transparency data, donor records, and political stances.">
    <link rel="canonical" href="{{ url('/politicians') }}">
    <meta property="og:type"        content="website">
    <meta property="og:url"         content="{{ url('/politicians') }}">
    <meta property="og:title"       content="Browse Politicians & Officials — {{ config('app.name', 'U9itus') }}">
    <meta property="og:description" content="Research verified politicians and local governance officials. View campaign profiles, transparency data, donor records, and political stances.">
    <meta name="twitter:card"       content="summary">
    <meta name="twitter:title"      content="Browse Politicians & Officials — {{ config('app.name', 'U9itus') }}">
    <meta name="twitter:description" content="Research verified politicians and governance officials on U9itus.">

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
                <a href="{{ route('district.lookup') }}" class="hidden sm:inline-block text-sm text-slate-300 hover:text-white transition">Find My District</a>
                @auth
                    <a href="{{ route('dashboard') }}" class="text-sm text-slate-300 hover:text-white transition">Dashboard</a>
                @else
                    <a href="{{ route('register.voter') }}" class="bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-semibold px-4 py-2 rounded-lg transition">
                        Create Free Account
                    </a>
                    <a href="{{ route('login') }}" class="text-sm text-slate-300 hover:text-white transition">Sign In</a>
                @endauth
            </div>
        </div>
    </nav>

    {{-- ── Header Section ── --}}
    <div class="bg-gradient-to-br from-emerald-900/20 via-slate-900/50 to-slate-950 border-b border-slate-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 py-4 sm:py-5">
            <div class="max-w-3xl">
                <h1 class="text-lg sm:text-xl font-bold text-white mb-1 flex items-center gap-2">
                    <span class="w-6 h-6 rounded-lg bg-emerald-500/20 border border-emerald-500/30 flex items-center justify-center flex-shrink-0">
                        <svg class="w-3.5 h-3.5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                    </span>
                    Browse Politicians & Officials
                </h1>
                <p class="hidden sm:block text-slate-400 text-xs leading-relaxed">
                    Research verified politicians and local governance officials on U9itus — profiles, campaign messages, and transparency data in a public, view-only directory.
                </p>
                @if($isGuestBrowsing)
                <div class="mt-2 inline-flex max-w-2xl items-start gap-2 rounded-xl border border-emerald-500/20 bg-emerald-500/10 px-3 py-1.5 text-xs text-emerald-100">
                    <svg class="mt-0.5 h-3.5 w-3.5 flex-shrink-0 text-emerald-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <p>
                        Public directory is view-only for earnings — commissions are only available after creating a voter account.
                    </p>
                </div>
                @endif
            </div>
        </div>
    </div>

    {{-- ── Filter Bar ── --}}
    <div class="border-b border-slate-800 sticky top-14 z-30 bg-slate-900/90 backdrop-blur-md">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 py-2.5">
            <form method="GET" action="{{ route('politicians.directory') }}#results" x-data="{ filtersOpen: false }" class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
                {{-- Search + mobile filters toggle --}}
                <div class="flex gap-2">
                    <div class="relative flex-1 min-w-0 sm:min-w-[220px]">
                        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-500 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        <input type="text" name="q" value="{{ request('q') }}"
                            placeholder="Search by name or office..."
                            aria-label="Search by name or office"
                            class="w-full bg-slate-800 border border-slate-700 text-white placeholder-slate-500 rounded-lg pl-9 pr-4 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 transition"/>
                    </div>

                    <button type="button" @click="filtersOpen = !filtersOpen"
                        class="sm:hidden flex-shrink-0 inline-flex items-center gap-1.5 bg-slate-800 border border-slate-700 text-slate-300 rounded-lg px-3 py-1.5 text-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h18M6 8h12M10 12h4"/>
                        </svg>
                        Filters
                        <svg class="w-3.5 h-3.5 transition-transform" :class="filtersOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                </div>

                {{-- Remaining filters: collapsible 2-col grid on mobile (halves the
                     stacked height vs. one filter per row), always visible in a
                     single row sm+. Only toggles `hidden` — NOT `contents` — when
                     closed/open, since an unconditional `contents` class would
                     beat the base `grid` utility in the compiled CSS cascade and
                     silently collapse the 2-col layout back to one column. --}}
                <div class="grid grid-cols-2 gap-2 sm:contents" :class="{ 'hidden': !filtersOpen }">
                    {{-- Topic Filter --}}
                    <div class="relative sm:min-w-[190px]">
                        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-500 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h8m-8 4h6"/>
                        </svg>
                        <input type="text" name="topic" value="{{ request('topic') }}"
                            placeholder="Topic (e.g. housing)"
                            aria-label="Filter by topic"
                            class="w-full bg-slate-800 border border-slate-700 text-white placeholder-slate-500 rounded-lg pl-9 pr-4 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 transition"/>
                    </div>

                    {{-- District Filter --}}
                    <div class="relative sm:min-w-[180px]">
                        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-500 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 01.553-.894L9 2m0 18l6-3m-6 3V2m6 15l5.447 2.724A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 2"/>
                        </svg>
                        <input type="text" name="district" value="{{ request('district') }}"
                            placeholder="District"
                            aria-label="Filter by district"
                            class="w-full bg-slate-800 border border-slate-700 text-white placeholder-slate-500 rounded-lg pl-9 pr-4 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 transition"/>
                    </div>

                    {{-- Governance Level --}}
                    <div class="relative">
                        <select name="level" aria-label="Filter by governance level"
                            class="w-full bg-slate-800 border border-slate-700 text-slate-300 rounded-lg pl-3 pr-8 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-emerald-500 appearance-none cursor-pointer">
                            <option value="">All Levels</option>
                            @foreach($governanceLevels as $level)
                            <option value="{{ $level }}" {{ request('level') === $level ? 'selected' : '' }}>{{ $level }}</option>
                            @endforeach
                        </select>
                        <svg class="absolute right-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-slate-500 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </div>

                    {{-- State Filter --}}
                    <div class="relative">
                        <select name="state" aria-label="Filter by state"
                            class="w-full bg-slate-800 border border-slate-700 text-slate-300 rounded-lg pl-3 pr-8 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-emerald-500 appearance-none cursor-pointer">
                            <option value="">All States</option>
                            @foreach($states as $abbr => $name)
                            <option value="{{ $abbr }}" {{ request('state') === $abbr ? 'selected' : '' }}>{{ $abbr }} - {{ $name }}</option>
                            @endforeach
                        </select>
                        <svg class="absolute right-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-slate-500 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </div>

                    {{-- Party Filter --}}
                    <div class="relative">
                        <select name="party" aria-label="Filter by party"
                            class="w-full bg-slate-800 border border-slate-700 text-slate-300 rounded-lg pl-3 pr-8 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-emerald-500 appearance-none cursor-pointer">
                            <option value="">All Parties</option>
                            @foreach($parties as $party)
                            <option value="{{ $party }}" {{ request('party') === $party ? 'selected' : '' }}>{{ $party }}</option>
                            @endforeach
                        </select>
                        <svg class="absolute right-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-slate-500 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </div>

                    {{-- Sort --}}
                    <div class="relative">
                        <select name="sort" aria-label="Sort results"
                            class="w-full bg-slate-800 border border-slate-700 text-slate-300 rounded-lg pl-3 pr-8 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-emerald-500 appearance-none cursor-pointer">
                            <option value="name" {{ request('sort', 'name') === 'name' ? 'selected' : '' }}>Name (A-Z)</option>
                            <option value="recent" {{ request('sort') === 'recent' ? 'selected' : '' }}>Recently Added</option>
                            <option value="verified" {{ request('sort') === 'verified' ? 'selected' : '' }}>Verified First</option>
                        </select>
                        <svg class="absolute right-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-slate-500 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </div>

                    {{-- Unclaimed only --}}
                    <label class="col-span-2 sm:col-auto inline-flex items-center gap-2 rounded-lg border border-slate-700 bg-slate-800 px-3 py-1.5 text-xs text-slate-300 cursor-pointer">
                        <input type="checkbox" name="unclaimed" value="1" {{ request()->boolean('unclaimed') ? 'checked' : '' }}
                            class="h-3.5 w-3.5 rounded border-slate-600 bg-slate-900 text-emerald-500 focus:ring-emerald-500" />
                        Unclaimed only
                    </label>

                    {{-- Action Buttons --}}
                    <div class="col-span-2 sm:col-auto flex items-center gap-3">
                        <button type="submit"
                            class="bg-emerald-700 hover:bg-emerald-800 text-white px-4 py-1.5 rounded-lg text-sm font-medium transition">
                            Filter
                        </button>

                        @if(request()->hasAny(['q', 'topic', 'district', 'level', 'state', 'party', 'sort', 'unclaimed']))
                        <a href="{{ route('politicians.directory') }}"
                           class="text-slate-400 hover:text-white text-sm flex items-center gap-1 px-3 py-1.5 transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                            Clear
                        </a>
                        @endif
                    </div>
                </div>
            </form>

            {{-- ── Topic chip row: one-click browse by issue ───────────────────
                 Each chip links to the directory with ?topic=<slug>, preserving
                 the other active filters. Highlights the active topic. --}}
            @if(isset($topics) && $topics->isNotEmpty())
                @php
                    $activeTopic = request('topic');
                    $baseQuery = collect(request()->only(['q', 'district', 'level', 'state', 'party', 'sort', 'unclaimed']))->filter();

                    // Topic badge_color is admin-configured and arbitrary, so raw
                    // colored text on a translucent tint can't guarantee WCAG AA
                    // (e.g. the #6366f1 default measured 3.65:1 — fails 4.5:1).
                    // Render chips on a fixed solid surface instead and lighten
                    // the accent color toward white, step by step, until it
                    // clears 4.5:1 against that known background — guarantees
                    // compliance for any admin-chosen hue while keeping the
                    // topic's own color visible in the text/border.
                    $chipSurface = '#1e293b';
                    $relLuminance = function (string $hex): float {
                        $hex = ltrim($hex, '#');
                        if (strlen($hex) === 3) $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
                        [$r, $g, $b] = [hexdec(substr($hex, 0, 2)) / 255, hexdec(substr($hex, 2, 2)) / 255, hexdec(substr($hex, 4, 2)) / 255];
                        $lin = fn ($c) => $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
                        return 0.2126 * $lin($r) + 0.7152 * $lin($g) + 0.0722 * $lin($b);
                    };
                    $contrastRatio = function (string $a, string $b) use ($relLuminance): float {
                        [$l1, $l2] = [$relLuminance($a), $relLuminance($b)];
                        if ($l1 < $l2) [$l1, $l2] = [$l2, $l1];
                        return ($l1 + 0.05) / ($l2 + 0.05);
                    };
                    $mixToward = function (string $hex, string $toward, float $amount): string {
                        $hex = ltrim($hex, '#');
                        $toward = ltrim($toward, '#');
                        $out = '';
                        for ($i = 0; $i < 6; $i += 2) {
                            $a = hexdec(substr($hex, $i, 2));
                            $b = hexdec(substr($toward, $i, 2));
                            $out .= str_pad(dechex((int) round($a + ($b - $a) * $amount)), 2, '0', STR_PAD_LEFT);
                        }
                        return '#' . $out;
                    };
                    $readableAccent = function (string $hex) use ($chipSurface, $contrastRatio, $mixToward): string {
                        $c = $hex;
                        for ($i = 0; $i < 12 && $contrastRatio($c, $chipSurface) < 4.5; $i++) {
                            $c = $mixToward($c, '#ffffff', 0.12);
                        }
                        return $c;
                    };
                @endphp
                <div class="flex flex-nowrap sm:flex-wrap items-center gap-1.5 mt-2 overflow-x-auto sm:overflow-visible -mx-4 px-4 sm:mx-0 sm:px-0 pb-1 sm:pb-0">
                    <span class="flex-shrink-0 text-[10px] font-semibold uppercase tracking-wider text-slate-400 mr-0.5">Issues</span>
                    <a href="{{ route('politicians.directory', $baseQuery->toArray()) }}#results"
                       class="flex-shrink-0 inline-flex items-center rounded-full px-2.5 py-0.5 text-[10px] font-semibold border transition {{ empty($activeTopic) ? 'bg-slate-700 border-slate-600 text-white' : 'border-slate-700 text-slate-400 hover:text-white hover:border-slate-600' }}"
                       @if(empty($activeTopic)) aria-current="true" @endif>
                        All
                    </a>
                    @foreach($topics as $topic)
                        @php
                            $chipQuery = $baseQuery->put('topic', $topic->slug)->toArray();
                            $isActive = $activeTopic === $topic->slug;
                            $color = $topic->badge_color ?: '#6366f1';
                            $textColor = $readableAccent($color);
                        @endphp
                        <a href="{{ route('politicians.directory', $chipQuery) }}#results"
                           class="flex-shrink-0 inline-flex items-center gap-x-1 rounded-full px-2.5 py-0.5 text-[10px] font-semibold border transition-all hover:brightness-125 whitespace-nowrap {{ $isActive ? 'ring-2 ring-offset-1 ring-offset-slate-900' : '' }}"
                           style="color:{{ $textColor }};border-color:{{ $color }}55;background-color:{{ $chipSurface }};--tw-ring-color:{{ $color }};"
                           title="Browse candidates focused on {{ $topic->name }}"
                           @if($isActive) aria-current="true" @endif>
                            @if(!empty($topic->icon))<span aria-hidden="true">{{ $topic->icon }}</span>@endif
                            {{ $topic->name }}
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- ── Results Section ── --}}
    <div id="results" class="max-w-7xl mx-auto px-4 sm:px-6 py-6 scroll-mt-[170px] sm:scroll-mt-[110px]">

        {{-- Result count --}}
        <div class="flex items-center justify-between mb-6">
            <p class="text-slate-400 text-sm">
                {{ $politicians->total() }} {{ Str::plural('politician', $politicians->total()) }} found
            </p>
        </div>

        {{-- Empty State --}}
        @if($politicians->isEmpty())
        <div class="text-center py-20 bg-slate-800/40 border border-slate-700/60 rounded-2xl">
            <div class="w-14 h-14 rounded-2xl bg-slate-700/60 flex items-center justify-center mx-auto mb-4">
                <svg class="w-7 h-7 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
            </div>
            <h3 class="text-white font-semibold mb-1">No politicians found</h3>
            <p class="text-slate-400 text-sm max-w-xs mx-auto">
                @if(request()->hasAny(['q', 'topic', 'district', 'level', 'state', 'party', 'unclaimed']))
                    No politicians match your current filters. Try adjusting your search criteria.
                @else
                    No politicians are currently available in the directory.
                @endif
            </p>
            @if(request()->hasAny(['q', 'topic', 'district', 'level', 'state', 'party', 'unclaimed']))
            <a href="{{ route('politicians.directory') }}" class="inline-block mt-4 text-emerald-400 hover:text-emerald-300 text-sm transition">
                Clear filters →
            </a>
            @endif
        </div>
        @else

        {{-- Politicians Grid --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
            @foreach($politicians as $politician)
            @php
                $canOpenProfile = filled($politician->slug);
                $safeName = trim((string) ($politician->full_name ?? ''));
                $safeName = $safeName !== '' ? $safeName : 'Unnamed Politician';
                $profileHref = $canOpenProfile ? route('politician.public.show', $politician->slug) : null;
            @endphp
            <div class="group flex flex-col bg-slate-800/50 border border-slate-700/60 {{ $canOpenProfile ? 'hover:border-emerald-500/40' : '' }} rounded-2xl overflow-hidden transition">
                @if($canOpenProfile)
                <a href="{{ $profileHref }}" class="contents">
                @endif
                
                {{-- Profile Photo --}}
                <div class="relative bg-gradient-to-br from-slate-700 to-slate-800 aspect-square overflow-hidden">
                    @if($politician->profile_photo_url)
                            <img src="{{ $politician->profile_photo_url }}"
                             alt="{{ $safeName }}"
                             class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" />
                    @else
                        <div class="w-full h-full flex items-center justify-center">
                            <span class="text-5xl font-bold text-slate-600">
                                {{ strtoupper(substr($safeName, 0, 1)) }}
                            </span>
                        </div>
                    @endif
                    
                    {{-- Verified Badge --}}
                    @if($politician->verified_official)
                    <div class="absolute top-3 right-3 bg-emerald-500 rounded-full p-1.5">
                        <svg class="w-3.5 h-3.5 text-white" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                        </svg>
                    </div>
                    @endif

                    {{-- Active Campaigns Badge --}}
                    @php
                        $activeCampaignsCount = $politician->campaigns->count();
                    @endphp
                    @if($activeCampaignsCount > 0)
                    <div class="absolute bottom-3 left-3 bg-slate-900/90 backdrop-blur-sm border border-slate-700 rounded-lg px-2 py-1 flex items-center gap-1.5">
                        <svg class="w-3 h-3 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.069A1 1 0 0121 8.87v6.26a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                        </svg>
                        <span class="text-xs text-emerald-400 font-medium">{{ $activeCampaignsCount }} active</span>
                    </div>
                    @endif
                </div>

                {{-- Info Section --}}
                <div class="p-4 flex-1 flex flex-col">
                    <h3 class="text-white font-semibold text-sm mb-1 group-hover:text-emerald-400 transition truncate">
                        {{ $safeName }}
                    </h3>

                    @if(is_null($politician->user_id))
                    <div class="mb-2">
                        <span class="inline-flex items-center gap-1 rounded-full border border-amber-500/30 bg-amber-500/10 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-amber-300">
                            Unclaimed Profile
                        </span>
                    </div>
                    @endif

                    @if($politician->is_running_candidate)
                    <div class="mb-2">
                        <span class="inline-flex items-center gap-1.5 rounded-full border border-violet-500/30 bg-violet-500/10 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-violet-300">
                            <svg class="w-2.5 h-2.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/></svg>
                            2026 Candidate
                        </span>
                    </div>
                    @elseif(in_array($politician->term_status ?? 'unknown', ['retired', 'lost']))
                    <div class="mb-2">
                        <span class="inline-flex items-center gap-1.5 rounded-full border border-slate-600/40 bg-slate-700/30 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-400">
                            {{ $politician->term_status === 'lost' ? 'Former Candidate' : 'Former Member' }}
                        </span>
                    </div>
                    @endif

                    @if($politician->political_office)
                    <p class="text-slate-400 text-xs mb-2 truncate">
                        {{ $politician->political_office }}
                    </p>
                    @endif

                    {{-- Details --}}
                    <div class="mt-auto space-y-1.5">
                        @if($politician->governance_level)
                        <div class="flex items-center gap-1.5 text-xs">
                            <svg class="w-3 h-3 text-slate-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                            </svg>
                            <span class="text-slate-400 truncate">{{ $politician->governance_level }}</span>
                        </div>
                        @endif

                        @if($politician->state || $politician->city)
                        <div class="flex items-center gap-1.5 text-xs">
                            <svg class="w-3 h-3 text-slate-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            <span class="text-slate-400 truncate">
                                {{ implode(', ', array_filter([$politician->city, $politician->state])) }}
                            </span>
                        </div>
                        @endif

                        @if($politician->district)
                        <div class="flex items-center gap-1.5 text-xs">
                            <svg class="w-3 h-3 text-slate-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
                            </svg>
                            <span class="text-slate-400 truncate">{{ $politician->district }}</span>
                        </div>
                        @endif

                        @if($politician->party_affiliation)
                        <div class="flex items-center gap-1.5 text-xs">
                            <svg class="w-3 h-3 text-slate-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 21v-4m0 0V5a2 2 0 012-2h6.5l1 1H21l-3 6 3 6h-8.5l-1-1H5a2 2 0 00-2 2zm9-13.5V9"/>
                            </svg>
                            <span class="text-slate-400 truncate">{{ $politician->party_affiliation }}</span>
                        </div>
                        @endif
                    </div>

                    {{-- Latest news headline (pre-loaded by controller, no N+1) --}}
                    @php $latestArticle = $latestNewsMap[$politician->id] ?? null; @endphp
                    @if($latestArticle)
                    <div class="mt-3 pt-3 border-t border-slate-700/50">
                        <p class="text-[10px] uppercase tracking-wide text-slate-400 font-semibold mb-1">Latest News</p>
                        <a href="{{ $latestArticle->source_url }}" target="_blank" rel="noopener noreferrer"
                           class="text-xs text-slate-400 hover:text-white line-clamp-2 leading-snug transition"
                           onclick="event.stopPropagation()">
                            {{ $latestArticle->headline }}
                        </a>
                        @if($latestArticle->published_at)
                        <p class="text-[10px] text-slate-400 mt-0.5">{{ $latestArticle->published_at->diffForHumans() }}</p>
                        @endif
                    </div>
                    @endif

                    {{-- View Profile CTA --}}
                    <div class="mt-3 pt-3 border-t border-slate-700/50">
                        @if($canOpenProfile)
                        <span class="text-emerald-400 text-xs font-medium group-hover:text-emerald-300 flex items-center gap-1">
                            View Profile
                            <svg class="w-3.5 h-3.5 group-hover:translate-x-0.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </span>
                        @else
                        <span class="text-slate-400 text-xs font-medium flex items-center gap-1">
                            Profile unavailable
                        </span>
                        @endif
                    </div>
                </div>
                @if($canOpenProfile)
                </a>
                @endif
            </div>
            @endforeach
        </div>

        {{-- Pagination --}}
        @if($politicians->hasPages())
        <div class="mt-8">
            {{ $politicians->withQueryString()->links('pagination::tailwind') }}
        </div>
        @endif
        @endif

    </div>

    {{-- ── Footer ── --}}
    <footer class="border-t border-slate-800 mt-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 py-8">
            <div class="flex flex-col sm:flex-row items-center justify-between gap-4 text-sm text-slate-400">
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
