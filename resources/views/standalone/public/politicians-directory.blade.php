<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Browse Politicians & Officials — {{ config('app.name', 'U9itus') }}</title>
    <meta name="description" content="Research and learn about verified politicians and local governance officials on U9itus. View campaign profiles, transparency data, and political stances.">

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
                <a href="{{ route('district.lookup') }}" class="text-sm text-slate-300 hover:text-white transition">Find My District</a>
                @auth
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
                    Browse Politicians & Officials
                </h1>
                <p class="text-slate-400 text-base leading-relaxed">
                    Research verified politicians and local governance officials on U9itus.
                    View their profiles, campaign messages, and transparency data in a public, view-only directory.
                </p>
                @if($isGuestBrowsing)
                <div class="mt-5 inline-flex max-w-2xl items-start gap-3 rounded-2xl border border-emerald-500/20 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-100">
                    <svg class="mt-0.5 h-4 w-4 flex-shrink-0 text-emerald-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <p>
                        Public directory is view-only for earnings. Guests can browse profiles and watch active public campaign videos, but commissions are only available after creating a voter account.
                    </p>
                </div>
                @endif
            </div>
        </div>
    </div>

    {{-- ── Filter Bar ── --}}
    <div class="border-b border-slate-800 sticky top-14 z-30 bg-slate-900/90 backdrop-blur-md">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 py-4">
            <form method="GET" action="{{ route('politicians.directory') }}" class="flex flex-wrap gap-3">
                {{-- Search --}}
                <div class="relative flex-1 min-w-[220px]">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-500 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input type="text" name="q" value="{{ request('q') }}"
                        placeholder="Search by name or office..."
                        class="w-full bg-slate-800 border border-slate-700 text-white placeholder-slate-500 rounded-lg pl-9 pr-4 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 transition"/>
                </div>

                {{-- Topic Filter --}}
                <div class="relative min-w-[190px]">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-500 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h8m-8 4h6"/>
                    </svg>
                    <input type="text" name="topic" value="{{ request('topic') }}"
                        placeholder="Topic (e.g. housing)"
                        class="w-full bg-slate-800 border border-slate-700 text-white placeholder-slate-500 rounded-lg pl-9 pr-4 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 transition"/>
                </div>

                {{-- District Filter --}}
                <div class="relative min-w-[180px]">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-500 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 01.553-.894L9 2m0 18l6-3m-6 3V2m6 15l5.447 2.724A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 2"/>
                    </svg>
                    <input type="text" name="district" value="{{ request('district') }}"
                        placeholder="District"
                        class="w-full bg-slate-800 border border-slate-700 text-white placeholder-slate-500 rounded-lg pl-9 pr-4 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 transition"/>
                </div>

                {{-- Governance Level --}}
                <select name="level"
                    class="bg-slate-800 border border-slate-700 text-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-emerald-500 appearance-none cursor-pointer">
                    <option value="">All Levels</option>
                    @foreach($governanceLevels as $level)
                    <option value="{{ $level }}" {{ request('level') === $level ? 'selected' : '' }}>{{ $level }}</option>
                    @endforeach
                </select>

                {{-- State Filter --}}
                <select name="state"
                    class="bg-slate-800 border border-slate-700 text-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-emerald-500 appearance-none cursor-pointer">
                    <option value="">All States</option>
                    @foreach($states as $abbr => $name)
                    <option value="{{ $abbr }}" {{ request('state') === $abbr ? 'selected' : '' }}>{{ $abbr }} - {{ $name }}</option>
                    @endforeach
                </select>

                {{-- Party Filter --}}
                <select name="party"
                    class="bg-slate-800 border border-slate-700 text-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-emerald-500 appearance-none cursor-pointer">
                    <option value="">All Parties</option>
                    @foreach($parties as $party)
                    <option value="{{ $party }}" {{ request('party') === $party ? 'selected' : '' }}>{{ $party }}</option>
                    @endforeach
                </select>

                {{-- Sort --}}
                <select name="sort"
                    class="bg-slate-800 border border-slate-700 text-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-emerald-500 appearance-none cursor-pointer">
                    <option value="name" {{ request('sort', 'name') === 'name' ? 'selected' : '' }}>Name (A-Z)</option>
                    <option value="recent" {{ request('sort') === 'recent' ? 'selected' : '' }}>Recently Added</option>
                    <option value="verified" {{ request('sort') === 'verified' ? 'selected' : '' }}>Verified First</option>
                </select>

                {{-- Unclaimed only --}}
                <label class="inline-flex items-center gap-2 rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-xs text-slate-300 cursor-pointer">
                    <input type="checkbox" name="unclaimed" value="1" {{ request()->boolean('unclaimed') ? 'checked' : '' }}
                        class="h-3.5 w-3.5 rounded border-slate-600 bg-slate-900 text-emerald-500 focus:ring-emerald-500" />
                    Unclaimed only
                </label>

                {{-- Action Buttons --}}
                <button type="submit"
                    class="bg-emerald-600 hover:bg-emerald-500 text-white px-4 py-2 rounded-lg text-sm font-medium transition">
                    Filter
                </button>

                                                                @if(request()->hasAny(['q', 'topic', 'district', 'level', 'state', 'party', 'sort', 'unclaimed']))
                     <a href="{{ route('politicians.directory') }}"
                   class="text-slate-400 hover:text-white text-sm flex items-center gap-1 px-3 py-2 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                    Clear
                </a>
                @endif
            </form>
        </div>
    </div>

    {{-- ── Results Section ── --}}
    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-8">
        
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
            <p class="text-slate-500 text-sm max-w-xs mx-auto">
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
            <a href="{{ route('politician.public.show', $politician->slug) }}"
               class="group flex flex-col bg-slate-800/50 border border-slate-700/60 hover:border-emerald-500/40 rounded-2xl overflow-hidden transition">
                
                {{-- Profile Photo --}}
                <div class="relative bg-gradient-to-br from-slate-700 to-slate-800 aspect-square overflow-hidden">
                    @if($politician->profile_photo_url)
                            <img src="{{ $politician->profile_photo_url }}"
                             alt="{{ $politician->full_name }}"
                             class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" />
                    @else
                        <div class="w-full h-full flex items-center justify-center">
                            <span class="text-5xl font-bold text-slate-600">
                                {{ strtoupper(substr($politician->full_name, 0, 1)) }}
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
                        {{ $politician->full_name }}
                    </h3>

                    @if(is_null($politician->user_id))
                    <div class="mb-2">
                        <span class="inline-flex items-center gap-1 rounded-full border border-amber-500/30 bg-amber-500/10 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-amber-300">
                            Unclaimed Profile
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
                            <span class="text-slate-500 truncate">{{ $politician->governance_level }}</span>
                        </div>
                        @endif

                        @if($politician->state || $politician->city)
                        <div class="flex items-center gap-1.5 text-xs">
                            <svg class="w-3 h-3 text-slate-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            <span class="text-slate-500 truncate">
                                {{ implode(', ', array_filter([$politician->city, $politician->state])) }}
                            </span>
                        </div>
                        @endif

                        @if($politician->party_affiliation)
                        <div class="flex items-center gap-1.5 text-xs">
                            <svg class="w-3 h-3 text-slate-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 21v-4m0 0V5a2 2 0 012-2h6.5l1 1H21l-3 6 3 6h-8.5l-1-1H5a2 2 0 00-2 2zm9-13.5V9"/>
                            </svg>
                            <span class="text-slate-500 truncate">{{ $politician->party_affiliation }}</span>
                        </div>
                        @endif
                    </div>

                    {{-- View Profile CTA --}}
                    <div class="mt-3 pt-3 border-t border-slate-700/50">
                        <span class="text-emerald-400 text-xs font-medium group-hover:text-emerald-300 flex items-center gap-1">
                            View Profile
                            <svg class="w-3.5 h-3.5 group-hover:translate-x-0.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </span>
                    </div>
                </div>
            </a>
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
