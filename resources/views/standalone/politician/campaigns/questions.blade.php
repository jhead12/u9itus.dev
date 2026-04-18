@extends('standalone.layouts.dashboard')

@section('title', 'Campaign Questions')
@section('page-title', 'Campaign Questions')

@section('content')
<div class="space-y-6">
    <div class="flex items-center gap-3">
        <a href="{{ route('politician.analytics.campaign', $campaign) }}" class="text-sm text-slate-400 hover:text-white transition">← Campaign Analytics</a>
        <div class="flex-1"></div>
        <a href="{{ route('politician.campaigns.show', $campaign) }}" class="text-sm text-emerald-400 hover:text-emerald-300">View Campaign →</a>
    </div>

    <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-5">
        <p class="text-xs uppercase tracking-widest text-slate-500">Q&amp;A Inbox</p>
        <h1 class="mt-2 text-lg font-semibold text-white">{{ $campaign->title }}</h1>
        <p class="mt-1 text-sm text-slate-400">Manage voter questions, reply publicly, and monitor moderation status for this campaign.</p>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-5">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">Total Questions</p>
            <p class="text-2xl font-bold text-white">{{ number_format($questionCounts['total'] ?? 0) }}</p>
        </div>
        <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-5">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">Needs Reply</p>
            <p class="text-2xl font-bold text-cyan-300">{{ number_format($questionCounts['open'] ?? 0) }}</p>
        </div>
        <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-5">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">Pending Public Review</p>
            <p class="text-2xl font-bold text-amber-300">{{ number_format($questionCounts['pending_public'] ?? 0) }}</p>
        </div>
        <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-5">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">Replied</p>
            <p class="text-2xl font-bold text-emerald-300">{{ number_format($questionCounts['replied'] ?? 0) }}</p>
        </div>
    </div>

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
                            <div class="flex items-center gap-2">
                                <span class="text-[11px] px-2 py-0.5 rounded-full {{ $question->status === 'open' ? 'bg-cyan-500/15 text-cyan-300' : 'bg-slate-600/40 text-slate-300' }}">
                                    {{ ucfirst(str_replace('_', ' ', $question->status)) }}
                                </span>
                                <span class="text-[11px] px-2 py-0.5 rounded-full {{ ($question->public_visibility ?? 'pending') === 'pending' ? 'bg-amber-500/15 text-amber-300' : 'bg-emerald-500/15 text-emerald-300' }}">
                                    Public: {{ ucfirst($question->public_visibility ?? 'pending') }}
                                </span>
                            </div>
                        </div>

                        <p class="text-slate-200 text-sm mt-1.5">{{ $question->body }}</p>
                        <p class="text-xs text-slate-500 mt-1">From: {{ $question->voter->full_name ?? 'Voter' }} {{ ($question->voter->email ?? null) ? '(' . $question->voter->email . ')' : '' }}</p>

                        @if(!empty($question->campaign_reply))
                            <div class="mt-3 rounded-lg border border-emerald-500/25 bg-emerald-500/10 px-3 py-2">
                                <p class="text-[11px] uppercase tracking-wide text-emerald-300 mb-1">Your Published Reply</p>
                                <p class="text-sm text-emerald-100 whitespace-pre-line">{{ $question->campaign_reply }}</p>
                            </div>
                        @endif

                        <form method="POST" action="{{ route('politician.campaigns.questions.reply', [$campaign, $question]) }}" class="mt-3 space-y-2">
                            @csrf
                            <label for="reply-{{ $question->id }}" class="text-xs text-slate-400">Official campaign reply</label>
                            <textarea id="reply-{{ $question->id }}" name="campaign_reply" rows="3" maxlength="2000"
                                class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50"
                                placeholder="Write your official response...">{{ old('campaign_reply') }}</textarea>
                            <div class="flex items-center justify-between gap-2">
                                <p class="text-[11px] text-slate-500">Public alias: {{ $question->public_alias ?: 'Voter alias pending' }}</p>
                                <button type="submit" class="px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-medium transition">
                                    Save Reply
                                </button>
                            </div>
                        </form>
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
</div>
@endsection
