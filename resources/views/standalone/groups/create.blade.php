@extends(auth()->user()->hasRole('citizen') ? 'standalone.layouts.dashboard' : 'layouts.voter')

@section('title', 'Create a Neighborhood Group')
@section('page-title', 'Create a Neighborhood Group')

@section('content')
<div class="max-w-2xl mx-auto py-8 px-4">
    <h1 class="text-2xl font-bold text-white mb-2">Create a Neighborhood Group</h1>
    <p class="text-slate-400 text-sm mb-6">
        Start a group around a cause, ballot measure, or local issue. Membership is free —
        anyone can join once your group is live.
    </p>

    @if ($errors->any())
    <div class="mb-6 rounded-xl border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-200">
        <ul class="list-disc list-inside space-y-1">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <form method="POST" action="{{ route('groups.store') }}" class="space-y-5">
        @csrf

        <div>
            <label for="name" class="block text-sm font-medium text-slate-300 mb-1.5">Group Name</label>
            <input type="text" name="name" id="name" value="{{ old('name') }}" required maxlength="255"
                class="w-full bg-slate-800 border border-slate-700 text-white placeholder-slate-500 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 transition"
                placeholder="e.g. Riverside Neighbors for Safer Streets"/>
        </div>

        <div>
            <label for="description" class="block text-sm font-medium text-slate-300 mb-1.5">Description</label>
            <textarea name="description" id="description" rows="5" maxlength="5000"
                class="w-full bg-slate-800 border border-slate-700 text-white placeholder-slate-500 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 transition"
                placeholder="What is this group organizing around?">{{ old('description') }}</textarea>
        </div>

        <div>
            <label for="scope" class="block text-sm font-medium text-slate-300 mb-1.5">Scope</label>
            <select name="scope" id="scope"
                class="w-full bg-slate-800 border border-slate-700 text-slate-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-1 focus:ring-emerald-500 appearance-none cursor-pointer">
                <option value="">— No scope —</option>
                @foreach(\App\Models\NeighborhoodGroup::SCOPES as $scopeOption)
                <option value="{{ $scopeOption }}" {{ old('scope') === $scopeOption ? 'selected' : '' }}>{{ $scopeOption }}</option>
                @endforeach
            </select>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="sm:col-span-1">
                <label for="city" class="block text-sm font-medium text-slate-300 mb-1.5">City</label>
                <input type="text" name="city" id="city" value="{{ old('city') }}" maxlength="255"
                    class="w-full bg-slate-800 border border-slate-700 text-white placeholder-slate-500 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 transition"/>
            </div>
            <div class="sm:col-span-1">
                <label for="state" class="block text-sm font-medium text-slate-300 mb-1.5">State</label>
                <select name="state" id="state"
                    class="w-full bg-slate-800 border border-slate-700 text-slate-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-1 focus:ring-emerald-500 appearance-none cursor-pointer">
                    <option value="">—</option>
                    @foreach(config('u9itus.us_states', []) as $abbr => $name)
                    <option value="{{ $abbr }}" {{ old('state') === $abbr ? 'selected' : '' }}>{{ $abbr }}</option>
                    @endforeach
                </select>
            </div>
            <div class="sm:col-span-1">
                <label for="zip" class="block text-sm font-medium text-slate-300 mb-1.5">ZIP</label>
                <input type="text" name="zip" id="zip" value="{{ old('zip') }}" maxlength="10"
                    class="w-full bg-slate-800 border border-slate-700 text-white placeholder-slate-500 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 transition"/>
            </div>
        </div>

        <div class="flex items-center gap-3 pt-2">
            <button type="submit" class="bg-emerald-600 hover:bg-emerald-500 text-white text-sm font-semibold px-5 py-2.5 rounded-lg transition">
                Create Group
            </button>
            <a href="{{ route('groups.directory') }}" class="text-slate-400 hover:text-white text-sm transition">
                Cancel
            </a>
        </div>
    </form>
</div>
@endsection
