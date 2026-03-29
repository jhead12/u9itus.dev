@extends('standalone.layouts.dashboard')

@section('title', 'Campaign Analytics')
@section('page-title', 'Campaign Analytics')

@section('content')
<div class="space-y-6">

    <div class="flex items-center gap-3">
        <a href="{{ route('politician.analytics') }}" class="text-sm text-slate-400 hover:text-white transition">← Analytics</a>
        <div class="flex-1"></div>
        <a href="{{ route('politician.campaigns.show', $campaign) }}"
           class="text-sm text-emerald-400 hover:text-emerald-300">View Campaign →</a>
    </div>

    {{-- Campaign header --}}
    <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-5">
        <h1 class="text-base font-semibold text-white">{{ $campaign->title }}</h1>
        <p class="text-xs text-slate-500 mt-0.5">{{ ucfirst(str_replace('_', ' ', $campaign->status?->value ?? $campaign->status ?? 'draft')) }} · {{ ucfirst($campaign->campaign_type?->value ?? $campaign->campaign_type) }}</p>
    </div>

    {{-- Stats --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-5">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">Views Completed</p>
            <p class="text-2xl font-bold text-white">{{ number_format($completedViews) }}</p>
            <p class="text-xs text-slate-500 mt-0.5">of {{ number_format($campaign->total_views_requested) }}</p>
        </div>
        <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-5">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">Budget Spent</p>
            <p class="text-2xl font-bold text-white">${{ number_format($budgetUsed, 2) }}</p>
        </div>
        <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-5">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">Remaining</p>
            <p class="text-2xl font-bold {{ $budgetLeft > 0 ? 'text-emerald-400' : 'text-red-400' }}">${{ number_format($budgetLeft, 2) }}</p>
        </div>
        <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-5">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">Total Sessions</p>
            <p class="text-2xl font-bold text-white">{{ number_format($sessions->total()) }}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-5">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">Open Voter Questions</p>
            <p class="text-2xl font-bold text-cyan-300">{{ number_format($openVoterQuestions ?? 0) }}</p>
        </div>
        <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-5">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-2">Question Status</p>
            @if(($voterQuestionCounts ?? collect())->isEmpty())
                <p class="text-slate-500 text-sm">No questions submitted yet.</p>
            @else
                <div class="flex flex-wrap gap-2">
                    @foreach($voterQuestionCounts as $row)
                        <span class="text-xs px-2.5 py-1 rounded-full bg-slate-700/60 text-slate-200">
                            {{ ucfirst(str_replace('_', ' ', $row->status)) }}: {{ number_format($row->total) }}
                        </span>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- Status breakdown --}}
    @if($byStatus->isNotEmpty())
    <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-700/50">
            <h3 class="text-sm font-semibold text-slate-200">Sessions by Status</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-700/50">
                        <th class="text-left px-5 py-2.5 text-xs font-medium text-slate-500">Status</th>
                        <th class="text-right px-5 py-2.5 text-xs font-medium text-slate-500">Count</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700/30">
                    @foreach($byStatus as $row)
                    <tr>
                        <td class="px-5 py-3 font-medium text-slate-200">{{ ucfirst($row->status) }}</td>
                        <td class="px-5 py-3 text-right text-slate-300 font-mono">{{ number_format($row->total) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-700/50">
            <h3 class="text-sm font-semibold text-slate-200">Voter Questions</h3>
        </div>
        @if(($voterQuestions ?? collect())->isEmpty())
            <p class="text-slate-500 text-sm text-center py-10">No voter questions yet.</p>
        @else
            <div class="divide-y divide-slate-700/30">
                @foreach($voterQuestions as $question)
                    <div class="px-5 py-4">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="text-xs text-slate-500">{{ $question->created_at?->format('M j, Y H:i') }}</p>
                            <span class="text-[11px] px-2 py-0.5 rounded-full {{ $question->status === 'open' ? 'bg-cyan-500/15 text-cyan-300' : 'bg-slate-600/40 text-slate-300' }}">
                                {{ ucfirst(str_replace('_', ' ', $question->status)) }}
                            </span>
                        </div>
                        <p class="text-slate-200 text-sm mt-1.5">{{ $question->body }}</p>
                        <p class="text-xs text-slate-500 mt-1">From: {{ $question->voter->full_name ?? 'Voter' }} {{ ($question->voter->email ?? null) ? '(' . $question->voter->email . ')' : '' }}</p>
                    </div>
                @endforeach
            </div>
            @if($voterQuestions->hasPages())
            <div class="px-5 py-4 border-t border-slate-700/50">
                {{ $voterQuestions->links() }}
            </div>
            @endif
        @endif
    </div>

    {{-- Session log --}}
    <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-700/50">
            <h3 class="text-sm font-semibold text-slate-200">View Sessions</h3>
        </div>
        @if($sessions->isEmpty())
            <p class="text-slate-500 text-sm text-center py-10">No view sessions yet.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-700/50">
                            <th class="text-left px-5 py-2.5 text-xs font-medium text-slate-500">Date</th>
                            <th class="text-left px-5 py-2.5 text-xs font-medium text-slate-500">Status</th>
                            <th class="text-right px-5 py-2.5 text-xs font-medium text-slate-500">Watch Time</th>
                            <th class="text-right px-5 py-2.5 text-xs font-medium text-slate-500">Completion</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700/30">
                        @foreach($sessions as $session)
                        <tr>
                            <td class="px-5 py-3 text-slate-500 text-xs whitespace-nowrap">{{ $session->created_at?->format('M j, Y H:i') }}</td>
                            <td class="px-5 py-3 text-slate-300">{{ ucfirst($session->status) }}</td>
                            <td class="px-5 py-3 text-right text-slate-300 font-mono">{{ $session->watch_time_seconds ?? 0 }}s</td>
                            <td class="px-5 py-3 text-right text-slate-300 font-mono">{{ $session->completion_percentage ?? 0 }}%</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($sessions->hasPages())
            <div class="px-5 py-4 border-t border-slate-700/50">
                {{ $sessions->links() }}
            </div>
            @endif
        @endif
    </div>

</div>
@endsection
