<div class="min-w-0" data-politician-picker>
    <label class="block" hidden data-politician-search-label>
        <span class="text-xs font-semibold text-slate-300">Search candidates</span>
        <input type="search" autocomplete="off" data-politician-search placeholder="Type a name, state, office, or party…" class="mt-1 w-full rounded-lg border-slate-600 bg-slate-900 text-sm text-white">
    </label>
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
