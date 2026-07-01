@extends('standalone.layouts.dashboard')

@section('title', 'Add Citizen Profile')
@section('page-title', 'Add Citizen Profile')

@section('content')
<div class="max-w-xl">

    <div class="mb-6">
        <a href="{{ route('voter.dashboard') }}" class="text-sm text-slate-400 hover:text-white transition">← Back to dashboard</a>
    </div>

    {{-- Explainer --}}
    <div class="bg-amber-500/10 border border-amber-500/30 rounded-xl p-5 mb-6">
        <div class="flex items-start gap-3">
            <span class="text-2xl">🏘️</span>
            <div>
                <h2 class="text-white font-semibold mb-1">Add a Citizen profile to your account</h2>
                <p class="text-sm text-slate-400 leading-relaxed">
                    You keep your Voter account and earnings exactly as they are.
                    Adding a Citizen profile lets you run local business, community,
                    or ballot-issue ads — paid per view, no election-commission
                    requirements for standard ads.
                </p>
                <div class="flex flex-wrap gap-x-5 gap-y-1 text-xs text-slate-400 mt-3">
                    <span><span class="text-amber-400 font-medium">${{ number_format($citizenRate, 2) }}</span> / view (standard)</span>
                    <span><span class="text-amber-400 font-medium">$0.50</span> voter payout</span>
                    <span><span class="text-amber-400 font-medium">$10</span> minimum campaign</span>
                    <span><span class="text-amber-400 font-medium">500</span> daily view cap</span>
                </div>
                <p class="text-xs text-slate-500 mt-2">
                    You'll be able to switch between your Voter and Citizen portals after sign-in.
                    Same email, same password — one account.
                </p>
            </div>
        </div>
    </div>

    @if($errors->any())
    <div class="mb-6 rounded-xl border border-red-500/30 bg-red-500/10 p-4">
        <ul class="list-disc pl-5 text-xs text-red-200 space-y-1">
            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
    @endif

    <form method="POST" action="{{ route('voter.add-citizen-profile.submit') }}" class="space-y-5">
        @csrf

        <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-6 space-y-4">
            <h3 class="text-sm font-semibold text-slate-200">Your details</h3>

            <div>
                <label class="block text-sm font-medium text-slate-300 mb-1.5">Full Name <span class="text-red-400">*</span></label>
                <input type="text" name="full_name" value="{{ old('full_name', $user->name) }}" required maxlength="255"
                    class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500 transition" />
                @error('full_name')<p class="text-xs text-red-400 mt-1">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-300 mb-1.5">Business / Organisation Name <span class="text-slate-500 font-normal">(optional)</span></label>
                <input type="text" name="business_name" value="{{ old('business_name') }}" maxlength="255"
                    class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500 transition"
                    placeholder="Maple Street Bakery" />
                @error('business_name')<p class="text-xs text-red-400 mt-1">{{ $message }}</p>@enderror
            </div>
        </div>

        <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-6 space-y-4">
            <h3 class="text-sm font-semibold text-slate-200">Local address <span class="text-xs text-slate-500 font-normal">(used for targeting — not shown publicly)</span></h3>

            <div>
                <label class="block text-sm font-medium text-slate-300 mb-1.5">Address Line 1 <span class="text-red-400">*</span></label>
                <input type="text" name="address_line_1" value="{{ old('address_line_1') }}" required maxlength="255"
                    class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500 transition"
                    placeholder="123 Maple St" />
                @error('address_line_1')<p class="text-xs text-red-400 mt-1">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-300 mb-1.5">Address Line 2</label>
                <input type="text" name="address_line_2" value="{{ old('address_line_2') }}" maxlength="255"
                    class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500 transition"
                    placeholder="Suite 4B (optional)" />
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div class="col-span-2 sm:col-span-1">
                    <label class="block text-sm font-medium text-slate-300 mb-1.5">City <span class="text-red-400">*</span></label>
                    <input type="text" name="city" value="{{ old('city') }}" required maxlength="100"
                        class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500 transition" />
                    @error('city')<p class="text-xs text-red-400 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1.5">State <span class="text-red-400">*</span></label>
                    <input type="text" name="state" value="{{ old('state') }}" required maxlength="2" placeholder="CA"
                        class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500 transition uppercase" />
                    @error('state')<p class="text-xs text-red-400 mt-1">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="max-w-xs">
                <label class="block text-sm font-medium text-slate-300 mb-1.5">ZIP Code <span class="text-red-400">*</span></label>
                <input type="text" name="zip" value="{{ old('zip') }}" required maxlength="5" pattern="[0-9]{5}"
                    class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500 transition"
                    placeholder="90210" />
                @error('zip')<p class="text-xs text-red-400 mt-1">{{ $message }}</p>@enderror
            </div>
        </div>

        <div class="flex gap-3">
            <button type="submit"
                class="flex-1 bg-amber-500 hover:bg-amber-400 text-slate-900 font-semibold rounded-lg px-6 py-3 text-sm transition-colors">
                Add Citizen Profile
            </button>
            <a href="{{ route('voter.dashboard') }}"
               class="px-5 py-3 text-sm text-slate-400 hover:text-white border border-slate-700 hover:border-slate-500 rounded-lg transition">
                Cancel
            </a>
        </div>
    </form>

</div>
@endsection
