@extends('standalone.layouts.dashboard')

@section('title', 'Public Chatter Review')
@section('page-title', 'Public Chatter Review')

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
            <h1 class="mt-1 text-3xl font-bold text-white">Public chatter collection</h1>
            <p class="mt-2 max-w-3xl text-sm text-slate-400">Collect social and publication links, add neutral context, and explicitly approve what appears on politician profiles. A high engagement count is evidence of attention—not truth.</p>
        </div>
        <div class="grid grid-cols-4 gap-2 text-center">
            @foreach($stats as $key => $count)
                <a href="{{ route('admin.politician-chatter.index', ['status' => $key]) }}" class="rounded-lg border border-slate-700 bg-slate-800/60 px-3 py-2 hover:border-slate-500">
                    <span class="block text-lg font-bold text-white">{{ $count }}</span>
                    <span class="text-[10px] uppercase tracking-wide text-slate-400">{{ $key }}</span>
                </a>
            @endforeach
        </div>
    </div>

    <details class="rounded-2xl border border-slate-700/70 bg-slate-800/40" {{ $errors->any() ? 'open' : '' }}>
        <summary class="cursor-pointer px-5 py-4 font-semibold text-white">+ Collect a source for review</summary>
        @can('chatter.create')
<form method="POST" action="{{ route('admin.politician-chatter.store') }}" class="border-t border-slate-700/60 p-5 grid grid-cols-1 md:grid-cols-2 gap-4">
            @csrf
            @include('standalone.admin.partials.chatter-fields', ['chatter' => null])
            <div class="md:col-span-2 flex justify-end">
                <button class="rounded-lg bg-amber-500 px-5 py-2.5 text-sm font-bold text-slate-950 hover:bg-amber-400">Add to review queue</button>
            </div>
        </form>
@endcan
    </details>

    <form method="GET" class="flex flex-col sm:flex-row gap-3 rounded-xl border border-slate-700/60 bg-slate-800/30 p-4">
        <input name="q" value="{{ request('q') }}" placeholder="Search politician, headline, or author" class="flex-1 rounded-lg border-slate-600 bg-slate-900 text-sm text-white">
        <select name="status" class="rounded-lg border-slate-600 bg-slate-900 text-sm text-white">
            @foreach(['pending' => 'Pending review', 'published' => 'Published', 'rejected' => 'Rejected', 'archived' => 'Archived', 'all' => 'All'] as $value => $label)
                <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <button class="rounded-lg bg-slate-700 px-5 py-2 text-sm font-semibold text-white hover:bg-slate-600">Filter</button>
    </form>

    <div class="space-y-4">
        @forelse($items as $chatter)
            <article class="rounded-2xl border border-slate-700/70 bg-slate-800/40 overflow-hidden">
                <div class="p-5 flex flex-col lg:flex-row lg:items-start gap-4">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2 text-xs">
                            <span class="rounded-full bg-slate-700 px-2.5 py-1 font-semibold text-slate-200">{{ $chatter->platformLabel() }}</span>
                            <span class="rounded-full bg-amber-500/10 px-2.5 py-1 font-semibold text-amber-200">{{ $chatter->claimStatusLabel() }}</span>
                            <span class="rounded-full bg-slate-900 px-2.5 py-1 font-semibold uppercase text-slate-400">{{ $chatter->moderation_status }}</span>
                        </div>
                        <h2 class="mt-3 text-lg font-semibold text-white">{{ $chatter->headline }}</h2>
                        <p class="mt-1 text-sm text-slate-400">{{ $chatter->politician->full_name }}{{ $chatter->source_author ? ' · '.$chatter->source_author : '' }}</p>
                        @if($chatter->summary)<p class="mt-3 text-sm leading-relaxed text-slate-300">{{ $chatter->summary }}</p>@endif
                        <a href="{{ $chatter->source_url }}" target="_blank" rel="noopener noreferrer" class="mt-3 inline-block break-all text-xs text-emerald-400 hover:underline">{{ $chatter->source_url }} ↗</a>
                    </div>

                    <div class="flex flex-wrap lg:w-64 gap-2">
                        @if($chatter->moderation_status !== 'published')
                            @can('chatter.publish')
<form method="POST" action="{{ route('admin.politician-chatter.moderate', $chatter) }}">@csrf<input type="hidden" name="action" value="publish"><button class="rounded-lg bg-emerald-500 px-3 py-2 text-xs font-bold text-slate-950 hover:bg-emerald-400">Publish</button></form>
@endcan
                        @endif
                        @if($chatter->moderation_status !== 'rejected')
                            @can('chatter.moderate')
<form method="POST" action="{{ route('admin.politician-chatter.moderate', $chatter) }}">@csrf<input type="hidden" name="action" value="reject"><button class="rounded-lg border border-red-500/40 px-3 py-2 text-xs font-semibold text-red-200 hover:bg-red-500/10">Reject</button></form>
@endcan
                        @endif
                        @if($chatter->moderation_status !== 'archived')
                            @can('chatter.moderate')
<form method="POST" action="{{ route('admin.politician-chatter.moderate', $chatter) }}">@csrf<input type="hidden" name="action" value="archive"><button class="rounded-lg border border-slate-600 px-3 py-2 text-xs font-semibold text-slate-300 hover:bg-slate-700">Archive</button></form>
@endcan
                        @endif
                        @if($chatter->moderation_status !== 'pending')
                            @can('chatter.moderate')
<form method="POST" action="{{ route('admin.politician-chatter.moderate', $chatter) }}">@csrf<input type="hidden" name="action" value="return_to_review"><button class="rounded-lg border border-amber-500/40 px-3 py-2 text-xs font-semibold text-amber-200">Review again</button></form>
@endcan
                        @endif
                    </div>
                </div>

                <details class="border-t border-slate-700/60">
                    <summary class="cursor-pointer px-5 py-3 text-sm font-semibold text-slate-300 hover:text-white">Edit evidence and review history</summary>
                    <div class="border-t border-slate-700/60 p-5 grid grid-cols-1 xl:grid-cols-[2fr_1fr] gap-6">
                        @can('chatter.edit')
<form method="POST" action="{{ route('admin.politician-chatter.update', $chatter) }}" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            @csrf @method('PUT')
                            @include('standalone.admin.partials.chatter-fields', ['chatter' => $chatter])
                            <div class="md:col-span-2 flex justify-end"><button class="rounded-lg bg-slate-600 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-500">Save changes</button></div>
                        </form>
@endcan
                        <aside>
                            @if($chatter->submitted_by_user_id || $chatter->contributor_notes)
                                <div class="mb-5 rounded-lg border border-amber-500/30 p-3 text-sm">
                                    <h3 class="font-semibold text-amber-200">Contributor input — private</h3>
                                    <p class="mt-2 text-slate-400">Submitted by {{ $chatter->submittedBy?->name ?? 'Deleted account' }}. Add neutral public context in the edit form before publishing.</p>
                                    <p class="mt-2 whitespace-pre-wrap text-slate-300">{{ $chatter->contributor_notes }}</p>
                                    @if($chatter->source_excerpt)<blockquote class="mt-3 border-l border-slate-600 pl-3 whitespace-pre-wrap text-slate-400">{{ $chatter->source_excerpt }}</blockquote>@endif
                                </div>
                            @endif
                            <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-400">Audit history</h3>
                            <ol class="mt-3 space-y-3">
                                @foreach($chatter->moderationLogs as $log)
                                    <li class="border-l border-slate-600 pl-3 text-xs text-slate-400">
                                        <p><span class="font-semibold text-slate-200">{{ ucfirst(str_replace('_', ' ', $log->action)) }}</span> by {{ $log->admin?->name ?? 'System' }}</p>
                                        <p>{{ $log->created_at->format('M j, Y g:i A') }}</p>
                                        @if($log->note)<p class="mt-1 text-slate-300">{{ $log->note }}</p>@endif
                                    </li>
                                @endforeach
                            </ol>
                        </aside>
                    </div>
                </details>
            </article>
        @empty
            <div class="rounded-2xl border border-dashed border-slate-700 p-12 text-center text-slate-400">No chatter items match this view.</div>
        @endforelse
    </div>

    {{ $items->links() }}
</div>
@endsection
