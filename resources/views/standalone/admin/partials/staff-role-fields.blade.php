<label class="block"><span class="text-sm">Role name</span><input name="name" required maxlength="100" value="{{ $role ? substr($role->name, 6) : old('name') }}" class="mt-1 block w-full rounded-lg bg-slate-900 border-slate-600"></label>
@foreach(collect($catalog)->groupBy(fn ($label, $key) => explode('.', $key)[0], preserveKeys: true) as $group => $capabilities)
    <fieldset class="rounded-lg border border-slate-700 p-3">
        <legend class="px-2 font-semibold capitalize">{{ str_replace('_', ' ', $group) }}</legend>
        <div class="grid sm:grid-cols-2 gap-3">
            @foreach($capabilities as $key => $label)
                <label class="text-sm text-slate-300"><input type="checkbox" name="permissions[]" value="{{ $key }}" @checked($role?->permissions->contains('name', $key))> {{ $label }}</label>
            @endforeach
        </div>
    </fieldset>
@endforeach
<button class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold">Save role</button>
