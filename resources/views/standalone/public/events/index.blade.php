@extends('standalone.layouts.public')

@section('title', 'Civic Events')
@section('meta_description', 'Discover town halls, ballot measure drives, rallies, workshops, fundraisers, and community meetings on U9itus.')
@section('canonical', route('events.index'))

@section('content')
<section class="max-w-7xl mx-auto px-4 sm:px-6 py-10">
    <div class="mb-8">
        <h1 class="text-3xl sm:text-4xl font-extrabold text-white mb-2">Civic Events</h1>
        <p class="text-slate-400 max-w-2xl">Town halls, ballot measure drives, community meetings, rallies, workshops, and fundraisers happening near you.</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
        <aside class="lg:col-span-1">
            <form method="GET" action="{{ route('events.index') }}" class="bg-slate-900/60 border border-slate-800 rounded-xl p-4 space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-400 uppercase mb-2">Search</label>
                    <input type="text" name="q" value="{{ request('q') }}" placeholder="Keyword..."
                        class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white focus:ring-emerald-500 focus:border-emerald-500">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-400 uppercase mb-2">Location</label>
                    <input type="text" name="location" value="{{ request('location') }}" placeholder="City or state..."
                        class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white focus:ring-emerald-500 focus:border-emerald-500">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-400 uppercase mb-2">Topic</label>
                    <select name="topic" class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white">
                        <option value="">All topics</option>
                        @foreach($topics as $topic)
                            <option value="{{ $topic->slug }}" @selected(request('topic') === $topic->slug)>{{ $topic->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex gap-2">
                    <button type="submit" class="flex-1 bg-emerald-600 hover:bg-emerald-500 text-white text-sm font-semibold rounded-lg px-4 py-2">Filter</button>
                    <a href="{{ route('events.index') }}" class="px-4 py-2 text-sm text-slate-400 hover:text-white border border-slate-700 rounded-lg">Reset</a>
                </div>
            </form>
        </aside>

        <div class="lg:col-span-3 space-y-5">
            @if($events->isEmpty())
                <div class="bg-slate-900/40 border border-slate-800 rounded-xl p-10 text-center">
                    <p class="text-slate-300">No upcoming civic events match your filters.</p>
                </div>
            @else
                @foreach($events as $event)
                    <article class="bg-slate-900/60 border border-slate-800 rounded-xl p-5 hover:border-slate-700 transition">
                        <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
                            <div class="flex-1">
                                <div class="flex flex-wrap items-center gap-2 mb-2">
                                    <span class="px-2 py-1 rounded text-xs font-medium bg-indigo-500/10 text-indigo-300 border border-indigo-500/20">{{ $event->event_type->label() }}</span>
                                    @if($event->isFull())
                                        <span class="px-2 py-1 rounded text-xs font-medium bg-red-500/10 text-red-300 border border-red-500/20">Full</span>
                                    @endif
                                    @if($event->is_virtual)
                                        <span class="px-2 py-1 rounded text-xs font-medium bg-slate-700 text-slate-300">Virtual</span>
                                    @endif
                                </div>
                                <h2 class="text-xl font-bold text-white mb-1">
                                    <a href="{{ route('events.show', $event->slug) }}" class="hover:text-emerald-300 transition">{{ $event->title }}</a>
                                </h2>
                                <p class="text-slate-400 text-sm line-clamp-2 mb-3">{{ $event->description }}</p>

                                <div class="flex flex-wrap items-center gap-4 text-sm text-slate-300">
                                    <span class="flex items-center gap-1">
                                        <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                        {{ $event->starts_at->format('l, F j, Y g:i A') }} {{ $event->timezone }}
                                    </span>
                                    <span class="flex items-center gap-1">
                                        <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                        {{ $event->location_name }}
                                    </span>
                                </div>
                            </div>

                            <div class="shrink-0 text-right">
                                <p class="text-sm text-slate-400 mb-2">{{ $event->attendingCount() }} attending</p>
                                <a href="{{ route('events.show', $event->slug) }}" class="inline-block px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white text-sm font-semibold rounded-lg">Details & RSVP</a>
                            </div>
                        </div>
                    </article>
                @endforeach

                @if($events->hasPages())
                    <div class="mt-6">{{ $events->links() }}</div>
                @endif
            @endif
        </div>
    </div>
</section>
@endsection
