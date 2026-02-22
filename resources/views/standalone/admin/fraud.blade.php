@extends('standalone.layouts.dashboard')

@section('title', 'Fraud Detection')
@section('page-title', 'Fraud Detection')

@section('content')
<div class="space-y-6">

    {{-- Stats --}}
    <div class="grid grid-cols-3 gap-4">
        <div class="stat-card">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">Total Sessions</p>
            <p class="text-3xl font-bold text-white">{{ number_format($stats['total_sessions']) }}</p>
        </div>
        <div class="stat-card">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">Flagged (score &gt; 50)</p>
            <p class="text-3xl font-bold text-amber-400">{{ number_format($stats['flagged_sessions']) }}</p>
        </div>
        <div class="stat-card">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">High Risk (score &gt; 80)</p>
            <p class="text-3xl font-bold text-red-400">{{ number_format($stats['high_risk_sessions']) }}</p>
        </div>
    </div>

    {{-- Top flagged sessions --}}
    <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-700/50 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-white">Top Flagged Sessions</h3>
            <a href="{{ route('admin.fraud.views') }}" class="text-xs text-emerald-400 hover:text-emerald-300 transition">View all →</a>
        </div>

        @if($flaggedSessions->isEmpty())
        <div class="px-5 py-10 text-center">
            <p class="text-sm text-slate-500">No flagged sessions detected. 🎉</p>
        </div>
        @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-700/50">
                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wide">Session</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wide">Voter</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wide">Fraud Score</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wide">Status</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wide">Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700/30">
                    @foreach($flaggedSessions as $session)
                    <tr class="hover:bg-slate-700/20 transition">
                        <td class="px-5 py-3 text-xs font-mono text-slate-400">{{ substr($session->id, 0, 8) }}…</td>
                        <td class="px-5 py-3 text-xs text-slate-300">{{ $session->voter?->user?->email ?? $session->voter_id }}</td>
                        <td class="px-5 py-3">
                            <span class="text-xs font-bold {{ $session->fraud_score > 80 ? 'text-red-400' : 'text-amber-400' }}">
                                {{ number_format($session->fraud_score, 1) }}
                            </span>
                        </td>
                        <td class="px-5 py-3">
                            @php $st = $session->status instanceof \BackedEnum ? $session->status->value : $session->status; @endphp
                            <span class="text-xs px-2 py-0.5 rounded-full bg-slate-700 text-slate-300">{{ $st }}</span>
                        </td>
                        <td class="px-5 py-3 text-xs text-slate-400">{{ $session->created_at->format('M j, Y') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>

</div>
@endsection
