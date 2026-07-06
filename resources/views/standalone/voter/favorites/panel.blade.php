{{-- HTML fragment rendered inside the favorites side panel (see layouts/voter.blade.php) --}}

@if($favorites->isEmpty())
    <div class="py-12 px-4 text-center">
        <div class="text-3xl mb-2">⭐</div>
        <p class="text-sm font-medium text-white mb-1">No favorites yet</p>
        <p class="text-xs text-slate-400 mb-4">Follow politicians to keep them one click away.</p>
        <a href="{{ route('politicians.directory') }}"
           class="inline-block bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-semibold px-4 py-2 rounded-lg transition">
            Browse Politicians
        </a>
    </div>
@else
    <ul class="space-y-1">
        @foreach($favorites as $politician)
            <li class="group flex items-center gap-3 rounded-lg px-2 py-2 hover:bg-slate-800/70 transition">

                {{-- Avatar --}}
                @if($politician->profile_photo_url)
                    <img src="{{ $politician->profile_photo_url }}" alt=""
                         class="h-9 w-9 rounded-full object-cover shrink-0">
                @else
                    <div class="h-9 w-9 rounded-full bg-indigo-600 flex items-center justify-center text-white font-bold text-sm shrink-0">
                        {{ strtoupper(substr($politician->full_name, 0, 1)) }}
                    </div>
                @endif

                {{-- Name / office --}}
                <div class="min-w-0 flex-1">
                    @if($politician->slug)
                        <a href="{{ route('politician.public.show', $politician->slug) }}"
                           class="block text-sm font-medium text-slate-200 hover:text-white truncate">
                            {{ $politician->full_name }}
                        </a>
                    @else
                        <span class="block text-sm font-medium text-slate-200 truncate">{{ $politician->full_name }}</span>
                    @endif
                    <p class="text-[11px] text-slate-500 truncate">
                        {{ $politician->political_office }}{{ $politician->state ? ', '.$politician->state : '' }}
                    </p>
                </div>

                {{-- Unfollow --}}
                <form method="POST" action="{{ route('voter.favorites.destroy', $politician->id) }}" data-favorite-unfollow>
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                            class="p-1.5 rounded-md text-slate-600 hover:text-red-400 hover:bg-slate-700/60 opacity-0 group-hover:opacity-100 focus:opacity-100 transition"
                            title="Unfollow {{ $politician->full_name }}"
                            aria-label="Unfollow {{ $politician->full_name }}">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </form>
            </li>
        @endforeach
    </ul>
@endif
