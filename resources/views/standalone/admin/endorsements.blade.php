@extends('standalone.layouts.dashboard')

@section('title', 'Endorsement Review')
@section('page-title', 'Endorsement Review')

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

    <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[.18em] text-amber-300">Human review required</p>
            <h1 class="mt-1 text-3xl font-bold text-white">Endorsement review</h1>
            <p class="mt-2 max-w-3xl text-sm text-slate-400">These were detected automatically from news headlines. Detection only sees an office title near a word like “endorses” or “backs”, so it cannot tell who endorsed whom. Read the headline, then confirm who was endorsed. Only confirmed endorsements appear on profiles, the map, and comparisons.</p>
        </div>
        <div class="grid grid-cols-3 gap-2 text-center">
            @foreach($stats as $key => $count)
                <a href="{{ route('admin.endorsements.index', ['status' => $key]) }}" class="rounded-lg border px-3 py-2 hover:border-slate-500 {{ $status === $key ? 'border-amber-400/60 bg-amber-500/10' : 'border-slate-700 bg-slate-800/60' }}">
                    <span class="block text-lg font-bold text-white">{{ $count }}</span>
                    <span class="text-[10px] uppercase tracking-wide text-slate-400">{{ $key === 'detected' ? 'awaiting review' : $key }}</span>
                </a>
            @endforeach
        </div>
    </div>

    <form method="GET" action="{{ route('admin.endorsements.index') }}" class="flex flex-wrap gap-2">
        <input type="hidden" name="status" value="{{ $status }}">
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Search politician or endorser" class="w-72 rounded-lg border-slate-600 bg-slate-900 text-sm text-white">
        <button class="rounded-lg border border-slate-600 px-4 py-2 text-sm text-slate-200 hover:bg-slate-800">Search</button>
    </form>

    <div class="space-y-4">
        @forelse($endorsements as $endorsement)
            @php
                $article = $endorsement->sourceArticle;
                $suggestedBill = $endorsement->suggestedBillTitle();
                $href = $endorsement->source_url && preg_match('#^https?://#i', $endorsement->source_url) ? $endorsement->source_url : null;
            @endphp
            <article class="rounded-2xl border border-slate-700/70 bg-slate-800/40 p-5" id="endorsement-{{ $endorsement->id }}">
                <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-xs uppercase tracking-wide text-slate-400">Endorsement of</p>
                        <p class="text-lg font-semibold text-white">
                            @if($endorsement->politician?->slug)
                                <a href="{{ url('/p/'.$endorsement->politician->slug) }}" target="_blank" rel="noopener" class="hover:underline">{{ $endorsement->politician->full_name }}</a>
                            @else
                                {{ $endorsement->politician?->full_name ?? 'Unknown politician' }}
                            @endif
                            <span class="text-sm font-normal text-slate-400">{{ collect([$endorsement->politician?->political_office, $endorsement->politician?->state])->filter()->implode(' · ') }}</span>
                        </p>
                        <p class="mt-1 text-sm text-slate-300">Detected endorser: <strong class="text-white">{{ $endorsement->endorserLabel() }}</strong>@if($endorsement->endorser_name) <span class="text-slate-400">({{ $endorsement->label }})</span>@endif</p>
                    </div>
                    <div class="shrink-0 text-right text-xs text-slate-400">
                        <p>Detection confidence {{ number_format((float) $endorsement->confidence * 100) }}% · {{ $endorsement->match_count }} {{ Str::plural('article', $endorsement->match_count) }}</p>
                        @if($endorsement->status !== 'detected')
                            <p class="mt-1 font-semibold {{ $endorsement->status === 'confirmed' ? 'text-emerald-300' : 'text-slate-300' }}">
                                {{ $endorsement->status === 'confirmed' ? ($endorsement->isBill() ? 'Confirmed: endorsed their bill' : 'Confirmed: endorsed the candidate') : 'Dismissed' }}
                            </p>
                            <p>{{ $endorsement->reviewedBy?->name ?? 'An editor' }} · {{ $endorsement->reviewed_at?->diffForHumans() }}</p>
                        @endif
                    </div>
                </div>

                <div class="mt-4 rounded-xl border border-slate-700/60 bg-slate-900/60 p-4 text-sm">
                    <p class="text-xs uppercase tracking-wide text-slate-500">Evidence</p>
                    @if($article?->headline)
                        <p class="mt-1 font-medium text-white">{{ $article->headline }}</p>
                    @endif
                    <p class="mt-1 text-slate-300">Matched phrase: “{{ $endorsement->matched_phrase }}”</p>
                    <p class="mt-2 text-xs text-slate-400">
                        {{ collect([$article?->source_name, $article?->published_at?->format('M j, Y')])->filter()->implode(' · ') }}
                        @if($href) · <a href="{{ $href }}" target="_blank" rel="noopener noreferrer" class="underline hover:text-white">Read the article</a>@endif
                    </p>
                    @if($endorsement->isBill() && $endorsement->bill_title)
                        <p class="mt-2 text-emerald-200">Bill: {{ $endorsement->bill_title }}</p>
                    @endif
                    @if($endorsement->review_note)
                        <p class="mt-2 text-xs text-slate-400">Note: {{ $endorsement->review_note }}</p>
                    @endif
                </div>

                @can('civic.edit')
                <form method="POST" action="{{ route('admin.endorsements.review', $endorsement) }}" class="mt-4 grid grid-cols-1 md:grid-cols-[1fr_1fr_auto] gap-3 items-end">
                    @csrf
                    <label class="block">
                        <span class="text-xs font-semibold text-slate-300">Bill title (for “endorsed their bill”)</span>
                        <input name="bill_title" maxlength="255" value="{{ old('bill_title', $endorsement->bill_title ?? $suggestedBill) }}" placeholder="e.g. Dignity Act" class="mt-1 w-full rounded-lg border-slate-600 bg-slate-900 text-sm text-white">
                        @if($suggestedBill && ! $endorsement->bill_title)
                            <span class="mt-1 block text-xs text-amber-300">The headline mentions a bill; check whether the bill, not the candidate, was endorsed.</span>
                        @endif
                    </label>
                    <label class="block">
                        <span class="text-xs font-semibold text-slate-300">Note (optional)</span>
                        <input name="review_note" maxlength="500" value="{{ old('review_note') }}" placeholder="Why this decision" class="mt-1 w-full rounded-lg border-slate-600 bg-slate-900 text-sm text-white">
                    </label>
                    <div class="flex flex-wrap gap-2">
                        @if($endorsement->status === 'detected')
                            <button name="action" value="confirm_candidate" class="rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-500">Endorsed the candidate</button>
                            <button name="action" value="confirm_bill" class="rounded-lg bg-sky-600 px-3 py-2 text-sm font-semibold text-white hover:bg-sky-500">Endorsed their bill</button>
                            <button name="action" value="dismiss" class="rounded-lg border border-slate-600 px-3 py-2 text-sm text-slate-200 hover:bg-slate-800">Not an endorsement</button>
                        @else
                            <button name="action" value="return_to_review" class="rounded-lg border border-slate-600 px-3 py-2 text-sm text-slate-200 hover:bg-slate-800">Return to review</button>
                        @endif
                    </div>
                </form>
                @endcan
            </article>
        @empty
            <p class="rounded-2xl border border-dashed border-slate-700 p-8 text-center text-slate-400">No endorsements {{ $status === 'detected' ? 'awaiting review' : 'in this list' }}.</p>
        @endforelse
    </div>

    {{ $endorsements->links() }}
</div>
@endsection
