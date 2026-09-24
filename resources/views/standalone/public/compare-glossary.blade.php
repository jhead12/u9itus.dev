@extends('standalone.layouts.public')
@section('title', 'What each office does')
@section('meta_description', 'Plain-language descriptions of the federal, state, and local offices you can compare on U9itus.')
@section('content')
<div class="mx-auto max-w-3xl px-4 py-12 text-slate-200">
    <p class="text-xs font-bold tracking-widest text-emerald-300">VOTER RESEARCH · GLOSSARY</p>
    <h1 class="mt-3 text-4xl font-bold text-white">What does each office do?</h1>
    <p class="mt-4 leading-relaxed text-slate-300">Short, general descriptions of the offices on your ballot. The same title can carry very different powers from place to place: a New York City mayor runs city agencies and the budget, while in many California cities a professional city manager runs day-to-day operations. Where we have reviewed a state's or city's own rules, a sourced note appears under the general description. Otherwise, check your state or local election office's official voter guide.</p>
    <p class="mt-4"><a href="{{ route('candidates.compare') }}" class="text-emerald-300 underline hover:text-emerald-200">Back to compare candidates</a></p>
    <dl class="mt-10 space-y-8">
        @foreach($entries as $slug => $entry)
            <div id="{{ $slug }}" class="scroll-mt-24 border-t border-slate-700 pt-6">
                <dt class="text-xl font-semibold text-white">{{ $entry['title'] }}</dt>
                <dd class="mt-2 leading-relaxed text-slate-300"><span class="text-xs font-semibold uppercase tracking-wide text-slate-400">General description</span><br>{{ $entry['description'] }}</dd>
                @foreach($notes[$slug] ?? [] as $note)
                    <dd class="mt-4 border-l-2 border-emerald-500/60 pl-4 leading-relaxed text-slate-300">
                        <span class="text-xs font-semibold uppercase tracking-wide text-emerald-300">In {{ $note['place'] }}</span><br>{{ $note['description'] }}
                        <span class="mt-1 block text-sm text-slate-400">Source: @if(preg_match('#^https?://#i', $note['source_url']))<a href="{{ $note['source_url'] }}" class="underline hover:text-slate-200" target="_blank" rel="noopener noreferrer">{{ $note['source_label'] }}</a>@else{{ $note['source_label'] }}@endif · Reviewed {{ \Illuminate\Support\Carbon::parse($note['reviewed_at'])->format('F j, Y') }}</span>
                    </dd>
                @endforeach
            </div>
        @endforeach
    </dl>
</div>
@endsection
