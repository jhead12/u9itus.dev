@extends('standalone.layouts.public')

@section('title', $event->title)
@section('meta_description', Str::limit(strip_tags($event->description), 160))
@section('canonical', route('events.show', $event->slug))

@push('meta')
    <meta property="og:title" content="{{ $event->title }}">
    <meta property="og:description" content="{{ Str::limit(strip_tags($event->description), 160) }}">
    <meta property="og:type" content="article">
    <meta property="og:url" content="{{ route('events.show', $event->slug) }}">
    @if($event->image_url)
        <meta property="og:image" content="{{ $event->image_url }}">
    @endif
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $event->title }}">
    <meta name="twitter:description" content="{{ Str::limit(strip_tags($event->description), 160) }}">
    @if($event->image_url)
        <meta name="twitter:image" content="{{ $event->image_url }}">
    @endif
@endpush

@section('content')
<section class="max-w-5xl mx-auto px-4 sm:px-6 py-10">
    <div class="mb-6">
        <a href="{{ route('events.index') }}" class="text-sm text-slate-400 hover:text-white transition">← All events</a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <div class="lg:col-span-2">
            <div class="flex flex-wrap items-center gap-2 mb-4">
                <span class="px-2.5 py-1 rounded text-xs font-semibold bg-indigo-500/10 text-indigo-300 border border-indigo-500/20">{{ $event->event_type->label() }}</span>
                @if($event->status->value === 'cancelled')
                    <span class="px-2.5 py-1 rounded text-xs font-semibold bg-red-500/10 text-red-300 border border-red-500/20">Cancelled</span>
                @elseif($event->isFull())
                    <span class="px-2.5 py-1 rounded text-xs font-semibold bg-red-500/10 text-red-300 border border-red-500/20">Full</span>
                @endif
            </div>

            <h1 class="text-3xl sm:text-4xl font-extrabold text-white mb-4">{{ $event->title }}</h1>

            <div class="flex flex-wrap items-center gap-5 text-slate-300 mb-8">
                <span class="flex items-center gap-1.5">
                    <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    {{ $event->starts_at->format('l, F j, Y g:i A') }} {{ $event->timezone }}
                </span>
                <span class="flex items-center gap-1.5">
                    <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    {{ $event->location_name }}
                </span>
            </div>

            @if($event->image_url)
                <img src="{{ $event->image_url }}" alt="{{ $event->title }}" class="w-full rounded-xl border border-slate-800 mb-8">
            @endif

            <div class="prose prose-invert max-w-none">
                {!! $event->description !!}
            </div>

            @if($event->venue_name || $event->address)
                <div class="mt-8 bg-slate-900/60 border border-slate-800 rounded-xl p-5">
                    <h3 class="text-sm font-semibold text-slate-200 uppercase mb-3">Venue</h3>
                    <p class="text-slate-300">
                        @if($event->venue_name)<strong>{{ $event->venue_name }}</strong><br>@endif
                        @if($event->address){{ $event->address }}<br>@endif
                        {{ $event->city }}@if($event->state), {{ $event->state }}@endif @if($event->zip){{ $event->zip }}@endif
                    </p>
                </div>
            @endif
        </div>

        <aside class="lg:col-span-1">
            @include('standalone.public.events.rsvp-card', ['event' => $event, 'rsvp' => $rsvp])

            <div class="mt-6 bg-slate-900/60 border border-slate-800 rounded-xl p-5">
                <h3 class="text-sm font-semibold text-slate-200 uppercase mb-3">Hosted by</h3>
                @php
                    $host = $event->host;
                    $hostName = match(true) {
                        $host instanceof \App\Models\Politician => $host->public_name ?? $host->user->name ?? 'Politician',
                        $host instanceof \App\Models\NeighborhoodGroup => $host->name,
                        default => $host?->organization_name ?? $host?->user->name ?? 'Citizen',
                    };
                    $hostUrl = match(true) {
                        $host instanceof \App\Models\Politician => route('politician.public.show', $host->slug ?? $host->id),
                        $host instanceof \App\Models\NeighborhoodGroup => route('groups.public.show', $host->scope ? ['group' => $host, 'scope' => $host->scopeUrlSegment()] : $host),
                        default => null,
                    };
                @endphp
                <p class="text-white font-medium">
                    @if($hostUrl)
                        <a href="{{ $hostUrl }}" class="text-emerald-300 hover:text-emerald-200">{{ $hostName }}</a>
                    @else
                        {{ $hostName }}
                    @endif
                </p>
            </div>
        </aside>
    </div>
</section>
@endsection
