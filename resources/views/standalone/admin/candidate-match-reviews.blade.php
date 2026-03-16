@extends('standalone.layouts.dashboard')

@section('title', 'Candidate Match Reviews')
@section('page-title', 'Candidate Match Reviews')

@section('content')
<div class="space-y-6">

    <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-4 sm:p-5">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
            <h3 class="text-sm font-semibold text-white">Import Candidate Data</h3>
            <p class="text-xs text-slate-500">Runs artisan command: elections:import-candidates</p>
        </div>

        <form method="POST" action="{{ route('admin.candidate-matches.import') }}" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-4 gap-3">
            @csrf
            <div>
                  <label for="import-source" class="block text-xs text-slate-400 mb-1">Source</label>
                <input type="text"
                      id="import-source"
                       name="source"
                       value="{{ old('source', 'local_feed') }}"
                       class="w-full bg-slate-900 border border-slate-700 text-sm text-slate-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-1 focus:ring-emerald-500" />
            </div>
            <div class="md:col-span-2">
                  <label for="import-file" class="block text-xs text-slate-400 mb-1">File Path</label>
                <input type="text"
                      id="import-file"
                       name="file"
                       value="{{ old('file', 'imports/local-elections.json') }}"
                       class="w-full bg-slate-900 border border-slate-700 text-sm text-slate-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-1 focus:ring-emerald-500" />
            </div>
            <div>
                <label for="import-upload" class="block text-xs text-slate-400 mb-1">Upload JSON (optional)</label>
                <input type="file"
                       id="import-upload"
                       name="file_upload"
                       accept=".json,application/json,text/plain"
                       class="w-full bg-slate-900 border border-slate-700 text-xs text-slate-200 rounded-lg px-3 py-2 file:mr-3 file:rounded file:border-0 file:bg-slate-700 file:px-2 file:py-1 file:text-xs file:text-slate-200" />
                <p class="text-[11px] text-slate-500 mt-1">Use either File Path or Upload JSON.</p>
            </div>
            <div class="flex items-end gap-3 md:col-span-4">
                <label class="inline-flex items-center gap-2 text-xs text-slate-300">
                    <input type="checkbox" name="dry_run" value="1" {{ old('dry_run') ? 'checked' : '' }} class="rounded border-slate-600 bg-slate-900 text-emerald-500 focus:ring-emerald-500" />
                    Dry Run
                </label>
                <button type="submit" class="text-xs bg-emerald-600 hover:bg-emerald-500 text-white px-3 py-2 rounded-lg transition">
                    Run Import
                </button>
            </div>
        </form>

        @if(session('import_output'))
        <div class="mt-4 rounded-lg border border-slate-700 bg-slate-900/70 p-3">
            <p class="text-xs text-slate-400 mb-2">Import Output</p>
            <pre class="text-xs text-slate-200 whitespace-pre-wrap">{{ session('import_output') }}</pre>
        </div>
        @endif
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="stat-card">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">Pending</p>
            <p class="text-3xl font-bold text-amber-400">{{ number_format($stats['pending']) }}</p>
        </div>
        <div class="stat-card">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">Approved</p>
            <p class="text-3xl font-bold text-emerald-400">{{ number_format($stats['approved']) }}</p>
        </div>
        <div class="stat-card">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">Rejected</p>
            <p class="text-3xl font-bold text-red-400">{{ number_format($stats['rejected']) }}</p>
        </div>
    </div>

    <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-700/50 flex flex-wrap items-center justify-between gap-3">
            <h3 class="text-sm font-semibold text-white">Pending Candidate Matches</h3>

            <form method="GET" action="{{ route('admin.candidate-matches.index') }}" class="flex items-center gap-2">
                <input type="text"
                       name="q"
                       value="{{ request('q') }}"
                       placeholder="Search by name or office..."
                       class="bg-slate-900 border border-slate-700 text-sm text-slate-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-1 focus:ring-emerald-500" />
                <button type="submit" class="text-xs bg-slate-700 hover:bg-slate-600 text-slate-200 px-3 py-2 rounded-lg transition">Search</button>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-700/50">
                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wide">Politician</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wide">Candidate Record</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wide">Score</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wide">Breakdown</th>
                        <th class="px-5 py-3 text-right text-xs font-semibold text-slate-400 uppercase tracking-wide">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700/30">
                    @forelse($reviews as $review)
                    <tr class="hover:bg-slate-700/20 transition">
                        <td class="px-5 py-4 align-top">
                            <p class="font-medium text-white">{{ $review->politician?->full_name ?? '—' }}</p>
                            <p class="text-xs text-slate-500 mt-1">{{ $review->politician?->political_office ?? 'Unknown office' }}</p>
                            <p class="text-xs text-slate-500">{{ implode(', ', array_filter([$review->politician?->city, $review->politician?->state])) }}</p>
                        </td>
                        <td class="px-5 py-4 align-top">
                            <p class="font-medium text-slate-200">{{ $review->candidateRecord?->full_name ?? '—' }}</p>
                            <p class="text-xs text-slate-500 mt-1">{{ $review->candidateRecord?->political_office ?? 'Unknown office' }}</p>
                            <p class="text-xs text-slate-500">{{ implode(', ', array_filter([$review->candidateRecord?->city, $review->candidateRecord?->state])) }}</p>
                            <p class="text-xs text-slate-600 mt-1">{{ $review->candidateRecord?->source }} · {{ $review->candidateRecord?->external_candidate_id }}</p>
                        </td>
                        <td class="px-5 py-4 align-top">
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold {{ $review->match_score >= 0.85 ? 'bg-emerald-500/15 text-emerald-400' : 'bg-amber-500/15 text-amber-400' }}">
                                {{ number_format((float) $review->match_score, 4) }}
                            </span>
                        </td>
                        <td class="px-5 py-4 align-top">
                            @php $b = $review->match_breakdown ?? []; @endphp
                            <div class="text-xs text-slate-400 space-y-1">
                                <p>Name: {{ number_format((float) ($b['name'] ?? 0), 2) }}</p>
                                <p>Office: {{ number_format((float) ($b['office'] ?? 0), 2) }}</p>
                                <p>State: {{ number_format((float) ($b['state'] ?? 0), 2) }}</p>
                                <p>Geo: {{ number_format((float) ($b['geo'] ?? 0), 2) }}</p>
                                <p>Party: {{ number_format((float) ($b['party'] ?? 0), 2) }}</p>
                            </div>
                        </td>
                        <td class="px-5 py-4 align-top">
                            <div class="flex flex-col items-end gap-2">
                                <form method="POST" action="{{ route('admin.candidate-matches.approve', $review) }}">
                                    @csrf
                                    <button type="submit" class="text-xs bg-emerald-600 hover:bg-emerald-500 text-white px-3 py-1.5 rounded-lg transition">
                                        Approve Link
                                    </button>
                                </form>

                                <form method="POST" action="{{ route('admin.candidate-matches.reject', $review) }}" class="flex items-center gap-2">
                                    @csrf
                                    <input type="text" name="reason" placeholder="Optional reason"
                                           class="w-40 bg-slate-900 border border-slate-700 text-xs text-slate-200 rounded-lg px-2 py-1.5 focus:outline-none focus:ring-1 focus:ring-red-500" />
                                    <button type="submit" class="text-xs bg-red-600 hover:bg-red-500 text-white px-3 py-1.5 rounded-lg transition">
                                        Reject
                                    </button>
                                </form>

                                @if($review->politician)
                                <form method="POST" action="{{ route('admin.candidate-matches.retry', $review->politician) }}">
                                    @csrf
                                    <button type="submit" class="text-xs bg-slate-700 hover:bg-slate-600 text-slate-200 px-3 py-1.5 rounded-lg transition">
                                        Retry Match
                                    </button>
                                </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-5 py-8 text-center text-sm text-slate-500">
                            No pending candidate match reviews.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="px-5 py-4 border-t border-slate-700/50">
            {{ $reviews->links() }}
        </div>
    </div>

</div>
@endsection
