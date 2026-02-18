@extends('layouts.app')

@section('title', 'Earnings History')

@section('content')
<div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">

    <div class="flex items-center justify-between">
        <h1 class="text-3xl font-bold text-white">Earnings History</h1>
        <a href="{{ route('voter.earnings') }}" class="text-emerald-400 hover:text-emerald-300 text-sm">← Back to Earnings</a>
    </div>

    @if($sessions instanceof \Illuminate\Pagination\LengthAwarePaginator ? $sessions->total() === 0 : $sessions->isEmpty())
    <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-10 text-center">
        <p class="text-slate-400">No view sessions yet. Check your email for ad invitations!</p>
    </div>
    @else
    <div class="bg-slate-800/50 border border-slate-700 rounded-xl overflow-hidden">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-slate-700 text-slate-400 text-left">
                    <th class="px-4 py-3 font-medium">Campaign</th>
                    <th class="px-4 py-3 font-medium">Politician</th>
                    <th class="px-4 py-3 font-medium">Watched</th>
                    <th class="px-4 py-3 font-medium">Completion</th>
                    <th class="px-4 py-3 font-medium">Payment</th>
                    <th class="px-4 py-3 font-medium text-right">Earned</th>
                    <th class="px-4 py-3 font-medium">Date</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-700/50">
                @foreach($sessions as $session)
                <tr class="hover:bg-slate-700/20 transition">
                    <td class="px-4 py-3 text-white">{{ $session->campaign->title ?? '—' }}</td>
                    <td class="px-4 py-3 text-slate-300">{{ $session->campaign->politician->full_name ?? '—' }}</td>
                    <td class="px-4 py-3 text-slate-300">{{ $session->watch_time_seconds }}s</td>
                    <td class="px-4 py-3 text-slate-300">{{ number_format($session->completion_percentage ?? 0, 0) }}%</td>
                    <td class="px-4 py-3">
                        @php
                            $badge = match($session->payment_status->value ?? '') {
                                'approved' => 'bg-emerald-900/50 text-emerald-400',
                                'paid'     => 'bg-blue-900/50 text-blue-400',
                                'rejected' => 'bg-red-900/50 text-red-400',
                                'held'     => 'bg-amber-900/50 text-amber-400',
                                default    => 'bg-slate-700 text-slate-300',
                            };
                        @endphp
                        <span class="inline-block px-2 py-0.5 rounded text-xs {{ $badge }}">
                            {{ ucfirst($session->payment_status->value ?? 'pending') }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-right font-medium
                        {{ ($session->voter_payout_amount ?? 0) > 0 ? 'text-emerald-400' : 'text-slate-500' }}">
                        ${{ number_format($session->voter_payout_amount ?? 0, 2) }}
                    </td>
                    <td class="px-4 py-3 text-slate-400 text-xs">
                        {{ $session->created_at?->format('M j, Y') }}
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if(method_exists($sessions, 'links'))
    <div>{{ $sessions->links() }}</div>
    @endif
    @endif

</div>
@endsection
