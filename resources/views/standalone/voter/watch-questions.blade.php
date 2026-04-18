@extends('layouts.voter')

@section('title', 'Campaign Q&A: ' . $campaign->title)

@section('content')
<div class="max-w-3xl mx-auto px-4 sm:px-6 py-8 space-y-6">
    <div class="flex items-center justify-between gap-3">
        <div>
            <p class="text-xs uppercase tracking-[0.24em] text-slate-500">Campaign Q&amp;A</p>
            <h1 class="mt-2 text-2xl font-semibold text-white">{{ $campaign->title }}</h1>
            <p class="mt-1 text-sm text-slate-400">Anonymous voter questions and official campaign responses for this campaign only.</p>
        </div>
        <a href="{{ route('voter.dashboard') }}" class="inline-flex items-center gap-2 rounded-lg border border-slate-700/60 bg-slate-800/60 px-4 py-2 text-sm text-slate-300 transition hover:text-white hover:bg-slate-700/60">
            Back to Dashboard
        </a>
    </div>

    <div class="rounded-2xl border border-slate-700/60 bg-slate-800/55 p-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-xs uppercase tracking-widest text-slate-500">Current campaign</p>
                <p class="mt-1 text-sm text-slate-300">{{ $campaign->politician->full_name ?? 'Campaign Owner' }} @if($campaign->politician->political_office)· {{ $campaign->politician->political_office }} @endif</p>
            </div>
            <button onclick="window.close()" type="button" class="inline-flex items-center gap-2 rounded-lg border border-emerald-500/25 bg-emerald-500/10 px-4 py-2 text-sm text-emerald-200 transition hover:bg-emerald-500/15">
                Close This Tab
            </button>
        </div>
    </div>

    @if($questions->isEmpty())
        <div class="rounded-2xl border border-slate-700/60 bg-slate-800/50 px-6 py-12 text-center">
            <p class="text-sm text-slate-400">No public campaign Q&amp;A has been published yet.</p>
        </div>
    @else
        <div class="space-y-4">
            @foreach($questions as $entry)
                <article class="rounded-2xl border border-slate-700/60 bg-slate-800/50 p-5">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="text-xs uppercase tracking-wide text-slate-500">Voter Question</p>
                        <p class="text-xs text-slate-500">{{ $entry->public_alias ?: 'Verified Voter' }}</p>
                    </div>

                    <p class="mt-2 text-base leading-relaxed text-slate-100">{{ $entry->body }}</p>

                    <div class="mt-4 rounded-xl border border-emerald-500/20 bg-emerald-500/10 px-4 py-3">
                        <p class="text-xs uppercase tracking-wide text-emerald-300">Official Campaign Response</p>
                        <p class="mt-1.5 text-sm leading-relaxed text-emerald-100 whitespace-pre-line">{{ $entry->campaign_reply ?: $entry->admin_notes }}</p>
                    </div>
                </article>
            @endforeach
        </div>

        <div>
            {{ $questions->links() }}
        </div>
    @endif
</div>
@endsection
