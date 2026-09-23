<div class="min-w-0" data-politician-picker data-district-lookup-url="{{ route('contributor.chatter.candidatesByCity') }}">
    <label class="block" hidden data-politician-search-label>
        <span class="text-xs font-semibold text-slate-300">Search candidates</span>
        <input type="search" autocomplete="off" data-politician-search placeholder="Type a name, state, office, or party…" class="mt-1 w-full rounded-lg border-slate-600 bg-slate-900 text-sm text-white">
    </label>
    <div class="mt-2 flex flex-wrap items-end gap-2" data-district-finder>
        <label class="min-w-0 flex-1">
            <span class="text-xs font-semibold text-slate-300">Find by address — locates their district</span>
            <input type="text" autocomplete="off" data-district-city placeholder="Street address of the source, e.g. 101 W Abram St, Arlington" class="mt-1 w-full rounded-lg border-slate-600 bg-slate-900 text-sm text-white">
        </label>
        <label class="w-20">
            <span class="text-xs font-semibold text-slate-300">State</span>
            <input type="text" autocomplete="off" maxlength="2" data-district-state placeholder="TX" class="mt-1 w-full rounded-lg border-slate-600 bg-slate-900 text-sm uppercase text-white">
        </label>
        <button type="button" data-district-find class="mt-1 rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-500">Find</button>
        <button type="button" data-district-clear hidden class="mt-1 rounded-lg border border-slate-600 px-3 py-2 text-sm text-slate-300 hover:bg-slate-800">Show all</button>
    </div>
    <p class="mt-1 text-xs text-slate-500">A city name alone often can't be resolved — many cities span several districts. A street address gives the most reliable match.</p>
    <p data-district-status role="status" aria-live="polite" class="mt-1 text-xs text-slate-400"></p>
    <label class="mt-2 block">
        <span class="text-xs font-semibold text-slate-300">Politician * — select a result</span>
        <select name="politician_id" required data-politician-results class="mt-1 w-full rounded-lg border-slate-600 bg-slate-900 text-sm text-white">
            <option value="">Select a politician</option>
            @foreach($politicians as $politician)
                @php($candidateContext = collect([$politician->state, $politician->political_office, $politician->party_affiliation])->filter()->implode(' · '))
                <option value="{{ $politician->id }}" @selected((string)old('politician_id', $selectedPolitician ?? null) === (string)$politician->id)>{{ $politician->full_name }}{{ $candidateContext ? ' — '.$candidateContext : '' }}</option>
            @endforeach
        </select>
    </label>
    <p data-politician-status role="status" aria-live="polite" class="mt-1 text-xs text-slate-400"></p>
</div>
@once
    @push('scripts')
        <script src="{{ asset('js/chatter-politician-picker.js') }}" defer></script>
    @endpush
@endonce
