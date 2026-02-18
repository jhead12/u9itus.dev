@extends('layouts.voter')

@section('title', 'Preferences')

@section('content')
<div class="px-4 sm:px-6 lg:px-8 py-8 max-w-2xl mx-auto space-y-7">

    <div>
        <h1 class="text-2xl font-bold text-white">Preferences</h1>
        <p class="text-slate-400 text-sm mt-0.5">Configure your payout method and content settings</p>
    </div>

    @if($voter)
    <form action="{{ route('voter.preferences.update') }}" method="POST">
        @csrf
        @method('PUT')

        <div class="bg-slate-800/50 border border-slate-700/60 rounded-2xl divide-y divide-slate-700/50 overflow-hidden">
            <div class="px-6 py-4">
                <h2 class="text-base font-semibold text-white">Payout Method</h2>
                <p class="text-slate-500 text-xs mt-0.5">Choose where your earnings are sent</p>
            </div>
            <div class="px-6 py-5">
                <div class="grid grid-cols-2 gap-3">
                    <label class="flex items-center gap-3 bg-slate-900 border rounded-xl px-4 py-3.5 cursor-pointer transition
                        {{ old('payment_method', $voter->payment_method) === 'paypal' ? 'border-emerald-500 ring-1 ring-emerald-500/30' : 'border-slate-600 hover:border-slate-500' }}">
                        <input type="radio" name="payment_method" value="paypal"
                            {{ old('payment_method', $voter->payment_method) === 'paypal' ? 'checked' : '' }}
                            class="text-emerald-500 focus:ring-emerald-500">
                        <span class="text-slate-300 text-sm font-medium">PayPal</span>
                    </label>
                    <label class="flex items-center gap-3 bg-slate-900 border rounded-xl px-4 py-3.5 cursor-pointer transition
                        {{ old('payment_method', $voter->payment_method) === 'cashapp' ? 'border-emerald-500 ring-1 ring-emerald-500/30' : 'border-slate-600 hover:border-slate-500' }}">
                        <input type="radio" name="payment_method" value="cashapp"
                            {{ old('payment_method', $voter->payment_method) === 'cashapp' ? 'checked' : '' }}
                            class="text-emerald-500 focus:ring-emerald-500">
                        <span class="text-slate-300 text-sm font-medium">Cash App</span>
                    </label>
                </div>
            </div>

            {{-- PayPal email --}}
            <div class="px-6 py-5">
                <label class="block text-sm font-medium text-slate-300 mb-1.5" for="paypal_email">PayPal Email</label>
                <input
                    id="paypal_email" name="paypal_email" type="email"
                    value="{{ old('paypal_email', $voter->paypal_email ?? '') }}"
                    placeholder="you@example.com"
                    class="w-full bg-slate-900 border border-slate-600 rounded-xl px-4 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
                >
            </div>

            {{-- CashApp tag --}}
            <div class="px-6 py-5">
                <label class="block text-sm font-medium text-slate-300 mb-1.5" for="cashapp_tag">Cash App $Cashtag</label>
                <div class="relative">
                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 font-bold text-sm">$</span>
                    <input
                        id="cashapp_tag" name="cashapp_tag" type="text"
                        value="{{ old('cashapp_tag', ltrim($voter->cashapp_tag ?? '', '$')) }}"
                        placeholder="YourCashtag"
                        class="w-full bg-slate-900 border border-slate-600 rounded-xl pl-8 pr-4 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
                    >
                </div>
            </div>

        </div>

        @if($errors->has('payout'))
        <p class="text-red-400 text-sm mt-2">{{ $errors->first('payout') }}</p>
        @endif

        <div class="mt-5 flex justify-end">
            <button type="submit" class="bg-emerald-600 hover:bg-emerald-500 text-white px-8 py-2.5 rounded-xl font-semibold transition text-sm">
                Save Preferences
            </button>
        </div>
    </form>

    @else
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-2xl p-10 text-center">
        <p class="text-slate-400">No voter profile found. Contact support.</p>
    </div>
    @endif

</div>
@endsection
