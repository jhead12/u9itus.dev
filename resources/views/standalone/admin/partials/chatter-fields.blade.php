@php
    $metrics = $chatter?->engagement_metrics ?? [];
@endphp
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
                <option value="{{ $politician->id }}" @selected((string)old('politician_id', $chatter?->politician_id) === (string)$politician->id)>{{ $politician->full_name }}{{ $candidateContext ? ' — '.$candidateContext : '' }}</option>
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
<label class="block"><span class="text-xs font-semibold text-slate-300">Platform *</span><select name="platform" required class="mt-1 w-full rounded-lg border-slate-600 bg-slate-900 text-sm text-white">@foreach(\App\Models\PoliticianChatterItem::PLATFORMS as $value => $label)<option value="{{ $value }}" @selected(old('platform', $chatter?->platform) === $value)>{{ $label }}</option>@endforeach</select></label>
<label class="block md:col-span-2"><span class="text-xs font-semibold text-slate-300">Original source URL *</span><input name="source_url" type="url" required maxlength="1000" value="{{ old('source_url', $chatter?->source_url) }}" class="mt-1 w-full rounded-lg border-slate-600 bg-slate-900 text-sm text-white" placeholder="https://..."></label>
<label class="block"><span class="text-xs font-semibold text-slate-300">Source author</span><input name="source_author" maxlength="191" value="{{ old('source_author', $chatter?->source_author) }}" class="mt-1 w-full rounded-lg border-slate-600 bg-slate-900 text-sm text-white" placeholder="@handle or publication"></label>
<label class="block"><span class="text-xs font-semibold text-slate-300">Source published at</span><input name="source_published_at" type="datetime-local" value="{{ old('source_published_at', $chatter?->source_published_at?->format('Y-m-d\TH:i')) }}" class="mt-1 w-full rounded-lg border-slate-600 bg-slate-900 text-sm text-white"></label>
<label class="block md:col-span-2"><span class="text-xs font-semibold text-slate-300">Neutral public headline *</span><input name="headline" required maxlength="240" value="{{ old('headline', $chatter?->headline) }}" class="mt-1 w-full rounded-lg border-slate-600 bg-slate-900 text-sm text-white" placeholder="Describe the narrative without endorsing it"></label>
<label class="block md:col-span-2"><span class="text-xs font-semibold text-slate-300">Context shown publicly *</span><textarea name="summary" rows="3" required maxlength="2000" class="mt-1 w-full rounded-lg border-slate-600 bg-slate-900 text-sm text-white" placeholder="What is being discussed, where it originated, and what is or is not confirmed">{{ old('summary', $chatter?->summary) }}</textarea></label>
<label class="block"><span class="text-xs font-semibold text-slate-300">Claim status *</span><select name="claim_status" required class="mt-1 w-full rounded-lg border-slate-600 bg-slate-900 text-sm text-white">@foreach(\App\Models\PoliticianChatterItem::CLAIM_STATUSES as $value => $label)<option value="{{ $value }}" @selected(old('claim_status', $chatter?->claim_status ?? 'unverified') === $value)>{{ $label }}</option>@endforeach</select></label>
<label class="block"><span class="text-xs font-semibold text-slate-300">Expires at</span><input name="expires_at" type="datetime-local" value="{{ old('expires_at', $chatter?->expires_at?->format('Y-m-d\TH:i')) }}" class="mt-1 w-full rounded-lg border-slate-600 bg-slate-900 text-sm text-white"></label>
<div class="md:col-span-2 grid grid-cols-2 sm:grid-cols-4 gap-3">
    @foreach(['likes' => 'Likes', 'reposts' => 'Reposts / shares', 'comments' => 'Comments', 'views' => 'Views'] as $key => $label)
        <label class="block"><span class="text-xs font-semibold text-slate-300">{{ $label }}</span><input name="{{ $key }}" type="number" min="0" value="{{ old($key, $metrics[$key] ?? '') }}" class="mt-1 w-full rounded-lg border-slate-600 bg-slate-900 text-sm text-white"></label>
    @endforeach
</div>
<label class="block md:col-span-2"><span class="text-xs font-semibold text-slate-300">Internal editorial notes</span><textarea name="admin_notes" rows="2" maxlength="3000" class="mt-1 w-full rounded-lg border-slate-600 bg-slate-900 text-sm text-white">{{ old('admin_notes', $chatter?->admin_notes) }}</textarea></label>
