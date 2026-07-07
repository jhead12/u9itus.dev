<div class="text-center mb-8">
    <a href="/" class="inline-block">
        {{-- Use image asset when available; always render accessible text markup --}}
        @if(file_exists(public_path('media/u9itus-logo.png')))
            <img
                src="{{ asset('media/u9itus-logo.png') }}"
                alt="U9itus"
                class="h-12 mx-auto mb-2"
            >
        @else
            <span class="text-3xl font-light tracking-tight">
                <span class="font-bold text-white">U9</span><span class="text-emerald-400">itus</span>
            </span>
        @endif
    </a>
    @isset($subtitle)
        <p class="mt-2 text-slate-400 text-sm">{{ $subtitle }}</p>
    @endisset
</div>
