@props(['name', 'label', 'checked' => false, 'disabled' => false])

<label class="flex items-center {{ $disabled ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer' }}">
    <input 
        type="checkbox" 
        name="{{ $name }}" 
        value="1"
        {{ $checked ? 'checked' : '' }}
        {{ $disabled ? 'disabled' : '' }}
        class="w-4 h-4 text-indigo-600 bg-gray-100 border-gray-300 rounded focus:ring-indigo-500 dark:focus:ring-indigo-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600 {{ $disabled ? 'cursor-not-allowed' : '' }}"
    >
    <span class="ml-3 text-sm text-gray-700 dark:text-gray-300">{{ $label }}</span>
</label>
