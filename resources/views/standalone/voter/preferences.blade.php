@extends('layouts.app')

@section('title', 'Payout Preferences')

@section('content')
<div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">

    <h1 class="text-3xl font-bold text-white">Payout Preferences</h1>

    @if(session('success'))
    <div class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm rounded-lg px-4 py-3">
        {{ session('success') }}
    </div>
    @endif

    @if($voter)
    <form action="{{ route('voter.preferences.update') }}" method="POST">
        @csrf
        @method('PUT')

        <div class="bg-slate-800/50 border border-slate-700 rounded-xl divide-y divide-slate-700/50">

            {{-- Payout method --}}
            <div class="px-6 py-5">
                <p class="text-white font-medium mb-3">Payout Method</p>
                <div class="grid grid-cols-2 gap-3">
                    <label class="flex items-center gap-3 bg-slate-900 border border-slate-600 rounded-lg px-4 py-3 cursor-pointer has-[:checked]:border-emerald-500">
                        <input type="radio" name="payment_method" value="paypal"
                            {{ old('payment_method', $voter->payment_method) === 'paypal' ? 'checked' : '' }}
                            class="text-emerald-500">
                        <span class="text-slate-300 text-sm">PayPal</span>
                    </label>
                    <label class="flex items-center gap-3 bg-slate-900 border border-slate-600 rounded-lg px-4 py-3 cursor-pointer has-[:checked]:border-emerald-500">
                        <input type="radio" name="payment_method" value="cashapp"
                            {{ old('payment_method', $voter->payment_method) === 'cashapp' ? 'checked' : '' }}
                            class="text-emerald-500">
                        <span class="text-slate-300 text-sm">Cash App</span>
                    </label>
                </div>
            </div>

            {{-- PayPal email --}}
            <div class="px-6 py-5">
                <label class="block text-sm font-medium text-slate-300 mb-1" for="paypal_email">PayPal Email</label>
                <input
                    id="paypal_email" name="paypal_email" type="email"
                    value="{{ old('paypal_email', $voter->paypal_email ?? '') }}"
                    placeholder="you@example.com"
                    class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
                >
            </div>

            {{-- CashApp tag --}}
            <div class="px-6 py-5">
                <label class="block text-sm font-medium text-slate-300 mb-1" for="cashapp_tag">Cash App $Cashtag</label>
                <input
                    id="cashapp_tag" name="cashapp_tag" type="text"
                    value="{{ old('cashapp_tag', $voter->cashapp_tag ?? '') }}"
                    placeholder="$YourCashtag"
                    class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
                >
            </div>

        </div>

        @if($errors->has('payout'))
        <p class="text-red-400 text-sm mt-2">{{ $errors->first('payout') }}</p>
        @endif

        <div class="mt-6 flex justify-end">
            <button type="submit" class="bg-emerald-600 hover:bg-emerald-500 text-white px-6 py-2.5 rounded-lg font-medium transition">
                Save Preferences
            </button>
        </div>
    </form>

    @else
    <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-10 text-center">
        <p class="text-slate-400">No voter profile found.</p>
    </div>
    @endif

</div>
@endsection
