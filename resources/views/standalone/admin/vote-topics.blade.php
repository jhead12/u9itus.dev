@extends('standalone.layouts.dashboard')

@section('title', 'Vote Tagging')
@section('page-title', 'Vote Tagging')

@section('content')
<div class="max-w-7xl mx-auto space-y-6">
    @if(session('success'))
        <div class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-200">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-200">
            <p class="font-semibold">Please correct the following:</p>
            <ul class="mt-1 list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div>
        <p class="text-xs font-semibold uppercase tracking-[.18em] text-amber-300">Human review required</p>
        <h1 class="mt-1 text-3xl font-bold text-white">Vote tagging</h1>
        <p class="mt-2 max-w-3xl text-sm text-slate-400">Tie a roll call to a topic so members who voted get a badge for it. A yes vote does not always mean support: on a war powers resolution, yea is a vote to limit military action. Read the measure, choose what a yea vote means, and describe each vote in plain, neutral words. The badge shows that wording, e.g. “Voted to limit military action against Iran”. Only topics with support and oppose wording (set on the Topics page) can be tagged.</p>
    </div>

    <form method="GET" action="{{ route('admin.vote-topics.index') }}" class="flex flex-wrap gap-2">
        <input type="search" name="q" value="{{ $search }}" placeholder="Search title, question or bill (e.g. Iran, S.J.Res.)" class="w-96 rounded-lg border-slate-600 bg-slate-900 text-sm text-white">
        <select name="show" class="rounded-lg border-slate-600 bg-slate-900 text-sm text-white">
            <option value="tagged" @selected($show === 'tagged')>Tagged votes</option>
            <option value="all" @selected($show === 'all')>All votes</option>
        </select>
        <button class="rounded-lg border border-slate-600 px-4 py-2 text-sm text-slate-200 hover:bg-slate-800">Search</button>
    </form>

    <div class="space-y-4">
        @forelse($votes as $vote)
            @php $billUrl = $vote->billUrl(); @endphp
            <article class="rounded-2xl border border-slate-700/70 bg-slate-800/40 p-5" id="vote-{{ $vote->id }}">
                <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-xs uppercase tracking-wide text-slate-400">{{ ucfirst($vote->chamber) }} roll call {{ $vote->roll_number }} · {{ $vote->congress }}th Congress · {{ $vote->voted_at?->format('M j, Y') }}</p>
                        <p class="mt-1 text-lg font-semibold text-white">{{ $vote->title ?: $vote->question }}</p>
                        @if($vote->title && $vote->question)
                            <p class="mt-1 text-sm text-slate-300">Question: {{ $vote->question }}</p>
                        @endif
                        <p class="mt-1 text-xs text-slate-400">
                            {{ collect([$vote->bill_number, $vote->result, "Yea {$vote->yeas} · Nay {$vote->nays}"])->filter()->implode(' · ') }}
                            @if($billUrl) · <a href="{{ $billUrl }}" target="_blank" rel="noopener noreferrer" class="underline hover:text-white">Read the measure</a>@endif
                            @if($vote->source_url) · <a href="{{ $vote->source_url }}" target="_blank" rel="noopener noreferrer" class="underline hover:text-white">Roll call</a>@endif
                        </p>
                    </div>
                </div>

                @foreach($vote->topicTags as $tag)
                    <div class="mt-4 rounded-xl border border-slate-700/60 bg-slate-900/60 p-4 text-sm">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="font-semibold text-white">{{ $tag->topic?->icon }} {{ $tag->topic?->name }}</p>
                                <p class="mt-1 text-emerald-200">Yea: {{ $tag->yea_label }} <span class="text-slate-400">({{ $tag->topic?->stanceLabel($tag->yea_stance) }})</span></p>
                                <p class="text-rose-200">Nay: {{ $tag->nay_label }}</p>
                                <p class="mt-1 text-xs text-slate-400">{{ $tag->taggedBy?->name ?? 'An editor' }} · {{ $tag->updated_at?->diffForHumans() }}@if($tag->note) · {{ $tag->note }}@endif</p>
                            </div>
                            @can('civic.edit')
                                <form method="POST" action="{{ route('admin.vote-topics.destroy', $tag) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button class="rounded-lg border border-slate-600 px-3 py-2 text-sm text-slate-200 hover:bg-slate-800">Remove tag</button>
                                </form>
                            @endcan
                        </div>
                    </div>
                @endforeach

                @can('civic.edit')
                <details class="mt-4" @if($errors->any() && (int) old('vote_id') === $vote->id) open @endif>
                    <summary class="cursor-pointer text-sm font-semibold text-sky-300">{{ $vote->topicTags->isEmpty() ? 'Tag this vote' : 'Tag another topic or edit a tag' }}</summary>
                    <form method="POST" action="{{ route('admin.vote-topics.store', $vote) }}" class="mt-3 grid grid-cols-1 md:grid-cols-2 gap-3">
                        @csrf
                        <input type="hidden" name="vote_id" value="{{ $vote->id }}">
                        <label class="block">
                            <span class="text-xs font-semibold text-slate-300">Topic</span>
                            <select name="topic_id" required class="mt-1 w-full rounded-lg border-slate-600 bg-slate-900 text-sm text-white">
                                @foreach($topics as $topic)
                                    <option value="{{ $topic->id }}">{{ $topic->icon }} {{ $topic->name }}{{ $topic->kind === 'current_event' ? ' (current event)' : '' }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="block">
                            <span class="text-xs font-semibold text-slate-300">A yea vote means the member…</span>
                            <select name="yea_stance" required class="mt-1 w-full rounded-lg border-slate-600 bg-slate-900 text-sm text-white">
                                <option value="support">takes the topic’s “support” side</option>
                                <option value="oppose">takes the topic’s “oppose” side</option>
                            </select>
                        </label>
                        <label class="block">
                            <span class="text-xs font-semibold text-slate-300">Badge for a yea vote</span>
                            <input name="yea_label" required maxlength="255" placeholder="e.g. Voted to limit military action against Iran" class="mt-1 w-full rounded-lg border-slate-600 bg-slate-900 text-sm text-white">
                        </label>
                        <label class="block">
                            <span class="text-xs font-semibold text-slate-300">Badge for a nay vote</span>
                            <input name="nay_label" required maxlength="255" placeholder="e.g. Voted against limiting military action against Iran" class="mt-1 w-full rounded-lg border-slate-600 bg-slate-900 text-sm text-white">
                        </label>
                        <label class="block md:col-span-2">
                            <span class="text-xs font-semibold text-slate-300">Note (optional)</span>
                            <input name="note" maxlength="500" placeholder="e.g. Passage vote on the war powers resolution" class="mt-1 w-full rounded-lg border-slate-600 bg-slate-900 text-sm text-white">
                        </label>
                        <div class="md:col-span-2">
                            <button class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500">Save tag</button>
                            <span class="ml-2 text-xs text-slate-400">Saving an existing topic’s tag replaces it.</span>
                        </div>
                    </form>
                </details>
                @endcan
            </article>
        @empty
            <p class="rounded-2xl border border-dashed border-slate-700 p-8 text-center text-slate-400">
                {{ $search !== '' ? 'No roll calls match that search.' : ($show === 'tagged' ? 'No votes tagged yet. Search for a roll call to tag one.' : 'No roll calls imported yet.') }}
            </p>
        @endforelse
    </div>

    {{ $votes->links() }}
</div>
@endsection
