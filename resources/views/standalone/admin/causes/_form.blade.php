@csrf
@if($cause->exists)
    @method('PUT')
@endif

<div class="bg-slate-800/50 border border-slate-700 rounded-xl p-6 space-y-5">
    <div>
        <label for="topic_id" class="block text-sm font-medium text-slate-300 mb-1.5">Topic <span class="text-red-400">*</span></label>
        <select id="topic_id" name="topic_id" required
            class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50 @error('topic_id') border-red-500 @enderror">
            <option value="">— Select —</option>
            @foreach($topics as $topic)
                <option value="{{ $topic->id }}" @selected((string) old('topic_id', $cause->topic_id) === (string) $topic->id)>{{ $topic->name }}</option>
            @endforeach
        </select>
        @error('topic_id')<p class="text-red-400 text-xs mt-1">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="title" class="block text-sm font-medium text-slate-300 mb-1.5">Title <span class="text-red-400">*</span></label>
        <input type="text" id="title" name="title" value="{{ old('title', $cause->title) }}" placeholder="e.g. Expand Medicaid in Texas"
            class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2.5 text-white placeholder-slate-500 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50 @error('title') border-red-500 @enderror" required>
        @error('title')<p class="text-red-400 text-xs mt-1">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="description" class="block text-sm font-medium text-slate-300 mb-1.5">Description</label>
        <textarea id="description" name="description" rows="4"
            class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2.5 text-white placeholder-slate-500 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50 resize-y">{{ old('description', $cause->description) }}</textarea>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
        <div>
            <label for="state" class="block text-sm font-medium text-slate-300 mb-1.5">State</label>
            <input type="text" id="state" name="state" maxlength="2" value="{{ old('state', $cause->state) }}" placeholder="Leave blank for national"
                class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2.5 text-white placeholder-slate-500 text-sm uppercase focus:outline-none focus:ring-2 focus:ring-emerald-500/50 @error('state') border-red-500 @enderror">
            @error('state')<p class="text-red-400 text-xs mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="county" class="block text-sm font-medium text-slate-300 mb-1.5">County</label>
            <input type="text" id="county" name="county" value="{{ old('county', $cause->county) }}"
                class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2.5 text-white placeholder-slate-500 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50">
        </div>
    </div>

    <div>
        <label for="status" class="block text-sm font-medium text-slate-300 mb-1.5">Status <span class="text-red-400">*</span></label>
        <select id="status" name="status" required
            class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50">
            @foreach(['active', 'closed'] as $status)
                <option value="{{ $status }}" @selected(old('status', $cause->status ?? 'active') === $status)>{{ ucfirst($status) }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <label for="source_url" class="block text-sm font-medium text-slate-300 mb-1.5">Source URL</label>
        <input type="url" id="source_url" name="source_url" value="{{ old('source_url', $cause->source_url) }}"
            class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2.5 text-white placeholder-slate-500 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50 @error('source_url') border-red-500 @enderror">
        @error('source_url')<p class="text-red-400 text-xs mt-1">{{ $message }}</p>@enderror
    </div>
</div>

<div class="flex items-center justify-between gap-4 pt-6">
    <a href="{{ route('admin.causes.index') }}" class="px-5 py-2.5 rounded-lg bg-slate-700 hover:bg-slate-600 text-white text-sm font-medium transition">← Back to list</a>
    <button type="submit" class="px-6 py-2.5 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-sm font-semibold transition shadow-md shadow-emerald-900/30">
        {{ $cause->exists ? 'Save Changes' : 'Create Cause' }}
    </button>
</div>
