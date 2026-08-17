@extends('standalone.layouts.dashboard')

@section('title', 'Business Settings')
@section('page-title', 'Business Settings')

@section('content')
<div class="space-y-6 max-w-2xl">

    @if(session('success'))
        <div class="bg-emerald-500/10 border border-emerald-500/30 rounded-xl px-5 py-4">
            <p class="text-sm text-emerald-300">{{ session('success') }}</p>
        </div>
    @endif

    <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-6">
        <h2 class="text-white font-semibold mb-1">Business Location</h2>
        <p class="text-slate-400 text-sm mb-5">
            Used to place your business on the U9itus map. Your precise address is never shown publicly
            unless you turn on "Show on map" below.
        </p>

        <form method="POST" action="{{ route('citizen.settings.update') }}" class="space-y-4">
            @csrf
            @method('PUT')

            <div>
                <label class="block text-sm text-slate-300 mb-1.5">Business Name</label>
                <input type="text" name="business_name" value="{{ old('business_name', $citizen->business_name) }}"
                    class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition">
                @error('business_name')<p class="text-xs text-red-400 mt-1">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-sm text-slate-300 mb-1.5">Category</label>
                <select name="business_category"
                    class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition">
                    <option value="">— Select a category —</option>
                    @foreach(['food' => 'Food & Dining', 'retail' => 'Retail', 'service' => 'Service', 'nonprofit' => 'Nonprofit', 'other' => 'Other'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('business_category', $citizen->business_category) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('business_category')<p class="text-xs text-red-400 mt-1">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-sm text-slate-300 mb-1.5">Address Line 1</label>
                <input type="text" name="address_line_1" value="{{ old('address_line_1', $citizen->address_line_1) }}" required
                    class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition">
                @error('address_line_1')<p class="text-xs text-red-400 mt-1">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-sm text-slate-300 mb-1.5">Address Line 2 (optional)</label>
                <input type="text" name="address_line_2" value="{{ old('address_line_2', $citizen->address_line_2) }}"
                    class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition">
                @error('address_line_2')<p class="text-xs text-red-400 mt-1">{{ $message }}</p>@enderror
            </div>

            <div class="grid grid-cols-3 gap-3">
                <div class="col-span-2">
                    <label class="block text-sm text-slate-300 mb-1.5">City</label>
                    <input type="text" name="city" value="{{ old('city', $citizen->city) }}" required
                        class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition">
                    @error('city')<p class="text-xs text-red-400 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm text-slate-300 mb-1.5">State</label>
                    <input type="text" name="state" maxlength="2" value="{{ old('state', $citizen->state) }}" required
                        class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm uppercase focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition">
                    @error('state')<p class="text-xs text-red-400 mt-1">{{ $message }}</p>@enderror
                </div>
            </div>

            <div>
                <label class="block text-sm text-slate-300 mb-1.5">ZIP Code</label>
                <input type="text" name="zip" value="{{ old('zip', $citizen->zip) }}" required
                    class="w-full sm:w-48 bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition">
                @error('zip')<p class="text-xs text-red-400 mt-1">{{ $message }}</p>@enderror
            </div>

            <div class="border-t border-slate-700/50 pt-4">
                <label class="flex items-start gap-3 cursor-pointer">
                    <input type="checkbox" name="show_on_map" value="1" @checked(old('show_on_map', $citizen->show_on_map))
                        class="mt-0.5 rounded border-slate-600 bg-slate-900/60 text-emerald-600 focus:ring-emerald-500">
                    <span>
                        <span class="block text-sm text-white font-medium">Show my business on the map</span>
                        <span class="block text-xs text-slate-500 mt-0.5">
                            Plots a pin at your address on the U9itus interactive map so voters in your area can find you.
                            Off by default — your address stays private until you turn this on.
                        </span>
                    </span>
                </label>
            </div>

            @if(!$citizen->show_on_map || !$citizen->latitude)
                <p class="text-xs text-slate-500">
                    @if(!$citizen->latitude)
                        We haven't been able to place your address on the map yet — it's queued for geocoding
                        and will appear shortly after you save.
                    @endif
                </p>
            @endif

            <button type="submit" class="bg-emerald-600 hover:bg-emerald-500 text-white text-sm font-semibold px-5 py-2.5 rounded-lg transition">
                Save Settings
            </button>
        </form>
    </div>
</div>
@endsection
