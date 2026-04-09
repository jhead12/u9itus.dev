@extends('layouts.voter')

@section('title', 'Browse Politicians')

@section('content')
<div class="py-6">
    {{-- Page header inside the shared voter shell --}}
    <div class="bg-gradient-to-br from-emerald-900/20 via-slate-900/50 to-slate-950 border-y border-slate-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 py-8 sm:py-10">
            <div class="max-w-3xl">
                <h1 class="text-2xl sm:text-3xl font-bold text-white mb-3 flex items-center gap-3">
                    <span class="w-9 h-9 rounded-xl bg-emerald-500/20 border border-emerald-500/30 flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                    </span>
                    Browse Politicians & Officials
                </h1>
                <p class="text-slate-400 text-sm sm:text-base leading-relaxed">
                    Research verified politicians and local governance officials.
                    View profiles, campaign messaging, and transparency details before watching campaigns.
                </p>
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="border-b border-slate-800 bg-slate-900/70">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 py-4">
            @if(!empty($zipValidationError))
            <div class="mb-3 rounded-lg border border-rose-500/30 bg-rose-500/10 px-3 py-2 text-sm text-rose-200">
                {{ $zipValidationError }}
            </div>
            @endif

            <form method="GET" action="{{ route('politicians.directory') }}" class="flex flex-wrap gap-3">
                <div class="relative min-w-[180px]">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-500 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    <input type="text" name="zip" value="{{ request('zip', $zipInput ?? '') }}"
                        placeholder="ZIP Code"
                        inputmode="numeric"
                        maxlength="10"
                        pattern="\d{5}(-\d{4})?"
                        required
                        aria-label="ZIP Code"
                        class="w-full bg-slate-800 border border-slate-700 text-white placeholder-slate-500 rounded-lg pl-9 pr-4 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 transition"/>
                </div>

                <div class="relative flex-1 min-w-[220px]">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-500 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input type="text" name="q" value="{{ request('q') }}"
                        placeholder="Search by name or office..."
                        class="w-full bg-slate-800 border border-slate-700 text-white placeholder-slate-500 rounded-lg pl-9 pr-4 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 transition"/>
                </div>

                <div class="relative min-w-[190px]">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-500 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h8m-8 4h6"/>
                    </svg>
                    <input type="text" name="topic" value="{{ request('topic') }}"
                        placeholder="Topic (e.g. housing)"
                        class="w-full bg-slate-800 border border-slate-700 text-white placeholder-slate-500 rounded-lg pl-9 pr-4 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 transition"/>
                </div>

                <div class="relative min-w-[180px]">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-500 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 01.553-.894L9 2m0 18l6-3m-6 3V2m6 15l5.447 2.724A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 2"/>
                    </svg>
                    <input type="text" name="district" value="{{ request('district') }}"
                        placeholder="District"
                        class="w-full bg-slate-800 border border-slate-700 text-white placeholder-slate-500 rounded-lg pl-9 pr-4 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 transition"/>
                </div>

                <select name="level" class="bg-slate-800 border border-slate-700 text-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-emerald-500 appearance-none cursor-pointer">
                    <option value="">All Levels</option>
                    @foreach($governanceLevels as $level)
                    <option value="{{ $level }}" {{ request('level') === $level ? 'selected' : '' }}>{{ $level }}</option>
                    @endforeach
                </select>

                <select name="state" class="bg-slate-800 border border-slate-700 text-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-emerald-500 appearance-none cursor-pointer">
                    <option value="">All States</option>
                    @foreach($states as $abbr => $name)
                    <option value="{{ $abbr }}" {{ request('state') === $abbr ? 'selected' : '' }}>{{ $abbr }} - {{ $name }}</option>
                    @endforeach
                </select>

                <select name="party" class="bg-slate-800 border border-slate-700 text-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-emerald-500 appearance-none cursor-pointer">
                    <option value="">All Parties</option>
                    @foreach($parties as $party)
                    <option value="{{ $party }}" {{ request('party') === $party ? 'selected' : '' }}>{{ $party }}</option>
                    @endforeach
                </select>

                <select name="sort" class="bg-slate-800 border border-slate-700 text-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-emerald-500 appearance-none cursor-pointer">
                    <option value="name" {{ request('sort', 'name') === 'name' ? 'selected' : '' }}>Name (A-Z)</option>
                    <option value="recent" {{ request('sort') === 'recent' ? 'selected' : '' }}>Recently Added</option>
                    <option value="verified" {{ request('sort') === 'verified' ? 'selected' : '' }}>Verified First</option>
                </select>

                <label class="inline-flex items-center gap-2 rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-xs text-slate-300 cursor-pointer">
                    <input type="checkbox" name="unclaimed" value="1" {{ request()->boolean('unclaimed') ? 'checked' : '' }}
                        class="h-3.5 w-3.5 rounded border-slate-600 bg-slate-900 text-emerald-500 focus:ring-emerald-500" />
                    Unclaimed only
                </label>

                <button type="submit" class="bg-emerald-600 hover:bg-emerald-500 text-white px-4 py-2 rounded-lg text-sm font-medium transition">
                    Filter
                </button>

                @if(request()->hasAny(['zip', 'q', 'topic', 'district', 'level', 'state', 'party', 'sort', 'unclaimed']))
                <a href="{{ route('politicians.directory') }}" class="text-slate-400 hover:text-white text-sm flex items-center gap-1 px-3 py-2 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                    Clear
                </a>
                @endif
            </form>
        </div>
    </div>

    {{-- Results --}}
    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-8">
        <div class="flex items-center justify-between mb-6">
            <p class="text-slate-400 text-sm">
                {{ $politicians->total() }} {{ Str::plural('politician', $politicians->total()) }} found
            </p>
        </div>

        @if($politicians->isEmpty())
        <div class="text-center py-20 bg-slate-800/40 border border-slate-700/60 rounded-2xl">
            <div class="w-14 h-14 rounded-2xl bg-slate-700/60 flex items-center justify-center mx-auto mb-4">
                <svg class="w-7 h-7 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
            </div>
            <h3 class="text-white font-semibold mb-1">No politicians found</h3>
            <p class="text-slate-500 text-sm max-w-xs mx-auto">
                @if(request()->hasAny(['zip', 'q', 'topic', 'district', 'level', 'state', 'party', 'unclaimed']))
                    No politicians match your current filters. Try adjusting your search criteria.
                @else
                    No politicians are currently available in the directory.
                @endif
            </p>
            @if(request()->hasAny(['zip', 'q', 'topic', 'district', 'level', 'state', 'party', 'unclaimed']))
            <a href="{{ route('politicians.directory') }}" class="inline-block mt-4 text-emerald-400 hover:text-emerald-300 text-sm transition">
                Clear filters →
            </a>
            @endif
        </div>
        @else

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

                    @if($politician->verified_official)
                    <div class="absolute top-3 right-3 bg-emerald-500 rounded-full p-1.5">
                        <svg class="w-3.5 h-3.5 text-white" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                        </svg>
                    </div>
                    @endif

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

                    @if($politician->political_office)
                    <p class="text-slate-400 text-xs mb-2 truncate">
                        {{ $politician->political_office }}
                    </p>
                    @endif

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
                            <span class="text-slate-500 truncate">{{ implode(', ', array_filter([$politician->city, $politician->state])) }}</span>
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
                            <span class="text-slate-500 truncate">{{ $politician->party_affiliation }}</span>
                        </div>
                        @endif
                    </div>

                    <div class="mt-3 pt-3 border-t border-slate-700/50">
                        @if($canOpenProfile)
                        <span class="text-emerald-400 text-xs font-medium group-hover:text-emerald-300 flex items-center gap-1">
                            View Profile
                            <svg class="w-3.5 h-3.5 group-hover:translate-x-0.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </span>
                        @else
                        <span class="text-slate-500 text-xs font-medium flex items-center gap-1">
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

        @if($politicians->hasPages())
        <div class="mt-8">
            {{ $politicians->withQueryString()->links('pagination::tailwind') }}
        </div>
        @endif
        @endif
    </div>
</div>
@endsection
