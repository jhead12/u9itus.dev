@extends('standalone.layouts.dashboard')

@section('title', 'Engagement Report')
@section('page-title', 'Engagement Report')

@section('content')
<div class="space-y-6">

    <div>
        <a href="{{ route('admin.analytics') }}" class="text-sm text-slate-400 hover:text-white transition">← Back to analytics</a>
    </div>

    <div class="flex items-center justify-between gap-3 flex-wrap">
        <span class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-semibold uppercase tracking-wide {{ ($activePaymentMode ?? null) === 'live' ? 'border-emerald-500/40 bg-emerald-500/10 text-emerald-300' : 'border-amber-500/40 bg-amber-500/10 text-amber-300' }}">
            {{ ($activePaymentMode ?? 'test') === 'live' ? 'Live Mode Engagement' : 'Test Mode Engagement' }}
        </span>
        <div class="flex items-center gap-2">
            <a href="{{ route('admin.analytics.ledger.campaign') }}" class="inline-flex items-center rounded-lg border border-slate-600 px-3 py-2 text-xs font-semibold text-slate-200 hover:border-emerald-400/50 hover:text-emerald-200 transition">
                Campaign Ledger
            </a>
            <a href="{{ route('admin.analytics.ledger.voter') }}" class="inline-flex items-center rounded-lg border border-slate-600 px-3 py-2 text-xs font-semibold text-slate-200 hover:border-emerald-400/50 hover:text-emerald-200 transition">
                Voter Ledger
            </a>
            <a href="{{ route('admin.analytics.export.campaign-accounting') }}" class="inline-flex items-center rounded-lg border border-slate-600 px-3 py-2 text-xs font-semibold text-slate-200 hover:border-sky-400/50 hover:text-sky-200 transition">
                ↓ Campaign CSV
            </a>
            <a href="{{ route('admin.analytics.export.voter-accounting') }}" class="inline-flex items-center rounded-lg border border-slate-600 px-3 py-2 text-xs font-semibold text-slate-200 hover:border-sky-400/50 hover:text-sky-200 transition">
                ↓ Voter CSV
            </a>
        </div>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="stat-card">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">Total Sessions</p>
            <p class="text-3xl font-bold text-white">{{ number_format($engagement['total_sessions']) }}</p>
        </div>
        <div class="stat-card">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">Completed</p>
            <p class="text-3xl font-bold text-emerald-400">{{ number_format($engagement['completed_sessions']) }}</p>
        </div>
        <div class="stat-card">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">Flagged</p>
            <p class="text-3xl font-bold text-amber-400">{{ number_format($engagement['flagged_sessions']) }}</p>
        </div>
        <div class="stat-card">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">Avg Watch %</p>
            <p class="text-3xl font-bold text-white">{{ number_format($engagement['avg_watch_percent'], 1) }}%</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div class="stat-card">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">Survey Responses</p>
            <p class="text-3xl font-bold text-white">{{ number_format($engagement['survey_responses']) }}</p>
            <p class="text-xs text-slate-500 mt-1">{{ $days }}-day window: {{ number_format($engagement['survey_last_window']) }}</p>
        </div>
        <div class="stat-card">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">Voter Questions</p>
            <p class="text-3xl font-bold text-white">{{ number_format($engagement['voter_questions']) }}</p>
            <p class="text-xs text-slate-500 mt-1">{{ $days }}-day window: {{ number_format($engagement['questions_last_window']) }}</p>
        </div>
    </div>

    <form method="GET" action="{{ route('admin.reports.engagement') }}" class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-4">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 items-end">
            <div>
                <label for="days" class="block text-xs font-medium text-slate-400 uppercase tracking-wide mb-2">Time Window</label>
                <select id="days" name="days" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white">
                    <option value="7" {{ (int) $days === 7 ? 'selected' : '' }}>Last 7 days</option>
                    <option value="30" {{ (int) $days === 30 ? 'selected' : '' }}>Last 30 days</option>
                    <option value="90" {{ (int) $days === 90 ? 'selected' : '' }}>Last 90 days</option>
                </select>
            </div>
            <div>
                <label for="question_status" class="block text-xs font-medium text-slate-400 uppercase tracking-wide mb-2">Question Status</label>
                <select id="question_status" name="question_status" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white">
                    <option value="all" {{ $questionStatus === 'all' ? 'selected' : '' }}>All questions</option>
                    <option value="open" {{ $questionStatus === 'open' ? 'selected' : '' }}>Open only</option>
                    <option value="in_review" {{ $questionStatus === 'in_review' ? 'selected' : '' }}>In review</option>
                    <option value="resolved" {{ $questionStatus === 'resolved' ? 'selected' : '' }}>Resolved</option>
                    <option value="dismissed" {{ $questionStatus === 'dismissed' ? 'selected' : '' }}>Dismissed</option>
                </select>
            </div>
            <div>
                <button type="submit" class="w-full md:w-auto inline-flex items-center justify-center px-4 py-2 rounded-lg bg-emerald-500/90 hover:bg-emerald-500 text-slate-900 font-semibold text-sm transition">
                    Apply Filters
                </button>
            </div>
        </div>
    </form>

    <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-6">
        <h3 class="text-sm font-semibold text-white mb-4">Session Breakdown</h3>
        <dl class="space-y-3 text-sm">
            <div class="flex justify-between items-center py-2 border-b border-slate-700/30">
                <dt class="text-slate-400">Total sessions created</dt>
                <dd class="font-semibold text-white">{{ number_format($engagement['total_sessions']) }}</dd>
            </div>
            <div class="flex justify-between items-center py-2 border-b border-slate-700/30">
                <dt class="text-slate-400">Completed (paid)</dt>
                <dd class="font-semibold text-emerald-400">{{ number_format($engagement['completed_sessions']) }}</dd>
            </div>
            <div class="flex justify-between items-center py-2 border-b border-slate-700/30">
                <dt class="text-slate-400">Fraud-flagged</dt>
                <dd class="font-semibold text-amber-400">{{ number_format($engagement['flagged_sessions']) }}</dd>
            </div>
            <div class="flex justify-between items-center py-2 border-b border-slate-700/30">
                <dt class="text-slate-400">Completion rate</dt>
                @php
                    $cr = $engagement['total_sessions'] > 0
                        ? round($engagement['completed_sessions'] / $engagement['total_sessions'] * 100, 1)
                        : 0;
                @endphp
                <dd class="font-semibold text-white">{{ $cr }}%</dd>
            </div>
            <div class="flex justify-between items-center py-2">
                <dt class="text-slate-400">Avg watch percentage</dt>
                <dd class="font-semibold text-white">{{ number_format($engagement['avg_watch_percent'], 1) }}%</dd>
            </div>
        </dl>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
        <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-6">
            <h3 class="text-sm font-semibold text-white mb-4">Question Queue Status</h3>
            <dl class="space-y-3 text-sm">
                <div class="flex justify-between items-center py-2 border-b border-slate-700/30">
                    <dt class="text-slate-400">All Questions</dt>
                    <dd class="font-semibold text-white">{{ number_format($questionStats['all']) }}</dd>
                </div>
                <div class="flex justify-between items-center py-2 border-b border-slate-700/30">
                    <dt class="text-slate-400">Open</dt>
                    <dd class="font-semibold text-amber-300">{{ number_format($questionStats['open']) }}</dd>
                </div>
                <div class="flex justify-between items-center py-2 border-b border-slate-700/30">
                    <dt class="text-slate-400">In Review</dt>
                    <dd class="font-semibold text-sky-300">{{ number_format($questionStats['in_review']) }}</dd>
                </div>
                <div class="flex justify-between items-center py-2 border-b border-slate-700/30">
                    <dt class="text-slate-400">Resolved</dt>
                    <dd class="font-semibold text-emerald-300">{{ number_format($questionStats['resolved']) }}</dd>
                </div>
                <div class="flex justify-between items-center py-2">
                    <dt class="text-slate-400">Dismissed</dt>
                    <dd class="font-semibold text-slate-300">{{ number_format($questionStats['dismissed']) }}</dd>
                </div>
            </dl>
        </div>

        <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-6">
            <h3 class="text-sm font-semibold text-white mb-4">Survey Option Distribution ({{ $days }} days)</h3>
            @if($surveyOptionBreakdown->isEmpty())
                <p class="text-sm text-slate-500">No survey responses in this time window.</p>
            @else
                <ul class="space-y-3">
                    @foreach($surveyOptionBreakdown as $option)
                        <li class="flex items-center justify-between text-sm">
                            <span class="text-slate-300 truncate pr-4">{{ $option->response_value }}</span>
                            <span class="text-white font-semibold">{{ number_format($option->total) }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

    <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-6">
        <h3 class="text-sm font-semibold text-white mb-4">Top Campaigns by Survey Responses ({{ $days }} days)</h3>
        @if($surveyCampaignBreakdown->isEmpty())
            <p class="text-sm text-slate-500">No campaign-level survey activity in this window.</p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="text-left text-slate-400 border-b border-slate-700/40">
                            <th class="py-2 pr-3 font-medium">Campaign</th>
                            <th class="py-2 font-medium">Responses</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700/20">
                        @foreach($surveyCampaignBreakdown as $row)
                            <tr>
                                <td class="py-2 pr-3 text-slate-200">{{ $row->title }}</td>
                                <td class="py-2 text-white font-semibold">{{ number_format($row->total) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-6">
        <h3 class="text-sm font-semibold text-white mb-4">Recent Voter Questions</h3>
        @if($recentQuestions->isEmpty())
            <p class="text-sm text-slate-500">No voter questions found for the selected filters.</p>
        @else
            <div class="space-y-3">
                @foreach($recentQuestions as $question)
                    <article class="rounded-lg border border-slate-700/50 bg-slate-900/40 p-4">
                        <div class="flex flex-wrap items-center gap-2 justify-between mb-2">
                            <p class="text-sm text-slate-200 font-medium">
                                {{ optional($question->campaign)->title ?? 'Campaign #' . $question->campaign_id }}
                            </p>
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold uppercase tracking-wide
                                {{ $question->status === 'open' ? 'bg-amber-500/20 text-amber-200 border border-amber-400/30' : '' }}
                                {{ $question->status === 'in_review' ? 'bg-sky-500/20 text-sky-200 border border-sky-400/30' : '' }}
                                {{ $question->status === 'resolved' ? 'bg-emerald-500/20 text-emerald-200 border border-emerald-400/30' : '' }}
                                {{ $question->status === 'dismissed' ? 'bg-slate-600/30 text-slate-200 border border-slate-400/30' : '' }}">
                                {{ str_replace('_', ' ', $question->status) }}
                            </span>
                        </div>
                        <p class="text-sm text-slate-300 leading-relaxed">{{ $question->body }}</p>
                        <div class="mt-3 text-xs text-slate-500 flex flex-wrap gap-3">
                            <span>Voter: {{ optional(optional($question->voter)->user)->name ?? optional($question->voter)->full_name ?? 'Unknown' }}</span>
                            <span>Submitted: {{ optional($question->created_at)->diffForHumans() }}</span>
                        </div>
                    </article>
                @endforeach
            </div>

            <div class="mt-4">
                {{ $recentQuestions->links() }}
            </div>
        @endif
    </div>

</div>
@endsection
