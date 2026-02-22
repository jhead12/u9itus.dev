@props([
    'id'           => null,
    'name'         => 'password',
    'placeholder'  => '••••••••',
    'autocomplete' => 'current-password',
    'btnClass'     => 'text-emerald-400 hover:text-emerald-300',
])

<div class="relative">
    <input
        id="{{ $id }}"
        type="password"
        name="{{ $name }}"
        placeholder="{{ $placeholder }}"
        autocomplete="{{ $autocomplete }}"
        {{ $attributes->merge(['class' => '']) }}
    />
    <button
        type="button"
        aria-label="Toggle password visibility"
        onclick="(function(btn){
            var inp = btn.previousElementSibling;
            var show = inp.type === 'password';
            inp.type = show ? 'text' : 'password';
            btn.textContent = show ? 'Hide' : 'Show';
        })(this)"
        class="absolute inset-y-0 right-0 px-4 text-sm font-semibold transition-colors focus:outline-none select-none {{ $btnClass }}"
        tabindex="-1"
    >Show</button>
</div>
