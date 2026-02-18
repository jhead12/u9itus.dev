@extends('layouts.voter')

@section('title', 'Earnings History')

@section('content')
<div class="px-4 sm:px-6 lg:px-8 py-8 max-w-5xl mx-auto space-y-7">

    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-white">View History</h1>
            <p class="text-slate-400 text-sm mt-0.5">All your ad-view sessions &mdash; every status</p>
        </div>
        <a href="{{ route('voter.earnings') }}"
           class="flex items-center gap-1 text-slate-400 hover:text-white text-sm transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            Earnings
        </a>
    </div>

    @if($sessions instanceof \Illuminate\Pagination\LengthAwarePaginator ? $sessions->total() === 0 : $sessions->isEmpty())
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-2xl p-12 text-center">
        <div class="w-14 h-14 rounded-full bg-slate-700/50 flex items-center justify-center mx-auto mb-4">
            <svg class="w-7 h-7 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                    d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
            </svg>
        </div>
        <p class="text-slate-400 text-sm font-medium">No view sessions yet</p>
        <p class="text-slate-600 text-xs mt-1">Check your email for ad invitations!</p>
    </div>
    @else
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-2xl overflow-hidden">
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
