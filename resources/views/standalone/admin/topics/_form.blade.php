@csrf
@if($topic->exists)
    @method('PUT')
@endif

<div class="bg-slate-800/50 border border-slate-700 rounded-xl p-6 space-y-5">
    <div>
        <label for="name" class="block text-sm font-medium text-slate-300 mb-1.5">Name <span class="text-red-400">*</span></label>
        <input type="text" id="name" name="name" value="{{ old('name', $topic->name) }}" placeholder="e.g. Healthcare"
            class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2.5 text-white placeholder-slate-500 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50 @error('name') border-red-500 @enderror" required>
        @error('name')<p class="text-red-400 text-xs mt-1">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="slug" class="block text-sm font-medium text-slate-300 mb-1.5">Slug <span class="text-red-400">*</span></label>
        <input type="text" id="slug" name="slug" value="{{ old('slug', $topic->slug) }}" placeholder="e.g. healthcare"
            class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2.5 text-white placeholder-slate-500 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-emerald-500/50 @error('slug') border-red-500 @enderror" required>
        @error('slug')<p class="text-red-400 text-xs mt-1">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="description" class="block text-sm font-medium text-slate-300 mb-1.5">Description</label>
        <textarea id="description" name="description" rows="3"
            class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2.5 text-white placeholder-slate-500 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50 resize-y">{{ old('description', $topic->description) }}</textarea>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
        <div>
            <label for="icon" class="block text-sm font-medium text-slate-300 mb-1.5">Icon</label>
            <input type="text" id="icon" name="icon" value="{{ old('icon', $topic->icon) }}" placeholder="icon key"
                class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2.5 text-white placeholder-slate-500 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50">
        </div>
        <div>
            <label for="sort_order" class="block text-sm font-medium text-slate-300 mb-1.5">Sort Order</label>
            <input type="number" id="sort_order" name="sort_order" min="0" value="{{ old('sort_order', $topic->sort_order ?? 0) }}"
                class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2.5 text-white placeholder-slate-500 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50">
        </div>
        <div>
            <label for="badge_color" class="block text-sm font-medium text-slate-300 mb-1.5">Badge Color</label>
            <input type="text" id="badge_color" name="badge_color" value="{{ old('badge_color', $topic->badge_color ?? '#6366f1') }}" placeholder="#6366f1"
                class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2.5 text-white placeholder-slate-500 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-emerald-500/50">
        </div>
    </div>

    <div>
        <label for="badge_icon_url" class="block text-sm font-medium text-slate-300 mb-1.5">Badge Icon URL</label>
        <input type="text" id="badge_icon_url" name="badge_icon_url" value="{{ old('badge_icon_url', $topic->badge_icon_url) }}"
            class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2.5 text-white placeholder-slate-500 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50">
    </div>

    <div class="space-y-3">
        <label class="flex items-center gap-3 cursor-pointer group">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $topic->exists ? $topic->is_active : true))
                class="w-4 h-4 rounded border-slate-600 bg-slate-900 text-emerald-500 focus:ring-emerald-500/50 cursor-pointer">
            <span class="text-sm font-medium text-slate-300 group-hover:text-white transition">Active</span>
        </label>
        <label class="flex items-center gap-3 cursor-pointer group">
            <input type="checkbox" name="voter_selectable" value="1" @checked(old('voter_selectable', $topic->exists ? $topic->voter_selectable : true))
                class="w-4 h-4 rounded border-slate-600 bg-slate-900 text-emerald-500 focus:ring-emerald-500/50 cursor-pointer">
            <span class="text-sm font-medium text-slate-300 group-hover:text-white transition">Voter selectable</span>
        </label>
        <label class="flex items-center gap-3 cursor-pointer group">
            <input type="checkbox" name="auto_earned_only" value="1" @checked(old('auto_earned_only', $topic->auto_earned_only))
                class="w-4 h-4 rounded border-slate-600 bg-slate-900 text-emerald-500 focus:ring-emerald-500/50 cursor-pointer">
            <span class="text-sm font-medium text-slate-300 group-hover:text-white transition">Auto-earned only</span>
        </label>
    </div>
</div>

<div class="flex items-center justify-between gap-4 pt-6">
    <a href="{{ route('admin.topics.index') }}" class="px-5 py-2.5 rounded-lg bg-slate-700 hover:bg-slate-600 text-white text-sm font-medium transition">← Back to list</a>
    <button type="submit" class="px-6 py-2.5 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-sm font-semibold transition shadow-md shadow-emerald-900/30">
        {{ $topic->exists ? 'Save Changes' : 'Create Topic' }}
    </button>
</div>
