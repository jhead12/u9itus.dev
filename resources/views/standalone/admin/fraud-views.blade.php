@extends('standalone.layouts.dashboard')

@section('title', 'Flagged Views')
@section('page-title', 'Flagged View Sessions')

@section('content')
<div class="space-y-6">

    <div>
        <a href="{{ route('admin.fraud.index') }}" class="text-sm text-slate-400 hover:text-white transition">← Back to fraud dashboard</a>
    </div>

    <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-700/50">
            <h3 class="text-sm font-semibold text-white">All Flagged Sessions</h3>
            <p class="text-xs text-slate-500 mt-0.5">{{ $sessions->total() }} sessions with fraud score &gt; 50</p>
        </div>

        @if($sessions->isEmpty())
        <div class="px-5 py-10 text-center">
            <p class="text-sm text-slate-500">No flagged sessions found.</p>
        </div>
        @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-700/50">
                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wide">Session ID</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wide">Voter</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wide">Campaign</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wide">Fraud Score</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wide">Status</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wide">Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700/30">
                    @foreach($sessions as $session)
                    <tr class="hover:bg-slate-700/20 transition">
                        <td class="px-5 py-3 text-xs font-mono text-slate-400">{{ substr($session->id, 0, 8) }}…</td>
                        <td class="px-5 py-3 text-xs text-slate-300">{{ $session->voter?->user?->email ?? $session->voter_id }}</td>
                        <td class="px-5 py-3 text-xs text-slate-300">{{ $session->campaign?->title ?? '—' }}</td>
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
        <div class="px-5 py-4 border-t border-slate-700/50">
            {{ $sessions->links() }}
        </div>
        @endif
    </div>

</div>
@endsection
