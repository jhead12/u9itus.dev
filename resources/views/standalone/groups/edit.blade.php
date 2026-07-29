@extends(auth()->user()->hasRole('citizen') ? 'standalone.layouts.dashboard' : 'layouts.voter')

@section('title', 'Group Settings — '.$group->name)
@section('page-title', 'Group Settings')

@section('content')
<div class="max-w-2xl mx-auto py-8 px-4">
    <h1 class="text-2xl font-bold text-white mb-2">Group Settings</h1>
    <p class="text-slate-400 text-sm mb-6">
        Editing <span class="text-slate-300 font-medium">{{ $group->name }}</span>.
        <a href="{{ route('groups.public.show', $group) }}" class="text-emerald-400 hover:text-emerald-300 transition">View public page →</a>
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

    <form method="POST" action="{{ route('groups.update', $group) }}" class="space-y-5">
        @csrf
        @method('PUT')

        <div>
            <label for="name" class="block text-sm font-medium text-slate-300 mb-1.5">Group Name</label>
            <input type="text" name="name" id="name" value="{{ old('name', $group->name) }}" required maxlength="255"
                class="w-full bg-slate-800 border border-slate-700 text-white placeholder-slate-500 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 transition"/>
        </div>

        <div>
            <label for="description" class="block text-sm font-medium text-slate-300 mb-1.5">Description</label>
            <textarea name="description" id="description" rows="5" maxlength="5000"
                class="w-full bg-slate-800 border border-slate-700 text-white placeholder-slate-500 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 transition"
                placeholder="What is this group organizing around?">{{ old('description', $group->description) }}</textarea>
        </div>

        <div>
            <label for="scope" class="block text-sm font-medium text-slate-300 mb-1.5">Scope</label>
            <select name="scope" id="scope"
                class="w-full bg-slate-800 border border-slate-700 text-slate-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-1 focus:ring-emerald-500 appearance-none cursor-pointer">
                <option value="">— No scope —</option>
                @foreach(\App\Models\NeighborhoodGroup::SCOPES as $scopeOption)
                <option value="{{ $scopeOption }}" {{ old('scope', $group->scope) === $scopeOption ? 'selected' : '' }}>{{ $scopeOption }}</option>
                @endforeach
            </select>
            <p class="text-xs text-slate-500 mt-1.5">Changing this updates the group's public URL.</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="sm:col-span-1">
                <label for="city" class="block text-sm font-medium text-slate-300 mb-1.5">City</label>
                <input type="text" name="city" id="city" value="{{ old('city', $group->city) }}" maxlength="255"
                    class="w-full bg-slate-800 border border-slate-700 text-white placeholder-slate-500 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 transition"/>
            </div>
            <div class="sm:col-span-1">
                <label for="state" class="block text-sm font-medium text-slate-300 mb-1.5">State</label>
                <select name="state" id="state"
                    class="w-full bg-slate-800 border border-slate-700 text-slate-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-1 focus:ring-emerald-500 appearance-none cursor-pointer">
                    <option value="">—</option>
                    @foreach(config('u9itus.us_states', []) as $abbr => $name)
                    <option value="{{ $abbr }}" {{ old('state', $group->state) === $abbr ? 'selected' : '' }}>{{ $abbr }}</option>
                    @endforeach
                </select>
            </div>
            <div class="sm:col-span-1">
                <label for="zip" class="block text-sm font-medium text-slate-300 mb-1.5">ZIP</label>
                <input type="text" name="zip" id="zip" value="{{ old('zip', $group->zip) }}" maxlength="10"
                    class="w-full bg-slate-800 border border-slate-700 text-white placeholder-slate-500 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 transition"/>
            </div>
        </div>

        <div class="flex items-center gap-3 pt-2">
            <button type="submit" class="bg-emerald-600 hover:bg-emerald-500 text-white text-sm font-semibold px-5 py-2.5 rounded-lg transition">
                Save Changes
            </button>
            <a href="{{ route('groups.public.show', $group) }}" class="text-slate-400 hover:text-white text-sm transition">
                Cancel
            </a>
        </div>
    </form>

    <div class="mt-10 pt-6 border-t border-slate-800">
        <h2 class="text-sm font-semibold text-rose-300 mb-2">Danger Zone</h2>
        <p class="text-slate-500 text-xs mb-3">
            Deleting this group removes it and all {{ $group->members()->count() }} {{ Str::plural('membership', $group->members()->count()) }} permanently. This cannot be undone.
        </p>
        <form method="POST" action="{{ route('groups.destroy', $group) }}"
              onsubmit="return confirm('Delete {{ addslashes($group->name) }}? This cannot be undone.');">
            @csrf
            @method('DELETE')
            <button type="submit" class="bg-rose-600/20 hover:bg-rose-600/30 border border-rose-500/40 text-rose-300 text-sm font-medium px-4 py-2 rounded-lg transition">
                Delete Group
            </button>
        </form>
    </div>
</div>
@endsection
