@extends('standalone.layouts.dashboard')

@section('title', 'Billing & Credits')
@section('page-title', 'Billing & Credits')

@section('content')
<div class="space-y-6">

    {{-- Credit Balance + Add Funds --}}
    <div class="grid sm:grid-cols-3 gap-4">
        <div class="sm:col-span-2 bg-slate-800/50 border border-slate-700/50 rounded-xl p-6">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">Credit Balance</p>
            <p class="text-4xl font-bold text-emerald-400">${{ number_format($creditBalance, 2) }}</p>
            <p class="text-xs text-slate-500 mt-2">Used to fund active campaigns at ${{ number_format(config('u9itus.revenue_per_view', 0.60), 2) }}/view</p>
        </div>
        <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-6">
            <p class="text-sm font-semibold text-slate-200 mb-3">Add Funds</p>
            <form method="POST" action="{{ route('politician.billing.add-funds') }}">
                @csrf
                <div class="relative mb-3">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">$</span>
                    <input type="number" name="amount" min="10" max="10000" step="10" value="100"
                        class="w-full bg-slate-900/60 border border-slate-700 rounded-lg pl-7 pr-4 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition" />
                </div>
                <button type="submit"
                    class="w-full bg-emerald-500 hover:bg-emerald-400 text-slate-900 font-semibold rounded-lg py-2.5 text-sm transition">
                    Add Credits via Stripe
                </button>
            </form>
            <p class="text-xs text-slate-600 mt-2 text-center">Minimum $10</p>
        </div>
    </div>

    {{-- Credit Ledger --}}
    <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-700/50 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-slate-200">Credit Ledger</h3>
            <a href="{{ route('politician.billing.invoices') }}" class="text-xs text-emerald-400 hover:text-emerald-300">View all invoices →</a>
        </div>

        @if($credits->isEmpty())
            <p class="text-slate-500 text-sm text-center py-10">No credit transactions yet.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-700/50">
                            <th class="text-left px-5 py-2.5 text-xs font-medium text-slate-500">Date</th>
                            <th class="text-left px-5 py-2.5 text-xs font-medium text-slate-500">Description</th>
                            <th class="text-left px-5 py-2.5 text-xs font-medium text-slate-500">Type</th>
                            <th class="text-right px-5 py-2.5 text-xs font-medium text-slate-500">Amount</th>
                            <th class="text-right px-5 py-2.5 text-xs font-medium text-slate-500">Balance</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700/30">
                        @foreach($credits as $credit)
                        <tr>
                            <td class="px-5 py-3 text-slate-500 text-xs whitespace-nowrap">
                                {{ $credit->created_at?->format('M j, Y H:i') }}
                            </td>
                            <td class="px-5 py-3 text-slate-300">{{ $credit->description ?? ucfirst(str_replace('_', ' ', $credit->transaction_type)) }}</td>
                            <td class="px-5 py-3">
                                <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium {{ str_contains($credit->transaction_type, 'debit') ? 'bg-red-500/10 text-red-400' : 'bg-emerald-500/10 text-emerald-400' }}">
                                    {{ ucfirst(str_replace('_', ' ', $credit->transaction_type)) }}
                                </span>
                            </td>
                            <td class="px-5 py-3 text-right font-mono {{ $credit->amount < 0 ? 'text-red-400' : 'text-emerald-400' }}">
                                {{ $credit->amount >= 0 ? '+' : '' }}${{ number_format($credit->amount, 2) }}
                            </td>
                            <td class="px-5 py-3 text-right font-mono text-slate-300">${{ number_format($credit->balance_after, 2) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($credits->hasPages())
            <div class="px-5 py-4 border-t border-slate-700/50">
                {{ $credits->links() }}
            </div>
            @endif
        @endif
    </div>

    {{-- Stripe Transactions --}}
    <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-700/50">
            <h3 class="text-sm font-semibold text-slate-200">Stripe Transactions</h3>
        </div>

        @if($transactions->isEmpty())
            <p class="text-slate-500 text-sm text-center py-10">No Stripe transactions yet.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-700/50">
                            <th class="text-left px-5 py-2.5 text-xs font-medium text-slate-500">Date</th>
                            <th class="text-left px-5 py-2.5 text-xs font-medium text-slate-500">Description</th>
                            <th class="text-left px-5 py-2.5 text-xs font-medium text-slate-500">Status</th>
                            <th class="text-right px-5 py-2.5 text-xs font-medium text-slate-500">Amount</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700/30">
                        @foreach($transactions as $tx)
                        @php
                            $txColor = match($tx->status) {
                                'succeeded' => 'bg-emerald-500/10 text-emerald-400',
                                'failed'    => 'bg-red-500/10 text-red-400',
                                default     => 'bg-yellow-500/10 text-yellow-400',
                            };
                        @endphp
                        <tr>
                            <td class="px-5 py-3 text-slate-500 text-xs whitespace-nowrap">
                                {{ $tx->created_at?->format('M j, Y H:i') }}
                            </td>
                            <td class="px-5 py-3 text-slate-300">{{ $tx->description ?? ucfirst(str_replace('_', ' ', $tx->transaction_type)) }}</td>
                            <td class="px-5 py-3">
                                <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium {{ $txColor }}">
                                    {{ ucfirst($tx->status) }}
                                </span>
                            </td>
                            <td class="px-5 py-3 text-right font-mono text-slate-300">${{ number_format($tx->amount, 2) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($transactions->hasPages())
            <div class="px-5 py-4 border-t border-slate-700/50">
                {{ $transactions->links() }}
            </div>
            @endif
        @endif
    </div>

</div>
@endsection
