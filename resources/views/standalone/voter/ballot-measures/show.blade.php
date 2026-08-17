@extends('layouts.voter')

@section('title', $measure->title)

@section('content')
<div class="max-w-4xl mx-auto py-8 px-4 sm:px-6 lg:px-8">

    <a href="{{ route('voter.ballot-measures.index') }}" class="inline-flex items-center gap-1.5 text-sm text-slate-400 hover:text-white transition mb-4">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        All Ballot Measures
    </a>

    @php
        $statusPill = match($measure->status) {
            'passed' => 'border-emerald-500/30 bg-emerald-500/10 text-emerald-300',
            'failed' => 'border-rose-500/30 bg-rose-500/10 text-rose-300',
            default => 'border-amber-500/30 bg-amber-500/10 text-amber-300',
        };
        $nearby = (int) $measure->nearby_supporters_count;
        $total  = (int) $measure->supporters_total_count;
    @endphp

    {{-- Hero --}}
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-2xl p-6 sm:p-8">
        <div class="flex flex-wrap items-start justify-between gap-4 mb-4">
            <div>
                @if($measure->measure_number)
                <p class="text-xs text-slate-500 mb-1">Measure #{{ $measure->measure_number }}</p>
                @endif
                <h1 class="text-2xl sm:text-3xl font-bold text-white">{{ $measure->title }}</h1>
            </div>
            <span class="inline-flex items-center rounded-full border {{ $statusPill }} px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wide">{{ ucfirst($measure->status) }}</span>
        </div>

        <div class="flex flex-wrap items-center gap-4 text-sm text-slate-400 mb-5">
            <span class="flex items-center gap-1.5">
                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                {{ strtoupper($measure->state ?? '') }}{{ $measure->county ? ' · ' . $measure->county : '' }}
            </span>
            @if($measure->election_date)
            <span class="flex items-center gap-1.5">
                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                {{ $measure->election_date->format('M j, Y') }}
            </span>
            @endif

            {{-- People near you count (state-scoped, peer-excluded) --}}
            <span class="flex items-center gap-1.5">
                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656.126-1.283.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                @if($nearby > 0)
                    {{ $nearby }} {{ Str::plural('supporter', $nearby) }} near you
                @else
                    Be the first supporter near you
                @endif
            </span>

            {{-- Total followers (nationwide) --}}
            <span class="flex items-center gap-1.5">
                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.6 9h16.8M3.6 15h16.8M12 3a15 15 0 010 18M12 3a15 15 0 000 18"/>
                </svg>
                {{ $total }} {{ Str::plural('follower', $total) }} total
            </span>
        </div>

        {{-- Favorite toggle --}}
        @include('standalone.voter.partials.favorite-toggle', [
            'isFavorited' => $isFavorited,
            'storeRoute' => route('voter.ballot-measures.store', $measure->id),
            'destroyRoute' => route('voter.ballot-measures.destroy', $measure->id),
            'followLabel' => 'Follow this Measure',
            'followingLabel' => 'Following',
        ])
    </div>

    {{-- Summary --}}
    @if($measure->summary)
    <div class="mt-6">
        <h2 class="text-lg font-bold text-white mb-3">Summary</h2>
        <div class="bg-slate-800/40 border border-slate-700/40 rounded-xl p-5 text-sm text-slate-300 leading-relaxed whitespace-pre-line">
            {{ $measure->summary }}
        </div>
    </div>
    @endif

    {{-- Yes / No meaning --}}
    @if($measure->yes_meaning || $measure->no_meaning)
    <div class="mt-6">
        <h2 class="text-lg font-bold text-white mb-3">What a Vote Means</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="bg-slate-800/40 border border-slate-700/40 rounded-xl p-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-emerald-400 mb-2">A YES vote means</p>
                <p class="text-sm text-slate-300 leading-relaxed">{{ $measure->yes_meaning ?? '—' }}</p>
            </div>
            <div class="bg-slate-800/40 border border-slate-700/40 rounded-xl p-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-rose-400 mb-2">A NO vote means</p>
                <p class="text-sm text-slate-300 leading-relaxed">{{ $measure->no_meaning ?? '—' }}</p>
            </div>
        </div>
    </div>
    @endif

    @if($measure->source_url)
    <a href="{{ $measure->source_url }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1.5 mt-4 text-sm text-emerald-400 hover:text-emerald-300 transition">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
        View source{{ $measure->source ? ' · ' . ucfirst($measure->source) : '' }}
    </a>
    @endif

</div>
@endsection