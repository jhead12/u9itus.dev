@extends('standalone.layouts.dashboard')

@section('title', 'Billing & Credits')
@section('page-title', 'Billing & Credits')

@section('content')
<div class="space-y-6">

    @if(!empty($activePaymentMode))
    <div class="bg-blue-500/10 border border-blue-500/30 rounded-xl px-5 py-3 flex items-center justify-between">
        <p class="text-sm text-blue-300">
            Billing view filtered to <span class="font-semibold uppercase">{{ $activePaymentMode }}</span> mode transactions.
        </p>
    </div>
    @endif

    {{-- Payment result notices — shown after Stripe redirect + server-side confirmation --}}
    <div id="notice-success" class="{{ session('payment_confirmed') ? '' : 'hidden' }} bg-emerald-500/10 border border-emerald-500/30 rounded-xl px-5 py-4 flex items-center gap-3">
        <svg class="w-5 h-5 text-emerald-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
        <p class="text-sm text-emerald-300">Payment successful! Your credit balance has been updated.</p>
    </div>
    <div id="notice-failed" class="{{ session('payment_failed') ? '' : 'hidden' }} bg-red-500/10 border border-red-500/30 rounded-xl px-5 py-4 flex items-center gap-3">
        <svg class="w-5 h-5 text-red-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        <p class="text-sm text-red-300">Payment failed or was cancelled. Please try again.</p>
    </div>

    {{-- Credit Balance + Add Funds --}}
    <div class="grid sm:grid-cols-3 gap-4">
        <div class="sm:col-span-2 bg-slate-800/50 border border-slate-700/50 rounded-xl p-6">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">Credit Balance</p>
            <p class="text-4xl font-bold text-emerald-400">${{ number_format($creditBalance, 2) }}</p>
            <p class="text-xs text-slate-500 mt-2">Used to fund active campaigns at ${{ number_format((float) \App\Services\PlatformSettingsService::get('revenue_per_view', null, (float) config('u9itus.revenue_per_view', 1.00)), 2) }}/view</p>
        </div>

        {{-- Receipt Email Settings --}}
        <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-6">
            <form method="POST" action="{{ route('politician.billing.update-receipt-email') }}" class="space-y-3">
                @csrf
                <div>
                    <label class="text-sm font-semibold text-slate-200 block mb-2">Receipt Email</label>
                    <input type="email" name="receipt_email" value="{{ $politician->receipt_email ?? $politician->user->email }}"
                        placeholder="{{ $politician->user->email }}"
                        class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-3 py-2.5 text-sm text-white placeholder-slate-600 focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition" />
                    <p class="text-xs text-slate-500 mt-1">For Stripe receipts (e.g., when using someone else's card)</p>
                </div>
                <button type="submit" class="w-full bg-slate-700 hover:bg-slate-600 text-slate-200 font-medium rounded-lg py-2 text-sm transition">
                    Save
                </button>
            </form>
        </div>
            <p class="text-sm font-semibold text-slate-200 mb-3">Add Funds</p>

            {{-- Step 1: amount selection --}}
            <div id="step-amount">
                <div class="relative mb-3">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">$</span>
                    <input id="fund-amount" type="number" min="10" max="10000" step="10" value="100"
                        class="w-full bg-slate-900/60 border border-slate-700 rounded-lg pl-7 pr-4 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition" />
                </div>
                <button id="btn-proceed"
                    class="w-full bg-emerald-500 hover:bg-emerald-400 text-slate-900 font-semibold rounded-lg py-2.5 text-sm transition">
                    Add Credits via Stripe
                </button>
                <p class="text-xs text-slate-600 mt-2 text-center">Minimum $10</p>
            </div>

            {{-- Step 2: Stripe PaymentElement (hidden until client_secret is loaded) --}}
            <div id="step-payment" class="hidden">
                {{-- Fee breakdown --}}
                <div class="bg-slate-900/60 border border-slate-700/50 rounded-lg px-3 py-2.5 mb-3 text-xs space-y-1">
                    <div class="flex justify-between text-slate-400">
                        <span>Credits added</span>
                        <span id="pay-credits"></span>
                    </div>
                    <div class="flex justify-between text-slate-500">
                        <span>Stripe processing fee (<span id="pay-fee-pct"></span>%)</span>
                        <span id="pay-fee"></span>
                    </div>
                    <div class="flex justify-between text-slate-200 font-semibold border-t border-slate-700/50 pt-1 mt-1">
                        <span>Total charged</span>
                        <span id="pay-display"></span>
                    </div>
                </div>
                <div id="payment-element" class="mb-3"></div>
                <div id="payment-message" class="hidden text-xs text-red-400 mb-2"></div>
                <button id="btn-pay"
                    class="w-full bg-emerald-500 hover:bg-emerald-400 disabled:opacity-50 text-slate-900 font-semibold rounded-lg py-2.5 text-sm transition">
                    Pay Now
                </button>
                <button id="btn-cancel" class="w-full mt-2 text-xs text-slate-500 hover:text-slate-300">
                    Cancel
                </button>
            </div>

            {{-- Loading spinner --}}
            <div id="step-loading" class="hidden text-center py-4">
                <svg class="animate-spin mx-auto h-6 w-6 text-emerald-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                </svg>
                <p class="text-xs text-slate-500 mt-2">Processing…</p>
            </div>
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
                            <th class="text-right px-5 py-2.5 text-xs font-medium text-slate-500">Credits</th>
                            <th class="text-right px-5 py-2.5 text-xs font-medium text-slate-500">Fee</th>
                            <th class="text-right px-5 py-2.5 text-xs font-medium text-slate-500">Charged</th>
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

@push('scripts')
<script src="https://js.stripe.com/v3/"></script>
<script>
(function () {
    const addFundsUrl  = @json(route('politician.billing.add-funds'));
    const csrfToken    = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

    let stripe, elements;

    const show = id => document.getElementById(id).classList.remove('hidden');
    const hide = id => document.getElementById(id).classList.add('hidden');
    const $msg = document.getElementById('payment-message');

    function showError(msg) {
        $msg.textContent = msg;
        $msg.classList.remove('hidden');
    }

    // ── Step 1: "Add Credits via Stripe" clicked ───────────────────────────
    document.getElementById('btn-proceed').addEventListener('click', async () => {
        const amount = parseFloat(document.getElementById('fund-amount').value);
        if (!amount || amount < 10) {
            alert('Minimum amount is $10.');
            return;
        }

        hide('step-amount');
        show('step-loading');

        try {
            const res = await fetch(addFundsUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({ amount }),
            });

            const data = await res.json();

            if (!res.ok || data.error) {
                throw new Error(data.error ?? data.message ?? 'Server error');
            }

            if (!data.client_secret) {
                throw new Error('Payment service not configured. Please contact support.');
            }

            // Mount Stripe PaymentElement
            stripe   = Stripe(data.publishable_key);
            elements = stripe.elements({
                clientSecret: data.client_secret,
                appearance: {
                    theme: 'night',
                    variables: { colorPrimary: '#10b981', colorBackground: '#0f172a', borderRadius: '8px' }
                },
            });

            const paymentEl = elements.create('payment');
            paymentEl.mount('#payment-element');

            document.getElementById('pay-credits').textContent  = '$' + parseFloat(data.credits_amount).toFixed(2);
            document.getElementById('pay-fee').textContent       = '$' + parseFloat(data.stripe_fee).toFixed(2);
            document.getElementById('pay-fee-pct').textContent   = parseFloat(data.stripe_fee_percent).toFixed(1);
            document.getElementById('pay-display').textContent   = '$' + parseFloat(data.amount).toFixed(2);

            hide('step-loading');
            show('step-payment');

            // Store return_url for confirmPayment
            document.getElementById('btn-pay').dataset.returnUrl = data.return_url;

        } catch (err) {
            hide('step-loading');
            show('step-amount');
            alert('Error: ' + err.message);
        }
    });

    // ── Step 2: "Pay Now" clicked ─────────────────────────────────────────
    document.getElementById('btn-pay').addEventListener('click', async (e) => {
        const btn = e.currentTarget;
        btn.disabled = true;
        btn.textContent = 'Processing…';
        $msg.classList.add('hidden');

        const { error, paymentIntent } = await stripe.confirmPayment({
            elements,
            confirmParams: { return_url: btn.dataset.returnUrl },
            // Do not redirect automatically for payment methods that succeed
            // in-browser without a redirect step (e.g. saved cards). We handle
            // both outcomes ourselves so the confirmPayment server route always runs.
            redirect: 'if_required',
        });

        if (error) {
            // Validation or payment error — show message and re-enable form.
            showError(error.message);
            btn.disabled = false;
            btn.textContent = 'Pay Now';
        } else if (paymentIntent && paymentIntent.status === 'succeeded') {
            // Payment succeeded without a browser redirect — navigate to the
            // confirm route so the server can finalize the transaction and
            // credit the politician's balance.
            const returnUrl = new URL(btn.dataset.returnUrl);
            returnUrl.searchParams.set('payment_intent', paymentIntent.id);
            returnUrl.searchParams.set('redirect_status', 'succeeded');
            window.location.href = returnUrl.toString();
        } else if (paymentIntent) {
            // Payment is in an unexpected state (requires_action, etc.).
            // Redirect to confirm route so the server can inspect and handle it.
            const returnUrl = new URL(btn.dataset.returnUrl);
            returnUrl.searchParams.set('payment_intent', paymentIntent.id);
            returnUrl.searchParams.set('redirect_status', paymentIntent.status);
            window.location.href = returnUrl.toString();
        }
    });

    // ── Cancel button ─────────────────────────────────────────────────────
    document.getElementById('btn-cancel').addEventListener('click', () => {
        $msg.classList.add('hidden');
        hide('step-payment');
        show('step-amount');
    });
})();
</script>
@endpush
