@props(['name', 'label', 'checked' => false, 'disabled' => false])

<label class="flex items-center {{ $disabled ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer' }}">
    <input
        type="checkbox"
        name="{{ $name }}"
        value="1"
        {{ $checked ? 'checked' : '' }}
        {{ $disabled ? 'disabled' : '' }}
        class="w-4 h-4 rounded border-slate-600 bg-slate-900 text-indigo-500 focus:ring-2 focus:ring-indigo-500/50 focus:ring-offset-0 {{ $disabled ? 'cursor-not-allowed' : '' }}"
    >
    <span class="ml-3 text-sm text-slate-300">{{ $label }}</span>
</label>