@if(($data['articles'] ?? collect())->isEmpty())
    <p class="text-sm text-slate-500">No verified local election news yet.</p>
@else
    <ul class="divide-y divide-slate-700/30 -my-1">
        @foreach($data['articles'] as $article)
            <li class="py-2.5">
                <a href="{{ $article->source_url }}" target="_blank" rel="noopener noreferrer" class="text-sm text-slate-200 hover:text-emerald-400 transition line-clamp-2">
                    {{ $article->headline }}
                </a>
                <p class="text-xs text-slate-500 mt-0.5">
                    {{ $article->source_name ?? 'Unknown source' }}
                    @if($article->matched_locality) · {{ $article->matched_locality }}, {{ $article->state }} @endif
                    @if($article->published_at) · {{ $article->published_at->diffForHumans() }} @endif
                </p>
            </li>
        @endforeach
    </ul>
@endif
