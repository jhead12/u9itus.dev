@extends('layouts.voter')

@section('title', $cause->title)

@section('content')
<div class="max-w-4xl mx-auto py-8 px-4 sm:px-6 lg:px-8">

    <a href="{{ route('voter.causes.index') }}" class="inline-flex items-center gap-1.5 text-sm text-slate-400 hover:text-white transition mb-4">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        All Causes
    </a>

    {{-- Hero --}}
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-2xl p-6 sm:p-8">
        <div class="flex flex-wrap items-start justify-between gap-4 mb-4">
            <div>
                <h1 class="text-2xl sm:text-3xl font-bold text-white">{{ $cause->title }}</h1>
                @if($cause->topic)
                <p class="mt-1 text-sm text-emerald-400/80">{{ $cause->topic->name }}</p>
                @endif
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-4 text-sm text-slate-400 mb-5">
            <span class="flex items-center gap-1.5">
                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                {{ $cause->state ? strtoupper($cause->state) . ($cause->county ? ' · ' . $cause->county : '') : 'National' }}
            </span>

            {{-- People near you count (state-scoped, peer-excluded) --}}
            <span class="flex items-center gap-1.5">
                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                @php
                    $nearby = (int) $cause->nearby_supporters_count;
                    $total  = (int) $cause->supporters_total_count;
                @endphp
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
            'storeRoute' => route('voter.causes.store', $cause->id),
            'destroyRoute' => route('voter.causes.destroy', $cause->id),
            'followLabel' => 'Follow this Cause',
            'followingLabel' => 'Following',
        ])
    </div>

    {{-- Description --}}
    @if($cause->description)
    <div class="mt-6">
        <h2 class="text-lg font-bold text-white mb-3">About This Cause</h2>
        <div class="bg-slate-800/40 border border-slate-700/40 rounded-xl p-5 text-sm text-slate-300 leading-relaxed whitespace-pre-line">
            {{ $cause->description }}
        </div>
    </div>
    @endif

    @if($cause->source_url)
    <a href="{{ $cause->source_url }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1.5 mt-4 text-sm text-emerald-400 hover:text-emerald-300 transition">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
        View source
    </a>
    @endif

</div>
@endsection