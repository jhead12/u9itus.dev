@extends('standalone.layouts.dashboard')

@section('title', 'Pending Payouts')
@section('page-title', 'Pending Payout Sessions')

@section('content')
<div class="space-y-6">

    <div>
        <a href="{{ route('admin.payouts.index') }}" class="text-sm text-slate-400 hover:text-white transition">← Back to payouts</a>
    </div>

    <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-700/50">
            <h3 class="text-sm font-semibold text-white">Pending Payout Sessions</h3>
            <p class="text-xs text-slate-500 mt-0.5">{{ $sessions->total() }} sessions awaiting payout</p>
        </div>

        @if($sessions->isEmpty())
        <div class="px-5 py-10 text-center">
            <p class="text-sm text-slate-500">No pending payouts. All sessions have been paid! 💸</p>
        </div>
        @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-700/50">
                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wide">Voter</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wide">Campaign</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wide">Amount</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wide">Status</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wide">Completed</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700/30">
                    @foreach($sessions as $session)
                    <tr class="hover:bg-slate-700/20 transition">
                        <td class="px-5 py-3 text-xs text-slate-300">{{ $session->voter?->user?->email ?? $session->voter_id }}</td>
                        <td class="px-5 py-3 text-xs text-slate-300">{{ $session->campaign?->title ?? '—' }}</td>
                        <td class="px-5 py-3 text-xs font-semibold text-emerald-400">${{ number_format($session->voter_payout_amount ?? 0, 2) }}</td>
                        <td class="px-5 py-3 text-xs">
                            @if($session->getRawOriginal('payment_status') === 'approved')
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-emerald-500/15 text-emerald-400 font-medium">
                                    Requested
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-amber-500/15 text-amber-400 font-medium">
                                    Pending
                                </span>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-xs text-slate-400">{{ $session->completed_at ? \Carbon\Carbon::parse($session->completed_at)->format('M j, Y') : $session->updated_at->format('M j, Y') }}</td>
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
