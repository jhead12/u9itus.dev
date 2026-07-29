@extends('layouts.voter')

@section('title', 'Causes')

@section('content')
<div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">

    {{-- Header --}}
    <div class="mb-6">
        <h1 class="text-2xl sm:text-3xl font-bold text-white flex items-center gap-3">
            <span class="w-10 h-10 rounded-xl bg-emerald-500/20 border border-emerald-500/30 flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/>
                </svg>
            </span>
            Causes
        </h1>
        <p class="mt-2 text-sm text-slate-400">Specific issues you can follow. See who's supporting them near you.</p>
    </div>

    {{-- Filter Bar --}}
    <div class="mb-6 bg-slate-800/40 border border-slate-700/60 rounded-2xl p-4">
        <form method="GET" action="{{ route('voter.causes.index') }}#results" class="flex flex-col gap-3 sm:flex-row sm:flex-wrap">
            <div class="relative flex-1 min-w-0 sm:min-w-[220px]">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-500 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <input type="text" name="q" value="{{ request('q') }}"
                    placeholder="Search causes by title..."
                    class="w-full bg-slate-900 border border-slate-700 text-white placeholder-slate-500 rounded-lg pl-9 pr-4 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 transition"/>
            </div>

            <div class="relative">
                <select name="state"
                    class="w-full bg-slate-900 border border-slate-700 text-slate-300 rounded-lg pl-3 pr-8 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-emerald-500 appearance-none cursor-pointer">
                    <option value="">All States</option>
                    @foreach($states as $abbr => $name)
                    <option value="{{ $abbr }}" {{ request('state') === $abbr ? 'selected' : '' }}>{{ $abbr }} - {{ $name }}</option>
                    @endforeach
                </select>
                <svg class="absolute right-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-slate-500 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </div>

            <div class="relative">
                <select name="topic_id"
                    class="w-full bg-slate-900 border border-slate-700 text-slate-300 rounded-lg pl-3 pr-8 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-emerald-500 appearance-none cursor-pointer">
                    <option value="">All Topics</option>
                    @foreach($topics as $id => $topicName)
                    <option value="{{ $id }}" {{ request('topic_id') === (string) $id ? 'selected' : '' }}>{{ $topicName }}</option>
                    @endforeach
                </select>
                <svg class="absolute right-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-slate-500 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </div>

            <div class="flex items-center gap-2">
                <button type="submit" class="bg-emerald-600 hover:bg-emerald-500 text-white px-4 py-2 rounded-lg text-sm font-medium transition">Filter</button>
                @if(request()->hasAny(['q', 'state', 'topic_id']))
                <a href="{{ route('voter.causes.index') }}" class="text-slate-400 hover:text-white text-sm px-3 py-2 transition">Clear</a>
                @endif
            </div>
        </form>
    </div>

    <div id="results" class="scroll-mt-32">
        <p class="text-slate-400 text-sm mb-4">{{ $causes->total() }} {{ Str::plural('cause', $causes->total()) }} found</p>

        @if($causes->isEmpty())
        <div class="text-center py-16 bg-slate-800/40 border border-slate-700/60 rounded-2xl">
            <h3 class="text-white font-semibold mb-1">No causes found</h3>
            <p class="text-slate-500 text-sm">No causes match your filters. Try widening your search.</p>
        </div>
        @else
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
            @foreach($causes as $cause)
            @php
                $scopeLabel = $cause->state
                    ? strtoupper($cause->state) . ($cause->county ? ' · ' . $cause->county : '')
                    : 'National';
            @endphp
            <a href="{{ route('voter.causes.show', $cause) }}"
               class="group flex flex-col bg-slate-800/50 border border-slate-700/60 hover:border-emerald-500/40 rounded-2xl p-5 transition">
                <div class="flex items-start justify-between gap-2 mb-1">
                    <h3 class="text-white font-semibold text-base group-hover:text-emerald-400 transition line-clamp-2">{{ $cause->title }}</h3>
                    @if(isset($cause->favorited_by_voter) && $cause->favorited_by_voter)
                    <svg class="w-4 h-4 text-amber-400 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 17.27l-5.18 3.04 1.4-5.92L3.5 9.5l6.06-.52L12 3.5l2.44 5.48 6.06.52-4.72 4.84 1.4 5.92z"/></svg>
                    @endif
                </div>

                @if($cause->topic)
                <p class="text-xs text-emerald-400/80 mb-2">{{ $cause->topic->name }}</p>
                @endif

                <p class="text-xs text-slate-500 mb-3">{{ $scopeLabel }}</p>

                @if($cause->description)
                <p class="text-slate-400 text-xs line-clamp-2 leading-snug mb-3">{{ $cause->description }}</p>
                @endif

                <div class="mt-auto pt-3 border-t border-slate-700/50 flex items-center justify-between">
                    <span class="text-slate-400 text-xs flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        @if($cause->state)
                            {{ $cause->nearby_supporters_count }} {{ Str::plural('supporter', $cause->nearby_supporters_count) }} near you
                        @else
                            {{ $cause->supporters_total_count }} {{ Str::plural('supporter', $cause->supporters_total_count) }} nationwide
                        @endif
                    </span>
                    <span class="text-emerald-400 text-xs font-medium group-hover:text-emerald-300 flex items-center gap-1">
                        View
                        <svg class="w-3.5 h-3.5 group-hover:translate-x-0.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </span>
                </div>
            </a>
            @endforeach
        </div>

        @if($causes->hasPages())
        <div class="mt-8">{{ $causes->withQueryString()->links('pagination::tailwind') }}</div>
        @endif
        @endif
    </div>

</div>
@endsection