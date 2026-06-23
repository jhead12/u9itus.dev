@extends('standalone.layouts.dashboard')

@section('title', 'Favorite Songs')

@section('content')
<div class="max-w-4xl mx-auto px-4 py-8 space-y-6">

    {{-- ── Header ────────────────────────────────────────────────────── --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-white flex items-center gap-2">
                <span aria-hidden="true">🎵</span> Favorite Songs
            </h1>
            <p class="text-sm text-slate-400 mt-1">
                Share up to 10 tracks that represent you. Voters can play them
                directly from your public profile and the U9itus map.
            </p>
        </div>
        <span class="inline-flex items-center gap-1 text-xs font-semibold
                     bg-emerald-400/10 border border-emerald-400/30 text-emerald-300
                     rounded-full px-3 py-1 self-start">
            {{ $picks->count() }} / 10
        </span>
    </div>

    @if(session('status'))
        <div class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-200 rounded-xl px-4 py-3 text-sm">
            {{ session('status') }}
        </div>
    @endif

    {{-- ── Add Song Form ─────────────────────────────────────────────── --}}
    @if($picks->count() < 10)
    <form action="{{ route('politician.song-picks.store') }}" method="POST"
          class="bg-slate-900/60 border border-indigo-500/20 rounded-2xl p-6 space-y-4">
        @csrf

        <div>
            <label for="track_url" class="block text-sm font-semibold text-slate-200 mb-1.5">
                Track URL
            </label>
            <input type="url" id="track_url" name="track_url" required
                   value="{{ old('track_url') }}"
                   placeholder="https://open.spotify.com/track/… or YouTube / Apple Music"
                   class="w-full bg-slate-950 border border-slate-700 focus:border-indigo-500
                          rounded-lg px-3 py-2.5 text-sm text-slate-100
                          placeholder:text-slate-600 focus:outline-none focus:ring-2 focus:ring-indigo-500/30">
            @error('track_url')
                <p class="mt-1.5 text-xs text-red-400">{{ $message }}</p>
            @enderror
            <p class="mt-1.5 text-xs text-slate-500">
                Paste a link from <strong class="text-slate-400">Spotify</strong>,
                <strong class="text-slate-400">Apple Music</strong>, or
                <strong class="text-slate-400">YouTube</strong>. The official embed player
                will appear on your profile.
            </p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label for="track_title" class="block text-sm font-semibold text-slate-200 mb-1.5">
                    Song title <span class="text-slate-500 font-normal">(optional)</span>
                </label>
                <input type="text" id="track_title" name="track_title" maxlength="200"
                       value="{{ old('track_title') }}"
                       class="w-full bg-slate-950 border border-slate-700 focus:border-indigo-500
                              rounded-lg px-3 py-2.5 text-sm text-slate-100">
            </div>
            <div>
                <label for="artist_name" class="block text-sm font-semibold text-slate-200 mb-1.5">
                    Artist <span class="text-slate-500 font-normal">(optional)</span>
                </label>
                <input type="text" id="artist_name" name="artist_name" maxlength="200"
                       value="{{ old('artist_name') }}"
                       class="w-full bg-slate-950 border border-slate-700 focus:border-indigo-500
                              rounded-lg px-3 py-2.5 text-sm text-slate-100">
            </div>
        </div>

        <div>
            <label for="note" class="block text-sm font-semibold text-slate-200 mb-1.5">
                Why this song? <span class="text-slate-500 font-normal">(optional, 280 char max)</span>
            </label>
            <textarea id="note" name="note" rows="2" maxlength="280"
                      class="w-full bg-slate-950 border border-slate-700 focus:border-indigo-500
                             rounded-lg px-3 py-2.5 text-sm text-slate-100 resize-none">{{ old('note') }}</textarea>
        </div>

        <label class="inline-flex items-center gap-2 text-sm text-slate-300 cursor-pointer">
            <input type="checkbox" name="is_explicit" value="1" @checked(old('is_explicit'))
                   class="rounded border-slate-600 bg-slate-950 text-indigo-500 focus:ring-indigo-500">
            Contains explicit content
        </label>

        <div class="flex items-center justify-end pt-2">
            <button type="submit"
                    class="bg-indigo-600 hover:bg-indigo-500 text-white font-semibold
                           text-sm px-5 py-2.5 rounded-lg transition">
                Add to my profile
            </button>
        </div>
    </form>
    @else
    <div class="bg-amber-500/10 border border-amber-500/30 text-amber-200 rounded-xl px-4 py-3 text-sm">
        You've reached the 10-song limit. Remove a track below to add a new one.
    </div>
    @endif

    {{-- ── Picks List ────────────────────────────────────────────────── --}}
    @if($picks->isEmpty())
        <div class="text-center py-12 bg-slate-900/40 border border-slate-800 rounded-2xl">
            <div class="text-4xl mb-3" aria-hidden="true">🎶</div>
            <p class="text-slate-300 font-semibold">No songs yet</p>
            <p class="text-sm text-slate-500 mt-1">Add your first track using the form above.</p>
        </div>
    @else
        <ul class="space-y-3">
            @foreach($picks as $pick)
            <li class="bg-slate-900/60 border border-slate-800 rounded-2xl p-4 sm:p-5
                       {{ $pick->is_active ? '' : 'opacity-60' }}">
                <div class="flex items-start gap-4">
                    {{-- Service badge --}}
                    <span class="shrink-0 text-xs font-bold uppercase tracking-wider
                                 px-2.5 py-1 rounded-md
                                 @class([
                                    'bg-green-500/10 border border-green-500/30 text-green-300'  => $pick->service === 'spotify',
                                    'bg-pink-500/10  border border-pink-500/30  text-pink-300'   => $pick->service === 'apple',
                                    'bg-red-500/10   border border-red-500/30   text-red-300'    => $pick->service === 'youtube',
                                 ])">
                        {{ $pick->service }}
                    </span>

                    <div class="flex-1 min-w-0">
                        <p class="font-semibold text-slate-100 truncate">
                            {{ $pick->track_title ?: 'Untitled track' }}
                        </p>
                        @if($pick->artist_name)
                            <p class="text-sm text-slate-400 truncate">{{ $pick->artist_name }}</p>
                        @endif
                        @if($pick->note)
                            <p class="text-sm text-slate-500 mt-1 line-clamp-2">{{ $pick->note }}</p>
                        @endif
                        @if(! $pick->is_active)
                            <p class="text-xs text-amber-300 mt-2">
                                ⚠ Removed by admin (takedown request). Contact support to appeal.
                            </p>
                        @endif
                    </div>

                    <form action="{{ route('politician.song-picks.destroy', $pick) }}" method="POST" class="shrink-0">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                                onclick="return confirm('Remove this song from your profile?');"
                                class="text-xs text-slate-500 hover:text-red-400 transition">
                            Remove
                        </button>
                    </form>
                </div>

                {{-- Live preview embed --}}
                @if($pick->is_active && $pick->embedUrl())
                <div class="mt-3">
                    <iframe src="{{ $pick->embedUrl() }}"
                            width="100%" height="{{ $pick->embedHeight() }}"
                            frameborder="0"
                            allow="{{ $pick->embedAllow() }}"
                            loading="lazy"
                            referrerpolicy="strict-origin-when-cross-origin"
                            sandbox="allow-scripts allow-same-origin allow-popups allow-presentation"
                            title="{{ $pick->track_title ?: 'Song preview' }}"
                            class="rounded-lg overflow-hidden bg-slate-950"></iframe>
                </div>
                @endif
            </li>
            @endforeach
        </ul>
    @endif

    {{-- ── Legal & ADA note ──────────────────────────────────────────── --}}
    <details class="bg-slate-900/40 border border-slate-800 rounded-xl">
        <summary class="cursor-pointer px-4 py-3 text-sm font-semibold text-slate-300">
            About this feature
        </summary>
        <div class="px-4 pb-4 text-sm text-slate-400 space-y-2 leading-relaxed">
            <p>
                U9itus does not host audio. We embed the streaming service's
                official player so you, the listener, get the same artist
                attribution, ads, and rights treatment as if you were on
                Spotify, Apple Music, or YouTube directly.
            </p>
            <p>
                If an artist asks us to remove your selection, we will
                temporarily disable it and reach out to you so you can pick
                an alternative. You can also remove tracks yourself at any time.
            </p>
        </div>
    </details>
</div>
@endsection
