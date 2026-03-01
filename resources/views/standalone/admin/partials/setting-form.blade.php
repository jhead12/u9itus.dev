{{-- Setting Update Form Partial --}}
<form action="{{ route('admin.platform-settings.update') }}" method="POST" class="mb-4 pb-4 border-b border-slate-700 last:border-0 last:pb-0 last:mb-0">
    @csrf
    <input type="hidden" name="key" value="{{ $key }}">
    <input type="hidden" name="category" value="{{ $category ?? 'general' }}">
    
    <div class="flex items-start gap-4">
        <div class="flex-1">
            <label class="block text-sm font-medium text-white mb-1">{{ $label }}</label>
            <p class="text-xs text-slate-400 mb-2">{{ $description }}</p>
            <div class="flex gap-2">
                <input 
                    type="{{ $type ?? 'text' }}"
                    name="value"
                    step="{{ $step ?? '1' }}"
                    placeholder="Current: {{ \App\Services\PlatformSettingsService::get($key) ?? 'Not set' }}"
                    class="flex-1 bg-slate-700 border border-slate-600 rounded-lg px-3 py-2 text-white text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                >
                <button type="submit" class="bg-blue-600 hover:bg-blue-500 text-white px-4 py-2 rounded-lg transition text-sm font-medium whitespace-nowrap">
                    Update
                </button>
            </div>
        </div>
    </div>
</form>
