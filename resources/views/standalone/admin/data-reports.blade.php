@extends('standalone.layouts.dashboard')

@section('title', 'Data Reports')
@section('page-title', 'Data Reports')

@section('content')
<div class="space-y-6">

    @if(session('success'))
    <div class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm rounded-lg px-4 py-3">{{ session('success') }}</div>
    @endif

    <p class="text-xs text-slate-500">Problems visitors reported from candidate cards on the map and profile pages. Resolving or dismissing only records the outcome — fix the row at source (<code class="text-slate-400">map:audit-candidates</code> lists the usual suspects).</p>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        @foreach(['pending' => 'text-amber-400', 'resolved' => 'text-emerald-400', 'dismissed' => 'text-slate-400'] as $key => $color)
        <a href="{{ route('admin.data-reports.index', ['status' => $key] + request()->except('status', 'page')) }}"
           class="stat-card {{ $statusFilter === $key ? 'ring-1 ring-slate-500/60' : '' }}">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">{{ ucfirst($key) }}</p>
            <p class="text-3xl font-bold {{ $color }}">{{ number_format($stats[$key]) }}</p>
        </a>
        @endforeach
    </div>

    <form method="GET" action="{{ route('admin.data-reports.index') }}"
          class="flex flex-col lg:flex-row gap-3 bg-slate-800/50 border border-slate-700/50 rounded-xl px-5 py-4">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Search by name or message..."
               class="flex-1 min-w-0 bg-slate-900/60 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-emerald-500/50">
        <input type="text" name="state" value="{{ request('state') }}" maxlength="2" placeholder="State"
               class="w-24 bg-slate-900/60 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white placeholder-slate-500 uppercase focus:outline-none focus:border-emerald-500/50">
        <input type="hidden" name="status" value="{{ $statusFilter }}">
        <button type="submit" class="px-4 py-2 rounded-lg bg-emerald-500 hover:bg-emerald-400 text-white text-sm font-semibold transition">Apply</button>
    </form>

    <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-700/50 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-white capitalize">{{ $statusFilter ?: 'All' }} reports</h3>
            <span class="text-xs text-slate-500">{{ $reports->total() }} total</span>
        </div>

        <div class="divide-y divide-slate-700/30">
            @forelse($reports as $report)
            <div class="px-5 py-4 flex flex-col lg:flex-row lg:items-start gap-4 {{ $report->status !== 'pending' ? 'opacity-75' : '' }}">
                <div class="flex-1 min-w-0 space-y-1">
                    <p class="text-sm font-semibold text-white">
                        {{ $report->subject_name ?: 'Unnamed subject' }}
                        <span class="text-xs font-normal text-slate-500">@if($report->subject_office)· {{ $report->subject_office }} @endif @if($report->state)· {{ $report->state }} @endif</span>
                    </p>
                    <p class="text-xs text-amber-300">{{ $problems[$report->problem] ?? $report->problem }}</p>
                    @if($report->message)
                    <p class="text-sm text-slate-300 whitespace-pre-line break-words">{{ $report->message }}</p>
                    @endif
                    <p class="text-xs text-slate-500">
                        {{ $report->subject_type }}@if($report->subject_id) #{{ $report->subject_id }}@endif
                        @if($report->source_label) · card said "{{ $report->source_label }}"@endif
                        · {{ $report->created_at->diffForHumans() }}
                        @if($report->page_url && \Illuminate\Support\Str::startsWith($report->page_url, ['http://', 'https://']))
                            · <a href="{{ $report->page_url }}" target="_blank" rel="noopener noreferrer" class="text-indigo-400 hover:underline">view page</a>
                        @endif
                    </p>
                    @if($report->status !== 'pending' && $report->resolution_note)
                    <p class="text-xs text-slate-400">Note: {{ $report->resolution_note }}</p>
                    @endif
                </div>

                @if($report->status === 'pending')
                <form method="POST" action="{{ route('admin.data-reports.update', $report) }}" class="flex flex-col sm:flex-row gap-2 lg:w-96">
                    @csrf
                    @method('PATCH')
                    <input type="text" name="resolution_note" maxlength="500" placeholder="Note (optional)"
                           class="flex-1 min-w-0 bg-slate-900/60 border border-slate-700 rounded-lg px-3 py-2 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-emerald-500/50">
                    <button name="status" value="resolved" class="px-3 py-2 rounded-lg bg-emerald-500 hover:bg-emerald-400 text-white text-xs font-semibold transition">Resolved</button>
                    <button name="status" value="dismissed" class="px-3 py-2 rounded-lg bg-slate-700 hover:bg-slate-600 text-slate-200 text-xs font-semibold transition">Dismiss</button>
                </form>
                @else
                <span class="text-xs font-semibold uppercase {{ $report->status === 'resolved' ? 'text-emerald-400' : 'text-slate-400' }}">{{ $report->status }}</span>
                @endif
            </div>
            @empty
            <p class="px-5 py-10 text-center text-sm text-slate-500">No reports here.</p>
            @endforelse
        </div>

        <div class="px-5 py-3">{{ $reports->links() }}</div>
    </div>
</div>
@endsection
