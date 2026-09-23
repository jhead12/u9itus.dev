@php
    $articles = $data['articles'] ?? collect();
    $personalized = $data['personalized'] ?? false;
@endphp

@if($personalized)
    <p class="text-[11px] text-amber-400/80 uppercase tracking-wide mb-2">Matched to your interests</p>
@endif

@if($articles->isEmpty())
    <p class="text-sm text-slate-500">No verified local news yet — check back soon.</p>
@else
    <ul class="divide-y divide-slate-700/30 -my-1">
        @foreach($articles as $article)
            <li class="py-2.5">
                <a href="{{ $article->source_url }}" target="_blank" rel="noopener noreferrer" class="text-sm text-slate-200 hover:text-amber-400 transition line-clamp-2">
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
