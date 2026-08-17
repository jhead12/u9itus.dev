@csrf
@if($ballotMeasure->exists)
    @method('PUT')
@endif

<div class="bg-slate-800/50 border border-slate-700 rounded-xl p-6 space-y-5">
    <div>
        <label for="title" class="block text-sm font-medium text-slate-300 mb-1.5">Title <span class="text-red-400">*</span></label>
        <input type="text" id="title" name="title" value="{{ old('title', $ballotMeasure->title) }}"
            class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2.5 text-white placeholder-slate-500 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50 @error('title') border-red-500 @enderror" required>
        @error('title')<p class="text-red-400 text-xs mt-1">{{ $message }}</p>@enderror
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
        <div>
            <label for="state" class="block text-sm font-medium text-slate-300 mb-1.5">State <span class="text-red-400">*</span></label>
            <input type="text" id="state" name="state" maxlength="2" value="{{ old('state', $ballotMeasure->state) }}"
                class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2.5 text-white placeholder-slate-500 text-sm uppercase focus:outline-none focus:ring-2 focus:ring-emerald-500/50 @error('state') border-red-500 @enderror" required>
            @error('state')<p class="text-red-400 text-xs mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="county" class="block text-sm font-medium text-slate-300 mb-1.5">County</label>
            <input type="text" id="county" name="county" value="{{ old('county', $ballotMeasure->county) }}"
                class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2.5 text-white placeholder-slate-500 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50">
        </div>
        <div>
            <label for="measure_number" class="block text-sm font-medium text-slate-300 mb-1.5">Measure Number</label>
            <input type="text" id="measure_number" name="measure_number" value="{{ old('measure_number', $ballotMeasure->measure_number) }}" placeholder="e.g. Prop 22"
                class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2.5 text-white placeholder-slate-500 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50">
        </div>
    </div>

    <div>
        <label for="summary" class="block text-sm font-medium text-slate-300 mb-1.5">Summary</label>
        <textarea id="summary" name="summary" rows="4"
            class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2.5 text-white placeholder-slate-500 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50 resize-y">{{ old('summary', $ballotMeasure->summary) }}</textarea>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
        <div>
            <label for="yes_meaning" class="block text-sm font-medium text-slate-300 mb-1.5">What "Yes" Means</label>
            <textarea id="yes_meaning" name="yes_meaning" rows="3"
                class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2.5 text-white placeholder-slate-500 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50 resize-y">{{ old('yes_meaning', $ballotMeasure->yes_meaning) }}</textarea>
        </div>
        <div>
            <label for="no_meaning" class="block text-sm font-medium text-slate-300 mb-1.5">What "No" Means</label>
            <textarea id="no_meaning" name="no_meaning" rows="3"
                class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2.5 text-white placeholder-slate-500 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50 resize-y">{{ old('no_meaning', $ballotMeasure->no_meaning) }}</textarea>
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
        <div>
            <label for="election_date" class="block text-sm font-medium text-slate-300 mb-1.5">Election Date</label>
            <input type="date" id="election_date" name="election_date"
                value="{{ old('election_date', $ballotMeasure->election_date?->format('Y-m-d')) }}"
                class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50">
        </div>
        <div>
            <label for="status" class="block text-sm font-medium text-slate-300 mb-1.5">Status <span class="text-red-400">*</span></label>
            <select id="status" name="status" required
                class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50">
                @foreach(['upcoming', 'passed', 'failed'] as $status)
                    <option value="{{ $status }}" @selected(old('status', $ballotMeasure->status ?? 'upcoming') === $status)>{{ ucfirst($status) }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div>
        <label for="source_url" class="block text-sm font-medium text-slate-300 mb-1.5">Source URL</label>
        <input type="url" id="source_url" name="source_url" value="{{ old('source_url', $ballotMeasure->source_url) }}"
            class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2.5 text-white placeholder-slate-500 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50 @error('source_url') border-red-500 @enderror">
        @error('source_url')<p class="text-red-400 text-xs mt-1">{{ $message }}</p>@enderror
    </div>

    @if($ballotMeasure->exists)
        <p class="text-slate-500 text-xs">Source: <span class="font-mono">{{ $ballotMeasure->source }}</span> — editing here does not change how it was originally imported.</p>
    @endif
</div>

<div class="flex items-center justify-between gap-4 pt-6">
    <a href="{{ route('admin.ballot-measures.index') }}" class="px-5 py-2.5 rounded-lg bg-slate-700 hover:bg-slate-600 text-white text-sm font-medium transition">← Back to list</a>
    <button type="submit" class="px-6 py-2.5 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-sm font-semibold transition shadow-md shadow-emerald-900/30">
        {{ $ballotMeasure->exists ? 'Save Changes' : 'Create Ballot Measure' }}
    </button>
</div>
