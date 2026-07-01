@extends('layouts.voter')

@section('title', 'Politicians I Follow')

@section('content')
<div class="max-w-4xl mx-auto py-8 px-4 sm:px-6 lg:px-8">

    <div class="mb-6">
        <h1 class="text-2xl font-bold text-white">Politicians I Follow</h1>
        <p class="mt-1 text-sm text-gray-400">Candidates and officials you've bookmarked for quick access.</p>
    </div>

    @if($favorites->isEmpty())
        <div class="rounded-xl border border-gray-700 bg-gray-800/50 p-12 text-center">
            <div class="text-4xl mb-3">⭐</div>
            <h3 class="text-lg font-medium text-white mb-1">No favorites yet</h3>
            <p class="text-sm text-gray-400">Browse the <a href="{{ route('politicians.directory') }}" class="text-indigo-400 hover:text-indigo-300 underline">politician directory</a> and tap "Follow" on any profile.</p>
        </div>
    @else
        <div class="grid gap-4 sm:grid-cols-2">
            @foreach($favorites as $politician)
                <div class="rounded-xl border border-gray-700 bg-gray-800/50 p-5 flex items-start gap-4">

                    {{-- Avatar --}}
                    <div class="flex-shrink-0">
                        @if($politician->profile_photo_url)
                            <img src="{{ $politician->profile_photo_url }}" alt="{{ $politician->full_name }}"
                                 class="h-12 w-12 rounded-full object-cover">
                        @else
                            <div class="h-12 w-12 rounded-full bg-indigo-600 flex items-center justify-center text-white font-bold text-lg">
                                {{ strtoupper(substr($politician->full_name, 0, 1)) }}
                            </div>
                        @endif
                    </div>

                    {{-- Info --}}
                    <div class="flex-1 min-w-0">
                        <p class="text-white font-semibold truncate">{{ $politician->full_name }}</p>
                        <p class="text-xs text-gray-400 mt-0.5">{{ $politician->political_office }}{{ $politician->state ? ', '.$politician->state : '' }}</p>

                        {{-- Badges --}}
                        @if($politician->publicBadges->isNotEmpty())
                            <div class="flex flex-wrap gap-1.5 mt-2">
                                @foreach($politician->publicBadges->take(4) as $badge)
                                    <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium text-white"
                                          style="background-color: {{ $badge->topic->badge_color ?? '#6366f1' }}">
                                        {{ $badge->topic->icon ?? '' }} {{ $badge->topic->name }}
                                    </span>
                                @endforeach
                            </div>
                        @endif

                        {{-- Actions --}}
                        <div class="flex items-center gap-3 mt-3">
                            @if($politician->slug)
                                <a href="{{ route('politician.public.show', $politician->slug) }}"
                                   class="text-xs text-indigo-400 hover:text-indigo-300">View profile →</a>
                            @endif

                            <form method="POST" action="{{ route('voter.favorites.destroy', $politician->id) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                        class="text-xs text-red-400 hover:text-red-300"
                                        onclick="return confirm('Unfollow {{ addslashes($politician->full_name) }}?')">
                                    Unfollow
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-6">
            {{ $favorites->links() }}
        </div>
    @endif

</div>
@endsection
