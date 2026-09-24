@php
    $stanceLabels = ['support' => 'Supports', 'oppose' => 'Opposes', 'mixed' => 'Mixed'];
    $cspanUrl = $speech->cspanUrl();
@endphp
<article class="bg-slate-800/40 border border-slate-700/40 rounded-xl p-5">
    <div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-slate-400">
        <time datetime="{{ $speech->spoken_on->toDateString() }}">{{ $speech->spoken_on->format('M j, Y') }}</time>
        <span aria-hidden="true">·</span>
        <span>{{ $speech->kind === 'written' ? 'Written statement' : ($speech->chamber === 'senate' ? 'Senate floor' : 'House floor') }}</span>
        @if($speech->topic)
            <span class="rounded-full bg-slate-700/60 px-2 py-0.5 text-slate-200">@if($speech->topic->icon)<span aria-hidden="true">{{ $speech->topic->icon }}</span> @endif{{ $speech->topic->name }}</span>
        @endif
        @if($speech->stance)
            <span class="rounded-full px-2 py-0.5 font-semibold {{ $speech->stance === 'support' ? 'bg-emerald-500/15 text-emerald-300' : ($speech->stance === 'oppose' ? 'bg-rose-500/15 text-rose-300' : 'bg-amber-500/15 text-amber-300') }}">{{ $stanceLabels[$speech->stance] }}</span>
        @endif
    </div>

    <h3 class="mt-2 font-semibold text-white">{{ $speech->displayTitle() }}</h3>

    @if($speech->position_summary)
        <p class="mt-1 text-sm text-slate-300">{{ $speech->position_summary }}</p>
    @endif

    @if($speech->quote)
        <blockquote class="mt-3 border-l-2 pl-3 text-sm italic text-slate-200" style="border-color:var(--p13-accent,#f59e0b)">“{{ $speech->quote }}”</blockquote>
    @elseif(! $speech->position_summary)
        <p class="mt-2 text-sm text-slate-300">{{ Str::limit($speech->body, 280) }}</p>
    @endif

    <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs font-semibold">
        <a href="{{ $speech->source_url }}" target="_blank" rel="noopener" class="underline" style="color:var(--p13-accent,#f59e0b)">Read in the Congressional Record</a>
        @if($cspanUrl)
            <a href="{{ $cspanUrl }}" target="_blank" rel="noopener" class="underline text-slate-300 hover:text-white" title="C-SPAN's video of the {{ $speech->chamber === 'senate' ? 'Senate' : 'House' }} floor that day">Watch that day on C-SPAN</a>
        @endif
    </div>
</article>
