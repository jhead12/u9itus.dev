@extends('standalone.layouts.dashboard')

@section('title', 'Billing Refunds')
@section('page-title', 'Billing Refunds Management')

@section('content')
<div class="space-y-6">

    {{-- Payment mode indicator --}}
    @if(!empty($activePaymentMode))
    <div class="bg-blue-500/10 border border-blue-500/30 rounded-xl px-5 py-3 flex items-center justify-between">
        <p class="text-sm text-blue-300">
            Viewing <span class="font-semibold uppercase">{{ $activePaymentMode }}</span> mode transactions only.
        </p>
    </div>
    @endif

    {{-- Refunds list --}}
    <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-700/50 flex items-center justify-between">
            <div>
                <h3 class="text-sm font-semibold text-white">Politician Credit Purchases</h3>
                <p class="text-xs text-slate-500 mt-0.5">Manage refunds for unused credits</p>
            </div>
            <form method="GET" class="flex gap-2">
                <input type="text" name="search" placeholder="Search by politician name or email..."
                    value="{{ request('search') }}"
                    class="px-3 py-2 bg-slate-900/60 border border-slate-700 rounded-lg text-sm text-white placeholder-slate-600 focus:outline-none focus:ring-2 focus:ring-blue-500/50">
                <button type="submit" class="px-4 py-2 bg-slate-700 hover:bg-slate-600 text-white text-sm font-medium rounded-lg transition">
                    Search
                </button>
            </form>
        </div>

        @if($transactions->isEmpty())
        <div class="px-5 py-10 text-center">
            <p class="text-sm text-slate-500">No credit purchases found.</p>
        </div>
        @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-700/50">
                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wide">Politician</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wide">Purchased</th>
                        <th class="px-5 py-3 text-right text-xs font-semibold text-slate-400 uppercase tracking-wide">Amount</th>
                        <th class="px-5 py-3 text-right text-xs font-semibold text-slate-400 uppercase tracking-wide">Spent</th>
                        <th class="px-5 py-3 text-right text-xs font-semibold text-slate-400 uppercase tracking-wide">Unused</th>
                        <th class="px-5 py-3 text-center text-xs font-semibold text-slate-400 uppercase tracking-wide">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700/30">
                    @foreach($transactions as $tx)
                    @php
                        $politician = $tx->politician;
                        $creditsAmount = isset($tx->metadata['credits_amount']) 
                            ? (float) $tx->metadata['credits_amount']
                            : (float) $tx->amount;
                        
                        try {
                            // Get refund summary to show unused credits
                            $summary = app(\App\Services\CampaignBillingService::class)
                                ->getUnusedRefundSummary($tx);
                            $refundable = $summary['refundable_credits_now'];
                            $hasUnused = $refundable > 0;
                        } catch (\Exception $e) {
                            $refundable = 0;
                            $hasUnused = false;
                        }
                    @endphp
                    <tr class="hover:bg-slate-700/20 transition">
                        <td class="px-5 py-3">
                            @if($politician)
                                <div class="text-sm text-slate-200 font-medium">{{ $politician->full_name }}</div>
                                <div class="text-xs text-slate-500">{{ $politician->user?->email }}</div>
                            @else
                                <div class="text-sm text-slate-500">—</div>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-xs text-slate-400">
                            {{ $tx->created_at?->format('M j, Y') }}
                        </td>
                        <td class="px-5 py-3 text-right font-mono font-semibold text-emerald-400">
                            ${{ number_format($creditsAmount, 2) }}
                        </td>
                        <td class="px-5 py-3 text-right font-mono text-slate-400">
                            {{-- Credits are pooled across purchases (no per-purchase-lot tracking), so
                                 "spent" for one purchase row isn't independently observable. Derive it
                                 from the same refund cap below so Spent + Unused always equals Amount. --}}
                            ${{ number_format(max(0, $creditsAmount - $refundable), 2) }}
                        </td>
                        <td class="px-5 py-3 text-right font-mono">
                            @if($hasUnused)
                                <span class="font-semibold text-amber-400">
                                    ${{ number_format($refundable, 2) }}
                                </span>
                            @else
                                <span class="text-slate-500">—</span>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-center">
                            @if($hasUnused)
                                <button type="button"
                                    onclick="openRefundModal({{ $tx->id }}, '{{ $politician?->full_name }}', {{ $creditsAmount }}, {{ $refundable }})"
                                    class="px-3 py-1 bg-amber-600 hover:bg-amber-500 text-white text-xs font-medium rounded transition">
                                    Refund
                                </button>
                            @else
                                <span class="text-xs text-slate-500">No unused</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="px-5 py-4 border-t border-slate-700/50">
            {{ $transactions->links() }}
        </div>
        @endif
    </div>

</div>

<!-- Refund Modal -->
<div id="refundModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50"
     role="dialog" aria-modal="true" aria-labelledby="refundModal-title" tabindex="-1">
    <div class="bg-slate-800 border border-slate-700 rounded-xl max-w-md w-full mx-4 shadow-2xl">
        <div class="px-6 py-4 border-b border-slate-700">
            <h3 id="refundModal-title" class="text-lg font-semibold text-white">Refund Unused Credits</h3>
        </div>

        <form id="refundForm" method="POST" class="space-y-4 p-6">
            @csrf
            <input type="hidden" id="txId" name="tx_id">
            
            <div>
                <label class="text-sm font-medium text-slate-300 block mb-2">Politician</label>
                <p id="politicianName" class="text-white font-medium"></p>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="text-xs font-medium text-slate-400 uppercase">Credits Purchased</label>
                    <p id="creditsPurchased" class="text-emerald-400 font-mono font-semibold mt-1"></p>
                </div>
                <div>
                    <label class="text-xs font-medium text-slate-400 uppercase">Refundable Now</label>
                    <p id="refundableAmount" class="text-amber-400 font-mono font-semibold mt-1"></p>
                </div>
            </div>

            <div>
                <label for="creditsAmount" class="text-sm font-medium text-slate-300 block mb-2">
                    Refund Amount (optional)
                </label>
                <div class="relative">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-500">$</span>
                    <input type="number" id="creditsAmount" name="credits_amount" step="0.01" min="0.01"
                        placeholder="Leave blank to refund all unused credits"
                        class="w-full bg-slate-900/60 border border-slate-700 rounded-lg pl-7 pr-4 py-2 text-white placeholder-slate-600 focus:outline-none focus:ring-2 focus:ring-amber-500/50">
                </div>
                <p class="text-xs text-slate-500 mt-1">Leave blank to refund the maximum refundable amount</p>
            </div>

            <div>
                <label for="reason" class="text-sm font-medium text-slate-300 block mb-2">Reason (optional)</label>
                <textarea id="reason" name="reason" rows="2" maxlength="500"
                    placeholder="e.g., Campaign cancelled, client request..."
                    class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2 text-white placeholder-slate-600 focus:outline-none focus:ring-2 focus:ring-amber-500/50 resize-none"></textarea>
                <p class="text-xs text-slate-500 mt-1">This will be logged in audit trail</p>
            </div>

            <div class="flex gap-3 pt-2">
                <button type="button" onclick="closeRefundModal()"
                    class="flex-1 px-4 py-2 bg-slate-700 hover:bg-slate-600 text-white font-medium rounded-lg transition">
                    Cancel
                </button>
                <button type="submit"
                    class="flex-1 px-4 py-2 bg-amber-600 hover:bg-amber-500 text-white font-semibold rounded-lg transition">
                    Process Refund
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openRefundModal(txId, politicianName, creditsPurchased, refundableAmount) {
    document.getElementById('txId').value = txId;
    document.getElementById('politicianName').textContent = politicianName;
    document.getElementById('creditsPurchased').textContent = '$' + creditsPurchased.toFixed(2);
    document.getElementById('refundableAmount').textContent = '$' + refundableAmount.toFixed(2);
    document.getElementById('creditsAmount').max = refundableAmount.toFixed(2);
    document.getElementById('creditsAmount').placeholder = 'Max: $' + refundableAmount.toFixed(2);
    
    const form = document.getElementById('refundForm');
    form.action = `/admin/billing/transactions/${txId}/refund-unused`;
    
    const modal = document.getElementById('refundModal');
    modal.classList.remove('hidden');
    modal.focus();
}

function closeRefundModal() {
    document.getElementById('refundModal').classList.add('hidden');
    document.getElementById('refundForm').reset();
}

// Close modal when clicking outside
document.getElementById('refundModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeRefundModal();
});

document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeRefundModal();
});
</script>

@endsection
