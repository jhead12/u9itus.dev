@extends('standalone.layouts.dashboard')

@section('title', 'Skipped Payout Diagnostics')
@section('page-title', 'Skipped Payout Diagnostics')

@section('content')
<div class="space-y-6">

    @if(session('success'))
    <div class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm rounded-lg px-4 py-3">
        {{ session('success') }}
    </div>
    @endif

    @if($errors->any())
    <div class="bg-rose-500/10 border border-rose-500/30 text-rose-300 text-sm rounded-lg px-4 py-3">
        {{ $errors->first() }}
    </div>
    @endif

    <div class="flex flex-wrap items-center gap-3 text-xs text-slate-400">
        <a href="{{ route('admin.payouts.index') }}" class="text-sm text-slate-300 hover:text-white transition">← Back to payouts</a>
        @if($selectedRun)
            <span>Run #{{ $selectedRun->id }} · {{ $selectedRun->created_at?->format('M j, Y g:i A') }}</span>
        @else
            <span>No payout run data yet.</span>
        @endif
    </div>

    <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-3">
        <div>
            <label class="block text-xs text-slate-400 mb-1">Run</label>
            <select name="run_id" class="w-full rounded-lg bg-slate-900 border border-slate-700 text-sm text-slate-100 px-3 py-2">
                <option value="">Latest</option>
                @foreach($recentRuns as $run)
                    <option value="{{ $run->id }}" {{ (string) request('run_id') === (string) $run->id ? 'selected' : '' }}>
                        #{{ $run->id }} · {{ $run->created_at?->format('M j, g:i A') }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-xs text-slate-400 mb-1">Reason</label>
            <select name="reason" class="w-full rounded-lg bg-slate-900 border border-slate-700 text-sm text-slate-100 px-3 py-2">
                <option value="">All reasons</option>
                <option value="below_min" {{ $reason === 'below_min' ? 'selected' : '' }}>Below minimum</option>
                <option value="missing_paypal_email" {{ $reason === 'missing_paypal_email' ? 'selected' : '' }}>Missing PayPal email</option>
                <option value="processor_unavailable" {{ $reason === 'processor_unavailable' ? 'selected' : '' }}>Processor unavailable</option>
            </select>
        </div>

        <div class="flex items-end gap-2">
            <button type="submit" class="px-4 py-2 rounded-lg bg-cyan-600 hover:bg-cyan-500 text-white text-sm font-semibold transition">Apply Filters</button>
            <a href="{{ route('admin.payouts.skipped') }}" class="px-4 py-2 rounded-lg border border-slate-600 text-slate-300 text-sm hover:text-white hover:border-slate-500 transition">Clear</a>
        </div>
    </form>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="stat-card">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">Below Minimum</p>
            <p class="text-3xl font-bold text-amber-400">{{ number_format((int) ($bucketSummary['below_min'] ?? 0)) }}</p>
        </div>
        <div class="stat-card">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">Missing PayPal Email</p>
            <p class="text-3xl font-bold text-rose-400">{{ number_format((int) ($bucketSummary['missing_paypal_email'] ?? 0)) }}</p>
        </div>
        <div class="stat-card">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">Processor Unavailable</p>
            <p class="text-3xl font-bold text-cyan-300">{{ number_format((int) ($bucketSummary['processor_unavailable'] ?? 0)) }}</p>
        </div>
    </div>

    <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-700/50">
            <h3 class="text-sm font-semibold text-white">Skipped Items</h3>
            <p class="text-xs text-slate-500 mt-0.5">{{ $items->total() }} rows</p>
        </div>

        @if($items->isEmpty())
        <div class="px-5 py-10 text-center">
            <p class="text-sm text-slate-500">No skipped payouts for this filter selection.</p>
        </div>
        @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-700/50">
                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wide">Voter</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wide">Amount</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wide">Reason</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wide">Processor</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wide">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700/30">
                    @foreach($items as $item)
                    <tr class="hover:bg-slate-700/20 transition align-top">
                        <td class="px-5 py-3 text-xs text-slate-200">
                            {{ $item->voter?->user?->email ?? $item->voter?->email ?? ('Voter #' . $item->voter_id) }}
                            <p class="text-slate-500 mt-1">Skipped {{ $item->created_at?->diffForHumans() }}</p>
                        </td>
                        <td class="px-5 py-3 text-xs font-semibold text-emerald-300">${{ number_format((float) $item->amount, 2) }}</td>
                        <td class="px-5 py-3 text-xs text-slate-200">
                            @php
                                $badgeClass = match($item->reason_bucket) {
                                    'below_min' => 'bg-amber-500/15 text-amber-400',
                                    'missing_paypal_email' => 'bg-rose-500/15 text-rose-400',
                                    default => 'bg-cyan-500/15 text-cyan-300',
                                };
                            @endphp
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full {{ $badgeClass }}">{{ str_replace('_', ' ', $item->reason_bucket) }}</span>
                            @if($item->reason_detail)
                                <p class="text-slate-400 mt-1">{{ $item->reason_detail }}</p>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-xs text-slate-300 font-mono">{{ $item->processor_selected ?? 'wallet' }}</td>
                        <td class="px-5 py-3 text-xs text-slate-300">
                            @if($item->reason_bucket === 'below_min' && !$item->force_paid_at)
                            <form method="POST" action="{{ route('admin.payouts.skipped.force-pay', $item) }}" class="space-y-2">
                                @csrf
                                <textarea
                                    name="reason"
                                    required
                                    rows="2"
                                    placeholder="Reason for exceptional below-minimum payout"
                                    class="w-full rounded-lg bg-slate-900 border border-slate-700 text-xs text-slate-100 px-2 py-1"
                                ></textarea>
                                <button type="submit" class="px-3 py-1.5 rounded bg-amber-500 hover:bg-amber-400 text-slate-900 text-xs font-semibold transition">
                                    Force Pay Below Minimum
                                </button>
                            </form>
                            @elseif($item->force_paid_at)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-emerald-500/15 text-emerald-400">Force paid</span>
                                <p class="text-slate-500 mt-1">{{ $item->force_paid_at?->format('M j, Y g:i A') }}</p>
                            @else
                                <span class="text-slate-500">No manual action</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="px-5 py-4 border-t border-slate-700/50">
            {{ $items->links() }}
        </div>
        @endif
    </div>

</div>
@endsection
