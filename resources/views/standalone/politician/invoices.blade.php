@extends('standalone.layouts.dashboard')

@section('title', 'Invoices')
@section('page-title', 'Invoices')

@section('content')
<div class="space-y-5">

    <div class="flex items-center gap-3">
        <a href="{{ route('politician.billing') }}" class="text-sm text-slate-400 hover:text-white transition">← Billing</a>
    </div>

    <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-700/50">
            <h3 class="text-sm font-semibold text-slate-200">All Transactions</h3>
        </div>

        @if($transactions->isEmpty())
            <p class="text-slate-500 text-sm text-center py-16">No transactions on record.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-700/50">
                            <th class="text-left px-5 py-2.5 text-xs font-medium text-slate-500">Date</th>
                            <th class="text-left px-5 py-2.5 text-xs font-medium text-slate-500">Reference</th>
                            <th class="text-left px-5 py-2.5 text-xs font-medium text-slate-500">Description</th>
                            <th class="text-left px-5 py-2.5 text-xs font-medium text-slate-500">Status</th>
                            <th class="text-right px-5 py-2.5 text-xs font-medium text-slate-500">Credits</th>
                            <th class="text-right px-5 py-2.5 text-xs font-medium text-slate-500">Fee</th>
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
                        <tr class="hover:bg-slate-700/10 transition">
                            <td class="px-5 py-3 text-slate-500 text-xs whitespace-nowrap">
                                {{ $tx->created_at?->format('M j, Y H:i') }}
                            </td>
                            <td class="px-5 py-3 text-slate-500 text-xs font-mono truncate max-w-[120px]">
                                {{ $tx->uuid ?? '—' }}
                            </td>
                            <td class="px-5 py-3 text-slate-300">
                                {{ $tx->description ?? ucfirst(str_replace('_', ' ', $tx->transaction_type)) }}
                            </td>
                            <td class="px-5 py-3">
                                <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium {{ $txColor }}">
                                    {{ ucfirst($tx->status) }}
                                </span>
                            </td>
                            <td class="px-5 py-3 text-right font-mono text-emerald-400">
                                @if(isset($tx->metadata['credits_amount']))
                                    ${{ number_format($tx->metadata['credits_amount'], 2) }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-5 py-3 text-right font-mono text-slate-400">
                                @if(isset($tx->metadata['stripe_fee']))
                                    ${{ number_format($tx->metadata['stripe_fee'], 2) }}
                                    @if(isset($tx->metadata['stripe_fee_percent']))
                                        <span class="text-slate-500 text-xs">({{ number_format($tx->metadata['stripe_fee_percent'], 1) }}%)</span>
                                    @endif
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-5 py-3 text-right font-mono text-slate-200">
                                ${{ number_format($tx->amount, 2) }}
                            </td>
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
