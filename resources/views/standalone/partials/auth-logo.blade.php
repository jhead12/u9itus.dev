<div class="text-center mb-8">
    <a href="/" class="inline-block">
        {{-- SVG preferred; falls back to PNG, then text markup --}}
        @php
            $logoSrc = file_exists(public_path('media/u9itus-logo.svg'))
                ? asset('media/u9itus-logo.svg')
                : (file_exists(public_path('media/u9itus-logo.png')) ? asset('media/u9itus-logo.png') : null);
        @endphp
        @if($logoSrc)
            <img
                src="{{ $logoSrc }}"
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
